import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

Map<String, dynamic> _formJson() => {
      'verticals': [{'key': 'outdoor', 'label': 'Outdoor'}, {'key': 'signage', 'label': 'Signage'}],
      'natures': {
        'outdoor': [{'key': 'creative_adaptation', 'label': 'Creative Adaptation'}],
        'signage': [{'key': 'mockup', 'label': 'Mockup'}],
      },
      'inhouse_natures': {
        'signage': [{'key': 'agreement', 'label': 'Agreement'}],
      },
      'fields': {
        'outdoor': {
          'creative_adaptation': [
            {'key': 'description', 'label': 'Description', 'type': 'multiline', 'required': true},
            {'key': 'outdoor_type', 'label': 'Outdoor Type', 'type': 'choice', 'required': true, 'options': ['Unipole', 'Bus Shelter']},
          ],
        },
        'signage': {
          'mockup': [{'key': 'description', 'label': 'Description', 'type': 'multiline', 'required': true}],
        },
      },
      'priorities': ['low', 'medium', 'high', 'urgent'],
      'max_due_at': DateTime.now().add(const Duration(days: 9)).toIso8601String(),
      // A Designer picks the BD who approves: the server sends `bds`, not `designers`.
      'bds': [
        {'id': 31, 'name': 'Meena BD', 'experienced_verticals': ['outdoor']},
        {'id': 32, 'name': 'Karthik BD'},
      ],
    };

void main() {
  late FakeAdapter api;

  Future<void> boot(WidgetTester tester) async {
    tester.view.physicalSize = const Size(900, 3200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    api = FakeAdapter({
      'GET /me': (_) => (status: 200, body: {'user': userJson(role: 'designer')}),
      'GET /notifications': (_) => (status: 200, body: {'unread_count': 0, 'data': []}),
      'GET /designer/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': []}),
      'GET /designer/task-form': (_) => (status: 200, body: _formJson()),
      'POST /designer/tasks': (_) => (status: 201, body: {'id': 55, 'message': 'Task submitted. Waiting for BD confirmation.'}),
      'POST /designer/tasks/in-house': (_) => (status: 201, body: {'id': 56, 'message': 'Task submitted. Waiting for BD confirmation.'}),
    });

    await tester.pumpWidget(ProviderScope(
      overrides: [
        sessionStoreProvider.overrideWithValue(MemoryStore(token: 'tok', user: AppUser.fromJson(userJson(role: 'designer')))),
        apiClientProvider.overrideWith((ref) => fakeClient(api, token: () => ref.read(authProvider).token)),
      ],
      child: const AdinnApp(),
    ));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('new-task')));
    await tester.pumpAndSettle();
  }

  Finder field(String key) => find.byWidgetPredicate((w) => w.key is ValueKey<String> && (w.key! as ValueKey<String>).value.startsWith('f-$key'));

  Future<void> choose(WidgetTester tester, String key, String label) async {
    await tester.ensureVisible(field(key));
    await tester.tap(field(key));
    await tester.pumpAndSettle();
    await tester.tap(find.text(label).last);
    await tester.pumpAndSettle();
  }

  Future<void> type(WidgetTester tester, String key, String text) async {
    await tester.ensureVisible(field(key));
    await tester.enterText(field(key), text);
  }

  Map<String, dynamic> body(String path) => Map<String, dynamic>.from(api.requests.lastWhere((r) => r.method == 'POST' && r.path == path).data as Map);

  Future<void> pickDue(WidgetTester tester) async {
    await tester.ensureVisible(find.byKey(const Key('due-date')));
    await tester.tap(find.byKey(const Key('due-date')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
  }

  testWidgets('a Designer sees the BD form: Assigned BD instead of a designer, no clone, no drafts', (tester) async {
    await boot(tester);

    expect(field('bd_id'), findsOneWidget);
    expect(find.byKey(const Key('bd-approval-note')), findsOneWidget);
    expect(field('designer_id'), findsNothing);
    expect(find.byKey(const Key('clone-toggle')), findsNothing);
    expect(find.byKey(const Key('drafts-open')), findsNothing);
    expect(find.byKey(const Key('save-draft')), findsNothing);
    expect(find.text('Submit for approval'), findsOneWidget);
    expect(find.text('Create task'), findsNothing);
  });

  testWidgets('a client task goes to the Designer endpoint with the chosen BD, assigned to the designer themself', (tester) async {
    await boot(tester);

    await type(tester, 'task_name', 'Station hoarding');
    await choose(tester, 'vertical', 'Outdoor');
    await choose(tester, 'task_nature', 'Creative Adaptation');
    await type(tester, 'party_name', 'Acme Foods');
    await pickDue(tester);
    await choose(tester, 'bd_id', 'Meena BD');
    await type(tester, 'total_creatives', '2');
    await type(tester, 'description', 'Adapt the festive creative');
    await choose(tester, 'outdoor_type', 'Unipole');

    await tester.ensureVisible(find.byKey(const Key('create-task')));
    await tester.tap(find.byKey(const Key('create-task')));
    await tester.pumpAndSettle();

    final sent = body('/designer/tasks');
    expect(sent['bd_id'], 31);
    expect(sent.containsKey('designer_id'), isFalse);
    expect(sent['task_name'], 'Station hoarding');
    expect(sent['vertical'], 'outdoor');
    expect(sent['party_name'], 'Acme Foods');
    expect(sent['total_creatives'], 2);
    expect(sent['outdoor_type'], 'Unipole');
    expect(api.requests.where((r) => r.method == 'POST' && r.path == '/bd/tasks'), isEmpty);
  });

  testWidgets('a task cannot be submitted without choosing the BD', (tester) async {
    await boot(tester);

    await type(tester, 'task_name', 'Station hoarding');
    await choose(tester, 'vertical', 'Outdoor');
    await choose(tester, 'task_nature', 'Creative Adaptation');
    await type(tester, 'party_name', 'Acme Foods');
    await pickDue(tester);
    await type(tester, 'total_creatives', '2');
    await type(tester, 'description', 'x');
    await choose(tester, 'outdoor_type', 'Unipole');

    await tester.ensureVisible(find.byKey(const Key('create-task')));
    await tester.tap(find.byKey(const Key('create-task')));
    await tester.pumpAndSettle();

    expect(find.text('Choose the BD who will approve it'), findsOneWidget);
    expect(api.requests.where((r) => r.method == 'POST'), isEmpty);
  });

  testWidgets('an in-house task goes to the Designer in-house endpoint with the BD', (tester) async {
    await boot(tester);

    await tester.tap(find.text('In-house'));
    await tester.pumpAndSettle();
    await type(tester, 'task_name', 'Office poster');
    await choose(tester, 'vertical', 'Signage');
    await choose(tester, 'task_nature', 'Agreement');
    await choose(tester, 'bd_id', 'Karthik BD');
    await type(tester, 'total_creatives', '1');
    await type(tester, 'description', 'For reception');

    await tester.ensureVisible(find.byKey(const Key('create-task')));
    await tester.tap(find.byKey(const Key('create-task')));
    await tester.pumpAndSettle();

    final sent = body('/designer/tasks/in-house');
    expect(sent['bd_id'], 32);
    expect(sent['description'], 'For reception');
    expect(sent.containsKey('designer_id'), isFalse);
    expect(sent.containsKey('party_name'), isFalse);
  });
}
