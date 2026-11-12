import 'package:adinn_tasks/core/app_update.dart';
import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/core/push.dart';
import 'package:adinn_tasks/features/widgets/liquid_nav_bar.dart';
import 'package:adinn_tasks/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

class MemorySeen implements SeenBuildStore {
  MemorySeen([this.value]);

  int? value;

  @override
  Future<int?> read() async => value;

  @override
  Future<void> write(int build) async => value = build;
}

/// The in-app update gate: a too-old build is frozen behind the Update screen; everything else passes.
void main() {
  late FakeAdapter adapter;
  late ProviderContainer container;
  late MemorySeen seen;

  Map<String, dynamic> release({int min = 0, int latest = 0, String? url = 'https://example.test/adinn.apk'}) =>
      {'platform': 'android', 'min_build': min, 'latest_build': latest, 'latest_version': '2.0.0', 'url': url, 'notes': 'Faster boards'};

  Future<void> boot(WidgetTester tester, {required int build, Handler? update, bool signedIn = true, int? lastSeen}) async {
    seen = MemorySeen(lastSeen);
    tester.view.physicalSize = const Size(800, 1600);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    adapter = FakeAdapter({
      'GET /me': (_) => (status: 200, body: {'user': userJson(role: 'bd')}),
      'GET /notifications': (_) => (status: 200, body: {'unread_count': 0, 'data': []}),
      'GET /bd/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': []}),
      'GET /bd/dashboard': (_) => (status: 200, body: emptyBdDashboardJson()),
      'GET /app-update': ?update,
    });

    container = ProviderContainer(overrides: [
      appBuildProvider.overrideWith((ref) async => build),
      seenBuildStoreProvider.overrideWithValue(seen),
      sessionStoreProvider.overrideWithValue(MemoryStore(token: signedIn ? 'tok' : null, user: signedIn ? AppUser.fromJson(userJson(role: 'bd')) : null)),
      apiClientProvider.overrideWith((ref) => fakeClient(adapter, token: () => ref.read(authProvider).token)),
    ]);
    addTearDown(container.dispose);

    await tester.pumpWidget(UncontrolledProviderScope(container: container, child: const AdinnApp()));
    await tester.pumpAndSettle();
    addTearDown(() {}); // (timers are drained below)
  }

  Future<void> drain(WidgetTester tester) => tester.pump(const Duration(seconds: 3));

  group('model', () {
    test('mustUpdate only on a definite "too old" answer', () {
      const none = AppUpdateInfo();
      expect(none.mustUpdate(1), isFalse); // nothing configured => never forced

      const forced = AppUpdateInfo(minBuild: 5, latestBuild: 7);
      expect(forced.mustUpdate(4), isTrue);
      expect(forced.mustUpdate(5), isFalse);
      expect(forced.canUpdate(5), isTrue);
      expect(forced.canUpdate(7), isFalse);
      expect(forced.canUpdate(4), isFalse); // blocked, not merely "available"
    });

    test('parses the server payload and tolerates strings and missing fields', () {
      final i = AppUpdateInfo.fromJson({'min_build': '3', 'latest_build': 4, 'url': 'u'});
      expect((i.minBuild, i.latestBuild, i.url, i.notes), (3, 4, 'u', null));
      expect(AppUpdateInfo.fromJson({}).minBuild, 0);
    });
  });

  testWidgets('an up-to-date app goes straight in', (tester) async {
    await boot(tester, build: 7, update: (_) => (status: 200, body: release(min: 5, latest: 7)));

    expect(find.byKey(const Key('update-required')), findsNothing);
    expect(find.byKey(const Key('update-available')), findsNothing);
    expect(find.text('BD Dashboard'), findsWidgets); // the BD home
  });

  testWidgets('a build below the minimum is frozen behind the Update screen, on top of the signed-in app', (tester) async {
    await boot(tester, build: 3, update: (_) => (status: 200, body: release(min: 5, latest: 7)));

    expect(find.byKey(const Key('update-required')), findsOneWidget);
    expect(find.text('Update required'), findsOneWidget);
    expect(find.text('Faster boards'), findsOneWidget);
    expect(tester.widget<FilledButton>(find.byKey(const Key('update-now'))).onPressed, isNotNull);
    expect(find.byType(LiquidNavBar), findsNothing); // nothing behind it is reachable
    await drain(tester);
  });

  testWidgets('it also blocks on the sign-in screen', (tester) async {
    await boot(tester, build: 3, signedIn: false, update: (_) => (status: 200, body: release(min: 5)));

    expect(find.byKey(const Key('update-required')), findsOneWidget);
    await drain(tester);
  });

  testWidgets('a missing download link disables the button and says so', (tester) async {
    await boot(tester, build: 3, update: (_) => (status: 200, body: release(min: 5, url: null)));

    expect(tester.widget<FilledButton>(find.byKey(const Key('update-now'))).onPressed, isNull);
    expect(find.byKey(const Key('update-no-link')), findsOneWidget);
    await drain(tester);
  });

  testWidgets('"check again" lets the app in once the build is no longer too old', (tester) async {
    var min = 5;
    await boot(tester, build: 3, update: (_) => (status: 200, body: release(min: min)));
    expect(find.byKey(const Key('update-required')), findsOneWidget);

    min = 2; // the admin lowered the minimum (or the app was updated)
    await tester.tap(find.byKey(const Key('update-recheck')));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('update-required')), findsNothing);
  });

  testWidgets('an optional update shows a dismissible bar and never blocks', (tester) async {
    await boot(tester, build: 5, update: (_) => (status: 200, body: release(min: 0, latest: 7)));

    expect(find.byKey(const Key('update-required')), findsNothing);
    expect(find.byKey(const Key('update-available')), findsOneWidget);

    await tester.tap(find.byKey(const Key('update-dismiss')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('update-available')), findsNothing);
  });

  testWidgets('an unreachable or failing update check never locks anyone out', (tester) async {
    await boot(tester, build: 1, update: (_) => (status: 500, body: {'message': 'down'}));

    expect(find.byKey(const Key('update-required')), findsNothing);
    expect(find.text('BD Dashboard'), findsWidgets);
  });

  testWidgets('with no release configured (min 0) nobody is forced', (tester) async {
    await boot(tester, build: 1, update: (_) => (status: 200, body: release()));

    expect(find.byKey(const Key('update-required')), findsNothing);
    expect(find.byKey(const Key('update-available')), findsNothing);
  });

  group("what's new", () {
    testWidgets('after an update it shows the release notes once, and "Got it" closes it', (tester) async {
      await boot(tester, build: 4, lastSeen: 3, update: (_) => (status: 200, body: release(min: 0, latest: 4)));

      expect(find.byKey(const Key('whats-new')), findsOneWidget);
      expect(find.text("What's new in v2.0.0"), findsOneWidget);
      expect(find.text('Faster boards'), findsOneWidget);
      expect(seen.value, 4); // remembered, so it won't repeat

      await tester.tap(find.byKey(const Key('whats-new-close')));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('whats-new')), findsNothing);
      await drain(tester);
    });

    testWidgets('a brand-new install says nothing and just remembers the build', (tester) async {
      await boot(tester, build: 4, update: (_) => (status: 200, body: release(min: 0, latest: 4)));

      expect(find.byKey(const Key('whats-new')), findsNothing);
      expect(seen.value, 4);
    });

    testWidgets('the same build again shows nothing', (tester) async {
      await boot(tester, build: 4, lastSeen: 4, update: (_) => (status: 200, body: release(min: 0, latest: 4)));

      expect(find.byKey(const Key('whats-new')), findsNothing);
    });

    testWidgets('no release notes means no card, but the build is still recorded', (tester) async {
      await boot(tester, build: 4, lastSeen: 3, update: (_) => (status: 200, body: {...release(latest: 4), 'notes': null}));

      expect(find.byKey(const Key('whats-new')), findsNothing);
      expect(seen.value, 4);
    });

    testWidgets('if the notes cannot be read yet, the build is not marked as seen', (tester) async {
      await boot(tester, build: 4, lastSeen: 3, update: (_) => (status: 500, body: {'message': 'down'}));

      expect(find.byKey(const Key('whats-new')), findsNothing);
      expect(seen.value, 3); // it will show on a later launch when the server answers
    });
  });

  test('an update push is recognised and a task push is not', () {
    expect(PushEvent.fromData({'type': 'app_update', 'build': '4'}).isAppUpdate, isTrue);
    final task = PushEvent.fromData({'task_id': '9'});
    expect((task.isAppUpdate, task.taskId), (false, 9));
  });
}
