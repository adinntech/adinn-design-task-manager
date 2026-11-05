import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

Map<String, dynamic> _task(int id, String name, String status, {String designer = 'Priya'}) => {
      'id': id,
      'task_id': 'DT-2026-0000$id',
      'task_name': name,
      'display_task_name': name,
      'vertical': 'outdoor',
      'party_type': 'client',
      'party_name': 'Acme $id',
      'priority': 'high',
      'status': status,
      'due_at': DateTime.now().add(const Duration(days: 5)).toUtc().toIso8601String(),
      'total_creatives': 3,
      'created_at': '2026-11-01T09:00:00+00:00',
      'now_with': designer,
      'designer': {'id': 21, 'name': designer},
    };

Map<String, dynamic> _board() => {
      'statuses': [
        {'key': 'pending_bd_approval', 'label': 'New Task Requests'},
        {'key': 'draft', 'label': 'Draft'},
        {'key': 'in_progress', 'label': 'In Progress'},
        {'key': 'waiting_confirmation', 'label': 'Waiting for BD Review'},
      ],
      'tasks': [
        _task(1, 'Highway hoarding', 'in_progress'),
        _task(2, 'Mall signage', 'waiting_confirmation', designer: 'Arun'),
        _task(3, 'Draft brief', 'draft'),
      ],
      'stats': {'total': 3, 'active': 2, 'waiting': 1, 'completed': 0},
      'designers': [],
    };

void main() {
  late FakeAdapter api;

  Future<void> boot(WidgetTester tester) async {
    tester.view.physicalSize = const Size(800, 1800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    api = FakeAdapter({
      'GET /me': (_) => (status: 200, body: {'user': userJson(role: 'bd')}),
      'GET /notifications': (_) => (status: 200, body: {'unread_count': 0, 'data': []}),
      'GET /bd/tasks': (_) => (status: 200, body: _board()),
      'GET /bd/tasks/2': (_) => (status: 200, body: {
            'task': taskJson(id: 2, name: 'Mall signage'),
            'progress': {'completed': 0, 'total': 3, 'remaining': 3, 'percentage': 0},
            'actions': {},
            'history': [],
          }),
    });

    await tester.pumpWidget(ProviderScope(
      retry: (_, _) => null,
      overrides: [
        sessionStoreProvider.overrideWithValue(MemoryStore(token: 'tok', user: AppUser.fromJson(userJson(role: 'bd')))),
        apiClientProvider.overrideWith((ref) => fakeClient(api, token: () => ref.read(authProvider).token)),
      ],
      child: const AdinnApp(),
    ));
    await tester.pumpAndSettle();
  }

  testWidgets('the BD board is a kanban: All Tickets first, one tab per stage with its count', (tester) async {
    await boot(tester);

    expect(find.byKey(const Key('kanban-tabs')), findsOneWidget);
    expect(find.byKey(const Key('count-all')), findsOneWidget);
    expect(tester.widget<Text>(find.byKey(const Key('count-all'))).data, '3');
    expect(find.byKey(const Key('tab-in_progress')), findsOneWidget);
    expect(find.byKey(const Key('new-task')), findsOneWidget);
  });

  testWidgets('cards are read-only for the BD (no Move button) and show who the task is with', (tester) async {
    await boot(tester);

    expect(find.textContaining('Now with: Arun'), findsOneWidget);
    expect(find.textContaining('Now with: Priya'), findsNWidgets(2));
    expect(find.text('Move to…'), findsNothing);
    expect(find.textContaining('ASSIGNED BY'), findsNothing);
  });

  testWidgets('tapping a card opens the task detail', (tester) async {
    await boot(tester);

    await tester.tap(find.text('Mall signage'));
    await tester.pumpAndSettle();

    expect(api.requests.any((r) => r.method == 'GET' && r.path == '/bd/tasks/2'), isTrue);
  });

  testWidgets('the filter sheet has no BD picker for a BD', (tester) async {
    await boot(tester);

    await tester.tap(find.byKey(const Key('kanban-filters')));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('f-priority')), findsOneWidget);
    expect(find.byKey(const Key('f-bd')), findsNothing);
  });
}
