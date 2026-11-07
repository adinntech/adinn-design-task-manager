import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

Map<String, dynamic> profileJson({String role = 'designer', List<String> verticals = const ['outdoor'], List<String> skills = const ['Photoshop'], bool push = true}) => {
      'profile': {
        'id': 1,
        'name': 'Demo Designer',
        'username': 'demo.designer',
        'employee_code': '123',
        'role': role,
        'role_name': 'Senior graphic designer',
        'email': 'designer@adinn.com',
        'phone_number': '9874563214',
        'last_login_at': '2026-11-07T09:25:00+00:00',
        'web_push_enabled': push,
        'has_verticals': role == 'designer' || role == 'bd',
        'has_skills': role == 'designer',
        'experienced_verticals': verticals,
        'skills': skills,
      },
      'vertical_options': {'outdoor': 'Outdoor', 'roadshow': 'Road Show', 'signage': 'Signage'},
    };

void main() {
  late FakeAdapter adapter;

  Future<void> boot(WidgetTester tester, {String role = 'designer', Map<String, Handler> routes = const {}}) async {
    tester.view.physicalSize = const Size(800, 3000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    adapter = FakeAdapter({
      'GET /me': (_) => (status: 200, body: {'user': userJson(role: role)}),
      'GET /notifications': (_) => (status: 200, body: {'unread_count': 0, 'data': []}),
      'GET /designer/tasks': (_) => (status: 200, body: {'statuses': [], 'columns': [], 'tasks': []}),
      'GET /profile': (_) => (status: 200, body: profileJson(role: role)),
      ...routes,
    });

    final container = ProviderContainer(overrides: [
      sessionStoreProvider.overrideWithValue(MemoryStore(token: 'tok', user: AppUser.fromJson(userJson(role: role)))),
      apiClientProvider.overrideWith((ref) => fakeClient(adapter, token: () => ref.read(authProvider).token)),
    ]);
    addTearDown(container.dispose);

    await tester.pumpWidget(UncontrolledProviderScope(container: container, child: const AdinnApp()));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Profile'));
    await tester.pumpAndSettle();
  }

  String text(WidgetTester tester, String key) => tester.widget<TextField>(find.byKey(Key(key))).controller!.text;

  testWidgets('shows the details from the server, e-mail locked, last login read-only', (tester) async {
    await boot(tester);

    expect(text(tester, 'profile-name'), 'Demo Designer');
    expect(text(tester, 'profile-username'), 'demo.designer');
    expect(text(tester, 'profile-employee_code'), '123');
    expect(text(tester, 'profile-role_name'), 'Senior graphic designer');
    expect(text(tester, 'profile-phone_number'), '9874563214');
    expect(tester.widget<TextField>(find.byKey(const Key('profile-email'))).readOnly, isTrue);
    expect(text(tester, 'profile-email'), 'designer@adinn.com');
    expect(find.byKey(const Key('profile-last-login')), findsOneWidget);
    expect(find.byKey(const Key('sign-out')), findsOneWidget);
  });

  testWidgets('saving the details sends them and shows the confirmation', (tester) async {
    await boot(tester, routes: {'PATCH /profile/basic-info': (_) => (status: 200, body: {...profileJson(), 'message': 'Profile details updated successfully.'})});

    await tester.enterText(find.byKey(const Key('profile-phone_number')), '9000000001');
    await tester.ensureVisible(find.byKey(const Key('save-details')));
    await tester.tap(find.byKey(const Key('save-details')));
    await tester.pumpAndSettle();

    final sent = adapter.requests.lastWhere((r) => r.path == '/profile/basic-info' && r.method == 'PATCH').data as Map;
    expect(sent['phone_number'], '9000000001');
    expect(sent['name'], 'Demo Designer');
    expect(find.text('Profile details updated successfully.'), findsOneWidget);
  });

  testWidgets('a rejected field shows its message under that field', (tester) async {
    await boot(tester, routes: {
      'PATCH /profile/basic-info': (_) => (status: 422, body: {'message': 'x', 'errors': {'phone_number': ['Enter a valid 10-digit phone number.']}}),
    });

    await tester.enterText(find.byKey(const Key('profile-phone_number')), '123');
    await tester.ensureVisible(find.byKey(const Key('save-details')));
    await tester.tap(find.byKey(const Key('save-details')));
    await tester.pumpAndSettle();

    expect(find.text('Enter a valid 10-digit phone number.'), findsOneWidget);
  });

  testWidgets('the password is sent with its confirmation, then the boxes empty', (tester) async {
    await boot(tester, routes: {'PATCH /profile/password': (_) => (status: 200, body: {'message': 'Password updated successfully.'})});

    await tester.enterText(find.byKey(const Key('profile-password')), 'NewPass@123');
    await tester.enterText(find.byKey(const Key('profile-password_confirmation')), 'NewPass@123');
    await tester.ensureVisible(find.byKey(const Key('save-password')));
    await tester.tap(find.byKey(const Key('save-password')));
    await tester.pumpAndSettle();

    final sent = adapter.requests.lastWhere((r) => r.path == '/profile/password').data as Map;
    expect(sent, {'password': 'NewPass@123', 'password_confirmation': 'NewPass@123'});
    expect(text(tester, 'profile-password'), '');
    expect(text(tester, 'profile-password_confirmation'), '');
  });

  testWidgets('the bell at the top turns push on or off and saves straight away', (tester) async {
    await boot(tester, routes: {'PATCH /profile/notifications': (o) => (status: 200, body: {'web_push_enabled': (o.data as Map)['web_push_enabled']})});

    expect(find.byIcon(Icons.notifications_active_rounded), findsOneWidget);
    await tester.tap(find.byKey(const Key('push-toggle')));
    await tester.pumpAndSettle();

    expect((adapter.requests.lastWhere((r) => r.path == '/profile/notifications').data as Map)['web_push_enabled'], false);
    expect(find.byIcon(Icons.notifications_off_rounded), findsOneWidget);
    expect(find.text('Push notifications are off.'), findsOneWidget);
  });

  testWidgets('if the server refuses, the bell goes back', (tester) async {
    await boot(tester, routes: {'PATCH /profile/notifications': (_) => (status: 500, body: {'message': 'down'})});

    await tester.tap(find.byKey(const Key('push-toggle')));
    await tester.pumpAndSettle();

    expect(find.byIcon(Icons.notifications_active_rounded), findsOneWidget);
  });

  testWidgets('a designer adds a vertical and a skill and saves both', (tester) async {
    await boot(tester, routes: {'PATCH /profile/verticals': (_) => (status: 200, body: {...profileJson(verticals: ['outdoor', 'signage'], skills: ['Photoshop', 'Figma']), 'message': 'Profile details updated successfully.'})});

    await tester.ensureVisible(find.byKey(const Key('vertical-add')));
    await tester.tap(find.byKey(const Key('vertical-add')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Signage').last);
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('vertical-signage')), findsOneWidget);

    await tester.enterText(find.byKey(const Key('skill-input')), 'Figma');
    await tester.tap(find.byKey(const Key('skill-add')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('skill-Figma')), findsOneWidget);

    await tester.ensureVisible(find.byKey(const Key('save-verticals')));
    await tester.tap(find.byKey(const Key('save-verticals')));
    await tester.pumpAndSettle();

    final sent = adapter.requests.lastWhere((r) => r.path == '/profile/verticals').data as Map;
    expect(sent['experienced_verticals'], ['outdoor', 'signage']);
    expect(sent['skills'], ['Photoshop', 'Figma']);
  });

  testWidgets('roles without verticals do not get that section; if the profile cannot load, the saved login still shows', (tester) async {
    await boot(tester, role: 'admin', routes: {'GET /profile': (_) => (status: 500, body: {'message': 'down'})});

    expect(find.byKey(const Key('vertical-add')), findsNothing);
    expect(find.byKey(const Key('profile-retry')), findsOneWidget);
    expect(text(tester, 'profile-email'), 't@example.test');
    expect(find.byKey(const Key('sign-out')), findsOneWidget);
  });
}
