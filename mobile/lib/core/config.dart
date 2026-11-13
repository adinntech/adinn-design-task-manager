/// Build-time configuration. Override with:
///   flutter run --dart-define=API_BASE_URL=https://example.com/design-workflow/api/v1
///
/// The default targets the Android emulator's alias for the host machine running `php artisan serve`.
class AppConfig {
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: _devServer,
  );

  /// The Android emulator's alias for the computer running `php artisan serve`.
  static const _devServer = 'http://10.0.2.2:8000/api/v1';

  /// The "Server address" button on the sign-in screen is for developers. A release build made for the live server
  /// (the pipeline passes API_BASE_URL) does not show it, so people cannot point the app somewhere else by mistake.
  static const showServerSettings = apiBaseUrl == _devServer;
}
