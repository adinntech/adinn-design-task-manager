import 'package:adinn_tasks/core/api_client.dart';
import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/core/role_api.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

void main() {
  group('ApiClient', () {
    test('sends the bearer token and parses JSON', () async {
      final adapter = FakeAdapter({'GET /me': (options) => (status: 200, body: {'user': userJson()})});
      final client = fakeClient(adapter, token: () => 'abc');

      final body = await client.get('/me');

      expect(body['user']['role'], 'designer');
      expect(adapter.requests.single.headers['Authorization'], 'Bearer abc');
    });

    test('maps Laravel validation errors to field + message', () async {
      final adapter = FakeAdapter({
        'POST /login': (_) => (status: 422, body: {'message': 'x', 'errors': {'login': ['The provided credentials are incorrect.']}}),
      });

      ApiException? error;
      try {
        await fakeClient(adapter).post('/login');
      } on ApiException catch (e) {
        error = e;
      }

      expect(error!.message, 'The provided credentials are incorrect.');
      expect(error.fieldErrors['login'], isNotNull);
      expect(error.statusCode, 422);
    });

    test('a rejected token fires onUnauthorized, a failed login does not', () async {
      var fired = 0;
      final adapter = FakeAdapter({'GET /me': (_) => (status: 401, body: {'message': 'Unauthenticated.'})});

      await fakeClient(adapter, token: () => 'stale', onUnauthorized: () => fired++).get('/me').then((_) {}, onError: (_) {});
      expect(fired, 1);

      await fakeClient(adapter, token: () => null, onUnauthorized: () => fired++).get('/me').then((_) {}, onError: (_) {});
      expect(fired, 1);
    });
  });

  group('AuthController', () {
    ProviderContainer container(FakeAdapter adapter, MemoryStore store) {
      late final ProviderContainer c;
      c = ProviderContainer(overrides: [
        sessionStoreProvider.overrideWithValue(store),
        apiClientProvider.overrideWith((ref) => fakeClient(adapter,
            token: () => ref.read(authProvider).token, onUnauthorized: () => ref.read(authProvider.notifier).signOutLocally())),
      ]);
      addTearDown(c.dispose);
      return c;
    }

    test('login stores the token and signs in', () async {
      final store = MemoryStore();
      final c = container(FakeAdapter({'POST /login': (_) => (status: 200, body: {'token': 'tok', 'user': userJson(role: 'bd')})}), store);

      await c.read(authProvider.notifier).login(' me@x.test ', 'pw');

      expect(c.read(authProvider).status, AuthStatus.signedIn);
      expect(c.read(authProvider).user!.role, 'bd');
      expect(store.token, 'tok');
    });

    test('bad credentials throw and leave the user signed out', () async {
      final c = container(FakeAdapter({'POST /login': (_) => (status: 422, body: {'errors': {'login': ['Nope.']}})}), MemoryStore());

      await expectLater(c.read(authProvider.notifier).login('a', 'b'), throwsA(isA<ApiException>()));
      expect(c.read(authProvider).status, isNot(AuthStatus.signedIn));
    });

    test('restore keeps a valid session and refreshes the user', () async {
      final store = MemoryStore(token: 'tok', user: AppUser.fromJson(userJson()));
      final c = container(FakeAdapter({'GET /me': (_) => (status: 200, body: {'user': userJson(role: 'designer_head')})}), store);

      await c.read(authProvider.notifier).restore();

      expect(c.read(authProvider).status, AuthStatus.signedIn);
      expect(c.read(authProvider).user!.role, 'designer_head');
    });

    test('restore signs out when the server rejects the token', () async {
      final store = MemoryStore(token: 'old', user: AppUser.fromJson(userJson()));
      final c = container(FakeAdapter({'GET /me': (_) => (status: 401, body: {'message': 'Unauthenticated.'})}), store);

      await c.read(authProvider.notifier).restore();

      expect(c.read(authProvider).status, AuthStatus.signedOut);
      expect(store.token, isNull);
    });

    test('restore keeps the cached session when offline', () async {
      final store = MemoryStore(token: 'tok', user: AppUser.fromJson(userJson()));
      final c = container(FakeAdapter({'GET /me': (_) => (status: 500, body: {'message': 'boom'})}), store);

      await c.read(authProvider.notifier).restore();

      expect(c.read(authProvider).status, AuthStatus.signedIn);
    });

    test('logout clears the session even if the server call fails', () async {
      final store = MemoryStore(token: 'tok', user: AppUser.fromJson(userJson()));
      final c = container(FakeAdapter({'POST /logout': (_) => (status: 500, body: {})}), store);
      await c.read(authProvider.notifier).restore();

      await c.read(authProvider.notifier).logout();

      expect(c.read(authProvider).status, AuthStatus.signedOut);
      expect(store.token, isNull);
    });
  });

  group('RoleApi', () {
    test('designer board hits /designer/tasks and parses columns + tasks', () async {
      final adapter = FakeAdapter({
        'GET /designer/tasks': (_) => (status: 200, body: {
              'statuses': [{'key': 'in_progress', 'label': 'In Progress'}],
              'tasks': [taskJson(), taskJson(id: 2, status: 'rework')],
            }),
      });

      final board = await RoleApi(fakeClient(adapter), 'designer').board();

      expect(board.tasks, hasLength(2));
      expect(board.inStatus('rework').single.taskId, 'DT-2026-0002');
      expect(board.statuses.single.label, 'In Progress');
    });

    test('admin board reads the paginated shape', () async {
      final adapter = FakeAdapter({
        'GET /admin/tasks': (_) => (status: 200, body: {
              'data': [taskJson()],
              'filters': {'statuses': [{'key': 'in_progress', 'label': 'In Progress'}]},
            }),
      });

      final board = await RoleApi(fakeClient(adapter), 'admin').board(search: 'x');

      expect(board.tasks.single.name, 'Banner');
      expect(adapter.requests.single.queryParameters['search'], 'x');
    });

    test('unknown roles are rejected', () {
      expect(() => RoleApi(fakeClient(FakeAdapter({})), 'ghost').board(), throwsA(isA<ApiException>()));
    });

    test('notifications parse title, message and task id', () async {
      final adapter = FakeAdapter({
        'GET /notifications': (_) => (status: 200, body: {
              'unread_count': 1,
              'data': [{'id': 'n1', 'read': false, 'created_at': '2026-11-04T10:00:00+00:00', 'data': {'title': 'New task', 'message': 'Hi', 'task_id': 7}}],
            }),
      });

      final result = await RoleApi(fakeClient(adapter), 'bd').notifications();

      expect(result.unread, 1);
      expect(result.items.single.taskId, 7);
      expect(result.items.single.title, 'New task');
    });
  });

  test('statusLabel falls back to a humanised key', () {
    expect(statusLabel('in_progress'), 'In progress');
    expect(statusLabel('some_new_status'), 'some new status');
  });
}
