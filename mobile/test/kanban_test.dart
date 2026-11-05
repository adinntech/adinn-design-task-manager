import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/features/kanban/kanban_card.dart';
import 'package:adinn_tasks/features/kanban/kanban_models.dart';
import 'package:adinn_tasks/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

String _iso(Duration fromNow) => DateTime.now().add(fromNow).toUtc().toIso8601String();

const _working = ['review_analysis', 'need_clarification', 'yet_to_start', 'in_progress', 'waiting_confirmation'];

Map<String, dynamic> _card(int id, String name, String status, {Map<String, dynamic>? extra}) => {
      'id': id,
      'task_id': 'DT-2026-0000$id',
      'task_name': name,
      'display_task_name': name,
      'vertical': 'roadshow',
      'party_type': 'client',
      'party_name': 'Acme $id',
      'priority': 'medium',
      'status': status,
      'due_at': _iso(const Duration(days: 10)),
      'total_creatives': 4,
      'assigner': 'Bela BD',
      'created_at': '2026-11-01T09:00:00+00:00',
      'completed_at': null,
      'rating': null,
      'now_with': null,
      'progress': null,
      'waiting_since': null,
      'continuation_label': null,
      'continuation_event_label': null,
      'previous_month_label': null,
      'tag': null,
      'forward_to': <String>[],
      'backward_to': <String>[],
      ...?extra,
    };

Map<String, dynamic> _board() => {
      'columns': [
        {'key': 'pending_bd_approval', 'label': 'Awaiting BD Approval', 'type': 'status'},
        {'key': 'bd_rejected', 'label': 'Rejected by BD', 'type': 'status'},
        {'key': 'assigned_tasks', 'label': 'New Assignments', 'type': 'status'},
        {'key': 'review_analysis', 'label': 'Requirement Review', 'type': 'status'},
        {'key': 'need_clarification', 'label': 'Clarification Needed', 'type': 'status'},
        {'key': 'yet_to_start', 'label': 'Ready to Start', 'type': 'status'},
        {'key': 'in_progress', 'label': 'In Progress', 'type': 'status'},
        {'key': 'waiting_confirmation', 'label': 'Waiting for BD Review', 'type': 'status'},
        {'key': 'rework', 'label': 'Rework', 'type': 'status'},
        {'key': 'prepare_printing_file', 'label': 'Prepare Printing File', 'type': 'status'},
        {'key': 'completed', 'label': 'Completed', 'type': 'status'},
        {'key': 'split_log', 'label': 'Split Tasks (Nov 2026)', 'type': 'split_log'},
        {'key': 'swap_tasks', 'label': 'Swapped Tasks', 'type': 'status'},
        {'key': 'self_declined', 'label': 'Self Declined', 'type': 'status'},
      ],
      'tasks': [
        _card(1, 'Awaiting card', 'pending_bd_approval'),
        _card(2, 'Fresh brief', 'assigned_tasks', extra: {
          'priority': 'urgent',
          'party_type': 'agency',
          'party_name': 'Zebra Agency',
          'due_at': _iso(const Duration(hours: -5)),
          'forward_to': _working,
          'tag': {'label': 'Decline · Pending', 'title': 'Decline · Pending', 'tone': 'pending'},
          'previous_month_label': 'Previous Month Task • Oct 2026',
        }),
        _card(3, 'Highway hoarding', 'in_progress', extra: {
          'priority': 'high',
          'due_at': _iso(const Duration(hours: 30)),
          'progress': {'completed': 1, 'remaining': 3, 'percentage': 25},
          'forward_to': ['waiting_confirmation'],
          'backward_to': ['assigned_tasks', 'review_analysis', 'need_clarification', 'yet_to_start'],
          'continuation_label': 'Continued to Dec 2026',
          'continuation_event_label': 'Completed 3 Dec',
        }),
        _card(4, 'Awaiting review', 'waiting_confirmation', extra: {
          'due_at': _iso(const Duration(hours: 3)),
          'progress': {'completed': 4, 'remaining': 0, 'percentage': 100},
          'waiting_since': _iso(const Duration(hours: -3, minutes: -20)),
          'backward_to': ['assigned_tasks'],
        }),
        _card(5, 'Festive creative', 'completed', extra: {
          'rating': 4.5,
          'completed_at': '2026-11-10T10:00:00+00:00',
          'due_at': _iso(const Duration(days: -3)),
        }),
        _card(6, 'Handed over', 'self_declined', extra: {'now_with': 'Meera'}),
        _card(7, 'Due today', 'yet_to_start', extra: {'due_at': DateTime.now().add(const Duration(minutes: 1)).toUtc().toIso8601String(), 'forward_to': ['in_progress']}),
      ],
      'split_log': [
        {
          'id': 20, 'request_id': 90, 'task_id': 'DT-2026-00020', 'name': 'Big job (split)', 'has_child': true,
          'status': {'key': 'approved', 'label': 'Split approved'}, 'split_from': 'DT-2026-00009', 'designer': 'Meera', 'creatives': 2,
          'requested_split': 2, 'approved_at': '2026-11-12T10:00:00+00:00', 'requested_at': '2026-11-11T10:00:00+00:00',
        },
        {
          'id': 21, 'request_id': 91, 'task_id': 'DT-2026-00021', 'name': 'Waiting split', 'has_child': false,
          'status': {'key': 'pending', 'label': 'Pending approval'}, 'split_from': 'DT-2026-00021', 'designer': null, 'creatives': 6,
          'requested_split': 3, 'approved_at': null, 'requested_at': '2026-11-13T10:00:00+00:00',
        },
        {
          'id': 22, 'request_id': 92, 'task_id': 'DT-2026-00022', 'name': 'Refused split', 'has_child': false,
          'status': {'key': 'rejected', 'label': 'Split rejected'}, 'split_from': 'DT-2026-00022', 'designer': null, 'creatives': 4,
          'requested_split': 1, 'approved_at': null, 'requested_at': '2026-11-09T10:00:00+00:00',
        },
        {
          'id': 23, 'request_id': 93, 'task_id': 'DT-2026-00023', 'name': 'Finished first', 'has_child': false,
          'status': {'key': 'completed_all', 'label': 'You completed all'}, 'split_from': 'DT-2026-00023', 'designer': null, 'creatives': 2,
          'requested_split': 1, 'approved_at': null, 'requested_at': '2026-11-08T10:00:00+00:00',
        },
      ],
      'stats': {'total': 7, 'active': 5, 'waiting': 1, 'completed': 1},
      'active_breakdown': [
        {'label': 'In Progress', 'count': 1},
        {'label': 'Rework', 'count': 0},
        {'label': 'New Assignments', 'count': 1},
      ],
      'period_label': 'Nov 2026',
      'bds': [
        {'id': 5, 'name': 'Bela BD'},
        {'id': 6, 'name': 'Chetan BD'},
      ],
    };

void main() {
  late FakeAdapter api;
  late int boardFetches;

  Future<void> boot(WidgetTester tester, {Map<String, dynamic>? board, Map<String, Handler> routes = const {}, Size size = const Size(800, 1800)}) async {
    tester.view.physicalSize = size;
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    boardFetches = 0;
    api = FakeAdapter({
      'GET /me': (_) => (status: 200, body: {'user': userJson()}),
      'GET /notifications': (_) => (status: 200, body: {'unread_count': 0, 'data': []}),
      'GET /designer/tasks': (_) {
        boardFetches++;
        return (status: 200, body: board ?? _board());
      },
      'GET /designer/tasks/3': (_) => (status: 200, body: {
            'task': taskJson(id: 3, name: 'Highway hoarding'),
            'access': {'read_only': true},
            'progress': {'completed': 1, 'total': 4, 'remaining': 3, 'percentage': 25},
            'history': [],
          }),
      'GET /designer/tasks/20': (_) => (status: 200, body: {
            'task': taskJson(id: 20, name: 'Big job (split)'),
            'access': {'read_only': true},
            'progress': {'completed': 0, 'total': 2, 'remaining': 2, 'percentage': 0},
            'history': [],
          }),
      ...routes,
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
    await openMyTasks(tester);
  }

  /// The tab strip scrolls sideways, so bring the tab into view before tapping it.
  Future<void> openTab(WidgetTester tester, String key) async {
    final strip = find.descendant(of: find.byKey(const Key('kanban-tabs')), matching: find.byType(Scrollable));
    // Start from the left end so the tab can be reached whichever way the strip was last scrolled.
    tester.state<ScrollableState>(strip.first).position.jumpTo(0);
    await tester.pump();
    for (var i = 0; i < 25 && find.byKey(Key('tab-$key')).evaluate().isEmpty; i++) {
      await tester.drag(strip.first, const Offset(-300, 0));
      await tester.pumpAndSettle();
    }
    await tester.ensureVisible(find.byKey(Key('tab-$key'))); // built is not the same as on screen
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(Key('tab-$key')));
    await tester.pumpAndSettle();
  }

  bool isSelected(WidgetTester tester, String key) => tester.widget<ChoiceChip>(find.byKey(Key('tab-$key'))).selected;

  Map<String, dynamic> lastBody(String path) => Map<String, dynamic>.from(api.requests.lastWhere((r) => r.method == 'POST' && r.path == path).data as Map);

  Map<String, dynamic> lastBoardQuery() => api.requests.lastWhere((r) => r.path == '/designer/tasks').queryParameters;

  group('model', () {
    test('filters serialise only what is set, and compare by value', () {
      expect(const BoardFilters().toQuery(), isEmpty);
      expect(const BoardFilters().isDefault, isTrue);

      final f = BoardFilters(search: ' zebra ', vertical: 'signage', priority: 'overdue', bdId: 5, projectNumber: 'ZP-1', period: 'custom', dateFrom: DateTime(2026, 11, 2), dateTo: DateTime(2026, 11, 20));
      expect(f.toQuery(), {'search': 'zebra', 'vertical': 'signage', 'priority': 'overdue', 'bd_id': 5, 'project_number': 'ZP-1', 'period': 'custom', 'date_from': '2026-11-02', 'date_to': '2026-11-20'});
      expect(f.isDefault, isFalse);
      expect(f.copyWith(bdId: null).bdId, isNull);
      expect(const BoardFilters(period: 'last_month').toQuery(), {'period': 'last_month'});
      expect(const BoardFilters(period: 'last_month', dateFrom: null).toQuery().containsKey('date_from'), isFalse);
      expect(const BoardFilters(search: 'a') == const BoardFilters(search: 'a'), isTrue);
      expect(const BoardFilters(search: ' ').isSearching, isFalse);
      expect(const BoardFilters(projectNumber: 'x').isSearching, isTrue);
    });

    test('the due pill follows the web rules', () {
      final now = DateTime(2026, 11, 5, 12);
      KanbanCard c(String status, DateTime? due) => KanbanCard(id: 1, taskId: 'T', name: 'n', status: status, priority: 'low', dueAt: due);

      expect(c('completed', now.subtract(const Duration(days: 9))).duePill(now), DuePill.completed);
      expect(c('in_progress', now.subtract(const Duration(minutes: 1))).duePill(now), DuePill.overdue);
      expect(c('in_progress', DateTime(2026, 11, 5, 23)).duePill(now), DuePill.dueToday);
      expect(c('in_progress', now.add(const Duration(hours: 40))).duePill(now), DuePill.dueSoon);
      expect(c('in_progress', now.add(const Duration(hours: 49))).duePill(now), DuePill.onTrack);
      expect(c('in_progress', null).duePill(now), DuePill.onTrack);
    });

    test('humanDuration', () {
      expect(humanDuration(const Duration(seconds: 20)), 'less than a minute');
      expect(humanDuration(const Duration(minutes: 12)), '12m');
      expect(humanDuration(const Duration(hours: 5, minutes: 12)), '5h 12m');
      expect(humanDuration(const Duration(days: 3, hours: 4)), '3d 4h');
    });

    test('a payload with cards but no columns still gets one column per status', () {
      final b = KanbanBoard.fromJson({'tasks': [_card(1, 'a', 'in_progress'), _card(2, 'b', 'completed')]});
      expect(b.columns.map((c) => c.key), ['in_progress', 'completed']);
      expect(KanbanBoard.fromJson({}).columns, isEmpty);
    });
  });

  group('board', () {
    testWidgets('lands on All Tickets, which counts every ticket; the stage tabs carry the web names with counts', (tester) async {
      await boot(tester);

      expect(isSelected(tester, 'all'), isTrue);
      expect(find.text('All Tickets'), findsOneWidget);
      expect(tester.widget<Text>(find.byKey(const Key('count-all'))).data, '7');
      expect(find.text('Awaiting BD Approval'), findsOneWidget);
      expect(tester.widget<Text>(find.byKey(const Key('count-pending_bd_approval'))).data, '1');
      expect(find.text('7 visible'), findsOneWidget);
    });

    testWidgets('All Tickets lists every ticket in pipeline order, each saying which stage it is in', (tester) async {
      await boot(tester, size: const Size(800, 6000));

      const stages = {
        1: 'Stage: Awaiting BD Approval',
        2: 'Stage: New Assignments',
        7: 'Stage: Ready to Start',
        3: 'Stage: In Progress',
        4: 'Stage: Waiting for BD Review',
        5: 'Stage: Completed',
        6: 'Stage: Self Declined',
      };

      // Pipeline order: awaiting, new, ready, in progress, waiting, completed, self declined.
      var lastY = -1.0;
      for (final e in stages.entries) {
        expect(find.byKey(Key('stage-${e.key}')), findsOneWidget, reason: 'ticket ${e.key}');
        expect(find.descendant(of: find.byKey(Key('stage-${e.key}')), matching: find.text(e.value)), findsOneWidget);
        final y = tester.getTopLeft(find.byKey(Key('card-${e.key}'))).dy;
        expect(y, greaterThan(lastY), reason: 'ticket ${e.key} should come after the previous stage');
        lastY = y;
      }
    });

    testWidgets('the stage pill appears only in All Tickets - a stage tab already says where its tickets are', (tester) async {
      await boot(tester);

      await openTab(tester, 'in_progress');
      expect(find.text('Highway hoarding'), findsOneWidget);
      expect(find.byKey(const Key('stage-3')), findsNothing);
    });

    testWidgets('all columns are there in the web order, the split log right after Completed', (tester) async {
      await boot(tester, size: const Size(5400, 1600));

      final keys = tester
          .widgetList(find.byWidgetPredicate((w) => w.key is ValueKey<String> && (w.key! as ValueKey<String>).value.startsWith('tab-')))
          .map((w) => (w.key! as ValueKey<String>).value.substring(4))
          .toList();

      expect(keys, ['all', 'pending_bd_approval', 'bd_rejected', 'assigned_tasks', 'review_analysis', 'need_clarification', 'yet_to_start', 'in_progress', 'waiting_confirmation', 'rework', 'prepare_printing_file', 'completed', 'split_log', 'swap_tasks', 'self_declined']);
      expect(find.text('Split Tasks (Nov 2026)'), findsOneWidget);
      expect(find.text('Self Declined'), findsOneWidget);
      expect(tester.widget<Text>(find.byKey(const Key('count-rework'))).data, '0');
      expect(tester.widget<Text>(find.byKey(const Key('count-split_log'))).data, '4');
    });

    testWidgets('summary cards show the totals, and the Active card lists only non-zero statuses', (tester) async {
      await boot(tester);

      expect(tester.widget<Text>(find.byKey(const Key('metric-total'))).data, '7');
      expect(tester.widget<Text>(find.byKey(const Key('metric-active'))).data, '5');
      expect(tester.widget<Text>(find.byKey(const Key('metric-waiting'))).data, '1');
      expect(tester.widget<Text>(find.byKey(const Key('metric-completed'))).data, '1');
      expect(find.text('In Progress: 1'), findsOneWidget);
      expect(find.text('New Assignments: 1'), findsOneWidget);
      expect(find.text('Rework: 0'), findsNothing);
    });

    testWidgets('a card shows id, due pill, priority, name, client, tag, badges and meta', (tester) async {
      await boot(tester);
      await openTab(tester, 'assigned_tasks');

      expect(find.text('DT-2026-00002'), findsOneWidget);
      expect(find.text('Overdue'), findsOneWidget);
      expect(find.text('URGENT'), findsOneWidget);
      expect(find.text('Fresh brief'), findsOneWidget);
      expect(find.text('Agency · Zebra Agency'), findsOneWidget);
      expect(find.text('Decline · Pending'), findsOneWidget);
      expect(find.text('Previous Month Task • Oct 2026'), findsOneWidget);
      expect(find.text('VERTICAL'), findsOneWidget);
      expect(find.text('ASSIGNED BY'), findsOneWidget);
      expect(find.text('Bela BD'), findsOneWidget);
    });

    testWidgets('an in-progress card shows progress, a due-soon pill and its continuation badge', (tester) async {
      await boot(tester);
      await openTab(tester, 'in_progress');

      expect(find.byKey(const Key('progress-3')), findsOneWidget);
      expect(find.text('25%'), findsOneWidget);
      expect(find.text('In progress'), findsOneWidget);
      expect(find.text('Due Soon'), findsOneWidget);
      expect(find.text('Continued to Dec 2026 · Completed 3 Dec'), findsOneWidget);
    });

    testWidgets('a waiting card shows the live BD review time', (tester) async {
      await boot(tester);
      await openTab(tester, 'waiting_confirmation');

      expect(find.byKey(const Key('progress-4')), findsOneWidget);
      expect(find.text('Awaiting BD'), findsOneWidget);
      expect(find.textContaining('BD Review Time: 3h 2'), findsOneWidget);
    });

    testWidgets('a completed card shows its rating and completion date', (tester) async {
      await boot(tester);
      await openTab(tester, 'completed');

      expect(find.text('Completed'), findsWidgets);
      expect(find.text('4.5 / 5'), findsOneWidget);
      expect(find.text('Overall Rating'), findsOneWidget);
      expect(find.text('COMPLETED'), findsOneWidget);
      expect(find.byKey(const Key('progress-5')), findsNothing);
    });

    testWidgets('a self-declined card says who has it now; a card due within the hour reads Due Today', (tester) async {
      await boot(tester);

      await openTab(tester, 'self_declined');
      expect(find.text('Now with: Meera'), findsOneWidget);

      await openTab(tester, 'yet_to_start');
      expect(find.text('Due Today'), findsOneWidget);
    });

    testWidgets('an empty column says so; a task opens when tapped', (tester) async {
      await boot(tester);

      await openTab(tester, 'rework');
      expect(find.text('No tasks'), findsOneWidget);

      await openTab(tester, 'in_progress');
      await tester.tap(find.text('Highway hoarding'));
      await tester.pumpAndSettle();
      expect(api.requests.any((r) => r.path == '/designer/tasks/3'), isTrue);
    });

    testWidgets('swiping turns to the next column and moves the tab selection with it', (tester) async {
      await boot(tester);
      expect(isSelected(tester, 'all'), isTrue);

      await tester.fling(find.byKey(const Key('kanban-pages')), const Offset(-500, 0), 1500);
      await tester.pumpAndSettle();
      expect(isSelected(tester, 'pending_bd_approval'), isTrue);

      await tester.fling(find.byKey(const Key('kanban-pages')), const Offset(-500, 0), 1500);
      await tester.pumpAndSettle();
      expect(isSelected(tester, 'bd_rejected'), isTrue);

      await tester.fling(find.byKey(const Key('kanban-pages')), const Offset(500, 0), 1500);
      await tester.pumpAndSettle();
      expect(isSelected(tester, 'pending_bd_approval'), isTrue);
    });

    testWidgets('the summary cards fit however many status chips the Active card has (no overflow)', (tester) async {
      final b = _board()
        ..['active_breakdown'] = [
          {'label': 'New Assignments', 'count': 1},
          {'label': 'Requirement Review', 'count': 1},
          {'label': 'In Progress', 'count': 1},
          {'label': 'Waiting for BD Review', 'count': 1},
          {'label': 'Prepare Printing File', 'count': 1},
        ];
      await boot(tester, board: b, size: const Size(411, 900));

      expect(find.text('Prepare Printing File: 1'), findsOneWidget);
      expect(tester.takeException(), isNull); // a RenderFlex overflow would be reported here
    });

    testWidgets('the Split Tasks column lists every split with its Status, an approved one opening the child task', (tester) async {
      await boot(tester);
      await openTab(tester, 'split_log');

      expect(tester.widget<Text>(find.byKey(const Key('count-split_log'))).data, '4');
      expect(find.text('Status: Split approved'), findsOneWidget);
      expect(find.text('Status: Pending approval'), findsOneWidget);
      expect(find.text('Status: Split rejected'), findsOneWidget);
      expect(find.text('Status: You completed all'), findsOneWidget);

      // Approved: the child task's details.
      expect(find.text('SPLIT FROM'), findsOneWidget);
      expect(find.text('DT-2026-00009'), findsOneWidget);
      expect(find.text('Meera'), findsOneWidget);
      expect(find.text('APPROVED'), findsOneWidget);

      await tester.tap(find.byKey(const Key('split-20')));
      await tester.pumpAndSettle();
      expect(api.requests.any((r) => r.path == '/designer/tasks/20'), isTrue);
    });

    testWidgets('a split with no child task shows what was requested and opens the original task', (tester) async {
      await boot(tester);
      await openTab(tester, 'split_log');

      expect(find.text('REQUESTED SPLIT'), findsWidgets);
      expect(find.text('REQUESTED'), findsWidgets);
      expect(find.text('3'), findsOneWidget); // asked to split 3 creatives

      await tester.ensureVisible(find.byKey(const Key('split-21')));
      await tester.tap(find.byKey(const Key('split-21')));
      await tester.pumpAndSettle();
      expect(api.requests.any((r) => r.path == '/designer/tasks/21'), isTrue);
    });

    testWidgets('an empty split column uses the web wording', (tester) async {
      await boot(tester, board: _board()..['split_log'] = []);
      await openTab(tester, 'split_log');

      expect(find.text('No split requests in Nov 2026.'), findsOneWidget);
    });

    testWidgets('Refresh reloads the board', (tester) async {
      await boot(tester);
      final before = boardFetches;

      await tester.tap(find.byKey(const Key('kanban-refresh')));
      await tester.pumpAndSettle();

      expect(boardFetches, greaterThan(before));
    });

    testWidgets('a failed load offers Try again', (tester) async {
      var calls = 0;
      await boot(tester, routes: {
        'GET /designer/tasks': (_) => ++calls == 1 ? (status: 500, body: {'message': 'Server hiccup'}) : (status: 200, body: _board()),
      });

      expect(find.text('Try again'), findsOneWidget);
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();
      expect(find.text('Awaiting card'), findsOneWidget);
    });
  });

  group('moving cards', () {
    testWidgets('only cards the server says can move get a Move to… button', (tester) async {
      await boot(tester);

      expect(find.byKey(const Key('card-menu-1')), findsNothing); // awaiting BD
      await openTab(tester, 'assigned_tasks');
      expect(find.byKey(const Key('card-menu-2')), findsOneWidget);
      await openTab(tester, 'in_progress');
      expect(find.byKey(const Key('card-menu-3')), findsOneWidget);
      await openTab(tester, 'completed');
      expect(find.byKey(const Key('card-menu-5')), findsNothing);
      await openTab(tester, 'self_declined');
      expect(find.byKey(const Key('card-menu-6')), findsNothing);
    });

    testWidgets('Move to ... works from the All Tickets list too', (tester) async {
      await boot(tester, size: const Size(800, 6000), routes: {'POST /designer/tasks/7/move': (_) => (status: 200, body: {'task': taskJson(id: 7)})});

      expect(find.byKey(const Key('card-menu-7')), findsOneWidget);
      expect(find.byKey(const Key('card-menu-1')), findsNothing); // awaiting BD: nothing to move
      await tester.tap(find.byKey(const Key('card-menu-7')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('move-in_progress')));
      await tester.pumpAndSettle();

      expect(lastBody('/designer/tasks/7/move'), {'status': 'in_progress'});
    });

    testWidgets('the sheet offers the forward moves and, separately, the requests to move back', (tester) async {
      await boot(tester);
      await openTab(tester, 'in_progress');

      await tester.tap(find.byKey(const Key('card-menu-3')));
      await tester.pumpAndSettle();

      expect(find.text('Move DT-2026-00003'), findsOneWidget);
      expect(find.text('Move to Waiting for BD Review'), findsOneWidget);
      expect(find.text('Request move back to New Assignments'), findsOneWidget);
      expect(find.text('Request move back to Ready to Start'), findsOneWidget);
      expect(find.text('Needs Designer Head approval'), findsWidgets);
    });

    testWidgets('Move to … posts the move, says so, and reloads the board', (tester) async {
      await boot(tester, routes: {'POST /designer/tasks/7/move': (_) => (status: 200, body: {'task': taskJson(id: 7)})});
      await openTab(tester, 'yet_to_start');
      final before = boardFetches;

      await tester.tap(find.byKey(const Key('card-menu-7')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('move-in_progress')));
      await tester.pumpAndSettle();

      expect(lastBody('/designer/tasks/7/move'), {'status': 'in_progress'});
      expect(find.text('Task status updated successfully.'), findsOneWidget);
      expect(boardFetches, greaterThan(before));
    });

    testWidgets("the server's refusal is shown, e.g. creatives not all done", (tester) async {
      await boot(tester, routes: {
        'POST /designer/tasks/3/move': (_) => (status: 422, body: {
              'message': 'x',
              'errors': {'status': ['Creative Work Incomplete: Complete all creatives before BD Review. 1 of 4 completed • 3 remaining.']},
            }),
      });
      await openTab(tester, 'in_progress');

      await tester.tap(find.byKey(const Key('card-menu-3')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('move-waiting_confirmation')));
      await tester.pumpAndSettle();

      expect(find.textContaining('Creative Work Incomplete'), findsOneWidget);
    });

    testWidgets('moving back asks for a reason, then raises a request for the Designer Head', (tester) async {
      await boot(tester, routes: {'POST /designer/tasks/3/backward-request': (_) => (status: 201, body: {'message': 'ok'})});
      await openTab(tester, 'in_progress');

      await tester.tap(find.byKey(const Key('card-menu-3')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('back-yet_to_start')));
      await tester.pumpAndSettle();

      expect(find.text('Are you sure you want to move this ticket?'), findsOneWidget);
      expect(find.text('In Progress  ↓  Ready to Start'), findsOneWidget);
      expect(find.textContaining('requires Designer Head approval'), findsOneWidget);

      // A reason is mandatory.
      await tester.tap(find.byKey(const Key('backward-yes')));
      await tester.pumpAndSettle();
      expect(find.text('Enter a reason for the backward movement.'), findsOneWidget);
      expect(api.requests.where((r) => r.path.endsWith('/backward-request')), isEmpty);

      await tester.enterText(find.byKey(const Key('backward-reason')), 'Client changed the brief');
      await tester.tap(find.byKey(const Key('backward-yes')));
      await tester.pumpAndSettle();

      expect(lastBody('/designer/tasks/3/backward-request'), {'to_status': 'yet_to_start', 'reason': 'Client changed the brief'});
      expect(find.textContaining('waiting for Designer Head approval'), findsOneWidget);
    });

    testWidgets('No leaves the task where it is and sends nothing', (tester) async {
      await boot(tester);
      await openTab(tester, 'in_progress');

      await tester.tap(find.byKey(const Key('card-menu-3')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('back-yet_to_start')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('backward-no')));
      await tester.pumpAndSettle();

      expect(api.requests.where((r) => r.method == 'POST'), isEmpty);
    });

    /// Long-press a card, then carry it to [target] (a tab, or a point) and let go.
    Future<TestGesture> pickUp(WidgetTester tester, String cardKey) async {
      final gesture = await tester.startGesture(tester.getCenter(find.byKey(Key(cardKey))));
      await tester.pump(const Duration(milliseconds: 650)); // long-press to pick it up
      return gesture;
    }

    Future<void> dropOnTab(WidgetTester tester, String cardKey, String tabKey) async {
      final gesture = await pickUp(tester, cardKey);
      await gesture.moveTo(tester.getCenter(find.byKey(Key('tab-$tabKey'))));
      await tester.pump(const Duration(milliseconds: 100));
      await gesture.up();
      await tester.pumpAndSettle();
    }

    testWidgets('dragging a card onto a later tab moves it', (tester) async {
      await boot(tester, size: const Size(3000, 1800), routes: {'POST /designer/tasks/2/move': (_) => (status: 200, body: {'task': taskJson(id: 2)})});
      await openTab(tester, 'assigned_tasks');

      await dropOnTab(tester, 'card-2', 'review_analysis');

      expect(lastBody('/designer/tasks/2/move'), {'status': 'review_analysis'});
      expect(find.text('Task status updated successfully.'), findsOneWidget);
    });

    testWidgets('dropping on a tab the task cannot go to is refused without calling the server', (tester) async {
      await boot(tester, size: const Size(3000, 1800));
      await openTab(tester, 'assigned_tasks');

      await dropOnTab(tester, 'card-2', 'bd_rejected');

      expect(find.textContaining("can't be moved to Rejected by BD"), findsOneWidget);
      expect(api.requests.where((r) => r.method == 'POST'), isEmpty);
    });

    testWidgets('dropping a card on an earlier tab opens the reason dialog instead of moving it', (tester) async {
      await boot(tester, size: const Size(3000, 1800));
      await openTab(tester, 'in_progress');

      await dropOnTab(tester, 'card-3', 'assigned_tasks');

      expect(find.byKey(const Key('backward-reason')), findsOneWidget);
      expect(api.requests.where((r) => r.method == 'POST'), isEmpty);
    });

    testWidgets('holding a card at the screen edge turns to the next column so it can be dropped there', (tester) async {
      await boot(tester, routes: {'POST /designer/tasks/2/move': (_) => (status: 200, body: {'task': taskJson(id: 2)})});
      await openTab(tester, 'assigned_tasks');

      final gesture = await pickUp(tester, 'card-2');
      await gesture.moveTo(const Offset(790, 900)); // right edge
      await tester.pump(const Duration(milliseconds: 1000));
      await tester.pumpAndSettle(); // let the page turn finish while the card is still held
      expect(isSelected(tester, 'review_analysis'), isTrue);
      expect(find.byKey(const Key('page-review_analysis')), findsOneWidget);

      await gesture.moveTo(const Offset(400, 900)); // now over the Requirement Review column
      await tester.pump(const Duration(milliseconds: 100));
      await gesture.up();
      await tester.pumpAndSettle();

      expect(lastBody('/designer/tasks/2/move'), {'status': 'review_analysis'});
    });
  });

  group('filters', () {
    testWidgets('typing in the search box searches on the server and hides the empty tabs', (tester) async {
      await boot(tester, routes: {
        'GET /designer/tasks': (o) {
          boardFetches++;
          final searching = (o.queryParameters['search'] ?? '') == 'zebra';
          return (status: 200, body: searching ? (_board()..['tasks'] = [_card(2, 'Fresh brief', 'assigned_tasks', extra: {'party_name': 'Zebra Agency'})]) : _board());
        },
      });

      await tester.enterText(find.byKey(const Key('kanban-search')), 'zebra');
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pumpAndSettle();

      expect(lastBoardQuery()['search'], 'zebra');
      expect(find.byKey(const Key('tab-assigned_tasks')), findsOneWidget);
      expect(find.byKey(const Key('tab-in_progress')), findsNothing); // empty columns are hidden while searching
      expect(find.byKey(const Key('tab-all')), findsNothing); // matches show under their own stage, not in All Tickets
      expect(isSelected(tester, 'assigned_tasks'), isTrue); // and we jump to the stage that has the match
      expect(find.text('Fresh brief'), findsOneWidget);
      expect(find.byKey(const Key('stage-2')), findsNothing);
      expect(find.byKey(const Key('applied-filters')), findsOneWidget);
      expect(find.text('Search: zebra'), findsOneWidget);

      // Clearing the search brings All Tickets back.
      await tester.enterText(find.byKey(const Key('kanban-search')), '');
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('tab-all')), findsOneWidget);
    });

    testWidgets('the filter sheet applies vertical, priority, BD, project number and a custom period; chips show them; Clear resets', (tester) async {
      await boot(tester);

      await tester.tap(find.byKey(const Key('kanban-filters')));
      await tester.pumpAndSettle();

      Future<void> choose(String key, String label) async {
        await tester.tap(find.byKey(Key(key)));
        await tester.pumpAndSettle();
        await tester.tap(find.text(label).last);
        await tester.pumpAndSettle();
      }

      await choose('f-vertical', 'Signage');
      await choose('f-priority', 'Overdue');
      await choose('f-bd', 'Chetan BD');
      await tester.enterText(find.byKey(const Key('f-project')), 'ZP-77');
      await choose('f-period', 'Custom Period');
      await tester.tap(find.byKey(const Key('f-from')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('f-to')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('apply-filters')));
      await tester.pumpAndSettle();

      final q = lastBoardQuery();
      expect(q['vertical'], 'signage');
      expect(q['priority'], 'overdue');
      expect('${q['bd_id']}', '6');
      expect(q['project_number'], 'ZP-77');
      expect(q['period'], 'custom');
      expect(q['date_from'], isNotNull);
      expect(q['date_to'], isNotNull);

      expect(find.text('Vertical: Signage'), findsOneWidget);
      expect(find.text('Priority: Overdue'), findsOneWidget);
      expect(find.text('BD: Chetan BD'), findsOneWidget);
      expect(find.text('Zoho Project Number: ZP-77'), findsOneWidget);

      await tester.tap(find.byKey(const Key('clear-filters-chip')));
      await tester.pumpAndSettle();
      expect(lastBoardQuery(), isEmpty);
      expect(find.byKey(const Key('applied-filters')), findsNothing);
    });

    testWidgets('Clear Filters inside the sheet resets everything', (tester) async {
      await boot(tester);
      await tester.tap(find.byKey(const Key('kanban-filters')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('f-project')), 'ZP-1');
      await tester.tap(find.byKey(const Key('apply-filters')));
      await tester.pumpAndSettle();
      expect(lastBoardQuery()['project_number'], 'ZP-1');

      await tester.tap(find.byKey(const Key('kanban-filters')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('clear-all')));
      await tester.pumpAndSettle();

      expect(lastBoardQuery(), isEmpty);
    });
  });
}
