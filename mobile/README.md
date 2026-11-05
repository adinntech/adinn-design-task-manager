# Adinn Tasks (Flutter)

Mobile client for the Adinn Design Task Manager. Talks to the Laravel `/api/v1` API (Sanctum bearer tokens).

## Run

```bash
flutter pub get
# Android emulator against `php artisan serve` on the host (default):
flutter run
# Another server (note the /design-workflow prefix on the live site):
flutter run --dart-define=API_BASE_URL=https://<host>/design-workflow/api/v1
```

## Test

```bash
flutter analyze
flutter test
```

## Push notifications (FCM)

The app registers its FCM token with the API (`POST /api/v1/devices`) after sign-in and removes it on sign-out.
To turn it on:

1. Create a Firebase project and add an Android app with package name `in.co.adinn.adinn_tasks`.
2. Download `google-services.json` into `android/app/` (the Gradle plugin is applied only when the file exists).
3. On the server, set `FCM_CREDENTIALS` (path) or `FCM_CREDENTIALS_JSON` to a service-account key for the same project.

Without these the app builds and runs normally with push off. Tapping a notification opens its task.

## Status

Done: login/logout, session restore, role-aware task board (designer, designer head, BD, admin),
task detail, designer status move, task chat (polling) with attachments (designer/BD), notifications,
head/admin request approvals, BD rework + rating + confirm/reject, designer requests (decline/split/swap),
designer EOD + rework submissions with resumable multipart uploads (direct to storage, 64 MB parts),
FCM registration + tap-to-open (needs the Firebase files above).
Realtime: while the app is open it holds a Reverb websocket (settings come from `GET /api/v1/realtime`, so nothing is
hard-coded) and refreshes alerts, boards, requests and chat when something changes; the socket closes in the background
(push takes over) and reconnects on resume. It is off automatically if the server isn't set up for Reverb.
BD create: client + in-house tasks and drafts, with the form (fields, required rules, choices) read from the server.
Not yet: BD edit and clone, large-file (resumable) uploads at creation, designer-head chat attachments, printing-file mail tab.
