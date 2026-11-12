import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

/// End-to-end widget flows against a fake API: login → board → detail → move → chat → sign out.
void main() {
  late FakeAdapter adapter;
  late MemoryStore store;
  var status = 'yet_to_start';
  final messages = <Map<String, dynamic>>[];

  setUp(() {
    status = 'yet_to_start';
    messages.clear();
    store = MemoryStore();
    adapter = FakeAdapter({
      'POST /login': (o) {
        final d = o.data as Map;
        return d['password'] == 'secret'
            ? (status: 200, body: {'token': 'tok', 'user': userJson()})
            : (status: 422, body: {'message': 'x', 'errors': {'login': ['The provided credentials are incorrect.']}});
      },
      'POST /logout': (_) => (status: 200, body: {'message': 'ok'}),
      'GET /me': (_) => (status: 200, body: {'user': userJson()}),
      'GET /designer/tasks': (_) => (status: 200, body: {
            'statuses': [{'key': 'yet_to_start', 'label': 'Yet to start'}, {'key': 'in_progress', 'label': 'In Progress'}],
            'tasks': [taskJson(status: status)],
          }),
      'GET /designer/tasks/1': (_) => (status: 200, body: {
            'task': taskJson(status: status)..['contact_person'] = 'Jo',
            'access': {'current': true, 'read_only': false, 'reason': null},
            'progress': {'completed': 1, 'total': 3, 'remaining': 2, 'percentage': 33},
            'next_status': status == 'yet_to_start' ? 'in_progress' : null,
            'history': [],
            'requests': [],
          }),
      'POST /designer/tasks/1/move': (o) {
        status = (o.data as Map)['status'] as String;
        return (status: 200, body: {'task': taskJson(status: status)});
      },
      'GET /designer/tasks/1/comments': (_) => (status: 200, body: {'data': messages}),
      'POST /designer/tasks/1/comments': (o) {
        final m = {
          'id': messages.length + 1,
          'comment': (o.data as Map)['comment'],
          'created_at': '2026-11-04T10:00:00+00:00',
          'user': {'id': 1, 'name': 'Test User', 'role': 'designer'},
          'attachments': [],
        };
        messages.add(m);
        return (status: 201, body: {'comment': m});
      },
      'GET /notifications': (_) => (status: 200, body: {'unread_count': 2, 'data': []}),
    });
  });

  Widget app() => ProviderScope(
        overrides: [
          sessionStoreProvider.overrideWithValue(store),
          apiClientProvider.overrideWith((ref) => fakeClient(adapter,
              token: () => ref.read(authProvider).token, onUnauthorized: () => ref.read(authProvider.notifier).signOutLocally())),
        ],
        child: const AdinnApp(),
      );

  testWidgets('shows the login screen when signed out, with an error for bad credentials', (tester) async {
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();

    expect(find.text('Sign in'), findsOneWidget);

    await tester.enterText(find.byType(TextFormField).at(0), 'me@x.test');
    await tester.enterText(find.byType(TextFormField).at(1), 'wrong');
    await tester.tap(find.text('Sign in'));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('login-error')), findsOneWidget);
    expect(find.text('The provided credentials are incorrect.'), findsOneWidget);
    expect(store.token, isNull);
  });

  testWidgets('forgot password: login link → OTP request → reset with the OTP → back on sign in', (tester) async {
    tester.view.physicalSize = const Size(800, 2000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    var otpOk = false;
    adapter.routes['POST /password/forgot'] = (_) => (status: 200, body: {'message': 'If these details match an account, a 6-digit OTP has been sent to its registered email.'});
    adapter.routes['POST /password/reset'] = (o) {
      final d = o.data as Map;
      if (d['otp'] != '482913') {
        return (status: 422, body: {'message': 'x', 'errors': {'otp': ['Incorrect OTP. Check the mail and try again.']}});
      }
      otpOk = true;
      return (status: 200, body: {'message': 'Password reset successfully.'});
    };

    await tester.pumpWidget(app());
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('forgot-password-link')));
    await tester.pumpAndSettle();
    expect(find.text('Reset your password'), findsOneWidget);

    // Empty identifier is refused locally.
    await tester.tap(find.byKey(const Key('forgot-send')));
    await tester.pumpAndSettle();
    expect(find.text('Enter your username, email or employee code'), findsOneWidget);
    expect(adapter.requests.where((r) => r.path == '/password/forgot'), isEmpty);

    await tester.enterText(find.byKey(const Key('forgot-login')), '1761');
    await tester.tap(find.byKey(const Key('forgot-send')));
    await tester.pumpAndSettle();
    expect((adapter.requests.lastWhere((r) => r.path == '/password/forgot').data as Map)['login'], '1761');
    expect(find.text('Check your email'), findsOneWidget);
    expect(find.byKey(const Key('forgot-info')), findsOneWidget);

    // Wrong OTP stays on the page and shows the server's message under the OTP box.
    await tester.enterText(find.byKey(const Key('forgot-otp')), '111111');
    await tester.enterText(find.byKey(const Key('forgot-password')), 'NewPass@123');
    await tester.enterText(find.byKey(const Key('forgot-confirm')), 'NewPass@123');
    await tester.tap(find.byKey(const Key('forgot-reset')));
    await tester.pumpAndSettle();
    expect(find.text('Incorrect OTP. Check the mail and try again.'), findsOneWidget);
    expect(otpOk, isFalse);

    await tester.enterText(find.byKey(const Key('forgot-otp')), '482913');
    await tester.tap(find.byKey(const Key('forgot-reset')));
    await tester.pumpAndSettle();

    final sent = adapter.requests.lastWhere((r) => r.path == '/password/reset').data as Map;
    expect(sent, {'login': '1761', 'otp': '482913', 'password': 'NewPass@123', 'password_confirmation': 'NewPass@123'});
    expect(otpOk, isTrue);
    expect(find.text('Sign in'), findsOneWidget); // back on the login screen
    expect(find.text('Password reset successfully. Sign in with your new password.'), findsOneWidget);
  });

  testWidgets('forgot password: mismatched confirmation is refused without calling the API', (tester) async {
    tester.view.physicalSize = const Size(800, 2000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    adapter.routes['POST /password/forgot'] = (_) => (status: 200, body: {'message': 'sent'});

    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('forgot-password-link')));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('forgot-login')), 'me@x.test');
    await tester.tap(find.byKey(const Key('forgot-send')));
    await tester.pumpAndSettle();

    await tester.enterText(find.byKey(const Key('forgot-otp')), '482913');
    await tester.enterText(find.byKey(const Key('forgot-password')), 'NewPass@123');
    await tester.enterText(find.byKey(const Key('forgot-confirm')), 'Different@123');
    await tester.tap(find.byKey(const Key('forgot-reset')));
    await tester.pumpAndSettle();

    expect(find.text('Passwords do not match'), findsOneWidget);
    expect(adapter.requests.where((r) => r.path == '/password/reset'), isEmpty);
  });

  testWidgets('validates empty fields without calling the API', (tester) async {
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();

    await tester.tap(find.text('Sign in'));
    await tester.pumpAndSettle();

    expect(find.text('Enter your email, username or employee code'), findsOneWidget);
    expect(adapter.requests.where((r) => r.path == '/login'), isEmpty);
  });

  testWidgets('login → board → open task → move to next status → chat → sign out', (tester) async {
    // A tall screen so the detail list builds the move button without scrolling.
    tester.view.physicalSize = const Size(800, 2400);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(app());
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextFormField).at(0), 'me@x.test');
    await tester.enterText(find.byType(TextFormField).at(1), 'secret');
    await tester.tap(find.text('Sign in'));
    await tester.pumpAndSettle();

    // Board (a designer lands on the Dashboard first).
    await openMyTasks(tester);
    expect(find.text('My Tasks'), findsWidgets);
    expect(find.text('DT-2026-0001'), findsOneWidget);
    expect(find.text('Banner'), findsOneWidget);
    expect(store.token, 'tok');

    // Detail + status move.
    await tester.tap(find.text('Banner'));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('move-next')), findsOneWidget);

    await tester.tap(find.byKey(const Key('move-next')));
    await tester.pumpAndSettle();
    expect(status, 'in_progress');
    expect(find.byKey(const Key('move-next')), findsNothing);

    // Let the "Moved to …" snackbar expire so it doesn't cover the chat input.
    await tester.pump(const Duration(seconds: 6));
    await tester.pumpAndSettle();

    // Chat.
    await tester.tap(find.byKey(const Key('open-chat')));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('chat-input')), 'Hello BD');
    await tester.pump(); // with text in the field the mic becomes the send button
    await tester.tap(find.byKey(const Key('chat-send')));
    await tester.pumpAndSettle();
    expect(find.text('Hello BD'), findsOneWidget);

    // Back out of the chat, the task and the stage, to the shell, and sign out.
    for (var i = 0; i < 3; i++) {
      await tester.pageBack();
      await tester.pumpAndSettle();
    }
    await tester.tap(find.text('Profile'));
    await tester.pumpAndSettle();
    expect(find.textContaining('t@example.test'), findsOneWidget);

    await tester.tap(find.byKey(const Key('sign-out')));
    await tester.pumpAndSettle();
    expect(find.text('Sign in'), findsOneWidget);
    expect(store.token, isNull);
  });

  testWidgets('a saved session skips the login screen', (tester) async {
    store = MemoryStore(token: 'tok', user: AppUser.fromJson(userJson()));

    await tester.pumpWidget(app());
    await tester.pumpAndSettle();

    expect(find.text('Sign in'), findsNothing);
    expect(find.text('Designer Dashboard'), findsWidgets);
    await openMyTasks(tester);
    expect(find.text('DT-2026-0001'), findsOneWidget);
  });
}
