import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/core/uploads.dart';
import 'package:adinn_tasks/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

class _File implements UploadSource {
  _File(this.name);

  @override
  final String name;

  @override
  Future<int> length() async => 10;

  @override
  Stream<List<int>> read(int start, int end) => Stream.value(List.filled(end - start, 1));
}

class _Picker implements FilePickerService {
  List<UploadSource> next = [_File('brief.pdf')];

  @override
  Future<List<UploadSource>> pick({bool multiple = true}) async => next;
}

// The web form's own definition: [name, label, type, required, options].
List<dynamic> _outdoorMockup() => [
      ['__section_details', 'Outdoor / Board Details', 'section', false, []],
      ['outdoor_type', 'Outdoor Type', 'select', true, ['Bus Shelter', 'Unipole', 'Other']],
      ['board_details', 'Board / Display Size Details', 'dimensions', true, []],
      ['site_photo', 'Site Photo', 'file', false, []],
      ['__section_company', 'Company Details', 'section', false, []],
      ['company_details', 'Company Details', 'textarea', false, []],
      ['website_link', 'Website Link', 'url', false, []],
      ['__section_audio', 'Audio Reference', 'section', false, []],
      ['client_audio', 'Audio Reference', 'audio', false, []],
      ['attachments', 'Attachments', 'files', false, []],
    ];

Map<String, dynamic> _formJson() => {
      'verticals': [
        {'key': 'outdoor', 'label': 'Outdoor'},
        {'key': 'roadshow', 'label': 'Road Show'},
        {'key': 'media', 'label': 'Media'},
        {'key': 'digital_inhouse', 'label': 'Digital / Inhouse'},
      ],
      'natures': {
        'outdoor': [{'key': 'mockup_requirements', 'label': 'Mockup'}],
        'roadshow': [{'key': 'new_creative_design', 'label': 'Own Creative'}],
        'media': [{'key': 'own_creative', 'label': 'Own Creative'}],
        'digital_inhouse': [{'key': 'digital', 'label': 'Digital'}, {'key': 'inhouse', 'label': 'Inhouse'}],
      },
      'inhouse_natures': {'outdoor': [{'key': 'agreement', 'label': 'Agreement'}]},
      'fields': {},
      'web_forms': {
        'outdoor': {'label': 'Outdoor', 'natures': {'mockup_requirements': {'label': 'Mockup', 'fields': _outdoorMockup()}}},
        'roadshow': {
          'label': 'Road Show',
          'natures': {
            'new_creative_design': {
              'label': 'Own Creative',
              'fields': [
                ['vehicle_type', 'Vehicle Type', 'vehicle_select', true, ['Static Model', 'L-Shape LED']],
                ['location', 'Campaign Location', 'location', false, []],
                ['vehicle_quantity', 'Vehicle Quantity', 'number', false, []],
              ],
            },
          },
        },
        'media': {
          'label': 'Media',
          'natures': {
            'own_creative': {
              'label': 'Own Creative',
              'fields': [
                ['creative_size_details', 'Creative Size Details', 'media_sizes', true, []],
              ],
            },
          },
        },
        'digital_inhouse': {
          'label': 'Digital / Inhouse',
          'natures': {
            'digital': {'label': 'Digital', 'fields': [['brief', 'Brief', 'textarea', true, []]]},
            'inhouse': {'label': 'Inhouse', 'fields': [['brief', 'Brief', 'textarea', true, []]]},
          },
        },
      },
      'vehicle_images': {'Static Model': 'https://x.test/images/roadshow-vehicles/static-model.jpeg'},
      'locations': ['Chennai', 'Coimbatore'],
      'creative_types': ['Image Creative', 'Video Creative'],
      'inhouse_client_name': 'Adinn Advertising Services Ltd',
      'priorities': ['low', 'medium', 'high', 'urgent'],
      'max_due_at': DateTime.now().add(const Duration(days: 9)).toIso8601String(),
      'designers': [
        {'id': 21, 'name': 'Priya', 'experienced_verticals': ['outdoor'], 'skills': ['Illustrator']},
        {'id': 22, 'name': 'Arun'},
      ],
    };

Map<String, dynamic> _cloneJson() => {
      'task': {
        'task_name': 'Old hoarding',
        'vertical': 'outdoor',
        'task_nature': 'mockup_requirements',
        'party_type': 'client',
        'party_name': 'Zenith Motors',
        'contact_person': 'Ravi',
        'mobile_number': '9123456780',
        'priority': 'high',
        'designer_id': 21,
        'total_creatives': 2,
        'zoho_project_number': 'ZOHO-1',
      },
      'requirements': {
        'outdoor_type': 'Unipole',
        'company_details': 'Zenith pvt',
        'dimension_rows': [{'name': 'Board A', 'width': '40', 'height': '20'}],
      },
      'attachments': {
        'site_photo': [{'path': 'x/y/photo.jpg', 'name': 'photo.jpg', 'url': 'https://s/photo.jpg'}],
      },
      'source_task_id': 77,
    };

void main() {
  late FakeAdapter api;
  late _Picker picker;

  Future<void> boot(WidgetTester tester, {Map<String, Handler> routes = const {}}) async {
    tester.view.physicalSize = const Size(900, 3600);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    api = FakeAdapter({
      'GET /me': (_) => (status: 200, body: {'user': userJson(role: 'bd')}),
      'GET /notifications': (_) => (status: 200, body: {'unread_count': 0, 'data': []}),
      'GET /bd/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': []}),
      'GET /bd/dashboard': (_) => (status: 200, body: emptyBdDashboardJson()),
      'GET /bd/task-form': (_) => (status: 200, body: _formJson()),
      'GET /bd/designers/21/availability': (_) => (status: 200, body: {'label': 'Moderate', 'level': 'moderate', 'availability_percent': 45}),
      'POST /bd/tasks': (_) => (status: 201, body: {'id': 55, 'message': 'Task created.'}),
      'POST /bd/drafts': (_) => (status: 201, body: {'id': 56, 'message': 'Saved as draft.'}),
      'GET /bd/tasks/55': (_) => (status: 200, body: {'task': taskJson(id: 55, name: 'Created'), 'progress': {'completed': 0, 'total': 3, 'remaining': 3, 'percentage': 0}, 'actions': {}, 'history': []}),
      ...routes,
    });
    picker = _Picker();

    await tester.pumpWidget(ProviderScope(
      overrides: [
        sessionStoreProvider.overrideWithValue(MemoryStore(token: 'tok', user: AppUser.fromJson(userJson(role: 'bd')))),
        apiClientProvider.overrideWith((ref) => fakeClient(api, token: () => ref.read(authProvider).token)),
        filePickerProvider.overrideWithValue(picker),
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
    await tester.pump();
  }

  Map<String, dynamic> body(String path) => Map<String, dynamic>.from(api.requests.lastWhere((r) => r.method == 'POST' && r.path == path).data as Map);

  Future<void> tapKey(WidgetTester tester, String key) async {
    await tester.ensureVisible(find.byKey(Key(key)));
    await tester.tap(find.byKey(Key(key)));
    await tester.pumpAndSettle();
  }

  testWidgets('shows the web form: its sections, optional fields and uploads', (tester) async {
    await boot(tester);
    await choose(tester, 'vertical', 'Outdoor');
    await choose(tester, 'task_nature', 'Mockup');

    expect(find.text('OUTDOOR / BOARD DETAILS'), findsOneWidget);
    expect(find.text('COMPANY DETAILS'), findsOneWidget);
    expect(find.text('AUDIO REFERENCE'), findsOneWidget);
    expect(field('company_details'), findsOneWidget); // optional field, not only the required ones
    expect(field('website_link'), findsOneWidget);
    expect(find.byKey(const Key('pick-site_photo')), findsOneWidget);
    expect(find.byKey(const Key('pick-client_audio')), findsOneWidget);
    expect(find.byKey(const Key('pick-attachments')), findsOneWidget);
    expect(find.text('Outdoor Type *'), findsOneWidget);
  });

  testWidgets('Other needs a specification; a bad link, and non-audio in the audio field, are refused', (tester) async {
    await boot(tester);
    await choose(tester, 'vertical', 'Outdoor');
    await choose(tester, 'task_nature', 'Mockup');

    await choose(tester, 'outdoor_type', 'Other');
    expect(field('outdoor_type_other'), findsOneWidget);

    await type(tester, 'website_link', 'not a link');
    picker.next = [_File('notes.txt')];
    await tapKey(tester, 'pick-client_audio');
    expect(find.textContaining('Only MP3 or WAV'), findsOneWidget);
    expect(find.text('notes.txt'), findsNothing);

    picker.next = [_File('call.mp3')];
    await tapKey(tester, 'pick-client_audio');
    expect(find.text('call.mp3'), findsOneWidget);

    await tapKey(tester, 'create-task');
    expect(find.text('Specify the other option'), findsOneWidget);
    expect(find.textContaining('http://'), findsWidgets);
    expect(api.requests.where((r) => r.method == 'POST'), isEmpty);
  });

  testWidgets('Road Show: the vehicle picture shows and location suggests', (tester) async {
    await boot(tester);
    await choose(tester, 'vertical', 'Road Show');
    await choose(tester, 'task_nature', 'Own Creative');
    expect(find.byKey(const Key('vehicle-preview')), findsNothing);
    await choose(tester, 'vehicle_type', 'Static Model');
    expect(find.byKey(const Key('vehicle-preview')), findsOneWidget);

    await tester.ensureVisible(field('location'));
    await tester.tap(field('location'));
    await tester.pumpAndSettle();
    expect(find.text('Coimbatore'), findsOneWidget);
  });

  testWidgets('Media sizes need Name, Width, Height and Ratio in every row', (tester) async {
    await boot(tester);
    await choose(tester, 'vertical', 'Media');
    await choose(tester, 'task_nature', 'Own Creative');
    expect(find.byKey(const Key('row-media_size_rows-0-ratio')), findsOneWidget);

    await tester.enterText(find.byKey(const Key('row-media_size_rows-0-name')), 'Main');
    await tester.enterText(find.byKey(const Key('row-media_size_rows-0-width')), '16');
    await tapKey(tester, 'create-task');
    expect(find.textContaining('Complete Name, Width, Height, Ratio'), findsOneWidget);
  });

  testWidgets('size rows work out the area as you type', (tester) async {
    await boot(tester);
    await choose(tester, 'vertical', 'Outdoor');
    await choose(tester, 'task_nature', 'Mockup');
    await tester.ensureVisible(find.byKey(const Key('row-dimension_rows-0-width')));
    await tester.enterText(find.byKey(const Key('row-dimension_rows-0-width')), '40');
    await tester.enterText(find.byKey(const Key('row-dimension_rows-0-height')), '20.5');
    await tester.pump();
    expect(find.text('820.00'), findsOneWidget);
    await tester.tap(find.byKey(const Key('add-row-dimension_rows')));
    await tester.pump();
    expect(find.byKey(const Key('row-dimension_rows-1-name')), findsOneWidget);
  });

  testWidgets('Digital / Inhouse: creative type gates the creatives count, Inhouse fills in the client', (tester) async {
    await boot(tester);
    await choose(tester, 'vertical', 'Digital / Inhouse');
    expect(field('creative_type'), findsOneWidget);
    expect(field('total_creatives'), findsNothing);

    await choose(tester, 'creative_type', 'Image Creative');
    expect(field('total_creatives'), findsOneWidget);

    await choose(tester, 'task_nature', 'Inhouse');
    expect(find.widgetWithText(TextField, 'Adinn Advertising Services Ltd'), findsOneWidget);
  });

  testWidgets('clone: search, prefill from the earlier ticket, and its files go along', (tester) async {
    await boot(tester, routes: {
      'GET /bd/tasks/clone-search': (_) => (status: 200, body: [{'id': 77, 'task_id': 'DT-2026-0077', 'task_name': 'Old hoarding', 'party_name': 'Zenith Motors', 'zoho_project_number': 'ZOHO-1'}]),
      'GET /bd/tasks/77/clone-data': (_) => (status: 200, body: _cloneJson()),
    });

    await tester.tap(find.byKey(const Key('clone-toggle')));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('clone-search')), 'Old');
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('clone-hit-77')));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('prefill-label')), findsOneWidget);
    expect(find.widgetWithText(TextField, 'Old hoarding'), findsOneWidget);
    expect(find.widgetWithText(TextField, 'Zenith Motors'), findsOneWidget);
    expect(find.widgetWithText(TextField, 'Zenith pvt'), findsOneWidget);
    expect(find.text('photo.jpg'), findsOneWidget); // the earlier ticket's file, to keep or remove
    expect(find.byKey(const Key('availability')), findsOneWidget);

    // A due date is still the user's choice.
    await tester.ensureVisible(find.byKey(const Key('due-date')));
    await tester.tap(find.byKey(const Key('due-date')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();

    await tapKey(tester, 'create-task');
    final sent = body('/bd/tasks');
    expect(sent['task_name'], 'Old hoarding');
    expect(sent['outdoor_type'], 'Unipole');
    expect(sent['cloned_from_task_id'], 77);
    expect(sent['cloned_source_files_json'], contains('x/y/photo.jpg'));
    expect(sent['dimension_rows'], [{'name': 'Board A', 'width': '40', 'height': '20'}]);
  });

  testWidgets('drafts: list, resume, drop a saved file, and update the same draft', (tester) async {
    await boot(tester, routes: {
      'GET /bd/drafts': (_) => (status: 200, body: {'data': [{'id': 9, 'task_name': 'Half done', 'vertical': 'outdoor', 'task_nature': 'mockup_requirements', 'in_house': false, 'party_name': 'Zenith Motors', 'updated_at': '2026-11-10T10:00:00+00:00'}]}),
      'GET /bd/drafts/9': (_) => (status: 200, body: {..._cloneJson(), 'id': 9, 'in_house': false, 'source_task_id': null}),
    });

    await tester.tap(find.byKey(const Key('drafts-open')));
    await tester.pumpAndSettle();
    expect(find.text('Half done'), findsOneWidget);
    await tester.tap(find.byKey(const Key('draft-9')));
    await tester.pumpAndSettle();

    expect(find.textContaining('Draft: Half done'), findsOneWidget);
    expect(find.text('photo.jpg'), findsOneWidget);
    await tapKey(tester, 'remove-kept-site_photo-photo.jpg');
    expect(find.text('photo.jpg'), findsNothing);

    await tapKey(tester, 'save-draft');
    final sent = body('/bd/drafts');
    expect(sent['draft_id'], 9);
    expect(sent['removed_files_json'], contains('x/y/photo.jpg'));
    expect(sent['task_name'], 'Old hoarding');
  });

  testWidgets('the due time can be changed', (tester) async {
    await boot(tester);
    expect(find.byKey(const Key('due-time')), findsOneWidget);
    expect(find.text('6:00 PM'), findsOneWidget);
  });
}
