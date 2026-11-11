import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/features/home/navigation.dart';
import 'package:adinn_tasks/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

/// Delegate approvals: a designer the Admin allowed to approve teammates' Swap / Split requests.
void main() {
  late FakeAdapter adapter;
  late ProviderContainer container;

  Map<String, dynamic> approverJson({bool approver = true}) => {...userJson(), 'is_request_approver': approver};

  Map<String, dynamic> swapJson() => {
        'id': 7,
        'request_type': 'swap',
        'overall_status': 'pending_designer_head',
        'reason': 'Out of office',
        'split_details': null,
        'task': taskJson(),
        'requester': {'id': 9, 'name': 'Dee Signer'},
        'target_designer': null,
      };

  Future<void> boot(WidgetTester tester, {bool approver = true, Map<String, Handler> routes = const {}, Map<String, dynamic>? meUser}) async {
    tester.view.physicalSize = const Size(800, 2400);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    final store = MemoryStore(token: 'tok', user: AppUser.fromJson(approverJson(approver: approver)));
    adapter = FakeAdapter({
      'GET /me': (_) => (status: 200, body: {'user': meUser ?? approverJson(approver: approver)}),
      'GET /notifications': (_) => (status: 200, body: {'unread_count': 0, 'data': []}),
      'GET /designer/tasks': (_) => (status: 200, body: {'columns': [], 'statuses': [], 'tasks': [], 'split_log': []}),
      'GET /designer/approvals': (_) => (status: 200, body: {
            'data': [swapJson()],
            'designers': [{'id': 21, 'name': 'Priya'}, {'id': 22, 'name': 'Arun'}],
          }),
      ...routes,
    });

    container = ProviderContainer(overrides: [
      sessionStoreProvider.overrideWithValue(store),
      apiClientProvider.overrideWith((ref) => fakeClient(adapter,
          token: () => ref.read(authProvider).token, onUnauthorized: () => ref.read(authProvider.notifier).signOutLocally())),
    ]);
    addTearDown(container.dispose);

    await tester.pumpWidget(UncontrolledProviderScope(container: container, child: const AdinnApp()));
    await tester.pumpAndSettle();
  }

  Map<String, dynamic> lastBody(String path) =>
      Map<String, dynamic>.from(adapter.requests.lastWhere((r) => r.method == 'POST' && r.path == path).data as Map);

  test('tabs: only an allowed designer gets Approvals', () {
    expect(tabsFor('designer', approver: true), contains(HomeTab.requests));
    expect(tabsFor('designer'), isNot(contains(HomeTab.requests)));
    expect(tabsFor('designer', approver: true).first, HomeTab.dashboard);
  });

  testWidgets('an allowed designer sees the Approvals tab and the teammate request', (tester) async {
    await boot(tester);

    await tester.tap(find.text('Approvals'));
    await tester.pumpAndSettle();

    expect(find.text('by Dee Signer'), findsOneWidget);
    expect(find.text('Out of office'), findsOneWidget);
    expect(adapter.requests.any((r) => r.path == '/designer/approvals'), isTrue);
  });

  testWidgets('a designer without the right has no Approvals tab', (tester) async {
    await boot(tester, approver: false);

    expect(find.text('Approvals'), findsNothing);
    expect(adapter.requests.any((r) => r.path == '/designer/approvals'), isFalse);
  });

  testWidgets('approving needs a designer and posts to the designer approvals route', (tester) async {
    await boot(tester, routes: {'POST /designer/approvals/7/approve': (_) => (status: 200, body: {'message': 'ok'})});

    await tester.tap(find.text('Approvals'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('approve-7')));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('sheet-submit')));
    await tester.pumpAndSettle();
    expect(find.text('Assign to designer is required'), findsOneWidget);

    await tester.tap(find.byKey(const Key('field-designer')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Priya').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('sheet-submit')));
    await tester.pumpAndSettle();

    expect(lastBody('/designer/approvals/7/approve')['approved_designer_id'], 21);
  });

  testWidgets('choosing the final designer shows their availability meter', (tester) async {
    await boot(tester, routes: {
      'GET /designer/designers/21/availability': (_) => (status: 200, body: {
            'designer_id': 21, 'active_tasks': 3, 'active_creatives': 9, 'availability_percent': 40, 'level': 'moderate', 'label': 'Moderate', 'checked_in': true,
          }),
    });

    await tester.tap(find.text('Approvals'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('approve-7')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('availability')), findsNothing);

    await tester.tap(find.byKey(const Key('field-designer')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Priya').last);
    await tester.pumpAndSettle();

    expect(find.text('Availability: Moderate (40% free)'), findsOneWidget);
    expect(find.text('3 active tasks · 9 creatives · Checked in'), findsOneWidget);
  });

  testWidgets('declining needs a reason and posts to the designer approvals route', (tester) async {
    await boot(tester, routes: {'POST /designer/approvals/7/reject': (_) => (status: 200, body: {'message': 'ok'})});

    await tester.tap(find.text('Approvals'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('reject-7')));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('sheet-submit')));
    await tester.pumpAndSettle();
    expect(find.text('Reason is required'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('field-reason')), 'Not now');
    await tester.tap(find.byKey(const Key('sheet-submit')));
    await tester.pumpAndSettle();

    expect(lastBody('/designer/approvals/7/reject')['decision_reason'], 'Not now');
  });

  testWidgets('refreshUser picks up a newly granted approval right and shows the tab', (tester) async {
    await boot(tester, approver: false);
    expect(find.text('Approvals'), findsNothing);

    // The Admin grants the right afterwards.
    adapter.routes['GET /me'] = (_) => (status: 200, body: {'user': approverJson(approver: true)});

    await tester.runAsync(() => container.read(authProvider.notifier).refreshUser());
    await tester.pumpAndSettle();

    expect(container.read(authProvider).user?.isRequestApprover, isTrue);
    expect(find.text('Approvals'), findsWidgets);
    await tester.pumpAndSettle(const Duration(seconds: 1)); // the approvals badge is fetched as the tab appears
  });

  testWidgets('refreshUser is silent when offline and keeps the cached user', (tester) async {
    await boot(tester, approver: true, routes: {'GET /me': (_) => (status: 500, body: {'message': 'down'})});

    await tester.runAsync(() => container.read(authProvider.notifier).refreshUser());
    await tester.pumpAndSettle();

    expect(container.read(authProvider).user?.isRequestApprover, isTrue);
    await tester.pump(const Duration(seconds: 3)); // let the 2 s server-address timeout elapse
  });
}
