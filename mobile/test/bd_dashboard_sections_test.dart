import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

/// BD dashboard: the long sections show five rows and open the rest, ratings flow sideways, headings stay pinned.
void main() {
  Map<String, dynamic> dashboard() => {
        ...emptyBdDashboardJson(),
        'rework': [
          for (var i = 1; i <= 7; i++)
            {'id': i, 'task_id': 'MOCK-$i', 'name': 'Rework task $i', 'status': 'rework', 'designer': 'Aarav', 'rework_number': 1, 'rework_assigned_at': '2026-11-05T10:00:00+00:00', 'rework_creatives': 2, 'rework_spent': '1d'},
        ],
        'completions': [
          for (var i = 1; i <= 7; i++)
            {'id': 20 + i, 'task_id': 'DONE-$i', 'name': 'Finished $i', 'designer': 'thilak', 'completed_at': '2026-11-06T10:00:00+00:00', 'duration': '2d', 'rating': 4.5, 'rework_count': 0},
        ],
        'ratings': [
          for (var i = 1; i <= 4; i++)
            {'task_db_id': 40 + i, 'task_id': 'RATE-$i', 'task_name': 'Rated $i', 'designer': 'sk_designer', 'reviewer': 'Demo BD', 'rating': 4.0, 'comment': 'Comment $i', 'reviewed_at': '2026-11-06T10:00:00+00:00'},
        ],
      };

  Future<void> boot(WidgetTester tester) async {
    tester.view.physicalSize = const Size(800, 1800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    final adapter = FakeAdapter({
      'GET /me': (_) => (status: 200, body: {'user': userJson(role: 'bd')}),
      'GET /notifications': (_) => (status: 200, body: {'unread_count': 0, 'data': []}),
      'GET /bd/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': []}),
      'GET /bd/dashboard': (_) => (status: 200, body: dashboard()),
    });
    final container = ProviderContainer(overrides: [
      sessionStoreProvider.overrideWithValue(MemoryStore(token: 'tok', user: AppUser.fromJson(userJson(role: 'bd')))),
      apiClientProvider.overrideWith((ref) => fakeClient(adapter, token: () => ref.read(authProvider).token)),
    ]);
    addTearDown(container.dispose);

    await tester.pumpWidget(UncontrolledProviderScope(container: container, child: const AdinnApp()));
    await tester.pumpAndSettle();
  }

  Future<void> scrollTo(WidgetTester tester, Finder f) async {
    await tester.scrollUntilVisible(f, 300, scrollable: find.byType(Scrollable).first);
    await tester.pump();
  }

  testWidgets('rework analytics shows five rows, then "View 2 more" opens the rest and "Show less" folds them', (tester) async {
    await boot(tester);

    await scrollTo(tester, find.byKey(const Key('rework-more')));
    expect(find.text('MOCK-5'), findsOneWidget);
    expect(find.text('MOCK-6'), findsNothing);
    expect(find.descendant(of: find.byKey(const Key('rework-more')), matching: find.text('View 2 more')), findsOneWidget);

    await tester.tap(find.byKey(const Key('rework-more')));
    await tester.pumpAndSettle();
    await scrollTo(tester, find.text('MOCK-7'));
    expect(find.text('MOCK-7'), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('rework-more')), matching: find.text('Show less')), findsOneWidget);

    await tester.tap(find.byKey(const Key('rework-more')));
    await tester.pumpAndSettle();
    expect(find.text('MOCK-7'), findsNothing);
  });

  testWidgets('recent completion activity shows five rows and the rest behind "View 2 more"', (tester) async {
    await boot(tester);

    await scrollTo(tester, find.byKey(const Key('completions-more')));
    expect(find.text('DONE-5'), findsOneWidget);
    expect(find.text('DONE-6'), findsNothing);

    await tester.tap(find.byKey(const Key('completions-more')));
    await tester.pumpAndSettle();
    await scrollTo(tester, find.text('DONE-7'));
    expect(find.text('DONE-7'), findsOneWidget);
  });

  testWidgets('ratings flow sideways in a carousel with View all under it, which lists every rating', (tester) async {
    await boot(tester);

    await scrollTo(tester, find.byKey(const Key('ratings-view-all')));
    expect(find.byKey(const Key('ratings-pager')), findsOneWidget);
    expect(find.text('RATE-1'), findsOneWidget);

    await tester.drag(find.byKey(const Key('ratings-pager')), const Offset(-500, 0));
    await tester.pumpAndSettle();
    expect(find.text('RATE-2'), findsOneWidget);

    await tester.tap(find.byKey(const Key('ratings-view-all')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('ratings-all')), findsOneWidget);
    expect(find.text('Completed Task Ratings (4)'), findsOneWidget);
    await tester.pump(const Duration(seconds: 5)); // let the carousel's own timer pass
  });

  testWidgets('a section heading stays pinned at the top while its rows scroll under it', (tester) async {
    await boot(tester);

    await scrollTo(tester, find.byKey(const Key('rework-more')));
    final heading = tester.getTopLeft(find.text('Rework Analytics')).dy;

    expect(heading, lessThan(220)); // up under the app bar, not scrolled away with its first row
  });
}
