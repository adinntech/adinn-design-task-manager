import 'dart:io';

import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/features/kanban/kanban_card.dart';
import 'package:adinn_tasks/features/home/navigation.dart';
import 'package:adinn_tasks/features/kanban/kanban_models.dart';
import 'package:adinn_tasks/features/kanban/stage3d/designer_character.dart';
import 'package:adinn_tasks/features/kanban/stage3d/stage_meta.dart';
import 'package:adinn_tasks/features/kanban/stage_tickets_screen.dart';
import 'package:adinn_tasks/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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

  Future<void> boot(WidgetTester tester, {Map<String, dynamic>? board, Map<String, Handler> routes = const {}, Size size = const Size(3000, 1800), bool settle = true, bool openTab = true}) async {
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
    if (!openTab) return;
    await tester.tap(find.text('My Tasks'));
    settle ? await tester.pumpAndSettle() : await tester.pump();
  }

  /// Swipe the card in his hands to the next stage (a flick to the left) or back (to the right).
  Future<void> nextCard(WidgetTester tester) async {
    await tester.fling(find.byKey(const Key('card-pager')), const Offset(-400, 0), 1500);
    await tester.pumpAndSettle();
  }

  Future<void> prevCard(WidgetTester tester) async {
    await tester.fling(find.byKey(const Key('card-pager')), const Offset(400, 0), 1500);
    await tester.pumpAndSettle();
  }

  /// How visible the stage tiles are: 0 while the man fills the screen, 1 once he has stepped back.
  double panelOpacity(WidgetTester tester) => tester.widget<Opacity>(find.ancestor(of: find.byKey(const Key('stage-panel')), matching: find.byType(Opacity)).first).opacity;

  /// A slight drag up: the man steps back and the stage tiles come up into the space under his feet.
  Future<void> openPanel(WidgetTester tester) async {
    await tester.fling(find.byKey(const Key('card-pager')), const Offset(0, -140), 900);
    await tester.pumpAndSettle();
  }

  /// Open a stage from its tile: the first tap puts its card in the man's hands, the second (or the card) opens it. A stage
  /// with no tickets has no card, so its tile opens it at once.
  Future<void> openStage(WidgetTester tester, String key) async {
    final away = find.byKey(const Key('stage-panel')).evaluate().isEmpty || panelOpacity(tester) < 0.5;
    if (find.byKey(const Key('card-pager')).evaluate().isNotEmpty && away) await openPanel(tester);
    await tester.tap(find.byKey(Key('tile-$key')));
    await tester.pumpAndSettle();
    if (find.byType(StageTicketsScreen).evaluate().isEmpty) {
      await tester.tap(find.byKey(Key('tile-$key')));
      await tester.pumpAndSettle();
    }
  }

  /// The stage whose card is in his hands (the one with a card title on screen).
  String heldStage(WidgetTester tester) {
    final titles = find.byWidgetPredicate((w) => w.key is ValueKey<String> && (w.key! as ValueKey<String>).value.startsWith('held-title-'));
    expect(titles, findsOneWidget);
    return (titles.evaluate().single.widget.key! as ValueKey<String>).value.substring('held-title-'.length);
  }

  String stageTitle(WidgetTester tester) => tester.widget<Text>(find.byKey(const Key('stage-title'))).data!;

  Map<String, dynamic> lastBody(String path) => Map<String, dynamic>.from(api.requests.lastWhere((r) => r.method == 'POST' && r.path == path).data as Map);

  Map<String, dynamic> lastBoardQuery() => api.requests.lastWhere((r) => r.path == '/designer/tasks').queryParameters;

  group('assets', () {
    test('the supplied originals are untouched and the processed copies exist', () {
      expect(File('tasks screen assets/Smiling Adinn Representative Holding Invisible Sign.png').existsSync(), isTrue);
      expect(File('tasks screen assets/Glossy Red-Bordered Card Stack.png').existsSync(), isTrue);
      for (final f in ['adinn_rep', 'hand_left', 'hand_right']) {
        expect(File('assets/tasks/$f.png').existsSync(), isTrue, reason: f);
      }
    });

    testWidgets('all three are registered in pubspec and load as images', (tester) async {
      for (final path in [DesignerCharacterWidget.asset, GrippingHand.leftAsset, GrippingHand.rightAsset]) {
        final data = await rootBundle.load(path);
        expect(data.lengthInBytes, greaterThan(10000), reason: path);
      }
    });
  });

  group('model', () {
    test('filters serialise only what is set, and compare by value', () {
      expect(const BoardFilters().toQuery(), isEmpty);
      expect(const BoardFilters().isDefault, isTrue);

      final f = BoardFilters(search: ' zebra ', vertical: 'signage', priority: 'overdue', bdId: 5, projectNumber: 'ZP-1', period: 'custom', dateFrom: DateTime(2026, 11, 2), dateTo: DateTime(2026, 11, 20));
      expect(f.toQuery(), {'search': 'zebra', 'vertical': 'signage', 'priority': 'overdue', 'bd_id': 5, 'project_number': 'ZP-1', 'period': 'custom', 'date_from': '2026-11-02', 'date_to': '2026-11-20'});
      expect(f.isDefault, isFalse);
      expect(f.copyWith(bdId: null).bdId, isNull);
      expect(const BoardFilters(period: 'last_month').toQuery(), {'period': 'last_month'});
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
    });

    test('humanDuration', () {
      expect(humanDuration(const Duration(seconds: 20)), 'less than a minute');
      expect(humanDuration(const Duration(hours: 5, minutes: 12)), '5h 12m');
      expect(humanDuration(const Duration(days: 3, hours: 4)), '3d 4h');
    });

    test('a payload with cards but no columns still gets one column per status', () {
      final b = KanbanBoard.fromJson({'tasks': [_card(1, 'a', 'in_progress'), _card(2, 'b', 'completed')]});
      expect(b.columns.map((c) => c.key), ['in_progress', 'completed']);
      expect(KanbanBoard.fromJson({}).columns, isEmpty);
    });
  });

  group('overview', () {
    testWidgets('the representative holds a square card with the stack behind it, showing the total from the API', (tester) async {
      await boot(tester);

      expect(find.byKey(const Key('designer-character')), findsOneWidget);
      expect(find.byKey(const Key('card-edges')), findsOneWidget);
      expect(find.text('ALL TICKETS'), findsOneWidget);
      expect(find.text('Tickets'), findsOneWidget);
      expect(tester.widget<Text>(find.byKey(const Key('total-count'))).data, '7');
      expect(heldStage(tester), 'all');
    });

    testWidgets('the total is whatever the server says, not the number of cards loaded', (tester) async {
      await boot(tester, board: _board()..['stats'] = {'total': 42, 'active': 30, 'waiting': 5, 'completed': 7});

      expect(tester.widget<Text>(find.byKey(const Key('total-count'))).data, '42');
    });

    testWidgets('the card is square', (tester) async {
      await boot(tester);

      final size = tester.getSize(find.byKey(const Key('held-card-all')));
      expect(size.width, closeTo(size.height, 1));
    });

    testWidgets('the man fills the screen, with no strip of cards under him and the tiles not yet showing', (tester) async {
      await boot(tester, size: const Size(411, 780));

      expect(find.byKey(const Key('stage-carousel')), findsNothing);
      expect(tester.getSize(find.byKey(const Key('designer-character'))).height, greaterThan(560));
      expect(find.byKey(const Key('hero-hint')), findsOneWidget);
      expect(panelOpacity(tester), 0);
    });

    testWidgets('the introduction waits for My Tasks to be opened, then plays once and ends with everything visible', (tester) async {
      await boot(tester, openTab: false); // the designer is on the Dashboard; My Tasks is built but hidden

      expect(find.byKey(const Key('total-count')), findsNothing);
      expect(tester.hasRunningAnimations, isFalse);

      await tester.tap(find.text('My Tasks'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
      expect(tester.hasRunningAnimations, isTrue); // the introduction has started

      await tester.pump(const Duration(milliseconds: 700)); // mid-way: card in, camera settling
      expect(tester.hasRunningAnimations, isTrue);

      await tester.pumpAndSettle();
      expect(tester.hasRunningAnimations, isFalse);
      expect(tester.widget<Text>(find.byKey(const Key('total-count'))).data, '7');
    });

    testWidgets('opening a stage is animated: the card flies, the screen stays under it, nothing switches at once', (tester) async {
      await boot(tester);
      await tester.tap(find.byKey(const Key('held-card-all')));
      await tester.pumpAndSettle();
      Navigator.of(tester.element(find.byType(StageTicketsScreen))).pop();
      await tester.pumpAndSettle();
      await openPanel(tester);
      await tester.tap(find.byKey(const Key('tile-in_progress'))); // puts the card in his hands
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('held-card-in_progress')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 150)); // part-way through the 500 ms transition

      expect(find.byType(StageTicketsScreen), findsOneWidget); // arriving
      expect(find.byKey(const Key('stage-panel')), findsOneWidget); // the overview is still there underneath
      expect(find.byType(Hero), findsWidgets); // the card is in flight
      expect(tester.hasRunningAnimations, isTrue);

      await tester.pumpAndSettle();
      expect(tester.hasRunningAnimations, isFalse);
      expect(stageTitle(tester), 'In Progress');
      expect(tester.widget<Text>(find.byKey(const Key('stage-total'))).data, '1');
      expect(find.text('Highway hoarding'), findsOneWidget);
      expect(find.text('Fresh brief'), findsNothing);
    });

    testWidgets('Back plays the transition in reverse and returns to the screen as it was, tiles still up', (tester) async {
      await boot(tester);
      await openStage(tester, 'in_progress');

      Navigator.of(tester.element(find.byType(StageTicketsScreen))).pop();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 150)); // reversing (450 ms)
      expect(find.byType(StageTicketsScreen), findsOneWidget); // still on its way out
      expect(tester.hasRunningAnimations, isTrue);

      await tester.pumpAndSettle();
      expect(find.byType(StageTicketsScreen), findsNothing);
      expect(panelOpacity(tester), 1); // the tiles are where they were left
      expect(heldStage(tester), 'in_progress'); // and so is the card
    });

    testWidgets('a failed load offers Try again, and the man appears once it works', (tester) async {
      var calls = 0;
      await boot(tester, routes: {
        'GET /designer/tasks': (_) => ++calls == 1 ? (status: 500, body: {'message': 'Server hiccup'}) : (status: 200, body: _board()),
      });

      expect(find.text('Try again'), findsOneWidget);
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('total-count')), findsOneWidget);
    });

    testWidgets('an empty board still shows the man, with a total of 0', (tester) async {
      await boot(tester, board: _board()..['tasks'] = []..['split_log'] = []..['stats'] = {'total': 0, 'active': 0, 'waiting': 0, 'completed': 0});

      expect(tester.widget<Text>(find.byKey(const Key('total-count'))).data, '0');
    });

    testWidgets('the notification icon goes to Alerts', (tester) async {
      await boot(tester);

      await tester.tap(find.byKey(const Key('kanban-alerts')));
      await tester.pumpAndSettle();

      final container = ProviderScope.containerOf(tester.element(find.byType(Scaffold).first));
      expect(container.read(homeTabProvider), HomeTab.alerts);
    });
  });

  group('the cards in his hands', () {
    // In the fixture the stages with tickets, in board order: awaiting BD approval, new assignments, ready to start,
    // in progress, waiting for BD review, completed, split tasks, self declined (after All Tickets).
    const order = ['all', 'pending_bd_approval', 'assigned_tasks', 'yet_to_start', 'in_progress', 'waiting_confirmation', 'completed', 'split_log', 'self_declined'];

    testWidgets('swiping the card turns to the next stage, and back again', (tester) async {
      await boot(tester);
      expect(heldStage(tester), 'all');

      await tester.fling(find.byKey(const Key('card-pager')), const Offset(-400, 0), 1500);
      await tester.pumpAndSettle();
      expect(heldStage(tester), 'pending_bd_approval');
      expect(tester.widget<Text>(find.byKey(const Key('held-count-pending_bd_approval'))).data, '1');

      await tester.fling(find.byKey(const Key('card-pager')), const Offset(400, 0), 1500);
      await tester.pumpAndSettle();
      expect(heldStage(tester), 'all');
    });

    testWidgets('the next card comes out of the stack in his hands while the top one slides away, not in from the screen edge', (tester) async {
      await boot(tester, size: const Size(411, 780));
      final home = tester.getCenter(find.byKey(const Key('held-card-all')));

      final g = await tester.startGesture(const Offset(205, 380));
      for (var i = 0; i < 8; i++) {
        await g.moveBy(const Offset(-12, 0));
        await tester.pump(const Duration(milliseconds: 16));
      }

      // part-way through: both cards are there
      final top = tester.getCenter(find.byKey(const Key('held-card-all')));
      final rising = tester.getCenter(find.byKey(const Key('held-card-pending_bd_approval')));
      expect(top.dx, lessThan(home.dx - 20)); // the top card is on its way out to the left
      expect((rising.dx - home.dx).abs(), lessThan(40)); // the next one is still where the stack is, rising
      expect((rising.dy - home.dy).abs(), lessThan(40));

      for (var i = 0; i < 6; i++) {
        await g.moveBy(const Offset(-12, 0)); // carry it far enough
        await tester.pump(const Duration(milliseconds: 16));
      }
      await g.up();
      await tester.pumpAndSettle();
      expect(heldStage(tester), 'pending_bd_approval');
      expect(find.byKey(const Key('held-card-all')), findsNothing); // the first card has left
    });

    testWidgets('letting go before it is half way puts the top card back', (tester) async {
      await boot(tester, size: const Size(411, 780));

      final g = await tester.startGesture(const Offset(205, 380));
      for (var i = 0; i < 4; i++) {
        await g.moveBy(const Offset(-10, 0));
        await tester.pump(const Duration(milliseconds: 16));
      }
      await g.up();
      await tester.pumpAndSettle();

      expect(heldStage(tester), 'all');
      expect(find.byKey(const Key('held-card-pending_bd_approval')), findsNothing);
    });

    testWidgets('he grips the card: a hand on its left edge, and the right hand on the outer edge of the whole stack', (tester) async {
      await boot(tester, size: const Size(411, 780));

      final card = tester.getRect(find.byKey(const Key('held-card-all')));
      final left = tester.getRect(find.byKey(const Key('designer-hand-left')));
      final right = tester.getRect(find.byKey(const Key('designer-hand-right')));

      expect(left.left, lessThan(card.left)); // outside the edge...
      expect(left.right, greaterThan(card.left)); // ...with the fingers over the card
      expect(right.right, greaterThan(card.right)); // the right hand reaches past the card, to the edges of the stack
      expect(right.left, lessThan(card.right + card.width * 0.2)); // with its fingers over the edges of the stack
      expect((left.center.dy - right.center.dy).abs(), lessThan(1)); // at the same height, like a man holding a board
    });

    testWidgets('the right hand follows the stack: further out with three edges, on the card itself on the last card', (tester) async {
      await boot(tester, size: const Size(411, 780));
      final first = tester.getRect(find.byKey(const Key('designer-hand-right'))).center.dx - tester.getRect(find.byKey(const Key('held-card-all'))).right;

      for (var i = 0; i < order.length - 2; i++) {
        await nextCard(tester);
      }
      final onePast = tester.getRect(find.byKey(const Key('designer-hand-right'))).center.dx - tester.getRect(find.byKey(const Key('held-card-split_log'))).right;

      await nextCard(tester);
      final last = tester.getRect(find.byKey(const Key('designer-hand-right'))).center.dx - tester.getRect(find.byKey(const Key('held-card-self_declined'))).right;

      expect(first, greaterThan(onePast + 4));
      expect(onePast, greaterThan(last + 2));
      expect(last, closeTo(-tester.getRect(find.byKey(const Key('designer-hand-right'))).width * 0.08, 6)); // right on the card's edge
    });

    testWidgets('the left hand stays put however the stack changes', (tester) async {
      await boot(tester, size: const Size(411, 780));
      final before = tester.getRect(find.byKey(const Key('designer-hand-left'))).left;

      await nextCard(tester);
      await nextCard(tester);

      expect(tester.getRect(find.byKey(const Key('designer-hand-left'))).left, closeTo(before, 0.5));
    });

    testWidgets('his hands are above the card in the stack, so the card is behind them', (tester) async {
      await boot(tester, size: const Size(411, 780));

      bool contains(Element e, Key k) {
        var found = e.widget.key == k;
        e.visitChildren((c) => found = found || contains(c, k));
        return found;
      }

      Element? pager;
      void visit(Element e) {
        if (e.widget.key == const Key('card-pager')) pager ??= e;
        e.visitChildren(visit);
      }
      tester.binding.rootElement!.visitChildren(visit);
      expect(pager, isNotNull);

      // the Stack that holds the card holds the hands too; later children are painted on top
      Element? stack;
      pager!.visitAncestorElements((e) {
        if (e.widget is Stack) {
          stack = e;
          return false;
        }
        return true;
      });
      final order = <String>[];
      stack!.visitChildren((c) {
        if (contains(c, const Key('card-pager'))) order.add('card');
        if (contains(c, const Key('designer-hand-left'))) order.add('left');
        if (contains(c, const Key('designer-hand-right'))) order.add('right');
      });
      expect(order.indexOf('left'), greaterThan(order.indexOf('card')));
      expect(order.indexOf('right'), greaterThan(order.indexOf('card')));
    });

    testWidgets('there are no arrows beside the card: it is turned by swiping or from the tiles', (tester) async {
      await boot(tester);

      expect(find.byKey(const Key('pager-prev')), findsNothing);
      expect(find.byKey(const Key('pager-next')), findsNothing);
    });

    testWidgets('the card sits below the Adinn logo on his shirt', (tester) async {
      await boot(tester, size: const Size(411, 780));

      final man = tester.getRect(find.byKey(const Key('designer-character')));
      final card = tester.getRect(find.byKey(const Key('held-card-all')));
      // the logo is at about 0.28 of his height; the card starts under it
      expect(card.top, greaterThan(man.top + man.height * 0.28));
    });

    testWidgets('the stack behind the card has one edge for each card really left, three at most, and none on the last card', (tester) async {
      await boot(tester);
      int edges() => find.byWidgetPredicate((w) => w.key is ValueKey<String> && (w.key! as ValueKey<String>).value.startsWith('card-edge-')).evaluate().length;

      expect(edges(), 3); // eight cards still to come, but only three edges are drawn

      // walk to the card before the last: one card left, one edge
      for (var i = 0; i < order.length - 2; i++) {
        await nextCard(tester);
      }
      expect(heldStage(tester), 'split_log');
      expect(edges(), 1);

      await nextCard(tester);
      expect(heldStage(tester), 'self_declined');
      expect(edges(), 0); // the last card: nothing behind it
    });

    testWidgets('on the last card a swipe does nothing and the way on is shut', (tester) async {
      await boot(tester);
      for (var i = 0; i < order.length - 1; i++) {
        await nextCard(tester);
      }
      expect(heldStage(tester), 'self_declined');

      await tester.fling(find.byKey(const Key('card-pager')), const Offset(-400, 0), 1500);
      await tester.pumpAndSettle();

      expect(heldStage(tester), 'self_declined');
      expect(find.byKey(const Key('card-edge-0')), findsNothing); // and there is nothing behind it to pull
    });

    testWidgets('going back brings the previous card in from the side onto the top, and the stack gets its edge back', (tester) async {
      await boot(tester, size: const Size(411, 780));
      await nextCard(tester);
      expect(heldStage(tester), 'pending_bd_approval');
      final home = tester.getCenter(find.byKey(const Key('held-card-pending_bd_approval')));

      final g = await tester.startGesture(const Offset(205, 380));
      for (var i = 0; i < 8; i++) {
        await g.moveBy(const Offset(12, 0)); // right = back
        await tester.pump(const Duration(milliseconds: 16));
      }
      final coming = tester.getCenter(find.byKey(const Key('held-card-all')));
      expect(coming.dx, lessThan(home.dx - 20)); // the first card is still on its way in from the left

      for (var i = 0; i < 6; i++) {
        await g.moveBy(const Offset(12, 0));
        await tester.pump(const Duration(milliseconds: 16));
      }
      await g.up();
      await tester.pumpAndSettle();

      expect(heldStage(tester), 'all');
      expect(find.byKey(const Key('held-card-pending_bd_approval')), findsNothing);
      expect(find.byKey(const Key('card-edge-0')), findsOneWidget);
    });

    testWidgets('there is nothing to pull off past the first card', (tester) async {
      await boot(tester);

      await tester.fling(find.byKey(const Key('card-pager')), const Offset(400, 0), 1500); // right on the first card = "back"
      await tester.pumpAndSettle();

      expect(heldStage(tester), 'all');
    });

    testWidgets('swiping turns forward and back through every card, and stops at both ends', (tester) async {
      await boot(tester);

      await prevCard(tester); // nothing before All Tickets
      expect(heldStage(tester), 'all');

      await nextCard(tester);
      expect(heldStage(tester), 'pending_bd_approval');
      await prevCard(tester);
      expect(heldStage(tester), 'all');

      for (var i = 0; i < order.length - 1; i++) {
        await nextCard(tester);
        expect(heldStage(tester), order[i + 1]);
      }
      await nextCard(tester); // nothing after the last
      expect(heldStage(tester), 'self_declined');
    });

    testWidgets('only stages that have tickets get a card; the dots count them', (tester) async {
      await boot(tester);

      final dots = tester.widget<Row>(find.byKey(const Key('pager-dots')));
      expect(dots.children, hasLength(order.length)); // All Tickets and the eight stages with tickets
    });

    testWidgets('each card is in its stage\'s colour, with the next stages\' colours peeking out behind it', (tester) async {
      await boot(tester);
      await nextCard(tester);

      final card = tester.widget<Container>(find.descendant(of: find.byKey(const Key('held-card-pending_bd_approval')), matching: find.byType(Container)).first);
      final border = (card.decoration! as BoxDecoration).border! as Border;
      expect(border.top.color, stageAccent('pending_bd_approval'));
      expect(find.byKey(const Key('card-edges')), findsOneWidget);
    });

    testWidgets('every number on a card is the count the server sent for that stage; Split Tasks counts splits, not more tickets', (tester) async {
      await boot(tester);

      // walk the whole stack and compare each card with the board the API sent
      final board = KanbanBoard.fromJson(_board());
      for (var i = 0; i < order.length; i++) {
        final key = order[i];
        final expected = key == 'all' ? '7' : '${board.countFor(board.columns.firstWhere((c) => c.key == key))}';
        final counter = key == 'all' ? find.byKey(const Key('total-count')) : find.byKey(Key('held-count-$key'));
        expect(tester.widget<Text>(counter).data, expected, reason: key);
        if (key == 'split_log') expect(find.text('Splits'), findsOneWidget);
        if (key == 'in_progress') expect(find.text('Ticket'), findsOneWidget);
        if (i < order.length - 1) {
          await nextCard(tester);
        }
      }
    });

    testWidgets('tapping the card opens the stage it shows', (tester) async {
      await boot(tester);
      for (var i = 0; i < order.indexOf('in_progress'); i++) {
        await nextCard(tester);
      }
      expect(heldStage(tester), 'in_progress');

      await tester.tap(find.byKey(const Key('held-card-in_progress')));
      await tester.pumpAndSettle();

      expect(stageTitle(tester), 'In Progress');
      expect(find.text('Highway hoarding'), findsOneWidget);
    });

    testWidgets('tapping the All Tickets card opens every ticket, each saying which stage it is in', (tester) async {
      await boot(tester);

      await tester.tap(find.byKey(const Key('held-card-all')));
      await tester.pumpAndSettle();

      expect(stageTitle(tester), 'All Tickets');
      expect(tester.widget<Text>(find.byKey(const Key('stage-total'))).data, '7');
      expect(find.descendant(of: find.byKey(const Key('stage-2')), matching: find.text('Stage: New Assignments')), findsOneWidget);
      expect(find.text('Festive creative'), findsOneWidget);
    });

    testWidgets('searching narrows the cards; if the stage in his hands empties he goes back to All Tickets', (tester) async {
      await boot(tester, routes: {
        'GET /designer/tasks': (o) {
          boardFetches++;
          final searching = (o.queryParameters['search'] ?? '') == 'zebra';
          return (
            status: 200,
            body: searching
                ? (_board()
                  ..['tasks'] = [_card(2, 'Fresh brief', 'assigned_tasks', extra: {'party_name': 'Zebra Agency'})]
                  ..['split_log'] = []
                  ..['stats'] = {'total': 1, 'active': 1, 'waiting': 0, 'completed': 0})
                : _board()
          );
        },
      });
      for (var i = 0; i < order.indexOf('in_progress'); i++) {
        await nextCard(tester);
      }
      expect(heldStage(tester), 'in_progress');

      await tester.tap(find.byKey(const Key('kanban-search-toggle')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('kanban-search')), 'zebra');
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pumpAndSettle();

      expect(heldStage(tester), 'all');
      expect(tester.widget<Text>(find.byKey(const Key('total-count'))).data, '1');
      expect(find.byType(Row).evaluate().isNotEmpty, isTrue);
      expect(tester.widget<Row>(find.byKey(const Key('pager-dots'))).children, hasLength(2)); // All Tickets and New Assignments
    });
  });

  group('stage tiles', () {
    testWidgets('they start out of sight, below the man', (tester) async {
      await boot(tester, size: const Size(411, 780));

      expect(panelOpacity(tester), 0);
      expect(find.byKey(const Key('hero-hint')), findsOneWidget);
    });

    testWidgets('a slight drag up pushes the man back and brings the tiles up, straight on the page', (tester) async {
      await boot(tester, size: const Size(411, 780));
      final before = tester.getRect(find.byKey(const Key('held-card-all'))).width;

      await openPanel(tester);

      expect(panelOpacity(tester), 1);
      expect(find.text('Your stages'), findsOneWidget);
      expect(tester.getRect(find.byKey(const Key('held-card-all'))).width, lessThan(before)); // he has stepped back
      // transparent: there is no sheet behind the tiles
      expect(tester.widget<Container>(find.byKey(const Key('stage-panel'))).decoration, isNull);
      // and the tiles sit in the space under his feet
      expect(tester.getTopLeft(find.byKey(const Key('tile-pending_bd_approval'))).dy, greaterThan(tester.getBottomLeft(find.byKey(const Key('designer-character'))).dy - 30));
    });

    testWidgets('the tiles follow the finger, and a slight drag is enough to bring them all the way', (tester) async {
      await boot(tester, size: const Size(411, 780));

      final g = await tester.startGesture(const Offset(205, 500));
      for (var i = 0; i < 6; i++) {
        await g.moveBy(const Offset(0, -10));
        await tester.pump(const Duration(milliseconds: 16));
      }
      final following = panelOpacity(tester); // mid-drag: partly there
      await g.up();
      await tester.pumpAndSettle();

      expect(following, greaterThan(0));
      expect(following, lessThan(1));
      expect(panelOpacity(tester), 1);
    });

    testWidgets('a tap, or a drag too small to count, leaves the man where he is', (tester) async {
      await boot(tester, size: const Size(411, 780));

      final g = await tester.startGesture(const Offset(20, 300)); // empty space beside the man
      await g.moveBy(const Offset(0, -8)); // inside the touch slop
      await tester.pump(const Duration(milliseconds: 16));
      await g.up();
      await tester.pumpAndSettle();

      expect(panelOpacity(tester), 0);
    });

    testWidgets('dragging back down sends the tiles away and brings the man forward', (tester) async {
      await boot(tester, size: const Size(411, 780));
      await openPanel(tester);
      final stepped = tester.getRect(find.byKey(const Key('held-card-all'))).width;

      await tester.fling(find.byKey(const Key('card-pager')), const Offset(0, 300), 1200);
      await tester.pumpAndSettle();

      expect(panelOpacity(tester), 0);
      expect(tester.getRect(find.byKey(const Key('held-card-all'))).width, greaterThan(stepped));
    });

    testWidgets('the hint brings the tiles up too', (tester) async {
      await boot(tester, size: const Size(411, 780));

      await tester.tap(find.byKey(const Key('hero-hint')));
      await tester.pumpAndSettle();

      expect(panelOpacity(tester), 1);
    });

    testWidgets('there is a tile for every one of the 14 stages, empty ones included, with its count', (tester) async {
      await boot(tester);
      await openPanel(tester);

      const stages = ['pending_bd_approval', 'bd_rejected', 'assigned_tasks', 'review_analysis', 'need_clarification', 'yet_to_start', 'in_progress', 'waiting_confirmation', 'rework', 'prepare_printing_file', 'completed', 'split_log', 'swap_tasks', 'self_declined'];
      expect(stages, hasLength(14));
      for (final k in stages) {
        expect(find.byKey(Key('tile-$k')), findsOneWidget, reason: k);
      }
      expect(tester.widget<Text>(find.byKey(const Key('tile-count-rework'))).data, '0');
      expect(tester.widget<Text>(find.byKey(const Key('tile-count-in_progress'))).data, '1');
      expect(tester.widget<Text>(find.byKey(const Key('tile-count-split_log'))).data, '4');
      for (final label in ['Awaiting BD', 'Rejected', 'New', 'Review', 'Clarify', 'Start', 'Progress', 'BD Review', 'Rework', 'Print File', 'Completed', 'Split', 'Swapped', 'Declined']) {
        expect(find.text(label), findsOneWidget, reason: label);
      }
    });

    testWidgets('tiles come five to a row', (tester) async {
      await boot(tester, size: const Size(411, 780));
      await openPanel(tester);

      final first = tester.getTopLeft(find.byKey(const Key('tile-pending_bd_approval'))).dy;
      expect(tester.getTopLeft(find.byKey(const Key('tile-need_clarification'))).dy, first); // 5th in the row
      expect(tester.getTopLeft(find.byKey(const Key('tile-yet_to_start'))).dy, greaterThan(first)); // 6th starts the next
    });

    testWidgets('tapping a tile puts that stage\'s card in his hands, and outlines the tile', (tester) async {
      await boot(tester);
      await openPanel(tester);

      await tester.tap(find.byKey(const Key('tile-waiting_confirmation')));
      await tester.pumpAndSettle();

      expect(heldStage(tester), 'waiting_confirmation');
      expect(tester.widget<Text>(find.byKey(const Key('held-count-waiting_confirmation'))).data, '1');
      expect(find.byType(StageTicketsScreen), findsNothing); // choosing a tile does not open it
      double outline(String k) => (((tester.widget<AnimatedContainer>(find.descendant(of: find.byKey(Key('tile-$k')), matching: find.byType(AnimatedContainer))).decoration!) as BoxDecoration).border! as Border).top.width;
      expect(outline('waiting_confirmation'), greaterThan(2));
      expect(outline('in_progress'), lessThan(2));
    });

    testWidgets('turning the cards moves the outline from tile to tile', (tester) async {
      await boot(tester);
      await openPanel(tester);

      await nextCard(tester);

      double outline(String k) => (((tester.widget<AnimatedContainer>(find.descendant(of: find.byKey(Key('tile-$k')), matching: find.byType(AnimatedContainer))).decoration!) as BoxDecoration).border! as Border).top.width;
      expect(outline('pending_bd_approval'), greaterThan(2));
    });

    testWidgets('tapping the tile of the card already in his hands opens that stage', (tester) async {
      await boot(tester);
      await openPanel(tester);
      await tester.tap(find.byKey(const Key('tile-in_progress')));
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('tile-in_progress')));
      await tester.pumpAndSettle();

      expect(stageTitle(tester), 'In Progress');
    });

    testWidgets('a tile for a stage with no tickets has no card, so it opens the stage and says so', (tester) async {
      await boot(tester);
      await openPanel(tester);

      await tester.tap(find.byKey(const Key('tile-rework')));
      await tester.pumpAndSettle();

      expect(stageTitle(tester), 'Rework');
      expect(find.byKey(const Key('stage-empty')), findsOneWidget);
    });
  });

  group('responsive', () {
    for (final width in [360.0, 390.0, 411.0, 430.0]) {
      testWidgets('lays out without overflow at ${width.toInt()} dp wide', (tester) async {
        await boot(tester, size: Size(width, 780));
        await tester.pumpAndSettle();

        expect(tester.takeException(), isNull);
        expect(find.byKey(const Key('designer-character')), findsOneWidget);
        expect(find.byKey(const Key('total-count')), findsOneWidget);

        final stack = tester.getRect(find.byKey(const Key('held-card-all')));
        expect(stack.left, greaterThanOrEqualTo(-1));
        expect(stack.right, lessThanOrEqualTo(width + 1)); // the card is never clipped by the screen edge
      });
    }

    for (final width in [360.0, 411.0, 430.0]) {
      testWidgets('the tiles fit at ${width.toInt()} dp wide', (tester) async {
        await boot(tester, size: Size(width, 780));
        await openPanel(tester);

        expect(tester.takeException(), isNull);
        final tiles = tester.getRect(find.byKey(const Key('stage-tiles')));
        expect(tiles.left, greaterThanOrEqualTo(0));
        expect(tiles.right, lessThanOrEqualTo(width + 1));
        expect(tester.getBottomLeft(find.byKey(const Key('tile-self_declined'))).dy, lessThanOrEqualTo(780 - 56)); // the last row is on screen
        // he has stepped back far enough that his feet are above the first row of tiles
        expect(tester.getBottomLeft(find.byKey(const Key('designer-character'))).dy, lessThanOrEqualTo(tester.getTopLeft(find.byKey(const Key('tile-pending_bd_approval'))).dy + 40));
      });
    }

    testWidgets('a short screen scales the hero down instead of overflowing', (tester) async {
      await boot(tester, size: const Size(360, 560));

      expect(tester.takeException(), isNull);
      expect(find.byKey(const Key('held-card-all')), findsOneWidget);
      expect(find.byKey(const Key('hero-hint')), findsOneWidget);

      await openPanel(tester);
      expect(tester.takeException(), isNull);
    });
  });

  group('tickets', () {
    testWidgets('a ticket shows id, due pill, priority, name, client, vertical, creatives, assigned BD, tag and badges', (tester) async {
      await boot(tester);
      await openStage(tester, 'assigned_tasks');

      expect(find.text('DT-2026-00002'), findsOneWidget);
      expect(find.text('Overdue'), findsWidgets);
      expect(find.text('URGENT'), findsOneWidget);
      expect(find.text('Fresh brief'), findsOneWidget);
      expect(find.text('Agency · Zebra Agency'), findsOneWidget);
      expect(find.textContaining('Roadshow · 4 creatives · Due '), findsOneWidget);
      expect(find.text('Assigned by: Bela BD'), findsOneWidget);
      expect(find.text('Decline · Pending'), findsOneWidget);
      expect(find.text('Previous Month Task • Oct 2026'), findsOneWidget);
    });

    testWidgets('an in-progress ticket shows completed / total creatives, the percentage, a due-soon pill and its continuation badge', (tester) async {
      await boot(tester);
      await openStage(tester, 'in_progress');

      expect(find.textContaining('Roadshow · 1 / 4 creatives · Due '), findsOneWidget);
      expect(find.byKey(const Key('progress-3')), findsOneWidget);
      expect(find.text('25%'), findsOneWidget);
      expect(find.text('Due Soon'), findsOneWidget);
      expect(find.text('Continued to Dec 2026 · Completed 3 Dec'), findsOneWidget);
    });

    testWidgets('a waiting ticket shows the live BD review time', (tester) async {
      await boot(tester);
      await openStage(tester, 'waiting_confirmation');

      expect(find.textContaining('Awaiting BD · BD Review Time: 3h 2'), findsOneWidget);
    });

    testWidgets('a completed ticket shows its rating and completion date', (tester) async {
      await boot(tester);
      await openStage(tester, 'completed');

      expect(find.text('4.5 / 5'), findsOneWidget);
      expect(find.text('Completed 10 Nov 2026'), findsOneWidget);
    });

    testWidgets('a self-declined ticket says who has it now; a ticket due within the hour reads Due Today', (tester) async {
      await boot(tester);

      await openStage(tester, 'self_declined');
      expect(find.text('Now with: Meera'), findsOneWidget);
      Navigator.of(tester.element(find.byType(StageTicketsScreen))).pop();
      await tester.pumpAndSettle();

      await openStage(tester, 'yet_to_start');
      expect(find.text('Due Today'), findsOneWidget);
    });

    testWidgets('a ticket opens its task page when tapped', (tester) async {
      await boot(tester);
      await openStage(tester, 'in_progress');

      await tester.tap(find.text('Highway hoarding'));
      await tester.pumpAndSettle();

      expect(api.requests.any((r) => r.path == '/designer/tasks/3'), isTrue);
    });

    testWidgets('the Split Tasks stage lists every split with its Status, an approved one opening the child task', (tester) async {
      await boot(tester);
      await openStage(tester, 'split_log');

      expect(stageTitle(tester), 'Split Tasks (Nov 2026)');
      expect(find.text('Status: Split approved'), findsOneWidget);
      expect(find.text('Status: Pending approval'), findsOneWidget);
      expect(find.text('Status: Split rejected'), findsOneWidget);
      expect(find.text('Status: You completed all'), findsOneWidget);

      await tester.ensureVisible(find.byKey(const Key('split-20')));
      await tester.tap(find.byKey(const Key('split-20')));
      await tester.pumpAndSettle();
      expect(api.requests.any((r) => r.path == '/designer/tasks/20'), isTrue);
    });

    testWidgets('an empty Split Tasks stage uses the web wording', (tester) async {
      await boot(tester, board: _board()..['split_log'] = []);
      await openStage(tester, 'split_log');

      expect(find.text('No split requests in Nov 2026.'), findsOneWidget);
    });

    testWidgets('pull-to-refresh reloads the board', (tester) async {
      await boot(tester);
      await openStage(tester, 'in_progress');
      final before = boardFetches;

      await tester.fling(find.byKey(const Key('ticket-list')), const Offset(0, 400), 1000);
      await tester.pumpAndSettle();

      expect(boardFetches, greaterThan(before));
    });
  });

  group('moving tickets', () {
    testWidgets('only tickets the server says can move get the Move to… button', (tester) async {
      await boot(tester);

      await openStage(tester, 'in_progress');
      expect(find.byKey(const Key('card-menu-3')), findsOneWidget);
      Navigator.of(tester.element(find.byType(StageTicketsScreen))).pop();
      await tester.pumpAndSettle();

      await openStage(tester, 'pending_bd_approval');
      expect(find.byKey(const Key('card-menu-1')), findsNothing);
    });

    testWidgets('the sheet offers the forward moves and, separately, the requests to move back', (tester) async {
      await boot(tester);
      await openStage(tester, 'in_progress');

      await tester.tap(find.byKey(const Key('card-menu-3')));
      await tester.pumpAndSettle();

      expect(find.text('Move DT-2026-00003'), findsOneWidget);
      expect(find.text('Open ticket'), findsOneWidget);
      expect(find.text('Move to Waiting for BD Review'), findsOneWidget);
      expect(find.text('Request move back to New Assignments'), findsOneWidget);
      expect(find.text('Request move back to Ready to Start'), findsOneWidget);
      expect(find.text('Needs Designer Head approval'), findsWidgets);
    });

    testWidgets('a long press opens the same actions, even for a ticket that cannot move', (tester) async {
      await boot(tester);
      await openStage(tester, 'pending_bd_approval');

      await tester.longPress(find.byKey(const Key('card-1')));
      await tester.pumpAndSettle();

      expect(find.text('Open ticket'), findsOneWidget);
      expect(find.textContaining('Move to '), findsNothing);
    });

    testWidgets('Move to … posts the move, shows the green check and a message, and reloads the board', (tester) async {
      await boot(tester, routes: {'POST /designer/tasks/7/move': (_) => (status: 200, body: {'task': taskJson(id: 7)})});
      await openStage(tester, 'yet_to_start');
      final before = boardFetches;

      await tester.tap(find.byKey(const Key('card-menu-7')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('move-in_progress')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300)); // the check has popped in

      expect(lastBody('/designer/tasks/7/move'), {'status': 'in_progress'});
      expect(find.byKey(const Key('success-check')), findsOneWidget);
      expect(find.text('Task status updated successfully.'), findsOneWidget);
      expect(boardFetches, greaterThan(before));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('success-check')), findsNothing); // and gone again
    });

    testWidgets('swiping a ticket right moves it to its next stage', (tester) async {
      await boot(tester, routes: {'POST /designer/tasks/3/move': (_) => (status: 200, body: {'task': taskJson(id: 3)})});
      await openStage(tester, 'in_progress');

      await tester.fling(find.byKey(const Key('card-3')), const Offset(500, 0), 1500);
      await tester.pumpAndSettle();

      expect(lastBody('/designer/tasks/3/move'), {'status': 'waiting_confirmation'});
      expect(find.text('Task status updated successfully.'), findsOneWidget);
    });

    testWidgets('the next stage is the nearest allowed one after the current stage', (tester) async {
      await boot(tester, routes: {'POST /designer/tasks/2/move': (_) => (status: 200, body: {'task': taskJson(id: 2)})});
      await openStage(tester, 'assigned_tasks');

      await tester.fling(find.byKey(const Key('card-2')), const Offset(500, 0), 1500);
      await tester.pumpAndSettle();

      expect(lastBody('/designer/tasks/2/move'), {'status': 'review_analysis'});
    });

    testWidgets('swiping left asks for a reason when there is one place to go back to, and sends nothing until given', (tester) async {
      await boot(tester);
      await openStage(tester, 'waiting_confirmation');

      await tester.fling(find.byKey(const Key('card-4')), const Offset(-500, 0), 1500);
      await tester.pumpAndSettle();

      expect(find.text('Are you sure you want to move this ticket?'), findsOneWidget);
      expect(find.text('Waiting for BD Review  ↓  New Assignments'), findsOneWidget);
      expect(api.requests.where((r) => r.method == 'POST'), isEmpty);
    });

    testWidgets('swiping left with several places to go back to opens the sheet', (tester) async {
      await boot(tester);
      await openStage(tester, 'in_progress');

      await tester.fling(find.byKey(const Key('card-3')), const Offset(-500, 0), 1500);
      await tester.pumpAndSettle();

      expect(find.text('Request move back to New Assignments'), findsOneWidget);
      expect(find.text('Request move back to Ready to Start'), findsOneWidget);
    });

    testWidgets('a ticket that cannot move cannot be swiped', (tester) async {
      await boot(tester);
      await openStage(tester, 'pending_bd_approval');

      await tester.fling(find.byKey(const Key('card-1')), const Offset(500, 0), 1500);
      await tester.pumpAndSettle();

      expect(find.byType(Dismissible), findsNothing);
      expect(api.requests.where((r) => r.method == 'POST'), isEmpty);
    });

    testWidgets("the server's refusal is shown, e.g. creatives not all done, and no success is shown", (tester) async {
      await boot(tester, routes: {
        'POST /designer/tasks/3/move': (_) => (status: 422, body: {
              'message': 'x',
              'errors': {'status': ['Creative Work Incomplete: Complete all creatives before BD Review. 1 of 4 completed • 3 remaining.']},
            }),
      });
      await openStage(tester, 'in_progress');

      await tester.tap(find.byKey(const Key('card-menu-3')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('move-waiting_confirmation')));
      await tester.pumpAndSettle();

      expect(find.textContaining('Creative Work Incomplete'), findsOneWidget);
      expect(find.byKey(const Key('success-check')), findsNothing);
    });

    testWidgets('moving back asks for a reason, then raises a request for the Designer Head instead of moving', (tester) async {
      await boot(tester, routes: {'POST /designer/tasks/3/backward-request': (_) => (status: 201, body: {'message': 'ok'})});
      await openStage(tester, 'in_progress');

      await tester.tap(find.byKey(const Key('card-menu-3')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('back-yet_to_start')));
      await tester.pumpAndSettle();

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
      expect(api.requests.where((r) => r.path.endsWith('/move')), isEmpty); // the ticket itself was not moved
    });

    testWidgets('No leaves the task where it is and sends nothing', (tester) async {
      await boot(tester);
      await openStage(tester, 'in_progress');

      await tester.tap(find.byKey(const Key('card-menu-3')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('back-yet_to_start')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('backward-no')));
      await tester.pumpAndSettle();

      expect(api.requests.where((r) => r.method == 'POST'), isEmpty);
    });

    testWidgets('after a move the stage counts come from the reloaded board', (tester) async {
      var moved = false;
      await boot(tester, routes: {
        'GET /designer/tasks': (_) {
          boardFetches++;
          final b = _board();
          if (moved) {
            final tasks = (b['tasks'] as List).cast<Map<String, dynamic>>();
            tasks.firstWhere((t) => t['id'] == 3)['status'] = 'waiting_confirmation';
          }
          return (status: 200, body: b);
        },
        'POST /designer/tasks/3/move': (_) {
          moved = true;
          return (status: 200, body: {'task': taskJson(id: 3)});
        },
      });
      await openStage(tester, 'in_progress');
      expect(tester.widget<Text>(find.byKey(const Key('stage-total'))).data, '1');

      await tester.tap(find.byKey(const Key('card-menu-3')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('move-waiting_confirmation')));
      await tester.pumpAndSettle();

      expect(tester.widget<Text>(find.byKey(const Key('stage-total'))).data, '0'); // In Progress is now empty
      expect(find.text('Highway hoarding'), findsNothing);
    });
  });

  group('search and filters', () {
    testWidgets('search sits behind the search icon, searches on the server, and the total follows', (tester) async {
      await boot(tester, routes: {
        'GET /designer/tasks': (o) {
          boardFetches++;
          final searching = (o.queryParameters['search'] ?? '') == 'zebra';
          return (
            status: 200,
            body: searching
                ? (_board()
                  ..['tasks'] = [_card(2, 'Fresh brief', 'assigned_tasks', extra: {'party_name': 'Zebra Agency'})]
                  ..['split_log'] = []
                  ..['stats'] = {'total': 1, 'active': 1, 'waiting': 0, 'completed': 0})
                : _board()
          );
        },
      });

      expect(find.byKey(const Key('kanban-search')), findsNothing);
      await tester.tap(find.byKey(const Key('kanban-search-toggle')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('kanban-search')), 'zebra');
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pumpAndSettle();

      expect(lastBoardQuery()['search'], 'zebra');
      expect(tester.widget<Text>(find.byKey(const Key('total-count'))).data, '1');
      expect(find.byKey(const Key('applied-filters')), findsOneWidget);
      expect(find.text('Search: zebra'), findsOneWidget);
    });

    testWidgets('search also works from inside a stage', (tester) async {
      await boot(tester);
      await openStage(tester, 'in_progress');

      await tester.tap(find.byKey(const Key('kanban-search-toggle')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('kanban-search')), 'highway');
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pumpAndSettle();

      expect(lastBoardQuery()['search'], 'highway');
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
