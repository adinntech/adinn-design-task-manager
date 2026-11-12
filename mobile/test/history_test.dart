import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/main.dart';
import 'package:adinn_tasks/router.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

/// The History tab: Pipeline History (status changes AND chat messages) and Edit History, the most recent first.
void main() {
  Future<void> openHistory(WidgetTester tester, List<Map<String, dynamic>> history, {List<Map<String, dynamic>> edits = const []}) async {
    tester.view.physicalSize = const Size(800, 1800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    final api = FakeAdapter({
      'GET /me': (_) => (status: 200, body: {'user': userJson()}),
      'GET /notifications': (_) => (status: 200, body: {'unread_count': 0, 'data': []}),
      'GET /designer/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': []}),
      'GET /designer/tasks/1': (_) => (status: 200, body: {
            'task': taskJson(status: 'in_progress'),
            'access': {'current': true, 'read_only': false, 'reason': null},
            'progress': {'completed': 1, 'total': 3, 'remaining': 2, 'percentage': 33},
            'history': history,
            'edit_history': edits,
            'requests': [],
          }),
      'GET /designer/tasks/1/comments': (_) => (status: 200, body: {'data': []}),
    });

    await tester.pumpWidget(ProviderScope(
      retry: (_, _) => null,
      overrides: [
        sessionStoreProvider.overrideWithValue(MemoryStore(token: 'tok', user: AppUser.fromJson(userJson()))),
        apiClientProvider.overrideWith((ref) => fakeClient(api, token: () => ref.read(authProvider).token)),
      ],
      child: const AdinnApp(),
    ));
    await tester.pumpAndSettle();

    ProviderScope.containerOf(tester.element(find.byType(AdinnApp))).read(routerProvider).push('/task/1');
    await tester.pumpAndSettle();
    await tester.tap(find.text('History'));
    await tester.pumpAndSettle();
  }

  Map<String, dynamic> status(String to, String at, {String? note}) =>
      {'kind': 'status', 'from_status': null, 'to_status': to, 'change_source': 'x', 'note': note, 'created_at': at};

  Map<String, dynamic> comment(String text, String at, {String by = 'Bela BD', String role = 'bd', int files = 0}) =>
      {'kind': 'comment', 'note': text, 'by': by, 'role': role, 'attachments': files, 'created_at': at};

  testWidgets('chat messages appear in the timeline, and Current step stays the latest status change', (tester) async {
    await openHistory(tester, [
      status('assigned_tasks', '2026-11-01T09:00:00+00:00', note: 'Task Created'),
      comment('Please use the new logo', '2026-11-01T10:00:00+00:00'),
      status('in_progress', '2026-11-02T09:00:00+00:00'),
      comment('Sent the first draft', '2026-11-02T11:00:00+00:00', by: 'Priya', role: 'designer', files: 2),
    ]);

    expect(find.text('Please use the new logo'), findsOneWidget);
    expect(find.text('Sent the first draft'), findsOneWidget);
    expect(find.text('Bela BD · Bd'), findsOneWidget);
    expect(find.text('Priya · Designer'), findsOneWidget);
    expect(find.text('+ 2 files'), findsOneWidget);

    // Most recent first. The newest message came after the last status change, yet that status is still the current step:
    // so the message sits above it, and the older message below.
    expect(find.text('Current step'), findsOneWidget);
    final currentY = tester.getTopLeft(find.text('Current step')).dy;
    expect(tester.getTopLeft(find.text('Sent the first draft')).dy, lessThan(currentY));
    expect(tester.getTopLeft(find.text('Please use the new logo')).dy, greaterThan(currentY));
  });

  testWidgets('the newest event is at the top and the oldest at the bottom', (tester) async {
    await openHistory(tester, [
      status('assigned_tasks', '2026-11-01T09:00:00+00:00', note: 'Task Created'),
      status('review_analysis', '2026-11-01T11:00:00+00:00'),
      status('in_progress', '2026-11-02T09:00:00+00:00'),
    ]);

    final newest = tester.getTopLeft(find.text('In progress')).dy;
    final middle = tester.getTopLeft(find.text('Review & analysis')).dy;
    final oldest = tester.getTopLeft(find.text('Assigned')).dy;
    expect(newest, lessThan(middle));
    expect(middle, lessThan(oldest));
    expect(tester.widget<Text>(find.byKey(const Key('history-count'))).data, '3 Events');
  });

  testWidgets('Edit History is its own view: who changed what, old value struck through, newest update first', (tester) async {
    await openHistory(tester, [status('assigned_tasks', '2026-11-01T09:00:00+00:00')], edits: [
      {'editor': 'Bela BD', 'created_at': '2026-11-03T10:00:00+00:00', 'changes': [{'field': 'Priority', 'old': 'medium', 'new': 'high'}, {'field': 'Total Creatives', 'old': '3', 'new': '5'}]},
      {'editor': 'Bela BD', 'created_at': '2026-11-02T10:00:00+00:00', 'changes': [{'field': 'Due Date', 'old': null, 'new': '10 Nov'}]},
    ]);

    expect(find.text('Edit History'), findsOneWidget); // the switch button
    expect(find.text('Priority'), findsNothing); // still on Pipeline History

    await tester.tap(find.byKey(const Key('history-edits')));
    await tester.pumpAndSettle();

    expect(tester.widget<Text>(find.byKey(const Key('history-count'))).data, '2 Updates');
    expect(find.text('Priority'), findsOneWidget);
    expect(find.text('medium'), findsOneWidget);
    expect(find.text('high'), findsOneWidget);
    expect(find.text('—'), findsOneWidget); // an empty old value
    expect(tester.getTopLeft(find.text('Priority')).dy, lessThan(tester.getTopLeft(find.text('Due Date')).dy));

    await tester.tap(find.byKey(const Key('history-pipeline')));
    await tester.pumpAndSettle();
    expect(find.text('Priority'), findsNothing);
  });

  testWidgets('with no edits recorded, Edit History says so', (tester) async {
    await openHistory(tester, [status('assigned_tasks', '2026-11-01T09:00:00+00:00')]);

    await tester.tap(find.byKey(const Key('history-edits')));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('history-edits-empty')), findsOneWidget);
  });

  testWidgets('an older server without kind still draws every row as a status change', (tester) async {
    await openHistory(tester, [
      {'from_status': null, 'to_status': 'assigned_tasks', 'note': 'Task Created', 'created_at': '2026-11-01T09:00:00+00:00'},
    ]);

    expect(find.text('Current step'), findsOneWidget);
    expect(find.text('Task Created'), findsOneWidget);
  });
}
