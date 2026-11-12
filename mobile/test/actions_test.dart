import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

/// Role actions against a fake API: head/admin approvals, BD review, designer requests.
void main() {
  late FakeAdapter adapter;

  Future<void> boot(WidgetTester tester, String role, Map<String, Handler> routes) async {
    tester.view.physicalSize = const Size(800, 2400);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    final store = MemoryStore(token: 'tok', user: AppUser.fromJson(userJson(role: role)));
    adapter = FakeAdapter({
      'GET /me': (_) => (status: 200, body: {'user': userJson(role: role)}),
      'GET /notifications': (_) => (status: 200, body: {'unread_count': 0, 'data': []}),
      'GET /bd/dashboard': (_) => (status: 200, body: emptyBdDashboardJson()),
      ...routes,
    });

    await tester.pumpWidget(ProviderScope(
      overrides: [
        sessionStoreProvider.overrideWithValue(store),
        apiClientProvider.overrideWith((ref) => fakeClient(adapter,
            token: () => ref.read(authProvider).token, onUnauthorized: () => ref.read(authProvider.notifier).signOutLocally())),
      ],
      child: const AdinnApp(),
    ));
    await tester.pumpAndSettle();
    if (role == 'designer' || role == 'bd') await openMyTasks(tester);
  }

  Map<String, dynamic> requestJson({int id = 5, String type = 'decline'}) => {
        'id': id,
        'request_type': type,
        'overall_status': 'pending_approval',
        'reason': 'Overloaded this week',
        'split_details': type == 'split' ? {'creative_count': 2, 'details': 'x'} : null,
        'task': taskJson(),
        'requester': {'id': 9, 'name': 'Dee Signer'},
        'target_designer': null,
      };

  Map<String, dynamic> lastBody(String method, String path) =>
      Map<String, dynamic>.from(adapter.requests.lastWhere((r) => r.method == method && r.path == path).data as Map);

  testWidgets('designer head sees pending requests, and approving needs a designer', (tester) async {
    await boot(tester, 'designer_head', {
      'GET /designer-head/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': []}),
      'GET /designer-head/requests': (_) => (status: 200, body: {
            'data': [requestJson()],
            'designers': [{'id': 21, 'name': 'Priya'}, {'id': 22, 'name': 'Arun'}],
          }),
      'POST /designer-head/requests/5/approve': (_) => (status: 200, body: {'message': 'ok'}),
    });

    await tester.tap(find.text('Requests'));
    await tester.pumpAndSettle();

    expect(find.text('Dee Signer'), findsNothing); // shown as "by Dee Signer"
    expect(find.text('by Dee Signer'), findsOneWidget);
    expect(find.text('Overloaded this week'), findsOneWidget);

    await tester.tap(find.byKey(const Key('approve-5')));
    await tester.pumpAndSettle();

    // Required designer missing → blocked client-side.
    await tester.tap(find.byKey(const Key('sheet-submit')));
    await tester.pumpAndSettle();
    expect(find.text('Assign to designer is required'), findsOneWidget);
    expect(adapter.requests.where((r) => r.path.endsWith('/approve')), isEmpty);

    await tester.tap(find.byKey(const Key('field-designer')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Priya').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('sheet-submit')));
    await tester.pumpAndSettle();

    expect(lastBody('POST', '/designer-head/requests/5/approve')['approved_designer_id'], 21);
  });

  testWidgets('declining a request needs a reason and posts it', (tester) async {
    await boot(tester, 'designer_head', {
      'GET /designer-head/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': []}),
      'GET /designer-head/requests': (_) => (status: 200, body: {'data': [requestJson()], 'designers': []}),
      'POST /designer-head/requests/5/reject': (_) => (status: 200, body: {'message': 'ok'}),
    });

    await tester.tap(find.text('Requests'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('reject-5')));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('sheet-submit')));
    await tester.pumpAndSettle();
    expect(find.text('Reason is required'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('field-reason')), 'Not justified');
    await tester.tap(find.byKey(const Key('sheet-submit')));
    await tester.pumpAndSettle();

    expect(lastBody('POST', '/designer-head/requests/5/reject')['decision_reason'], 'Not justified');
  });

  testWidgets('BD does not get a Requests tab', (tester) async {
    await boot(tester, 'bd', {'GET /bd/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': []})});
    expect(find.text('Requests'), findsNothing);
  });

  group('BD review', () {
    Map<String, Handler> bdRoutes({bool review = true, bool confirm = false}) => {
          'GET /bd/tasks': (_) => (status: 200, body: {
                'statuses': [{'key': 'waiting_confirmation', 'label': 'Waiting'}],
                'tasks': [taskJson(status: 'waiting_confirmation')],
              }),
          'GET /bd/tasks/1': (_) => (status: 200, body: {
                'task': taskJson(status: confirm ? 'pending_bd_approval' : 'waiting_confirmation'),
                'progress': {'completed': 3, 'total': 3, 'remaining': 0, 'percentage': 100},
                'actions': {'can_review': review, 'can_decide_confirmation': confirm},
                'history': [],
              }),
          'GET /bd/tasks/1/comments': (_) => (status: 200, body: {'data': []}),
          'POST /bd/tasks/1/complete': (_) => (status: 200, body: {'task': taskJson(status: 'completed')}),
          'POST /bd/tasks/1/rework': (_) => (status: 200, body: {'task': taskJson(status: 'rework')}),
          'POST /bd/tasks/1/approve': (_) => (status: 200, body: {'task': taskJson()}),
          'POST /bd/tasks/1/reject': (_) => (status: 200, body: {'task': taskJson()}),
        };

    testWidgets('low overall rating requires a comment; valid rating posts all five scores', (tester) async {
      await boot(tester, 'bd', bdRoutes());
      await tester.tap(find.text('Banner'));
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('bd-complete')));
      await tester.pumpAndSettle();
      expect(find.text('Overall: 5.0 / 5'), findsOneWidget);

      // Drag every slider to the minimum → overall 0.5, comment becomes mandatory.
      for (final k in ['designer_attitude', 'design_satisfaction', 'rework_iteration', 'meeting_deadline', 'client_satisfaction']) {
        await tester.drag(find.byKey(Key('rate-$k')), const Offset(-2000, 0));
        await tester.pump();
      }
      expect(find.text('Overall: 0.5 / 5'), findsOneWidget);

      await tester.tap(find.byKey(const Key('rating-submit')));
      await tester.pumpAndSettle();
      expect(find.textContaining('required when the overall rating is below 3'), findsOneWidget);
      expect(adapter.requests.where((r) => r.path.endsWith('/complete')), isEmpty);

      await tester.enterText(find.byKey(const Key('rating-comment')), 'Missed the brief');
      await tester.tap(find.byKey(const Key('rating-submit')));
      await tester.pumpAndSettle();

      final body = lastBody('POST', '/bd/tasks/1/complete');
      expect(body['designer_attitude'], 0.5);
      expect(body['client_satisfaction'], 0.5);
      expect(body['rating_comment'], 'Missed the brief');
      expect(body['completion_type'], 'completed');
    });

    testWidgets('rework posts the creative count and comment', (tester) async {
      await boot(tester, 'bd', bdRoutes());
      await tester.tap(find.text('Banner'));
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('bd-rework')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('field-count')), '2');
      await tester.enterText(find.byKey(const Key('field-comment')), 'Fix the logo');
      await tester.tap(find.byKey(const Key('sheet-submit')));
      await tester.pumpAndSettle();

      final body = lastBody('POST', '/bd/tasks/1/rework');
      expect(body['number_of_creatives'], 2);
      expect(body['comment'], 'Fix the logo');
    });

    testWidgets('no review buttons when the task is not awaiting review', (tester) async {
      await boot(tester, 'bd', bdRoutes(review: false));
      await tester.tap(find.text('Banner'));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('bd-complete')), findsNothing);
      expect(find.byKey(const Key('bd-rework')), findsNothing);
    });

    testWidgets('rejecting a designer-created task requires a reason', (tester) async {
      await boot(tester, 'bd', bdRoutes(review: false, confirm: true));
      await tester.tap(find.text('Banner'));
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('bd-reject')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('sheet-submit')));
      await tester.pumpAndSettle();
      expect(find.text('Reason is required'), findsOneWidget);

      await tester.enterText(find.byKey(const Key('field-comment')), 'Wrong client');
      await tester.tap(find.byKey(const Key('sheet-submit')));
      await tester.pumpAndSettle();
      expect(lastBody('POST', '/bd/tasks/1/reject')['comment'], 'Wrong client');
    });
  });

  testWidgets('designer raises a swap request through the two-step form', (tester) async {
    await boot(tester, 'designer', {
      'GET /designer/tasks': (_) => (status: 200, body: {
            'statuses': [{'key': 'yet_to_start', 'label': 'Yet to start'}],
            'tasks': [taskJson(status: 'yet_to_start')],
          }),
      'GET /designer/tasks/1': (_) => (status: 200, body: {
            'task': taskJson(status: 'yet_to_start'),
            'access': {'current': true, 'read_only': false, 'reason': null},
            'progress': {'completed': 0, 'total': 3, 'remaining': 3, 'percentage': 0},
            'next_status': 'in_progress',
            'history': [],
          }),
      'GET /designer/tasks/1/request-options': (_) => (status: 200, body: {
            'allowed_types': ['split', 'swap'],
            'designers': [{'id': 31, 'name': 'Meera'}],
          }),
      'POST /designer/tasks/1/requests': (_) => (status: 201, body: {'message': 'ok'}),
    });

    await tester.tap(find.text('Banner'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('raise-request')));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('field-type')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Swap with another designer').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('sheet-submit')));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('field-designer')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Meera').last);
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('field-reason')), 'On leave');
    await tester.tap(find.byKey(const Key('sheet-submit')));
    await tester.pumpAndSettle();

    final body = lastBody('POST', '/designer/tasks/1/requests');
    expect(body['type'], 'swap');
    expect(body['target_designer_id'], 31);
    expect(body['reason'], 'On leave');
  });

  testWidgets('designer sees a friendly message when no request type is available', (tester) async {
    await boot(tester, 'designer', {
      'GET /designer/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': [taskJson(status: 'waiting_confirmation')]}),
      'GET /designer/tasks/1': (_) => (status: 200, body: {
            'task': taskJson(status: 'waiting_confirmation'),
            'access': {'current': true, 'read_only': false},
            'progress': {'completed': 3, 'total': 3, 'remaining': 0, 'percentage': 100},
            'history': [],
          }),
      'GET /designer/tasks/1/request-options': (_) => (status: 200, body: {'allowed_types': [], 'designers': []}),
    });

    await tester.tap(find.text('Banner'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('raise-request')));
    await tester.pumpAndSettle();

    expect(find.text('No requests are available for this task right now.'), findsOneWidget);
  });
}
