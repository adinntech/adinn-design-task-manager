/// Build-time configuration. Override with:
///   flutter run --dart-define=API_BASE_URL=https://example.com/design-workflow/api/v1
///
/// The default targets the Android emulator's alias for the host machine running `php artisan serve`.
class AppConfig {
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );
}
