import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'config.dart';

/// Persists a user-chosen server address. Abstract so tests can swap in memory.
abstract class ServerStore {
  Future<String?> read();
  Future<void> write(String url);
  Future<void> clear();
}

class SecureServerStore implements ServerStore {
  const SecureServerStore([this._storage = const FlutterSecureStorage()]);

  final FlutterSecureStorage _storage;
  static const _key = 'server_url';

  @override
  Future<String?> read() async {
    try {
      return await _storage.read(key: _key);
    } catch (_) {
      return null;
    }
  }

  @override
  Future<void> write(String url) => _storage.write(key: _key, value: url);

  @override
  Future<void> clear() => _storage.delete(key: _key);
}

/// Turns what someone types into the API base URL, or null if it can't be one.
///   "192.168.1.52:8000"        → http://192.168.1.52:8000/api/v1
///   "https://x.com/design-workflow/" → https://x.com/design-workflow/api/v1
String? normalizeServerUrl(String input) {
  var text = input.trim();
  if (text.isEmpty) return null;
  if (!RegExp(r'^[a-zA-Z][a-zA-Z0-9+.-]*://').hasMatch(text)) text = 'http://$text';

  final uri = Uri.tryParse(text);
  if (uri == null || !(uri.scheme == 'http' || uri.scheme == 'https') || uri.host.isEmpty) return null;

  // Something made only of digits and dots must be a real IPv4 address. This catches the classic typo of a
  // dot for the colon before the port ("192.168.1.52.8000"), which would otherwise look like a valid host.
  if (RegExp(r'^[\d.]+$').hasMatch(uri.host)) {
    final octets = uri.host.split('.');
    final valid = octets.length == 4 && octets.every((o) => o.isNotEmpty && o.length <= 3 && int.parse(o) <= 255);
    if (!valid) return null;
  }

  text = text.replaceAll(RegExp(r'/+$'), '');
  if (!text.endsWith('/api/v1')) text = '$text/api/v1';
  return text;
}

/// The Laravel health endpoint (`/up`) sits beside, not under, the API prefix.
Uri healthUri(String apiBaseUrl) {
  final base = apiBaseUrl.replaceAll(RegExp(r'/api/v1/?$'), '');
  return Uri.parse('$base/up');
}

/// Null when the server answers, otherwise a message fit to show.
typedef ServerChecker = Future<String?> Function(String apiBaseUrl);

Future<String?> _defaultChecker(String apiBaseUrl) async {
  final dio = Dio(BaseOptions(connectTimeout: const Duration(seconds: 6), receiveTimeout: const Duration(seconds: 6), validateStatus: (_) => true));
  try {
    final response = await dio.getUri<Object>(healthUri(apiBaseUrl));
    if (response.statusCode == 200) return null;
    return 'The server answered, but not like Adinn (status ${response.statusCode}). Check the address.';
  } on DioException {
    return 'Cannot reach that address. Check it is right and that this phone is on the same Wi-Fi as the server.';
  } finally {
    dio.close();
  }
}

final serverStoreProvider = Provider<ServerStore>((ref) => const SecureServerStore());
final serverCheckerProvider = Provider<ServerChecker>((ref) => _defaultChecker);

final serverUrlProvider = NotifierProvider<ServerUrlController, String>(ServerUrlController.new);

/// The API base URL in use: a saved override if there is one, else the build-time default.
class ServerUrlController extends Notifier<String> {
  bool _loaded = false;

  @override
  String build() => AppConfig.apiBaseUrl;

  bool get isCustom => state != AppConfig.apiBaseUrl;

  /// Reads the saved address once. Must finish before the first API call (see AuthController.restore).
  Future<void> load() async {
    if (_loaded) return;
    _loaded = true;
    // A hung keystore must never leave the app on the splash screen: fall back to the default address.
    final saved = await ref.read(serverStoreProvider).read().timeout(const Duration(seconds: 2), onTimeout: () => null);
    final normalized = saved == null ? null : normalizeServerUrl(saved);
    if (normalized != null) state = normalized;
  }

  Future<void> save(String url) async {
    _loaded = true;
    await ref.read(serverStoreProvider).write(url);
    state = url;
  }

  Future<void> reset() async {
    _loaded = true;
    await ref.read(serverStoreProvider).clear();
    state = AppConfig.apiBaseUrl;
  }
}
