import 'dart:async';
import 'dart:convert';

import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/core/realtime.dart';
import 'package:adinn_tasks/main.dart';
import 'package:fake_async/fake_async.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

const _event = r'Illuminate\Notifications\Events\BroadcastNotificationCreated';

class FakeSocket implements RealtimeSocket {
  FakeSocket(this.uri);

  final Uri uri;
  final _in = StreamController<String>();
  final sent = <Map<String, dynamic>>[];
  bool closed = false;

  @override
  Stream<String> get messages => _in.stream;

  @override
  void send(String text) => sent.add(jsonDecode(text) as Map<String, dynamic>);

  @override
  Future<void> close() async {
    closed = true;
    if (!_in.isClosed) await _in.close();
  }

  // ---- server side ----
  void serverSends(String event, Object data, {String? channel}) =>
      _in.add(jsonEncode({'event': event, 'data': data is String ? data : jsonEncode(data), 'channel': ?channel}));

  void established({String socketId = '1.2', int activityTimeout = 30}) =>
      serverSends('pusher:connection_established', {'socket_id': socketId, 'activity_timeout': activityTimeout});

  void subscribed() => serverSends('pusher_internal:subscription_succeeded', {}, channel: 'private-App.Models.User.1');

  void notification(Map<String, dynamic> data) => serverSends(_event, data, channel: 'private-App.Models.User.1');

  void drop() => _in.close();
}

Map<String, Handler> realtimeRoutes({Handler? auth, bool enabled = true, String scheme = 'https'}) => {
      'GET /realtime': (_) => (
            status: 200,
            body: enabled
                ? {'enabled': true, 'key': 'app-key', 'host': 'ws.example.test', 'port': 8081, 'scheme': scheme, 'channel': 'private-App.Models.User.1', 'event': _event}
                : {'enabled': false},
          ),
      'POST /broadcasting/auth': auth ?? (_) => (status: 200, body: {'auth': 'app-key:signature'}),
    };

void main() {
  late FakeAdapter api;
  late List<FakeSocket> sockets;
  late RealtimeClient client;

  RealtimeClient make({Map<String, Handler>? routes, Duration pongTimeout = const Duration(seconds: 30)}) {
    api = FakeAdapter(routes ?? realtimeRoutes());
    sockets = [];
    client = RealtimeClient(
      fakeClient(api, token: () => 'tok'),
      connector: (uri) => FakeSocket(uri)..let(sockets.add),
      backoff: (_) => Duration.zero,
      pongTimeout: pongTimeout,
    );
    addTearDown(client.dispose);
    return client;
  }

  Future<void> settle() => Future<void>.delayed(const Duration(milliseconds: 20));

  group('RealtimeClient', () {
    test('connects to the Reverb app URL, authorises the private channel with the bearer token, then subscribes', () async {
      make();
      await client.start();

      expect(sockets, hasLength(1));
      final uri = sockets.single.uri;
      expect([uri.scheme, uri.host, uri.port, uri.path], ['wss', 'ws.example.test', 8081, '/app/app-key']);
      expect(uri.queryParameters['protocol'], '7');

      sockets.single.established(socketId: '9.9');
      await settle();

      final authCall = api.requests.firstWhere((r) => r.path == '/broadcasting/auth');
      expect(authCall.data, {'socket_id': '9.9', 'channel_name': 'private-App.Models.User.1'});
      expect(authCall.headers['Authorization'], 'Bearer tok');
      expect(sockets.single.sent.single, {'event': 'pusher:subscribe', 'data': {'auth': 'app-key:signature', 'channel': 'private-App.Models.User.1'}});

      expect(client.connected.value, isFalse); // not until the server confirms
      sockets.single.subscribed();
      await settle();
      expect(client.connected.value, isTrue);
    });

    test('uses ws:// for http servers', () async {
      make(routes: realtimeRoutes(scheme: 'http'));
      await client.start();

      expect(sockets.single.uri.scheme, 'ws');
    });

    test('delivers notification events (data arrives as a JSON string) and ignores everything else', () async {
      make();
      await client.start();
      final received = <RealtimeEvent>[];
      client.events.listen(received.add);

      sockets.single.established();
      await settle();
      sockets.single.notification({'task_id': 42, 'category': 'status', 'title': 'Moved'});
      sockets.single.serverSends('some.other.Event', {'task_id': 1});
      sockets.single.serverSends(_event, 'not json');
      await settle();

      expect(received, hasLength(2)); // the malformed one yields an empty event, not a crash
      expect(received.first.taskId, 42);
      expect(received.first.category, 'status');
      expect(received.last.taskId, isNull);
    });

    test('answers pusher:ping with pusher:pong', () async {
      make();
      await client.start();
      sockets.single.serverSends('pusher:ping', {});
      await settle();

      expect(sockets.single.sent, contains(equals({'event': 'pusher:pong', 'data': {}})));
    });

    test('does nothing when the server says realtime is off', () async {
      make(routes: realtimeRoutes(enabled: false));
      await client.start();
      await settle();

      expect(sockets, isEmpty);
      expect(api.requests.where((r) => r.path == '/realtime'), hasLength(1)); // and no retry loop
    });

    test('reconnects after the socket drops, and subscribes again', () async {
      make();
      await client.start();
      sockets.first.established();
      sockets.first.subscribed();
      await settle();

      sockets.first.drop();
      await settle();

      expect(client.connected.value, isFalse);
      expect(sockets, hasLength(2));
      sockets.last.established();
      await settle();
      expect(sockets.last.sent.single['event'], 'pusher:subscribe');
    });

    test('a failed channel authorisation (server error) reconnects; a 401 stops for good', () async {
      make(routes: realtimeRoutes(auth: (_) => (status: 500, body: {'message': 'boom'})));
      await client.start();
      sockets.first.established();
      await settle();
      expect(sockets.length, greaterThan(1));
      await client.stop();

      make(routes: realtimeRoutes(auth: (_) => (status: 401, body: {'message': 'Unauthenticated.'})));
      await client.start();
      sockets.single.established();
      await settle();
      await settle();

      expect(sockets, hasLength(1));
      expect(sockets.single.closed, isTrue);
    });

    test('stop() closes the socket, ignores late events, and forgets the user (fresh config on next start)', () async {
      make();
      await client.start();
      final received = <RealtimeEvent>[];
      client.events.listen(received.add);
      final first = sockets.single;

      await client.stop();
      expect(first.closed, isTrue);
      expect(client.connected.value, isFalse);

      await client.start();
      expect(api.requests.where((r) => r.path == '/realtime'), hasLength(2));
      expect(sockets, hasLength(2));
      expect(received, isEmpty);
    });

    test('start() twice opens one connection', () async {
      make();
      await client.start();
      await client.start();

      expect(sockets, hasLength(1));
    });
  });

  group('keep-alive (fake time)', () {
    test('a silent connection is pinged, and dropped if nothing answers', () {
      fakeAsync((async) {
        make();
        client.start();
        async.elapse(const Duration(milliseconds: 50));
        sockets.first.established(activityTimeout: 30);
        async.elapse(const Duration(milliseconds: 50));

        async.elapse(const Duration(seconds: 31));
        expect(sockets.first.sent.any((m) => m['event'] == 'pusher:ping'), isTrue);

        async.elapse(const Duration(seconds: 31)); // no pong → connection treated as dead
        async.elapse(const Duration(milliseconds: 50));
        expect(sockets, hasLength(2));
        expect(sockets.first.closed, isTrue);
      });
    });

    test('a pong keeps the connection', () {
      fakeAsync((async) {
        make();
        client.start();
        async.elapse(const Duration(milliseconds: 50));
        sockets.first.established(activityTimeout: 30);
        async.elapse(const Duration(milliseconds: 50));

        async.elapse(const Duration(seconds: 31));
        sockets.first.serverSends('pusher:pong', {});
        async.elapse(const Duration(seconds: 31));

        expect(sockets, hasLength(1));
      });
    });
  });

  group('in the app', () {
    late FakeSocket socket;

    Future<void> boot(WidgetTester tester, {bool signedIn = true}) async {
      final store = signedIn ? MemoryStore(token: 'tok', user: AppUser.fromJson(userJson())) : MemoryStore();
      var notificationCalls = 0;
      api = FakeAdapter({
        ...realtimeRoutes(),
        'GET /me': (_) => (status: 200, body: {'user': userJson()}),
        'GET /notifications': (_) {
          notificationCalls++;
          return (status: 200, body: {'unread_count': notificationCalls, 'data': []});
        },
        'GET /designer/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': [taskJson()]}),
        'POST /login': (_) => (status: 200, body: {'token': 'tok', 'user': userJson()}),
        'POST /logout': (_) => (status: 200, body: {}),
        'DELETE /devices': (_) => (status: 200, body: {}),
      });
      sockets = [];

      await tester.pumpWidget(ProviderScope(
        overrides: [
          sessionStoreProvider.overrideWithValue(store),
          apiClientProvider.overrideWith((ref) => fakeClient(api, token: () => ref.read(authProvider).token, onUnauthorized: () => ref.read(authProvider.notifier).signOutLocally())),
          realtimeProvider.overrideWith((ref) {
            final c = RealtimeClient(ref.watch(apiClientProvider), connector: (uri) => FakeSocket(uri)..let(sockets.add), backoff: (_) => Duration.zero);
            ref.onDispose(c.dispose);
            return c;
          }),
        ],
        child: const AdinnApp(),
      ));
      await tester.pumpAndSettle();
      if (sockets.isNotEmpty) socket = sockets.last;
    }

    int notificationFetches() => api.requests.where((r) => r.path == '/notifications').length;

    testWidgets('a live notification refreshes the alerts without a manual refresh', (tester) async {
      await boot(tester);
      expect(sockets, hasLength(1));
      final before = notificationFetches();

      socket.established();
      await tester.pumpAndSettle();
      socket.notification({'task_id': 1, 'category': 'status'});
      await tester.pumpAndSettle();

      expect(notificationFetches(), greaterThan(before));
      expect(find.text('2'), findsWidgets); // the Alerts badge picked up the new unread count
    });

    testWidgets('when the Admin grants approval access the Approvals tab appears at once, without reopening the app', (tester) async {
      await boot(tester);
      socket.established();
      await tester.pumpAndSettle();
      expect(find.text('Approvals'), findsNothing);

      // The Admin grants the right; the server tells the app on the user's channel.
      api.routes['GET /me'] = (_) => (status: 200, body: {'user': {...userJson(), 'is_request_approver': true}});
      api.routes['GET /designer/approvals'] = (_) => (status: 200, body: {'data': [], 'designers': []});
      socket.notification({'title': 'Approval access granted', 'category': 'approval_access'});
      await tester.pumpAndSettle();

      expect(find.text('Approvals'), findsWidgets);
      await tester.pumpAndSettle(const Duration(seconds: 1)); // the approvals badge is fetched as the tab appears
    });

    testWidgets('a comment refreshes only that ticket chat and the alerts, a status change also reloads the board', (tester) async {
      await boot(tester);
      socket.established();
      await tester.pumpAndSettle();
      await tester.tap(find.text('My Tasks').last); // the board is built (and loaded) the first time the tab is opened
      await tester.pumpAndSettle();

      int boardFetches() => api.requests.where((r) => r.path == '/designer/tasks').length;
      final board = boardFetches();
      final alerts = notificationFetches();

      socket.notification({'task_id': 1, 'category': 'comment'});
      await tester.pumpAndSettle();
      expect(notificationFetches(), greaterThan(alerts)); // the bell is always refreshed
      expect(boardFetches(), board); // but a chat message does not reload every board

      socket.notification({'task_id': 1, 'category': 'status'});
      await tester.pumpAndSettle();
      expect(boardFetches(), greaterThan(board)); // a status change does
    });

    testWidgets('no socket while signed out; one opens on login and closes on sign-out', (tester) async {
      await boot(tester, signedIn: false);
      expect(sockets, isEmpty);

      await tester.enterText(find.byType(TextFormField).at(0), 'me@x.test');
      await tester.enterText(find.byType(TextFormField).at(1), 'pw');
      await tester.tap(find.text('Sign in'));
      await tester.pumpAndSettle();
      expect(sockets, hasLength(1));

      await tester.tap(find.text('Profile'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('sign-out')));
      await tester.pumpAndSettle();

      expect(sockets.single.closed, isTrue);
      expect(find.text('Sign in'), findsOneWidget);
    });

    testWidgets('backgrounding the app closes the socket; coming back reconnects and refreshes', (tester) async {
      await boot(tester);
      final first = sockets.single;
      final before = notificationFetches();

      // The real transition order Android reports.
      for (final s in [AppLifecycleState.inactive, AppLifecycleState.hidden, AppLifecycleState.paused]) {
        tester.binding.handleAppLifecycleStateChanged(s);
      }
      await tester.pumpAndSettle();
      expect(first.closed, isTrue);

      for (final s in [AppLifecycleState.hidden, AppLifecycleState.inactive, AppLifecycleState.resumed]) {
        tester.binding.handleAppLifecycleStateChanged(s);
      }
      await tester.pumpAndSettle();

      expect(sockets, hasLength(2));
      expect(notificationFetches(), greaterThan(before));
    });
  });
}

extension _Let<T> on T {
  void let(void Function(T) f) => f(this);
}
