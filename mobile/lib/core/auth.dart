import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'api_client.dart';
import 'models.dart';
import 'push.dart';

/// Where the bearer token + cached user live. Abstract so tests can swap in memory.
abstract class SessionStore {
  Future<({String? token, AppUser? user})> read();
  Future<void> write(String token, AppUser user);
  Future<void> clear();
}

class SecureSessionStore implements SessionStore {
  const SecureSessionStore([this._storage = const FlutterSecureStorage()]);

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'api_token';
  static const _userKey = 'api_user';

  @override
  Future<({String? token, AppUser? user})> read() async {
    try {
      final token = await _storage.read(key: _tokenKey);
      final raw = await _storage.read(key: _userKey);
      final user = raw == null ? null : AppUser.fromJson(jsonDecode(raw) as Map<String, dynamic>);
      return (token: token, user: user);
    } catch (_) {
      return (token: null, user: null);
    }
  }

  @override
  Future<void> write(String token, AppUser user) async {
    await _storage.write(key: _tokenKey, value: token);
    await _storage.write(key: _userKey, value: jsonEncode(user.toJson()));
  }

  @override
  Future<void> clear() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _userKey);
  }
}

enum AuthStatus { unknown, signedOut, signedIn }

class AuthState {
  const AuthState({this.status = AuthStatus.unknown, this.token, this.user});

  final AuthStatus status;
  final String? token;
  final AppUser? user;
}

final sessionStoreProvider = Provider<SessionStore>((ref) => const SecureSessionStore());

final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient(
    tokenProvider: () => ref.read(authProvider).token,
    onUnauthorized: () => ref.read(authProvider.notifier).signOutLocally(),
  );
});

final authProvider = NotifierProvider<AuthController, AuthState>(AuthController.new);

class AuthController extends Notifier<AuthState> {
  @override
  AuthState build() => const AuthState();

  /// Restores a saved session on app start and confirms the token is still accepted.
  Future<void> restore() async {
    final saved = await ref.read(sessionStoreProvider).read();
    if (saved.token == null || saved.user == null) {
      state = const AuthState(status: AuthStatus.signedOut);
      return;
    }

    state = AuthState(status: AuthStatus.signedIn, token: saved.token, user: saved.user);

    try {
      final me = await ref.read(apiClientProvider).get('/me');
      final user = AppUser.fromJson(me['user'] as Map<String, dynamic>);
      await ref.read(sessionStoreProvider).write(saved.token!, user);
      state = AuthState(status: AuthStatus.signedIn, token: saved.token, user: user);
    } on ApiException catch (e) {
      // Only a rejected token ends the session; being offline keeps the cached one.
      if (e.isUnauthorized) await signOutLocally();
    }
  }

  /// Throws [ApiException] (with `fieldErrors['login']`) on bad credentials.
  Future<void> login(String login, String password, {String deviceName = 'mobile'}) async {
    final body = await ref.read(apiClientProvider).post('/login', data: {
      'login': login.trim(),
      'password': password,
      'device_name': deviceName,
    });
    final token = body['token'] as String;
    final user = AppUser.fromJson(body['user'] as Map<String, dynamic>);
    await ref.read(sessionStoreProvider).write(token, user);
    state = AuthState(status: AuthStatus.signedIn, token: token, user: user);
  }

  Future<void> logout() async {
    // Unregister this device while the API token is still valid, so a signed-out phone stops getting pushes.
    await ref.read(pushRegistrarProvider).stop();
    try {
      await ref.read(apiClientProvider).post('/logout');
    } catch (_) {
      // The local sign-out below is what matters; an expired token can't be revoked anyway.
    }
    await signOutLocally();
  }

  Future<void> signOutLocally() async {
    await ref.read(pushRegistrarProvider).stop(unregister: false);
    await ref.read(sessionStoreProvider).clear();
    state = const AuthState(status: AuthStatus.signedOut);
  }
}
