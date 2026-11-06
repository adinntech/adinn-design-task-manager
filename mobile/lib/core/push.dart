import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'api_client.dart';
import 'auth.dart';

/// A push the user tapped (or one received while the app is open).
class PushEvent {
  const PushEvent({this.taskId, this.type});

  final int? taskId;

  /// `app_update` for the "Update available" announcement; null for task notifications.
  final String? type;

  bool get isAppUpdate => type == 'app_update';

  factory PushEvent.fromData(Map<String, dynamic> data) => PushEvent(taskId: int.tryParse('${data['task_id'] ?? ''}'), type: data['type'] as String?);
}

/// The bits of Firebase Messaging the app needs. Abstract so tests don't touch Firebase.
abstract class PushService {
  /// False when Firebase isn't configured for this build (no google-services.json). Everything else no-ops.
  Future<bool> init();

  /// Asks for notification permission (Android 13+). True if granted.
  Future<bool> requestPermission();

  Future<String?> token();
  Stream<String> get tokenRefreshes;

  /// Taps on a notification while the app is backgrounded or terminated.
  Stream<PushEvent> get opened;

  /// The notification that cold-started the app, if any.
  Future<PushEvent?> initialMessage();

  /// Messages that arrive while the app is in the foreground (the system shows no banner for these).
  Stream<PushEvent> get foreground;

  Future<void> deleteToken();
}

// firebase_messaging 17 deprecates getToken/onTokenRefresh/deleteToken in favour of register()/onRegistered, which
// hand out a Firebase *Installation ID* as a "direct-send" target. The backend sends through FCM HTTP v1 with a
// classic registration token (FcmService), which these still return — so they stay until the server side is
// verified against installation-ID targets. Revisit when moving to register()/onRegistered.
// ignore_for_file: deprecated_member_use

class FirebasePushService implements PushService {
  bool _ready = false;

  @override
  Future<bool> init() async {
    try {
      if (Firebase.apps.isEmpty) await Firebase.initializeApp();
      _ready = true;
    } catch (e) {
      // No google-services.json (or an invalid one): push is optional, never fatal.
      debugPrint('Push disabled — Firebase is not configured: $e');
      _ready = false;
    }
    return _ready;
  }

  FirebaseMessaging get _fm => FirebaseMessaging.instance;

  @override
  Future<bool> requestPermission() async {
    if (!_ready) return false;
    final settings = await _fm.requestPermission();
    return settings.authorizationStatus == AuthorizationStatus.authorized || settings.authorizationStatus == AuthorizationStatus.provisional;
  }

  @override
  Future<String?> token() async => _ready ? _fm.getToken() : null;

  @override
  Stream<String> get tokenRefreshes => _ready ? _fm.onTokenRefresh : const Stream.empty();

  @override
  Stream<PushEvent> get opened => _ready ? FirebaseMessaging.onMessageOpenedApp.map((m) => PushEvent.fromData(m.data)) : const Stream.empty();

  @override
  Future<PushEvent?> initialMessage() async {
    if (!_ready) return null;
    final m = await _fm.getInitialMessage();
    return m == null ? null : PushEvent.fromData(m.data);
  }

  @override
  Stream<PushEvent> get foreground => _ready ? FirebaseMessaging.onMessage.map((m) => PushEvent.fromData(m.data)) : const Stream.empty();

  @override
  Future<void> deleteToken() async {
    if (_ready) await _fm.deleteToken();
  }
}

final pushServiceProvider = Provider<PushService>((ref) => FirebasePushService());

/// Keeps this device's FCM token registered with the API for as long as someone is signed in.
class PushRegistrar {
  PushRegistrar(this._ref, this._push);

  final Ref _ref;
  final PushService _push;

  /// Wired by the app shell: open the tapped task / refresh badges when a push arrives in the foreground.
  void Function(PushEvent event)? onOpened;
  void Function()? onForeground;

  final _subs = <StreamSubscription<Object?>>[];
  String? _registered;
  String? _inFlight;
  bool _started = false;

  /// Call once after sign-in. Safe to call repeatedly.
  Future<void> start() async {
    if (_started) return;
    _started = true;

    try {
      if (!await _push.init()) return;

      _subs
        ..add(_push.tokenRefreshes.listen(_register))
        ..add(_push.opened.listen((e) => onOpened?.call(e)))
        ..add(_push.foreground.listen((_) => onForeground?.call()));

      final launched = await _push.initialMessage();
      if (launched != null) onOpened?.call(launched);

      // Not granted → no token is sent, so the server has nothing to push to.
      if (await _push.requestPermission()) {
        final token = await _push.token();
        if (token != null) await _register(token);
      }
    } catch (e) {
      debugPrint('Push setup failed: $e');
    }
  }

  Future<void> _register(String token) async {
    if (token == _registered || token == _inFlight) return;
    _inFlight = token;
    try {
      await _ref.read(apiClientProvider).post('/devices', data: {'token': token, 'platform': 'android'});
      _registered = token;
    } on ApiException catch (e) {
      debugPrint('Could not register device for push: ${e.message}');
    } finally {
      if (_inFlight == token) _inFlight = null;
    }
  }

  /// Best effort, and must run while the API token is still valid (before sign-out).
  Future<void> stop({bool unregister = true}) async {
    final token = _registered;

    // Not awaited: sign-out must never wait on a platform stream's cancel().
    for (final s in _subs) {
      unawaited(s.cancel());
    }
    _subs.clear();
    _started = false;
    _registered = null;

    if (!unregister || token == null) return;
    try {
      await _ref.read(apiClientProvider).delete('/devices', data: {'token': token});
      await _push.deleteToken();
    } catch (_) {
      // The server also drops tokens FCM reports as dead.
    }
  }
}

final pushRegistrarProvider = Provider<PushRegistrar>((ref) {
  final registrar = PushRegistrar(ref, ref.watch(pushServiceProvider));
  ref.onDispose(() => registrar.stop(unregister: false));
  return registrar;
});
