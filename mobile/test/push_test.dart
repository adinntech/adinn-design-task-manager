import 'dart:async';

import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/core/push.dart';
import 'package:adinn_tasks/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

class FakePush implements PushService {
  FakePush({this.configured = true, this.granted = true, this.currentToken = 'fcm-token-1', this.launch});

  final bool configured;
  final bool granted;
  String? currentToken;
  PushEvent? launch;

  final refresh = StreamController<String>.broadcast();
  final tapped = StreamController<PushEvent>.broadcast();
  final foregroundMsgs = StreamController<PushEvent>.broadcast();
  int deleted = 0;
  int permissionAsked = 0;

  @override
  Future<bool> init() async => configured;

  @override
  Future<bool> requestPermission() async {
    permissionAsked++;
    return granted;
  }

  @override
  Future<String?> token() async => currentToken;

  @override
  Stream<String> get tokenRefreshes => refresh.stream;

  @override
  Stream<PushEvent> get opened => tapped.stream;

  @override
  Future<PushEvent?> initialMessage() async => launch;

  @override
  Stream<PushEvent> get foreground => foregroundMsgs.stream;

  @override
  Future<void> deleteToken() async => deleted++;
}

void main() {
  late FakeAdapter api;
  late MemoryStore store;

  Future<void> boot(WidgetTester tester, FakePush push, {bool signedIn = true, bool openTasks = true, Map<String, Handler> routes = const {}}) async {
    store = signedIn ? MemoryStore(token: 'tok', user: AppUser.fromJson(userJson())) : MemoryStore();
    api = FakeAdapter({
      'GET /me': (_) => (status: 200, body: {'user': userJson()}),
      'GET /notifications': (_) => (status: 200, body: {'unread_count': 0, 'data': []}),
      'GET /designer/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': [taskJson()]}),
      'GET /designer/tasks/9': (_) => (status: 200, body: {
            'task': taskJson(id: 9, name: 'Pushed task'),
            'access': {'read_only': true},
            'progress': {'completed': 0, 'total': 3, 'remaining': 3, 'percentage': 0},
            'history': [],
          }),
      'POST /login': (_) => (status: 200, body: {'token': 'tok', 'user': userJson()}),
      'POST /devices': (_) => (status: 201, body: {'message': 'ok'}),
      'DELETE /devices': (_) => (status: 200, body: {'message': 'ok'}),
      'POST /logout': (_) => (status: 200, body: {'message': 'ok'}),
      ...routes,
    });

    await tester.pumpWidget(ProviderScope(
      overrides: [
        sessionStoreProvider.overrideWithValue(store),
        pushServiceProvider.overrideWithValue(push),
        apiClientProvider.overrideWith((ref) => fakeClient(api,
            token: () => ref.read(authProvider).token, onUnauthorized: () => ref.read(authProvider.notifier).signOutLocally())),
      ],
      child: const AdinnApp(),
    ));
    await tester.pumpAndSettle();
    if (signedIn && openTasks) await openMyTasks(tester);
  }

  List<String> calls(String method, String path) => api.requests.where((r) => r.method == method && r.path == path).map((r) => '${r.data}').toList();

  testWidgets('a signed-in device registers its FCM token with the API', (tester) async {
    final push = FakePush();
    await boot(tester, push);

    expect(push.permissionAsked, 1);
    expect(calls('POST', '/devices'), hasLength(1));
    final body = api.requests.firstWhere((r) => r.path == '/devices').data as Map;
    expect(body['token'], 'fcm-token-1');
    expect(body['platform'], 'android');
  });

  testWidgets('logging in registers the device too (not before)', (tester) async {
    final push = FakePush();
    await boot(tester, push, signedIn: false);
    expect(calls('POST', '/devices'), isEmpty);

    await tester.enterText(find.byType(TextFormField).at(0), 'me@x.test');
    await tester.enterText(find.byType(TextFormField).at(1), 'pw');
    await tester.tap(find.text('Sign in'));
    await tester.pumpAndSettle();

    expect(calls('POST', '/devices'), hasLength(1));
  });

  testWidgets('permission denied: nothing is registered and the app still works', (tester) async {
    final push = FakePush(granted: false);
    await boot(tester, push);

    expect(calls('POST', '/devices'), isEmpty);
    expect(find.text('DT-2026-0001'), findsOneWidget);
  });

  testWidgets('Firebase not configured: push is silently off', (tester) async {
    final push = FakePush(configured: false);
    await boot(tester, push);

    expect(push.permissionAsked, 0);
    expect(calls('POST', '/devices'), isEmpty);
    expect(find.text('DT-2026-0001'), findsOneWidget);
  });

  testWidgets('a refreshed token is sent once; the same token is not re-sent', (tester) async {
    final push = FakePush();
    await boot(tester, push);

    push.refresh.add('fcm-token-1'); // unchanged
    push.refresh.add('fcm-token-2');
    push.refresh.add('fcm-token-2');
    await tester.pumpAndSettle();

    final tokens = api.requests.where((r) => r.method == 'POST' && r.path == '/devices').map((r) => (r.data as Map)['token']).toList();
    expect(tokens, ['fcm-token-1', 'fcm-token-2']);
  });

  testWidgets('signing out removes the device BEFORE the session is revoked', (tester) async {
    final push = FakePush();
    await boot(tester, push);

    await tester.tap(find.text('Profile'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('sign-out')));
    await tester.pumpAndSettle();

    final order = api.requests.where((r) => r.path == '/devices' && r.method == 'DELETE' || r.path == '/logout').map((r) => r.path).toList();
    expect(order, ['/devices', '/logout']);
    expect((api.requests.firstWhere((r) => r.method == 'DELETE').data as Map)['token'], 'fcm-token-1');
    expect(push.deleted, 1);
    expect(find.text('Sign in'), findsOneWidget);
  });

  testWidgets('a token rejected by the server (401) signs out without trying to unregister', (tester) async {
    final push = FakePush();
    await boot(tester, push, openTasks: false, routes: {'GET /designer/tasks': (_) => (status: 401, body: {'message': 'Unauthenticated.'})});

    expect(find.text('Sign in'), findsOneWidget);
    expect(api.requests.where((r) => r.method == 'DELETE'), isEmpty);
  });

  testWidgets('tapping a push opens that task', (tester) async {
    final push = FakePush();
    await boot(tester, push);

    push.tapped.add(const PushEvent(taskId: 9));
    await tester.pumpAndSettle();

    expect(find.text('Pushed task'), findsWidgets);
    expect(api.requests.any((r) => r.path == '/designer/tasks/9'), isTrue);
  });

  testWidgets('a push that launched the app opens its task', (tester) async {
    await boot(tester, FakePush(launch: const PushEvent(taskId: 9)), openTasks: false);

    expect(find.text('Pushed task'), findsWidgets);
  });

  testWidgets('a push without a task id does not navigate', (tester) async {
    final push = FakePush();
    await boot(tester, push);

    push.tapped.add(const PushEvent());
    await tester.pumpAndSettle();

    expect(api.requests.where((r) => r.path.startsWith('/designer/tasks/')), isEmpty);
  });

  testWidgets('a push received in the foreground refreshes the alerts', (tester) async {
    final push = FakePush();
    await boot(tester, push);
    final before = api.requests.where((r) => r.path == '/notifications').length;

    push.foregroundMsgs.add(const PushEvent(taskId: 1));
    await tester.pumpAndSettle();

    expect(api.requests.where((r) => r.path == '/notifications').length, greaterThan(before));
  });

  test('PushEvent parses the task id the backend sends as a string', () {
    expect(PushEvent.fromData({'task_id': '42', 'tag': 'x'}).taskId, 42);
    expect(PushEvent.fromData({'task_id': 7}).taskId, 7);
    expect(PushEvent.fromData({}).taskId, isNull);
    expect(PushEvent.fromData({'task_id': 'abc'}).taskId, isNull);
  });
}
