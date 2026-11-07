import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/main.dart';
import 'package:adinn_tasks/router.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

/// The History tab lists status changes AND chat messages, oldest first.
void main() {
  Future<void> openHistory(WidgetTester tester, List<Map<String, dynamic>> history) async {
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

    // The newest message came after the last status change, yet that status is still the current step.
    expect(find.text('Current step'), findsOneWidget);
    final currentY = tester.getTopLeft(find.text('Current step')).dy;
    expect(tester.getTopLeft(find.text('Please use the new logo')).dy, lessThan(currentY));
    expect(tester.getTopLeft(find.text('Sent the first draft')).dy, greaterThan(currentY));
  });

  testWidgets('an older server without kind still draws every row as a status change', (tester) async {
    await openHistory(tester, [
      {'from_status': null, 'to_status': 'assigned_tasks', 'note': 'Task Created', 'created_at': '2026-11-01T09:00:00+00:00'},
    ]);

    expect(find.text('Current step'), findsOneWidget);
    expect(find.text('Task Created'), findsOneWidget);
  });
}
