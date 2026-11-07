import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/features/dashboard/dashboard_models.dart';
import 'package:adinn_tasks/features/home/navigation.dart';
import 'package:adinn_tasks/features/kanban/stage_tickets_screen.dart';
import 'package:adinn_tasks/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

Map<String, dynamic> _row(int id, String name, {String status = 'in_progress', String priority = 'medium', String vertical = 'roadshow', bool overdue = false, Map<String, dynamic>? extra}) => {
      'id': id,
      'task_id': 'DT-2026-0000$id',
      'project_number': 'ZP-$id',
      'name': name,
      'party': 'Acme $id',
      'vertical': vertical,
      'task_nature': 'new_creative_design',
      'assigned_by': 'Bela BD',
      'status': status,
      'priority': priority,
      'assigned_at': '2026-11-01T09:00:00+00:00',
      'due_at': '2026-11-20T12:00:00+00:00',
      'total_creatives': 4,
      'done': 1,
      'remaining': 3,
      'percentage': 25,
      'rework_count': 0,
      'rework_creatives': 0,
      'completed_at': null,
      'overdue': overdue,
      'completion': {'status': overdue ? 'overdue' : 'in_progress', 'days': overdue ? 3 : 0},
      'rating': null,
      ...?extra,
    };

Map<String, dynamic> _dashboard() {
  final base = emptyDashboardJson(
    recent: [
      _row(1, 'Highway hoarding', priority: 'high'),
      _row(2, 'Mall standee', status: 'assigned_tasks', vertical: 'signage', overdue: true),
      _row(3, 'Festive creative', status: 'completed', priority: 'low', extra: {
        'percentage': 100, 'done': 4, 'remaining': 0, 'rating': 4.5, 'completed_at': '2026-11-10T10:00:00+00:00',
        'completion': {'status': 'late', 'days': 2}, 'rework_count': 2, 'rework_creatives': 3,
      }),
    ],
    stats: {'total': 3, 'assigned': 1, 'in_progress': 1, 'completed': 1, 'waiting_bd_review': 2, 'waiting_bd_confirmation': 0, 'rework': 0},
  );

  return {
    ...base,
    'user': {'name': 'Dee Signer', 'last_login_at': '2026-11-04T08:30:00+00:00'},
    'completion_rate': 33,
    'overall_rating': {'average': 4.5, 'rated': 2, 'total': 3},
    'overall_rework': {'cycles': 2, 'creatives': 3},
    'request_counts': {'swap': 1, 'decline': 2, 'split': 0, 'approved': 1},
    'trend': {
      ...(base['trend'] as Map<String, dynamic>),
      'cards': [
        {'label': 'Assigned Tasks', 'value': 5, 'color': '#2970ff'},
        {'label': 'In Progress', 'value': 2, 'color': '#7c3aed'},
        {'label': 'Completed', 'value': 3, 'color': '#027a48'},
        {'label': 'Overdue & Completed', 'value': 1, 'color': '#f79009'},
        {'label': 'Declined', 'value': 0, 'color': '#c01048'},
      ],
      'line': [
        {'label': 'Jun 26', 'assigned': 0, 'in_progress': 0, 'completed': 0, 'overdue_completed': 0, 'declined': 0},
        {'label': 'Jul 26', 'assigned': 2, 'in_progress': 0, 'completed': 1, 'overdue_completed': 0, 'declined': 0},
        {'label': 'Aug 26', 'assigned': 4, 'in_progress': 1, 'completed': 2, 'overdue_completed': 1, 'declined': 1},
        {'label': 'Sep 26', 'assigned': 3, 'in_progress': 0, 'completed': 3, 'overdue_completed': 0, 'declined': 0},
        {'label': 'Oct 26', 'assigned': 6, 'in_progress': 2, 'completed': 4, 'overdue_completed': 2, 'declined': 0},
        {'label': 'Nov 26', 'assigned': 5, 'in_progress': 2, 'completed': 3, 'overdue_completed': 1, 'declined': 0},
      ],
    },
    'reviews': [
      {'rating': 4.5, 'comment': 'Delivered ahead of the brief', 'task_db_id': 3, 'task_id': 'DT-2026-00003', 'task_name': 'Festive creative', 'reviewer': 'Bela BD', 'reviewed_at': '2026-11-10T11:00:00+00:00', 'completed_at': '2026-11-10T10:00:00+00:00'},
      {'rating': 3.0, 'comment': 'Needs sharper typography', 'task_db_id': 1, 'task_id': 'DT-2026-00001', 'task_name': 'Highway hoarding', 'reviewer': 'Bela BD', 'reviewed_at': '2026-11-02T11:00:00+00:00', 'completed_at': '2026-11-02T10:00:00+00:00'},
    ],
    'my_requests': [
      {'id': 11, 'type': 'swap', 'status': 'approved', 'task_db_id': 2, 'task_id': 'DT-2026-00002', 'task_name': 'Mall standee', 'requested_by': 'Dee Signer', 'requested_at': '2026-11-03T09:00:00+00:00', 'responded_by': 'Harsha Head', 'responded_at': '2026-11-03T12:00:00+00:00', 'current_handler': 'Meera'},
      {'id': 12, 'type': 'decline', 'status': 'pending', 'task_db_id': 1, 'task_id': 'DT-2026-00001', 'task_name': 'Highway hoarding', 'requested_by': 'Dee Signer', 'requested_at': '2026-11-04T09:00:00+00:00', 'responded_by': null, 'responded_at': null, 'current_handler': 'Dee Signer'},
    ],
  };
}

/// The half-star widget's accessibility label (avoids needing the semantics tree switched on).
Finder _stars(double v) => find.byWidgetPredicate((w) => w is Semantics && w.properties.label == '$v out of 5 stars');

void main() {
  late FakeAdapter api;

  Future<void> boot(WidgetTester tester, {Map<String, dynamic>? dashboard, Map<String, Handler> routes = const {}, String role = 'designer'}) async {
    tester.view.physicalSize = const Size(900, 5200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    api = FakeAdapter({
      'GET /me': (_) => (status: 200, body: {'user': userJson(role: role)}),
      'GET /notifications': (_) => (status: 200, body: {'unread_count': 0, 'data': []}),
      'GET /designer/dashboard': (_) => (status: 200, body: dashboard ?? _dashboard()),
      'GET /designer/tasks': (_) => (status: 200, body: {
            'statuses': [{'key': 'assigned_tasks', 'label': 'New Assignments'}, {'key': 'in_progress', 'label': 'In Progress'}, {'key': 'completed', 'label': 'Completed'}],
            'tasks': [taskJson(id: 1, status: 'in_progress', name: 'Highway hoarding'), taskJson(id: 2, status: 'assigned_tasks', name: 'Mall standee'), taskJson(id: 3, status: 'completed', name: 'Festive creative')],
          }),
      'GET /designer/tasks/1': (_) => (status: 200, body: {
            'task': taskJson(id: 1, name: 'Highway hoarding'),
            'access': {'read_only': true},
            'progress': {'completed': 1, 'total': 4, 'remaining': 3, 'percentage': 25},
            'history': [],
          }),
      ...routes,
    });

    await tester.pumpWidget(ProviderScope(
      retry: (_, _) => null,
      overrides: [
        sessionStoreProvider.overrideWithValue(MemoryStore(token: 'tok', user: AppUser.fromJson(userJson(role: role)))),
        apiClientProvider.overrideWith((ref) => fakeClient(api, token: () => ref.read(authProvider).token)),
      ],
      child: const AdinnApp(),
    ));
    await tester.pumpAndSettle();
  }

  group('model', () {
    test('parses a full payload and tolerates missing sections', () {
      final d = DesignerDashboard.fromJson(_dashboard());
      expect(d.name, 'Dee Signer');
      expect(d.stat('total'), 3);
      expect(d.stat('nope'), 0);
      expect(d.recent.first.pillKey, 'in_progress');
      expect(d.recent[1].pillKey, 'overdue'); // an incomplete task past its due date reads "Overdue"
      expect(d.reviews.first.rating, 4.5);
      expect(d.trendLine, hasLength(6));

      final empty = DesignerDashboard.fromJson({});
      expect(empty.recent, isEmpty);
      expect(empty.ratingAverage, isNull);
      expect(empty.completionRate, 0);
    });
  });

  group('navigation', () {
    testWidgets('a designer lands on the Dashboard with Dashboard · My Tasks · Alerts · Profile', (tester) async {
      await boot(tester);

      expect(find.text('Designer Dashboard'), findsWidgets);
      for (final label in ['Dashboard', 'My Tasks', 'Alerts', 'Profile']) {
        expect(find.text(label), findsWidgets);
      }
      expect(find.text('Requests'), findsNothing);
    });

    test('tab sets per role', () {
      expect(tabsFor('designer').first, HomeTab.dashboard);
      expect(tabsFor('bd'), [HomeTab.tasks, HomeTab.alerts, HomeTab.profile]);
      expect(tabsFor('designer_head'), contains(HomeTab.requests));
      expect(tabsFor('admin'), contains(HomeTab.requests));
      expect(tabsFor(null), isNotEmpty);
    });

    testWidgets('BD does not get a dashboard tab', (tester) async {
      await boot(tester, role: 'bd', routes: {'GET /bd/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': []})});

      expect(find.text('Dashboard'), findsNothing);
      expect(find.text('Designer Dashboard'), findsNothing);
    });
  });

  group('hero and stat cards', () {
    testWidgets('greets the designer and shows the last login', (tester) async {
      await boot(tester);

      expect(find.text('Welcome back, Dee Signer'), findsOneWidget);
      expect(find.text('All important task and request information in one place.'), findsOneWidget);
      expect(find.textContaining('Last Login:'), findsOneWidget);
    });

    testWidgets('says so on a first login', (tester) async {
      final d = _dashboard();
      d['user'] = <String, dynamic>{'name': 'Dee Signer', 'last_login_at': null};
      await boot(tester, dashboard: d);

      expect(find.text('This is your first login'), findsOneWidget);
    });

    testWidgets('shows Total always and every other card only when it has something to report', (tester) async {
      await boot(tester);

      for (final k in ['total', 'assigned', 'in-progress', 'completed', 'waiting', 'rating']) {
        expect(find.byKey(Key('tile-$k')), findsOneWidget, reason: k);
      }
      for (final k in ['ready', 'rework', 'bd-approval', 'printing']) {
        expect(find.byKey(Key('tile-$k')), findsNothing, reason: k);
      }
      expect(find.text('Tasks assigned to you'), findsOneWidget);
    });

    testWidgets('an empty dashboard still shows Total, the rating tile and 0% completion', (tester) async {
      await boot(tester, dashboard: emptyDashboardJson());

      expect(find.byKey(const Key('tile-total')), findsOneWidget);
      expect(find.byKey(const Key('tile-assigned')), findsNothing);
      expect(find.text('—'), findsWidgets); // no rating yet
      expect(find.text('0%'), findsOneWidget);
      expect(find.byKey(const Key('overview-empty')), findsOneWidget);
      expect(find.text('No tasks assigned yet.'), findsOneWidget);
    });

    testWidgets('the rating tile shows the average, stars and how many completed tasks were rated', (tester) async {
      await boot(tester);

      expect(find.text('4.5/5'), findsOneWidget);
      expect(find.text('Rated 2 / 3 completed'), findsOneWidget);
      expect(_stars(4.5), findsWidgets);
    });

    testWidgets('shows the completion rate', (tester) async {
      await boot(tester);

      expect(find.byKey(const Key('completion-rate')), findsOneWidget);
      expect(find.text('33%'), findsOneWidget);
    });

    testWidgets('extra cards: swapped, declined, approved, and overall rework as "N tickets | M creatives"', (tester) async {
      await boot(tester);

      expect(find.byKey(const Key('tile-swap')), findsOneWidget);
      expect(find.byKey(const Key('tile-decline')), findsOneWidget);
      expect(find.byKey(const Key('tile-split')), findsNothing); // zero splits
      expect(find.byKey(const Key('tile-approved')), findsOneWidget);
      expect(find.text('2 tickets | 3 creatives'), findsOneWidget);
    });

    testWidgets('tapping a card jumps to My Tasks and opens that stage', (tester) async {
      await boot(tester);

      await tester.tap(find.byKey(const Key('tile-in-progress')));
      await tester.pumpAndSettle();

      expect(find.byType(StageTicketsScreen), findsOneWidget);
      expect(tester.widget<Text>(find.byKey(const Key('stage-title'))).data, 'In Progress');
      expect(find.text('Highway hoarding'), findsOneWidget);
    });

    testWidgets('a card for a stage that is not on the first screen of the carousel still opens it', (tester) async {
      await boot(tester);

      await tester.tap(find.byKey(const Key('tile-completed')));
      await tester.pumpAndSettle();

      expect(tester.widget<Text>(find.byKey(const Key('stage-title'))).data, 'Completed');
      expect(find.text('Festive creative'), findsOneWidget);
    });

    testWidgets('the Split Tasks card opens the Split Tasks stage', (tester) async {
      final d = _dashboard();
      (d['request_counts'] as Map)['split'] = 2;
      await boot(tester, dashboard: d, routes: {
        'GET /designer/tasks': (_) => (status: 200, body: {
              'columns': [{'key': 'in_progress', 'label': 'In Progress'}, {'key': 'split_log', 'label': 'Split Tasks', 'type': 'split_log'}],
              'statuses': [{'key': 'in_progress', 'label': 'In Progress'}],
              'tasks': [taskJson(id: 1, status: 'in_progress', name: 'Highway hoarding')],
              'split_log': [
                {'id': 21, 'task_id': 'DT-2026-00021', 'name': 'Waiting split', 'has_child': false, 'status': {'key': 'pending', 'label': 'Pending approval'}, 'creatives': 6, 'requested_split': 3},
              ],
            }),
      });

      await tester.tap(find.byKey(const Key('tile-split')));
      await tester.pumpAndSettle();

      expect(tester.widget<Text>(find.byKey(const Key('stage-title'))).data, 'Split Tasks');
      expect(find.text('Status: Pending approval'), findsOneWidget);
    });

    testWidgets('Open on board opens its stage and highlights its card', (tester) async {
      await boot(tester);

      await tester.tap(find.byKey(const Key('row-1')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('drawer-board')));
      for (var i = 0; i < 6; i++) {
        await tester.pump(const Duration(milliseconds: 120)); // sheet closes, tab switches, stage opens (500 ms)
      }
      await tester.pump(const Duration(milliseconds: 300)); // the stage has opened; the ring is still showing

      expect(find.byType(StageTicketsScreen), findsOneWidget);
      expect(tester.widget<Text>(find.byKey(const Key('stage-title'))).data, 'In Progress');
      expect(find.byKey(const Key('card-1')), findsOneWidget);
      // The ring is a foreground decoration on the flashed card, gone again after a moment.
      bool ringed() => tester.widgetList<Container>(find.descendant(of: find.byKey(const Key('card-1')), matching: find.byType(Container))).any((c) => c.foregroundDecoration != null);
      expect(ringed(), isTrue);
      await tester.pumpAndSettle();
      expect(ringed(), isFalse);
    });

    testWidgets('View All Tasks opens the board', (tester) async {
      await boot(tester);

      await tester.tap(find.byKey(const Key('hero-view-tasks')));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('held-card-all')), findsOneWidget);
      expect(find.byKey(const Key('total-count')), findsOneWidget);
    });
  });

  group('tasks overview', () {
    testWidgets('lists the recent tasks with their pills, and an overdue task reads Overdue', (tester) async {
      await boot(tester);

      expect(find.byKey(const Key('row-1')), findsOneWidget);
      expect(find.byKey(const Key('row-2')), findsOneWidget);
      expect(find.byKey(const Key('row-3')), findsOneWidget);
      expect(find.text('Overdue'), findsWidgets);
      expect(find.text('Total Tasks'), findsWidgets);
      expect(tester.widget<Text>(find.byKey(const Key('overview-total'))).data, '3');
    });

    testWidgets('filters by status, priority and vertical, and clears them', (tester) async {
      await boot(tester);

      Future<void> pick(String filter, String option) async {
        await tester.tap(find.byKey(Key('filter-$filter')));
        await tester.pumpAndSettle();
        await tester.tap(find.text(option).last);
        await tester.pumpAndSettle();
      }

      await pick('status', 'Overdue');
      expect(find.byKey(const Key('row-2')), findsOneWidget);
      expect(find.byKey(const Key('row-1')), findsNothing);

      await tester.tap(find.byKey(const Key('clear-filters')));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('row-1')), findsOneWidget);

      await pick('priority', 'High');
      expect(find.byKey(const Key('row-1')), findsOneWidget);
      expect(find.byKey(const Key('row-3')), findsNothing);

      await pick('vertical', 'Signage');
      expect(find.byKey(const Key('row-1')), findsNothing);
      expect(find.text('No matching tasks found.'), findsOneWidget);
    });

    testWidgets('typing searches every task on the server, and clearing the box returns to the recent list', (tester) async {
      await boot(tester, routes: {
        'GET /designer/dashboard/tasks': (o) => (status: 200, body: {'data': [_row(9, 'Needle banner')]}),
      });

      await tester.enterText(find.byKey(const Key('overview-search')), 'needle');
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pumpAndSettle();

      expect(api.requests.where((r) => r.path == '/designer/dashboard/tasks').single.queryParameters['search'], 'needle');
      expect(find.byKey(const Key('row-9')), findsOneWidget);
      expect(find.byKey(const Key('row-1')), findsNothing);

      await tester.enterText(find.byKey(const Key('overview-search')), '');
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('row-1')), findsOneWidget);
    });

    testWidgets('a search with no hits says so', (tester) async {
      await boot(tester, routes: {'GET /designer/dashboard/tasks': (_) => (status: 200, body: {'data': []})});

      await tester.enterText(find.byKey(const Key('overview-search')), 'zzz');
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pumpAndSettle();

      expect(find.text('No matching tasks found.'), findsOneWidget);
    });
  });

  group('task details drawer', () {
    testWidgets('shows progress, creatives, rework, deadlines and the lateness of a completed task', (tester) async {
      await boot(tester);

      await tester.tap(find.byKey(const Key('row-3')));
      await tester.pumpAndSettle();

      expect(find.text('Your Task Details'), findsOneWidget);
      expect(find.text('100%'), findsWidgets);
      expect(find.text('4 / 4   ·   0 remaining'), findsOneWidget);
      expect(find.text('2 · 3 creatives'), findsOneWidget);
      expect(find.text('Completed 2d after due'), findsOneWidget);
      expect(_stars(4.5), findsWidgets);
    });

    testWidgets('an incomplete overdue task says how many days overdue; others show a dash', (tester) async {
      await boot(tester);

      await tester.tap(find.byKey(const Key('row-2')));
      await tester.pumpAndSettle();
      expect(find.text('3d overdue'), findsOneWidget);
      expect(find.text('0'), findsWidgets); // no rework

      Navigator.of(tester.element(find.byKey(const Key('task-drawer')))).pop();
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('row-1')));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('drawer-overdue')), findsOneWidget);
      expect(find.descendant(of: find.byKey(const Key('drawer-overdue')), matching: find.text('—')), findsNothing);
      expect(find.text('—'), findsWidgets);
    });

    testWidgets('Open task goes to the task detail page', (tester) async {
      await boot(tester);

      await tester.tap(find.byKey(const Key('row-1')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('drawer-open')));
      await tester.pumpAndSettle();

      expect(api.requests.any((r) => r.path == '/designer/tasks/1'), isTrue);
    });
  });

  group('performance trend', () {
    testWidgets('shows the month, five KPI cards and the line chart by default', (tester) async {
      await boot(tester);

      expect(find.text('Performance Trend'), findsOneWidget);
      expect(find.byKey(const Key('trend-context')), findsOneWidget);
      for (final l in ['Assigned Tasks', 'In Progress', 'Completed', 'Overdue & Completed', 'Declined']) {
        expect(find.byKey(Key('trend-$l')), findsOneWidget, reason: l);
      }
      expect(find.byKey(const Key('chart-line')), findsOneWidget);
      expect(find.byKey(const Key('chart-bars')), findsNothing);
    });

    testWidgets('the second tab switches to the grouped bars', (tester) async {
      await boot(tester);

      await tester.tap(find.byKey(const Key('trend-tab-1')));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('chart-bars')), findsOneWidget);
      expect(find.byKey(const Key('chart-line')), findsNothing);
    });

    testWidgets('touching and dragging a chart picks months without error, on both charts', (tester) async {
      await boot(tester);

      final chart = find.byKey(const Key('trend-touch'));
      await tester.ensureVisible(chart);
      await tester.tap(chart);
      await tester.pump();
      await tester.drag(chart, const Offset(120, 0));
      await tester.pump();
      await tester.tap(chart); // tapping the same month again hides it
      await tester.pump();

      await tester.tap(find.byKey(const Key('trend-tab-1')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('trend-touch')));
      await tester.pump();
      expect(tester.takeException(), isNull);
    });

    testWidgets('a quiet account (all zeros) draws without error', (tester) async {
      await boot(tester, dashboard: emptyDashboardJson());

      expect(find.byKey(const Key('chart-line')), findsOneWidget);
      await tester.tap(find.byKey(const Key('trend-tab-1')));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('chart-bars')), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });

  group('reviews', () {
    testWidgets('shows the BD comment, reviewer and dates', (tester) async {
      await boot(tester);

      expect(find.text('“Delivered ahead of the brief”'), findsOneWidget);
      expect(find.textContaining('Bela BD'), findsWidgets);
      expect(find.textContaining('Reviewed:'), findsWidgets);
      expect(find.textContaining('Completed:'), findsWidgets);
    });

    testWidgets('says so when there are no comments yet', (tester) async {
      await boot(tester, dashboard: emptyDashboardJson());

      expect(find.text('No BD comments yet on your completed tasks.'), findsOneWidget);
    });

    testWidgets('tapping a review opens its task', (tester) async {
      await boot(tester, routes: {
        'GET /designer/tasks/3': (_) => (status: 200, body: {
              'task': taskJson(id: 3, name: 'Festive creative', status: 'completed'),
              'access': {'read_only': true},
              'progress': {'completed': 4, 'total': 4, 'remaining': 0, 'percentage': 100},
              'history': [],
            }),
      });

      await tester.tap(find.byKey(const Key('review-0')));
      await tester.pumpAndSettle();

      expect(api.requests.any((r) => r.path == '/designer/tasks/3'), isTrue);
    });
  });

  group('my requests', () {
    testWidgets('lists each request with status, responder and current handler', (tester) async {
      await boot(tester);

      expect(find.text('2 requests'), findsOneWidget);
      expect(find.byKey(const Key('request-11')), findsOneWidget);
      expect(find.text('Approved'), findsOneWidget);
      expect(find.text('Pending'), findsOneWidget);
      expect(find.text('Harsha Head'), findsOneWidget);
      expect(find.text('Meera'), findsOneWidget); // an approved swap is with the new designer
      expect(find.text('Swap Request'), findsOneWidget);
      expect(find.text('Decline Request'), findsOneWidget);
    });

    testWidgets('an empty list shows the web wording', (tester) async {
      await boot(tester, dashboard: emptyDashboardJson());

      expect(find.byKey(const Key('requests-empty')), findsOneWidget);
      expect(find.text('0 requests'), findsOneWidget);
    });
  });

  testWidgets('a failed load shows the error with Try again, which reloads', (tester) async {
    var calls = 0;
    await boot(tester, routes: {
      'GET /designer/dashboard': (_) => ++calls == 1 ? (status: 500, body: {'message': 'Server hiccup'}) : (status: 200, body: _dashboard()),
    });

    expect(find.text('Try again'), findsOneWidget);

    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();

    expect(find.text('Welcome back, Dee Signer'), findsOneWidget);
  });
}
