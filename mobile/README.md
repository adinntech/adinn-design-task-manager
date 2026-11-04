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

## Status

Done: login/logout, session restore, role-aware task board (designer, designer head, BD, admin),
task detail, designer status move, task chat (polling), notifications.
Done too: head/admin request approvals, BD rework + rating + confirm/reject, designer requests (decline/split/swap).
Not yet: attachments + EOD + rework uploads (multipart), FCM token registration, realtime (Reverb), BD create/edit.
