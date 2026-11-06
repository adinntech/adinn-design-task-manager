import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:web_socket_channel/web_socket_channel.dart';

import 'api_client.dart';
import 'auth.dart';

/// A live notification pushed over Reverb (the same payload the web bell receives).
class RealtimeEvent {
  const RealtimeEvent({this.taskId, this.category, this.title});

  final int? taskId;
  final String? category;
  final String? title;

  factory RealtimeEvent.fromData(Map<String, dynamic> data) => RealtimeEvent(
        taskId: int.tryParse('${data['task_id'] ?? ''}'),
        category: data['category'] as String?,
        title: data['title'] as String?,
      );
}

/// Minimal socket surface so tests don't need a real server.
abstract class RealtimeSocket {
  Stream<String> get messages;
  void send(String text);
  Future<void> close();
}

typedef SocketConnector = RealtimeSocket Function(Uri uri);

class _WebSocket implements RealtimeSocket {
  _WebSocket(this._channel);

  final WebSocketChannel _channel;

  @override
  Stream<String> get messages => _channel.stream.map((m) => m.toString());

  @override
  void send(String text) => _channel.sink.add(text);

  @override
  Future<void> close() => _channel.sink.close();
}

RealtimeSocket connectWebSocket(Uri uri) => _WebSocket(WebSocketChannel.connect(uri));

/// Speaks just enough of the Pusher protocol (which Laravel Reverb implements) to receive a user's private
/// notification channel: connect → authorise through the API → subscribe → answer pings → reconnect with backoff.
///
/// Realtime is a convenience on top of polling and push, never a dependency: if the server has it switched
/// off or the socket can't connect, the app keeps working and simply refreshes less eagerly.
class RealtimeClient {
  RealtimeClient(
    this._api, {
    this.connector = connectWebSocket,
    Duration Function(int attempt)? backoff,
    this.pongTimeout = const Duration(seconds: 30),
  }) : _backoff = backoff ?? _defaultBackoff;

  final ApiClient _api;
  final SocketConnector connector;
  final Duration Function(int attempt) _backoff;
  final Duration pongTimeout;

  static Duration _defaultBackoff(int attempt) => Duration(seconds: (1 << attempt.clamp(0, 5)).clamp(1, 30));

  final _events = StreamController<RealtimeEvent>.broadcast();
  Stream<RealtimeEvent> get events => _events.stream;

  final _connectedState = ValueNotifier<bool>(false);
  ValueListenable<bool> get connected => _connectedState;

  Map<String, dynamic>? _config;
  RealtimeSocket? _socket;
  StreamSubscription<String>? _sub;
  Timer? _reconnect;
  Timer? _idle;
  Timer? _pongWait;
  int _attempt = 0;
  bool _running = false;
  int _generation = 0;
  Duration _activityTimeout = const Duration(seconds: 30);

  /// Safe to call repeatedly.
  Future<void> start() async {
    if (_running) return;
    _running = true;
    _attempt = 0;
    await _connect();
  }

  Future<void> stop() async {
    _running = false;
    _config = null; // a different user may sign in next, with a different private channel
    _generation++;
    _reconnect?.cancel();
    _idle?.cancel();
    _pongWait?.cancel();
    _connectedState.value = false;
    final sub = _sub;
    final socket = _socket;
    _sub = null;
    _socket = null;
    unawaited(sub?.cancel());
    try {
      await socket?.close();
    } catch (_) {}
  }

  Future<void> dispose() async {
    await stop();
    await _events.close();
    _connectedState.dispose();
  }

  Future<void> _connect() async {
    final gen = ++_generation;

    try {
      _config ??= await _api.get('/realtime');
      final config = _config!;

      // Server-side switch: broadcasting isn't Reverb, or not configured. Stay quiet — nothing to retry.
      if (config['enabled'] != true) {
        _running = false;
        return;
      }

      final tls = config['scheme'] == 'https';
      final uri = Uri(
        scheme: tls ? 'wss' : 'ws',
        host: config['host'] as String,
        port: config['port'] as int,
        path: '/app/${config['key']}',
        queryParameters: {'protocol': '7', 'client': 'adinn-mobile', 'version': '1.0'},
      );

      final socket = connector(uri);
      if (gen != _generation) {
        unawaited(socket.close());
        return;
      }

      _socket = socket;
      _sub = socket.messages.listen(
        (raw) => _onMessage(gen, raw),
        onError: (_) => _onDropped(gen),
        onDone: () => _onDropped(gen),
        cancelOnError: true,
      );
    } on ApiException catch (e) {
      // 401 means the session is gone — the auth layer handles that; anything else is retried.
      if (e.isUnauthorized) {
        _running = false;
        return;
      }
      _scheduleReconnect(gen);
    } catch (_) {
      _scheduleReconnect(gen);
    }
  }

  Future<void> _onMessage(int gen, String raw) async {
    if (gen != _generation) return;
    _armIdle(gen);

    final Map<String, dynamic> msg;
    try {
      msg = jsonDecode(raw) as Map<String, dynamic>;
    } catch (_) {
      return;
    }

    final event = msg['event'] as String? ?? '';
    final data = _payload(msg['data']);

    switch (event) {
      case 'pusher:connection_established':
        final timeout = data['activity_timeout'];
        if (timeout is int && timeout > 0) _activityTimeout = Duration(seconds: timeout);
        _armIdle(gen);
        await _subscribe(gen, data['socket_id'] as String);
      case 'pusher:ping':
        _socket?.send(jsonEncode({'event': 'pusher:pong', 'data': {}}));
      case 'pusher:pong':
        _pongWait?.cancel();
      case 'pusher_internal:subscription_succeeded':
        _attempt = 0;
        _connectedState.value = true;
      case 'pusher:error':
        debugPrint('Realtime error from server: $data');
      default:
        if (event == _config?['event']) _events.add(RealtimeEvent.fromData(data));
    }
  }

  Future<void> _subscribe(int gen, String socketId) async {
    final channel = _config!['channel'] as String;

    try {
      final auth = await _api.post('/broadcasting/auth', data: {'socket_id': socketId, 'channel_name': channel});
      if (gen != _generation) return;

      _socket?.send(jsonEncode({
        'event': 'pusher:subscribe',
        'data': {'auth': auth['auth'], 'channel': channel},
      }));
    } on ApiException catch (e) {
      if (e.isUnauthorized) {
        await stop();
        return;
      }
      _onDropped(gen);
    }
  }

  /// Pusher sends `data` as a JSON *string* for server events, but as an object for some internal ones.
  Map<String, dynamic> _payload(Object? data) {
    if (data is Map) return data.cast<String, dynamic>();
    if (data is String && data.isNotEmpty) {
      try {
        final decoded = jsonDecode(data);
        if (decoded is Map) return decoded.cast<String, dynamic>();
      } catch (_) {}
    }
    return const {};
  }

  /// After a quiet spell, ping; if nothing comes back, treat the connection as dead.
  void _armIdle(int gen) {
    _idle?.cancel();
    _pongWait?.cancel();
    _idle = Timer(_activityTimeout, () {
      if (gen != _generation) return;
      _socket?.send(jsonEncode({'event': 'pusher:ping', 'data': {}}));
      _pongWait = Timer(pongTimeout, () => _onDropped(gen));
    });
  }

  void _onDropped(int gen) {
    if (gen != _generation) return;
    _connectedState.value = false;
    _idle?.cancel();
    _pongWait?.cancel();
    final sub = _sub;
    final socket = _socket;
    _sub = null;
    _socket = null;
    unawaited(sub?.cancel());
    unawaited(socket?.close().catchError((_) {}));
    _scheduleReconnect(gen);
  }

  void _scheduleReconnect(int gen) {
    if (!_running || gen != _generation) return;
    _reconnect?.cancel();
    _reconnect = Timer(_backoff(_attempt++), () {
      if (_running) _connect();
    });
  }
}

final realtimeProvider = Provider<RealtimeClient>((ref) {
  final client = RealtimeClient(ref.watch(apiClientProvider));
  ref.onDispose(client.dispose);
  return client;
});
