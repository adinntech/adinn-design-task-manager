import 'package:adinn_tasks/core/api_client.dart';
import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/config.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/core/server_settings.dart';
import 'package:adinn_tasks/main.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

class MemoryServerStore implements ServerStore {
  MemoryServerStore([this.url]);

  String? url;

  @override
  Future<String?> read() async => url;

  @override
  Future<void> write(String u) async => url = u;

  @override
  Future<void> clear() async => url = null;
}

void main() {
  group('normalizeServerUrl', () {
    test('adds http:// and the API prefix to a bare host:port', () {
      expect(normalizeServerUrl('192.168.1.52:8000'), 'http://192.168.1.52:8000/api/v1');
    });

    test('keeps https and a path prefix, trims slashes and spaces', () {
      expect(normalizeServerUrl('  https://tools.example.com/design-workflow/  '), 'https://tools.example.com/design-workflow/api/v1');
    });

    test('does not double the API prefix', () {
      expect(normalizeServerUrl('http://10.0.0.5:8000/api/v1'), 'http://10.0.0.5:8000/api/v1');
      expect(normalizeServerUrl('http://10.0.0.5:8000/api/v1/'), 'http://10.0.0.5:8000/api/v1');
    });

    test('rejects a dot typed instead of the colon before the port, and other malformed IPv4 hosts', () {
      expect(normalizeServerUrl('192.168.1.52.8000'), isNull);
      expect(normalizeServerUrl('http://192.168.1.52.8000/api/v1'), isNull);
      expect(normalizeServerUrl('192.168.1'), isNull);
      expect(normalizeServerUrl('192.168.1.300:8000'), isNull);
      expect(normalizeServerUrl('192.168.1.52:8000'), isNotNull);
      expect(normalizeServerUrl('localhost:8000'), isNotNull);
      expect(normalizeServerUrl('tools.example.com'), isNotNull);
    });

    test('rejects blanks, other schemes and hostless input', () {
      expect(normalizeServerUrl(''), isNull);
      expect(normalizeServerUrl('   '), isNull);
      expect(normalizeServerUrl('ftp://example.com'), isNull);
      expect(normalizeServerUrl('http://'), isNull);
    });
  });

  test('the health check sits beside the API prefix, including behind a path prefix', () {
    expect(healthUri('http://192.168.1.52:8000/api/v1').toString(), 'http://192.168.1.52:8000/up');
    expect(healthUri('https://x.com/design-workflow/api/v1').toString(), 'https://x.com/design-workflow/up');
  });

  group('ServerUrlController', () {
    ProviderContainer make(MemoryServerStore store) {
      final c = ProviderContainer(overrides: [serverStoreProvider.overrideWithValue(store)]);
      addTearDown(c.dispose);
      return c;
    }

    test('starts at the build-time default', () {
      expect(make(MemoryServerStore()).read(serverUrlProvider), AppConfig.apiBaseUrl);
    });

    test('load() adopts a saved address; a corrupt one is ignored', () async {
      final good = make(MemoryServerStore('http://192.168.1.52:8000/api/v1'));
      await good.read(serverUrlProvider.notifier).load();
      expect(good.read(serverUrlProvider), 'http://192.168.1.52:8000/api/v1');

      final bad = make(MemoryServerStore('ftp://nope'));
      await bad.read(serverUrlProvider.notifier).load();
      expect(bad.read(serverUrlProvider), AppConfig.apiBaseUrl);
    });

    test('save() persists and switches; reset() goes back to the default', () async {
      final store = MemoryServerStore();
      final c = make(store);

      await c.read(serverUrlProvider.notifier).save('http://10.1.1.1:8000/api/v1');
      expect(c.read(serverUrlProvider), 'http://10.1.1.1:8000/api/v1');
      expect(store.url, 'http://10.1.1.1:8000/api/v1');

      await c.read(serverUrlProvider.notifier).reset();
      expect(c.read(serverUrlProvider), AppConfig.apiBaseUrl);
      expect(store.url, isNull);
    });

    test('a later load() does not overwrite a just-saved address', () async {
      final store = MemoryServerStore();
      final c = make(store);

      await c.read(serverUrlProvider.notifier).save('http://10.1.1.1:8000/api/v1');
      store.url = 'http://stale:1/api/v1';
      await c.read(serverUrlProvider.notifier).load();

      expect(c.read(serverUrlProvider), 'http://10.1.1.1:8000/api/v1');
    });

    test('restore() loads the saved address before it calls the API', () async {
      final seen = <String>[];
      final adapter = FakeAdapter({
        'GET /me': (o) {
          seen.add(o.baseUrl);
          return (status: 200, body: {'user': userJson()});
        },
      });

      final c = ProviderContainer(overrides: [
        serverStoreProvider.overrideWithValue(MemoryServerStore('http://192.168.1.52:8000/api/v1')),
        sessionStoreProvider.overrideWithValue(MemoryStore(token: 'tok', user: AppUserFixture.user())),
        apiClientProvider.overrideWith((ref) => ApiClient(
              dio: Dio(BaseOptions(baseUrl: ref.watch(serverUrlProvider)))..httpClientAdapter = adapter,
              tokenProvider: () => ref.read(authProvider).token,
            )),
      ]);
      addTearDown(c.dispose);

      await c.read(authProvider.notifier).restore();

      expect(seen, ['http://192.168.1.52:8000/api/v1']);
    });
  });

  group('server dialog on the login screen', () {
    late MemoryServerStore store;
    late List<String> baseUrls;
    late FakeAdapter adapter;

    Future<void> boot(WidgetTester tester, {ServerChecker? checker}) async {
      store = MemoryServerStore();
      baseUrls = [];
      adapter = FakeAdapter({
        'POST /login': (o) {
          baseUrls.add(o.baseUrl);
          return (status: 422, body: {'errors': {'login': ['The provided credentials are incorrect.']}});
        },
      });

      await tester.pumpWidget(ProviderScope(
        overrides: [
          serverStoreProvider.overrideWithValue(store),
          sessionStoreProvider.overrideWithValue(MemoryStore()),
          serverCheckerProvider.overrideWithValue(checker ?? (_) async => null),
          apiClientProvider.overrideWith((ref) => ApiClient(dio: Dio(BaseOptions(baseUrl: ref.watch(serverUrlProvider)))..httpClientAdapter = adapter)),
        ],
        child: const AdinnApp(),
      ));
      await tester.pumpAndSettle();
    }

    Future<void> openDialog(WidgetTester tester) async {
      await tester.tap(find.byKey(const Key('server-settings')));
      await tester.pumpAndSettle();
    }

    testWidgets('rejects an unusable address without saving it', (tester) async {
      await boot(tester);
      await openDialog(tester);

      await tester.enterText(find.byKey(const Key('server-url')), 'ftp://nonsense');
      await tester.tap(find.byKey(const Key('server-save')));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('server-error')), findsOneWidget);
      expect(store.url, isNull);
    });

    testWidgets('Test reports success and failure with the checker\'s message', (tester) async {
      var reachable = true;
      await boot(tester, checker: (url) async => reachable ? null : 'Cannot reach that address.');
      await openDialog(tester);

      await tester.enterText(find.byKey(const Key('server-url')), '192.168.1.52:8000');
      await tester.tap(find.byKey(const Key('server-test')));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('server-ok')), findsOneWidget);

      reachable = false;
      await tester.tap(find.byKey(const Key('server-test')));
      await tester.pumpAndSettle();
      expect(find.text('Cannot reach that address.'), findsOneWidget);
      expect(find.byKey(const Key('server-ok')), findsNothing);
    });

    testWidgets('Save normalises and persists the address, and the next login uses it; Reset restores the default', (tester) async {
      await boot(tester);
      await openDialog(tester);

      await tester.enterText(find.byKey(const Key('server-url')), '192.168.1.52:8000');
      await tester.tap(find.byKey(const Key('server-save')));
      await tester.pumpAndSettle();
      expect(store.url, 'http://192.168.1.52:8000/api/v1');

      await tester.enterText(find.byType(TextFormField).at(0), 'me@x.test');
      await tester.enterText(find.byType(TextFormField).at(1), 'pw');
      await tester.tap(find.text('Sign in'));
      await tester.pumpAndSettle();
      expect(baseUrls, ['http://192.168.1.52:8000/api/v1']);

      await openDialog(tester);
      await tester.tap(find.byKey(const Key('server-reset')));
      await tester.pumpAndSettle();
      expect(store.url, isNull);

      await tester.tap(find.text('Sign in'));
      await tester.pumpAndSettle();
      expect(baseUrls.last, AppConfig.apiBaseUrl);
    });
  });
}

class AppUserFixture {
  static AppUser user() => AppUser.fromJson(userJson());
}
