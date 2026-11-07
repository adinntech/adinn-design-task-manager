import 'package:adinn_tasks/core/api_client.dart';
import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/core/uploads.dart';
import 'package:adinn_tasks/main.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

class MemorySource implements UploadSource {
  MemorySource(this.name, this.size);

  @override
  final String name;
  final int size;
  final List<(int, int)> reads = [];

  @override
  Future<int> length() async => size;

  @override
  Stream<List<int>> read(int start, int end) {
    reads.add((start, end));
    return Stream.value(List.filled(end - start, 1));
  }
}

class FakePicker implements FilePickerService {
  FakePicker(this.next);

  List<UploadSource> next;

  @override
  Future<List<UploadSource>> pick({bool multiple = true}) async => next;
}

/// API routes for a 3-part upload (part size 4 bytes, file of 10 bytes).
Map<String, Handler> uploadRoutes({int id = 77, int partSize = 4, List<int> already = const [], Handler? complete}) => {
      'POST /uploads/initiate': (o) => (status: 200, body: {'id': id, 'part_size_bytes': partSize, 'status': 'uploading'}),
      'GET /uploads/$id/parts': (_) => (status: 200, body: {'uploaded_bytes': 0, 'part_numbers': already}),
      'GET /uploads/$id/part-url': (o) => (status: 200, body: {'url': 'https://s3.test/part-${o.queryParameters['part_number']}'}),
      'POST /uploads/$id/complete': complete ?? (_) => (status: 200, body: {'id': id, 'status': 'completed'}),
    };

void main() {
  group('validateUploadSet', () {
    test('rejects empty files', () {
      expect(validateUploadSet(UploadPurpose.rework, [(name: 'a.zip', size: 0)]), isNotNull);
    });

    test('a big non-zip is refused, a big zip is fine', () {
      const big = 400 * 1024 * 1024;
      expect(validateUploadSet(UploadPurpose.progressUpdate, [(name: 'a.psd', size: big)]), contains('ZIP'));
      expect(validateUploadSet(UploadPurpose.progressUpdate, [(name: 'a.ZIP', size: big)]), isNull);
      expect(validateUploadSet(UploadPurpose.progressUpdate, [(name: 'a.zip', size: big), (name: 'b.zip', size: 1)]), contains('ZIP'));
    });

    test('chat is limited to 10 files and 350 MB total', () {
      expect(validateUploadSet(UploadPurpose.comment, [for (var i = 0; i < 11; i++) (name: '$i.png', size: 1)]), contains('10 files'));
      expect(validateUploadSet(UploadPurpose.comment, [(name: 'a.mp4', size: 351 * 1024 * 1024)]), contains('350 MB'));
      expect(validateUploadSet(UploadPurpose.comment, [(name: 'a.mp4', size: 349 * 1024 * 1024)]), isNull);
    });
  });

  group('UploadService', () {
    late FakeAdapter api;
    late FakeAdapter storage;

    UploadService service({int attempts = 3}) => UploadService(
          fakeClient(api, token: () => 'secret-token'),
          Dio(BaseOptions())..httpClientAdapter = storage,
          maxPartAttempts: attempts,
          retryDelay: Duration.zero,
        );

    setUp(() {
      storage = FakeAdapter({for (var n = 1; n <= 3; n++) 'PUT https://s3.test/part-$n': (_) => (status: 200, body: null)});
    });

    test('uploads every part in order with the right byte ranges, then completes', () async {
      api = FakeAdapter(uploadRoutes());
      final source = MemorySource('work.zip', 10);
      final progress = <double>[];

      final id = await service().upload(taskId: 5, purpose: UploadPurpose.progressUpdate, source: source, onProgress: progress.add);

      expect(id, 77);
      expect(source.reads, [(0, 4), (4, 8), (8, 10)]);
      expect(storage.requests.map((r) => r.headers[Headers.contentLengthHeader]), [4, 4, 2]);
      expect(api.requests.last.path, '/uploads/77/complete');
      expect(progress.last, 1);
      expect(progress, orderedEquals([...progress]..sort()));

      final initiate = api.requests.first;
      expect((initiate.data as Map)['purpose'], 'progress_update');
      expect((initiate.data as Map)['size_bytes'], 10);
    });

    test('never sends the API bearer token to the storage host', () async {
      api = FakeAdapter(uploadRoutes());

      await service().upload(taskId: 5, purpose: UploadPurpose.comment, source: MemorySource('a.png', 10));

      expect(api.requests.first.headers['Authorization'], 'Bearer secret-token');
      expect(storage.requests, isNotEmpty);
      expect(storage.requests.every((r) => !r.headers.containsKey('Authorization')), isTrue);
    });

    test('resumes: parts the server already has are skipped', () async {
      api = FakeAdapter(uploadRoutes(already: [1, 2]));
      final source = MemorySource('work.zip', 10);

      await service().upload(taskId: 5, purpose: UploadPurpose.rework, source: source);

      expect(source.reads, [(8, 10)]);
      expect(storage.requests.single.path, 'https://s3.test/part-3');
    });

    test('a failed part is retried and then succeeds', () async {
      api = FakeAdapter(uploadRoutes());
      var calls = 0;
      storage = FakeAdapter({
        'PUT https://s3.test/part-1': (_) => ++calls < 3 ? (status: 500, body: null) : (status: 200, body: null),
        'PUT https://s3.test/part-2': (_) => (status: 200, body: null),
        'PUT https://s3.test/part-3': (_) => (status: 200, body: null),
      });

      final id = await service().upload(taskId: 5, purpose: UploadPurpose.comment, source: MemorySource('a.png', 10));

      expect(id, 77);
      expect(calls, 3);
    });

    test('gives up after the attempt limit with a friendly, resumable message', () async {
      api = FakeAdapter(uploadRoutes());
      storage = FakeAdapter({'PUT https://s3.test/part-1': (_) => (status: 503, body: null)});

      await expectLater(
        service(attempts: 2).upload(taskId: 5, purpose: UploadPurpose.comment, source: MemorySource('a.png', 10)),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', contains('resume'))),
      );
      expect(api.requests.where((r) => r.path.endsWith('/complete')), isEmpty);
    });

    test('a refused initiate (validation) surfaces the server message and uploads nothing', () async {
      api = FakeAdapter({
        'POST /uploads/initiate': (_) => (status: 422, body: {'errors': {'size_bytes': ['Too big.']}}),
      });

      await expectLater(
        service().upload(taskId: 5, purpose: UploadPurpose.comment, source: MemorySource('a.png', 10)),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', 'Too big.')),
      );
      expect(storage.requests, isEmpty);
    });

    test('a cancelled upload stops with UploadCancelled and does not complete', () async {
      api = FakeAdapter(uploadRoutes());
      final cancel = CancelToken()..cancel();

      await expectLater(
        service().upload(taskId: 5, purpose: UploadPurpose.comment, source: MemorySource('a.png', 10), cancel: cancel),
        throwsA(isA<UploadCancelled>()),
      );
      expect(api.requests.where((r) => r.path.endsWith('/complete')), isEmpty);
    });
  });

  group('upload UI', () {
    late FakeAdapter api;
    late FakePicker picker;

    Future<void> boot(WidgetTester tester, String role, Map<String, Handler> routes) async {
      tester.view.physicalSize = const Size(800, 2600);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);

      final store = MemoryStore(token: 'tok', user: AppUser.fromJson(userJson(role: role)));
      api = FakeAdapter({
        'GET /me': (_) => (status: 200, body: {'user': userJson(role: role)}),
        'GET /notifications': (_) => (status: 200, body: {'unread_count': 0, 'data': []}),
        ...uploadRoutes(),
        ...routes,
      });
      final storage = FakeAdapter({for (var n = 1; n <= 3; n++) 'PUT https://s3.test/part-$n': (_) => (status: 200, body: null)});
      picker = FakePicker([MemorySource('work.zip', 10)]);

      await tester.pumpWidget(ProviderScope(
        overrides: [
          sessionStoreProvider.overrideWithValue(store),
          apiClientProvider.overrideWith((ref) => fakeClient(api, token: () => ref.read(authProvider).token)),
          uploadServiceProvider.overrideWith((ref) => UploadService(ref.watch(apiClientProvider), Dio()..httpClientAdapter = storage, retryDelay: Duration.zero)),
          filePickerProvider.overrideWithValue(picker),
        ],
        child: const AdinnApp(),
      ));
      await tester.pumpAndSettle();
      if (role == 'designer') await openMyTasks(tester);
    }

    Map<String, Handler> designerRoutes(String status) => {
          'GET /designer/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': [taskJson(status: status)]}),
          'GET /designer/tasks/1': (_) => (status: 200, body: {
                'task': taskJson(status: status),
                'access': {'current': true, 'read_only': false},
                'progress': {'completed': 1, 'total': 3, 'remaining': 2, 'percentage': 33},
                'history': [],
              }),
          'GET /designer/tasks/1/comments': (_) => (status: 200, body: {'data': []}),
          'POST /designer/tasks/1/eod': (_) => (status: 201, body: {'message': 'ok'}),
          'POST /designer/tasks/1/rework': (_) => (status: 201, body: {'message': 'ok'}),
          'POST /designer/tasks/1/comments': (o) => (status: 201, body: {
                'comment': {'id': 1, 'comment': (o.data as Map)['comment'], 'created_at': '2026-11-04T10:00:00+00:00', 'user': {'id': 1, 'name': 'Test User', 'role': 'designer'}, 'attachments': []},
              }),
        };

    testWidgets('EOD: submit is locked until the upload finishes, then posts count + upload id', (tester) async {
      await boot(tester, 'designer', designerRoutes('in_progress'));
      await tester.tap(find.text('Banner'));
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('submit-eod')));
      await tester.pumpAndSettle();

      FilledButton submit() => tester.widget<FilledButton>(find.byKey(const Key('progress-submit')));
      expect(submit().onPressed, isNull); // nothing attached yet
      expect(find.text('Add at least one file'), findsOneWidget);

      await tester.tap(find.byKey(const Key('add-files')));
      await tester.pumpAndSettle();
      expect(find.text('work.zip'), findsOneWidget);
      expect(submit().onPressed, isNotNull);

      // Too many creatives is refused locally (only 2 remain).
      await tester.enterText(find.byKey(const Key('progress-count')), '5');
      await tester.tap(find.byKey(const Key('progress-submit')));
      await tester.pumpAndSettle();
      expect(find.text('You can add at most 2 here.'), findsOneWidget);
      expect(api.requests.where((r) => r.path.endsWith('/eod')), isEmpty);

      await tester.enterText(find.byKey(const Key('progress-count')), '2');
      await tester.tap(find.byKey(const Key('progress-submit')));
      await tester.pumpAndSettle();

      final body = api.requests.lastWhere((r) => r.path == '/designer/tasks/1/eod').data as Map;
      expect(body['completed_count'], 2);
      expect(body['upload_ids'], '77');
    });

    testWidgets('an oversized non-zip is rejected before any upload starts', (tester) async {
      await boot(tester, 'designer', designerRoutes('in_progress'));
      await tester.tap(find.text('Banner'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('submit-eod')));
      await tester.pumpAndSettle();

      picker.next = [MemorySource('layout.psd', 400 * 1024 * 1024)];
      await tester.tap(find.byKey(const Key('add-files')));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('upload-notice')), findsOneWidget);
      expect(find.text('layout.psd'), findsNothing);
      expect(api.requests.where((r) => r.path == '/uploads/initiate'), isEmpty);
    });

    testWidgets('a task in rework offers Submit rework, which posts to the rework endpoint', (tester) async {
      await boot(tester, 'designer', designerRoutes('rework'));
      await tester.tap(find.text('Banner'));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('submit-eod')), findsNothing);
      await tester.tap(find.byKey(const Key('submit-rework')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('add-files')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('progress-count')), '1');
      await tester.tap(find.byKey(const Key('progress-submit')));
      await tester.pumpAndSettle();

      expect((api.requests.lastWhere((r) => r.path == '/designer/tasks/1/rework').data as Map)['upload_ids'], '77');
    });

    testWidgets('no EOD button for tasks that are not in progress', (tester) async {
      await boot(tester, 'designer', designerRoutes('yet_to_start'));
      await tester.tap(find.text('Banner'));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('submit-eod')), findsNothing);
      expect(find.byKey(const Key('submit-rework')), findsNothing);
    });

    testWidgets('chat: attachments upload first, then the message carries their ids', (tester) async {
      await boot(tester, 'designer', designerRoutes('in_progress'));
      await tester.tap(find.text('Banner'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('open-chat')));
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('add-files')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('chat-input')), 'See attached');
      await tester.tap(find.byKey(const Key('chat-send')));
      await tester.pumpAndSettle();

      final body = api.requests.lastWhere((r) => r.path == '/designer/tasks/1/comments' && r.method == 'POST').data as Map;
      expect(body['comment'], 'See attached');
      expect(body['upload_ids'], '77');
      // The tray is cleared after sending.
      expect(find.text('work.zip'), findsNothing);
    });

    testWidgets('designer head chat has no attach button', (tester) async {
      await boot(tester, 'designer_head', {
        'GET /designer-head/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': [taskJson()]}),
        'GET /designer-head/requests': (_) => (status: 200, body: {'data': [], 'designers': []}),
        'GET /designer-head/tasks/1': (_) => (status: 200, body: {'task': taskJson(), 'progress': {'completed': 0, 'total': 3, 'remaining': 3, 'percentage': 0}, 'history': []}),
        'GET /designer-head/tasks/1/comments': (_) => (status: 200, body: {'data': []}),
      });
      await tester.tap(find.text('Banner'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('open-chat')));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('add-files')), findsNothing);
      expect(find.byKey(const Key('chat-input')), findsOneWidget);
    });
  });
}
