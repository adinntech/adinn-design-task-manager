# Releasing a new app version (in-app update)

The app checks `GET /api/v1/app-update` on launch and whenever it returns from the background.
A phone whose build is **below `APP_MIN_BUILD`** is frozen behind a full-screen **Update required** page
with an **Update now** button (opens the APK download). Installing the APK restarts the app on the new build,
which passes straight through. If the server can't be reached the app is never blocked.

"Build" = the number after `+` in `mobile/pubspec.yaml` (`version: 1.0.0+1` → build `1`).

## Steps

1. Bump the version in `mobile/pubspec.yaml`, e.g. `version: 1.1.0+2`.
2. Build the APK with the script (checks, then builds signed with the release key and pointed at the live server):
   `cd mobile && .	ooluild_release.ps1 -ApiUrl https://<live-server>/api/v1`
   (output: `mobile/build/app/outputs/flutter-apk/app-release.apk`). A plain `flutter build apk --release` is refused without
   `android/key.properties`, and without `--dart-define=API_BASE_URL=...` it would point at the emulator's localhost.
3. Publish the APK on the server: `php artisan app:publish-apk mobile/build/app/outputs/flutter-apk/app-release.apk`.
   BD and Designers then download it from **Get the app** in the web top bar (`/app/download`, sign-in required) once
   `APP_DOWNLOAD_ENABLED=true` is set in the server `.env` (it is **off by default**: while the app is only being tested,
   leave it off and hand the APK file to testers directly).
   (Or host it elsewhere and set `APP_APK_URL`; the button then sends them to that link.)
4. On the server `.env`:
   ```
   APP_LATEST_BUILD=2
   APP_LATEST_VERSION=1.1.0
   APP_APK_URL=https://<host>/path/adinn-tasks.apk   # what the app's Update screen opens (needs a direct, public link)
   APP_UPDATE_NOTES="What changed, shown on the update screen"
   APP_MIN_BUILD=2        # raise this to FORCE everyone; leave the old value for an optional update
   ```
   then `php artisan config:clear` (or `config:cache`).

5. Tell every phone: `php artisan app:announce-update` (add `--dry-run` first to see how many users would be notified).
   Each phone gets an **Update available** push; tapping it opens the app, which re-checks the release and shows the
   Update screen (or the small bar, if the update is optional).

After a phone installs the new APK, the first launch shows a one-time **What's new** card with `APP_UPDATE_NOTES`
(a brand-new install shows nothing). Keep the notes short and written for users.

| Setting | Effect on phones below it |
|---|---|
| `APP_MIN_BUILD` | Frozen behind the Update screen |
| `APP_LATEST_BUILD` (≥ their build, ≤ min) | Small dismissible "Update available" bar |
| `APP_MIN_BUILD=0` | Nothing is ever forced |

## Notes

- First install on a phone: Android asks to allow "Install unknown apps" for the browser that downloads the APK.
- The update only works for builds that already contain this feature — phones on older builds must be updated by hand once.
- Always sign release APKs with the same key, or Android will refuse to install over the old app.

## Signing key (read this once)

- The release key is `mobile/android/app/adinn-release.jks`, its password is in `mobile/android/key.properties`
  (template: `key.properties.example`). Both are git-ignored on purpose.
- **Back both files up somewhere safe (not only on this PC).** Every future update must be signed with this same key; if it is
  lost, existing phones cannot be updated and everyone has to uninstall and reinstall.
- Phones that have a build signed with the old debug key cannot be updated over it: uninstall once, then install this build.
- Android auto-backup of the app's data is switched off (`allowBackup=false`).
