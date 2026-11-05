import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/core/uploads.dart';
import 'package:adinn_tasks/main.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

class _File implements UploadSource {
  _File(this.name, this.size);

  @override
  final String name;
  final int size;

  @override
  Future<int> length() async => size;

  @override
  Stream<List<int>> read(int start, int end) => Stream.value(List.filled(end - start, 7));
}

class _Picker implements FilePickerService {
  _Picker(this.next);

  List<UploadSource> next;

  @override
  Future<List<UploadSource>> pick({bool multiple = true}) async => next;
}

Map<String, dynamic> _formJson() => {
      'verticals': [{'key': 'outdoor', 'label': 'Outdoor'}, {'key': 'signage', 'label': 'Signage'}],
      'natures': {
        'outdoor': [{'key': 'creative_adaptation', 'label': 'Creative Adaptation'}, {'key': 'cutout_size_calculation', 'label': 'Cutout Size Calculation'}],
        'signage': [{'key': 'mockup', 'label': 'Mockup'}],
      },
      'inhouse_natures': {
        'signage': [{'key': 'agreement', 'label': 'Agreement'}, {'key': 'others', 'label': 'Others'}],
      },
      'fields': {
        'outdoor': {
          'creative_adaptation': [
            {'key': 'description', 'label': 'Description', 'type': 'multiline', 'required': true},
            {'key': 'outdoor_type', 'label': 'Outdoor Type', 'type': 'choice', 'required': true, 'options': ['Unipole', 'Bus Shelter']},
            {'key': 'board_type', 'label': 'Board Type', 'type': 'choice', 'required': true, 'options': ['Static', 'Digital']},
            {'key': 'dimension_rows', 'label': 'Dimension Rows', 'type': 'rows', 'required': true, 'columns': ['name', 'width', 'height'], 'or_upload': 'dimension_upload'},
            {'key': 'other_details', 'label': 'Other Details', 'type': 'multiline', 'required': false},
          ],
          'cutout_size_calculation': [
            {'key': 'description', 'label': 'Description', 'type': 'multiline', 'required': true},
            {'key': 'hoarding_artwork', 'label': 'Hoarding Artwork', 'type': 'file', 'required': true},
            {'key': 'outdoor_type', 'label': 'Outdoor Type', 'type': 'choice', 'required': true, 'options': ['Unipole']},
            {'key': 'dimension_rows', 'label': 'Dimension Rows', 'type': 'rows', 'required': true, 'columns': ['name', 'width', 'height'], 'or_upload': 'dimension_upload'},
          ],
        },
        'signage': {
          'mockup': [{'key': 'description', 'label': 'Description', 'type': 'multiline', 'required': true}],
        },
      },
      'priorities': ['low', 'medium', 'high', 'urgent'],
      'max_due_at': DateTime.now().add(const Duration(days: 9)).toIso8601String(),
      'designers': [{'id': 21, 'name': 'Priya'}, {'id': 22, 'name': 'Arun'}],
    };

void main() {
  late FakeAdapter api;
  late _Picker picker;

  Future<void> boot(WidgetTester tester, {String role = 'bd', Map<String, Handler> routes = const {}}) async {
    tester.view.physicalSize = const Size(900, 3200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    api = FakeAdapter({
      'GET /me': (_) => (status: 200, body: {'user': userJson(role: role)}),
      'GET /notifications': (_) => (status: 200, body: {'unread_count': 0, 'data': []}),
      'GET /bd/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': []}),
      'GET /bd/task-form': (_) => (status: 200, body: _formJson()),
      'GET /bd/tasks/55': (_) => (status: 200, body: {
            'task': taskJson(id: 55, name: 'Created task'),
            'progress': {'completed': 0, 'total': 3, 'remaining': 3, 'percentage': 0},
            'actions': {},
            'history': [],
          }),
      'GET /bd/designers/21/availability': (_) => (status: 200, body: {'label': 'Moderate', 'level': 'moderate', 'availability_percent': 45}),
      'GET /bd/clients/search': (o) => (status: 200, body: [{'id': 1, 'client_name': 'Zenith Motors', 'contact_person_name': 'Ravi', 'mobile_number': '9123456780'}]),
      'POST /bd/tasks': (_) => (status: 201, body: {'id': 55, 'message': 'Task created.'}),
      'POST /bd/tasks/in-house': (_) => (status: 201, body: {'id': 55, 'message': 'Task created.'}),
      'POST /bd/drafts': (_) => (status: 201, body: {'id': 56, 'message': 'Saved as draft.'}),
      ...routes,
    });
    picker = _Picker([_File('brief.pdf', 1024)]);

    await tester.pumpWidget(ProviderScope(
      overrides: [
        sessionStoreProvider.overrideWithValue(MemoryStore(token: 'tok', user: AppUser.fromJson(userJson(role: role)))),
        apiClientProvider.overrideWith((ref) => fakeClient(api, token: () => ref.read(authProvider).token)),
        filePickerProvider.overrideWithValue(picker),
      ],
      child: const AdinnApp(),
    ));
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

  Future<void> openForm(WidgetTester tester) async {
    await tester.tap(find.byKey(const Key('new-task')));
    await tester.pumpAndSettle();
  }

  Future<void> fillCommon(WidgetTester tester) async {
    await type(tester, 'task_name', 'Highway hoarding');
    await choose(tester, 'vertical', 'Outdoor');
    await choose(tester, 'task_nature', 'Creative Adaptation');
    await type(tester, 'party_name', 'Acme Foods');
    await type(tester, 'mobile_number', '9876543210');
    await tester.ensureVisible(find.byKey(const Key('due-date')));
    await tester.tap(find.byKey(const Key('due-date')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
    await choose(tester, 'designer_id', 'Priya');
    await type(tester, 'total_creatives', '3');
  }

  Map<String, dynamic> body(String path) => Map<String, dynamic>.from(api.requests.lastWhere((r) => r.method == 'POST' && r.path == path).data as Map);

  testWidgets('only BD gets the New task button', (tester) async {
    await boot(tester, role: 'designer', routes: {'GET /designer/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': []})});
    expect(find.byKey(const Key('new-task')), findsNothing);
  });

  testWidgets('creates a client task: server-defined fields, rows, and a JSON body', (tester) async {
    await boot(tester);
    await openForm(tester);

    // Fields appear only once a nature is chosen.
    expect(find.text('Outdoor Type *'), findsNothing);
    await fillCommon(tester);
    expect(find.text('Outdoor Type *'), findsOneWidget);

    await type(tester, 'description', 'Adapt the festive creative');
    await choose(tester, 'outdoor_type', 'Unipole');
    await choose(tester, 'board_type', 'Static');
    await tester.ensureVisible(find.byKey(const Key('row-dimension_rows-0-name')));
    await tester.enterText(find.byKey(const Key('row-dimension_rows-0-name')), 'Board A');
    await tester.enterText(find.byKey(const Key('row-dimension_rows-0-width')), '40');
    await tester.enterText(find.byKey(const Key('row-dimension_rows-0-height')), '20');

    await tester.ensureVisible(find.byKey(const Key('create-task')));
    await tester.tap(find.byKey(const Key('create-task')));
    await tester.pumpAndSettle();

    final sent = body('/bd/tasks');
    expect(sent['task_name'], 'Highway hoarding');
    expect(sent['vertical'], 'outdoor');
    expect(sent['task_nature'], 'creative_adaptation');
    expect(sent['party_name'], 'Acme Foods');
    expect(sent['designer_id'], 21);
    expect(sent['total_creatives'], 3);
    expect(sent['outdoor_type'], 'Unipole');
    expect(sent['description'], 'Adapt the festive creative');
    expect(sent['dimension_rows'], [{'name': 'Board A', 'width': '40', 'height': '20'}]);
    expect(RegExp(r'^\d{4}-\d{2}-\d{2} 18:00:00$').hasMatch(sent['due_at'] as String), isTrue);

    // Lands on the new task.
    expect(find.text('Created task'), findsWidgets);
  });

  testWidgets('required fields are checked before sending anything', (tester) async {
    await boot(tester);
    await openForm(tester);
    await choose(tester, 'vertical', 'Outdoor');
    await choose(tester, 'task_nature', 'Creative Adaptation');

    await tester.ensureVisible(find.byKey(const Key('create-task')));
    await tester.tap(find.byKey(const Key('create-task')));
    await tester.pumpAndSettle();

    expect(find.text('Task name is required'), findsOneWidget);
    expect(find.text('Client name is required'), findsOneWidget);
    expect(find.text('Choose a designer'), findsOneWidget);
    expect(find.text('Choose a due date'), findsOneWidget);
    expect(find.text('Description is required'), findsOneWidget);
    expect(find.text('Outdoor Type is required'), findsOneWidget);
    expect(find.textContaining('Add at least one complete row'), findsOneWidget);
    expect(api.requests.where((r) => r.method == 'POST'), isEmpty);
  });

  testWidgets("the server's field errors are shown next to the fields", (tester) async {
    await boot(tester, routes: {
      'POST /bd/tasks': (_) => (status: 422, body: {
            'message': 'The given data was invalid.',
            'errors': {'mobile_number': ['The mobile number must be 10 digits.'], 'dimension_rows': ['Complete Name, Width and Height for every Board Details row.']},
          }),
    });
    await openForm(tester);
    await fillCommon(tester);
    await type(tester, 'description', 'x');
    await choose(tester, 'outdoor_type', 'Unipole');
    await choose(tester, 'board_type', 'Static');
    await tester.enterText(find.byKey(const Key('row-dimension_rows-0-name')), 'A');
    await tester.enterText(find.byKey(const Key('row-dimension_rows-0-width')), '1');
    await tester.enterText(find.byKey(const Key('row-dimension_rows-0-height')), '1');

    await tester.ensureVisible(find.byKey(const Key('create-task')));
    await tester.tap(find.byKey(const Key('create-task')));
    await tester.pumpAndSettle();

    expect(find.text('The mobile number must be 10 digits.'), findsOneWidget);
    expect(find.text('Complete Name, Width and Height for every Board Details row.'), findsOneWidget);
    expect(find.byKey(const Key('form-banner')), findsOneWidget);
    // Still on the form, ready to correct.
    expect(find.byKey(const Key('create-task')), findsOneWidget);
  });

  testWidgets('a required file blocks submit until attached, then goes as multipart with bracket keys', (tester) async {
    await boot(tester);
    await openForm(tester);
    await type(tester, 'task_name', 'Cutout job');
    await choose(tester, 'vertical', 'Outdoor');
    await choose(tester, 'task_nature', 'Cutout Size Calculation');
    await type(tester, 'party_name', 'Acme');
    await tester.ensureVisible(find.byKey(const Key('due-date')));
    await tester.tap(find.byKey(const Key('due-date')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
    await choose(tester, 'designer_id', 'Priya');
    await type(tester, 'total_creatives', '2');
    await type(tester, 'description', 'Calculate the cutouts');
    await choose(tester, 'outdoor_type', 'Unipole');
    await tester.enterText(find.byKey(const Key('row-dimension_rows-0-name')), 'B1');
    await tester.enterText(find.byKey(const Key('row-dimension_rows-0-width')), '10');
    await tester.enterText(find.byKey(const Key('row-dimension_rows-0-height')), '5');

    await tester.ensureVisible(find.byKey(const Key('create-task')));
    await tester.tap(find.byKey(const Key('create-task')));
    await tester.pumpAndSettle();
    expect(find.text('Hoarding Artwork needs at least one file'), findsOneWidget);
    expect(api.requests.where((r) => r.method == 'POST'), isEmpty);

    await tester.ensureVisible(find.byKey(const Key('pick-hoarding_artwork')));
    await tester.tap(find.byKey(const Key('pick-hoarding_artwork')));
    await tester.pumpAndSettle();
    expect(find.text('brief.pdf'), findsOneWidget);

    await tester.ensureVisible(find.byKey(const Key('create-task')));
    await tester.tap(find.byKey(const Key('create-task')));
    await tester.pumpAndSettle();

    final sent = api.requests.lastWhere((r) => r.method == 'POST' && r.path == '/bd/tasks').data;
    expect(sent, isA<FormData>());
    final form = sent as FormData;
    final fields = {for (final f in form.fields) f.key: f.value};
    expect(fields['dimension_rows[0][name]'], 'B1');
    expect(fields['dimension_rows[0][width]'], '10');
    expect(fields['task_nature'], 'cutout_size_calculation');
    expect(fields['designer_id'], '21');
    expect(form.files.single.key, 'hoarding_artwork[]');
    expect(form.files.single.value.filename, 'brief.pdf');
    expect(form.files.single.value.length, 1024);
  });

  testWidgets('a draft needs only a name, vertical and nature', (tester) async {
    await boot(tester);
    await openForm(tester);

    await tester.tap(find.byKey(const Key('save-draft')));
    await tester.pumpAndSettle();
    expect(find.text('Task name is required'), findsOneWidget);
    expect(find.text('Choose a vertical'), findsOneWidget);
    expect(api.requests.where((r) => r.method == 'POST'), isEmpty);

    await type(tester, 'task_name', 'Later');
    await choose(tester, 'vertical', 'Outdoor');
    await choose(tester, 'task_nature', 'Creative Adaptation');
    await tester.ensureVisible(find.byKey(const Key('save-draft')));
    await tester.tap(find.byKey(const Key('save-draft')));
    await tester.pumpAndSettle();

    final sent = body('/bd/drafts');
    expect(sent['task_name'], 'Later');
    expect(sent.containsKey('designer_id'), isFalse);
    expect(find.byKey(const Key('new-task')), findsOneWidget); // back on the board
  });

  testWidgets('in-house tasks use their own natures, endpoint and fields', (tester) async {
    await boot(tester);
    await openForm(tester);

    await tester.tap(find.text('In-house'));
    await tester.pumpAndSettle();
    expect(field('party_name'), findsNothing);
    expect(find.byKey(const Key('due-date')), findsNothing);

    await type(tester, 'task_name', 'Office poster');
    await choose(tester, 'vertical', 'Signage');
    await choose(tester, 'task_nature', 'Others');
    await type(tester, 'task_nature_other', 'Banner stand');
    await choose(tester, 'designer_id', 'Arun');
    await type(tester, 'total_creatives', '1');
    await type(tester, 'description', 'For reception');

    await tester.ensureVisible(find.byKey(const Key('create-task')));
    await tester.tap(find.byKey(const Key('create-task')));
    await tester.pumpAndSettle();

    final sent = body('/bd/tasks/in-house');
    expect(sent['task_nature'], 'others');
    expect(sent['task_nature_other'], 'Banner stand');
    expect(sent['designer_id'], 22);
    expect(sent['description'], 'For reception');
    expect(sent.containsKey('party_name'), isFalse);
  });

  testWidgets('choosing a designer shows their availability', (tester) async {
    await boot(tester);
    await openForm(tester);

    await choose(tester, 'designer_id', 'Priya');
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('availability')), findsOneWidget);
    expect(find.textContaining('Moderate'), findsOneWidget);
  });

  testWidgets('picking a known client fills in their contact details', (tester) async {
    await boot(tester);
    await openForm(tester);

    await tester.ensureVisible(field('party_name'));
    await tester.enterText(field('party_name'), 'Zen');
    await tester.pumpAndSettle();
    await tester.tap(find.text('Zenith Motors').last);
    await tester.pumpAndSettle();

    expect(find.widgetWithText(TextField, 'Ravi'), findsOneWidget);
    expect(find.widgetWithText(TextField, '9123456780'), findsOneWidget);
  });
}
