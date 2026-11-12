# Releasing a new app version (in-app update)

The app checks `GET /api/v1/app-update` on launch and whenever it returns from the background.
A phone whose build is **below `APP_MIN_BUILD`** is frozen behind a full-screen **Update required** page
with an **Update now** button (opens the APK download). Installing the APK restarts the app on the new build,
which passes straight through. If the server can't be reached the app is never blocked.

"Build" = the number after `+` in `mobile/pubspec.yaml` (`version: 1.0.0+1` → build `1`).

## Automatic release (normal way)

Push app changes to the live branch `laravel-srinivashan-open-AI`. If the push changes anything under `mobile/`,
GitHub Actions (`.github/workflows/mobile-release.yml`) does everything:

1. `flutter analyze` + `flutter test`, then builds the signed release APK (version name from `pubspec.yaml`,
   build number = `100 + workflow run number`, so it only ever goes up).
2. Refuses to continue unless the APK is signed with the expected release key.
3. Uploads it to `POST /api/v1/internal/app-release` (secret token). The server checks the file, saves it in
   `public/downloads/`, makes it the current release with `min_build = build` (**every release is a forced update**),
   and sends the **Update available** push once.
4. Checks `/api/v1/app-update` now shows the new build. The APK is also kept as a workflow artifact for 90 days.

Notes for the update screen come from the first line of the commit message: write it for users.
Add `[skip release]` to a commit message to push `mobile/` changes without releasing. Web-only pushes never build the app.
Dry run: Actions > Mobile release > Run workflow (leave "dry run" ticked) builds and verifies without publishing.

### Emergency: a bad release is blocking everyone

On the server: `php artisan app:release --min-build=0` frees every phone immediately (no cache clearing needed).
Then push a fixed version. `php artisan app:release` alone shows what is published.

### One-time setup

GitHub repo, Settings > Secrets and variables > Actions:

| Secret | Value |
|---|---|
| `ANDROID_KEYSTORE_BASE64` | base64 of `mobile/android/app/adinn-release.jks` (PowerShell: `[Convert]::ToBase64String([IO.File]::ReadAllBytes("adinn-release.jks"))`) |
| `ANDROID_STORE_PASSWORD`, `ANDROID_KEY_PASSWORD`, `ANDROID_KEY_ALIAS` | from `mobile/android/key.properties` (alias `adinn`) |
| `API_BASE_URL` | `https://adinntech.in/design-workflow/api/v1` |
| `RELEASE_URL` | `https://adinntech.in/design-workflow/api/v1/internal/app-release` |
| `RELEASE_TOKEN` | a long random string (you choose it) |
| `RELEASE_CERT_SHA256` | `4d3ad2b24409db26294bef149f5334467ad31153e39f51575b9de2fbcbf93113` |

Server `.env`: `RELEASE_UPLOAD_TOKEN=<same value as RELEASE_TOKEN>` (without it the endpoint does not exist).
Also: `public/downloads/` must be writable by the web user; PHP `upload_max_filesize` and `post_max_size` must be at least 100M
(and any proxy body limit too, the APK is about 65 MB); FCM credentials (`FCM_CREDENTIALS`) must be set for the push.

## Manual release (fallback)

Build with `mobile/tool/build_release.ps1 -ApiUrl https://<live-server>/api/v1`, put the APK where phones can download it,
then set `APP_LATEST_BUILD`, `APP_LATEST_VERSION`, `APP_APK_URL`, `APP_UPDATE_NOTES`, `APP_MIN_BUILD` in the server `.env`
and run `php artisan app:announce-update` (`--dry-run` first). Values stored by the pipeline take priority over `.env`.

After a phone installs the new APK, the first launch shows a one-time **What's new** card with the notes
(a brand-new install shows nothing).

| Value | Effect on phones below it |
|---|---|
| `min_build` | Frozen behind the Update screen |
| `latest_build` (>= their build, <= min) | Small dismissible "Update available" bar |
| `min_build = 0` | Nothing is ever forced |

## Notes

- First install on a phone: Android asks to allow "Install unknown apps" for the browser that downloads the APK.
- The update only works for builds that already contain this feature; phones on older builds must be updated by hand once.
- Always sign release APKs with the same key, or Android will refuse to install over the old app.

## Signing key (read this once)

- The release key is `mobile/android/app/adinn-release.jks`, its password is in `mobile/android/key.properties`
  (template: `key.properties.example`). Both are git-ignored on purpose.
- **Back both files up somewhere safe (not only on this PC).** Every future update must be signed with this same key; if it is
  lost, existing phones cannot be updated and everyone has to uninstall and reinstall.
- Phones that have a build signed with the old debug key cannot be updated over it: uninstall once, then install this build.
- Android auto-backup of the app's data is switched off (`allowBackup=false`).
