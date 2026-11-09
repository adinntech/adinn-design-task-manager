import 'dart:async';

import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/file_access.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/core/uploads.dart';
import 'package:adinn_tasks/core/voice.dart';
import 'package:adinn_tasks/main.dart';
import 'package:adinn_tasks/router.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

class _FakeFiles implements FileAccess {
  final downloads = <String>[];
  final opened = <String>[];

  @override
  Future<String> download(String url, String name) async {
    downloads.add(url);
    return '/tmp/$name';
  }

  @override
  Future<void> open(String path) async => opened.add(path);
}

class _FakeRecorder implements VoiceRecorder {
  bool allowed = true;
  int started = 0;
  int stopped = 0;
  int cancelled = 0;

  @override
  Future<bool> start() async {
    started++;
    return allowed;
  }

  @override
  Future<String?> stop() async {
    stopped++;
    return '/tmp/voice_test.m4a';
  }

  @override
  Future<void> cancel() async => cancelled++;
}

class _FakePlayer implements AudioPlaying {
  static final played = <String>[];
  final _playing = StreamController<bool>.broadcast();

  @override
  Stream<Duration> get onPosition => const Stream.empty();
  @override
  Stream<Duration> get onDuration => const Stream.empty();
  @override
  Stream<bool> get onPlaying => _playing.stream;
  @override
  Stream<void> get onComplete => const Stream.empty();

  @override
  Future<void> play(String url) async {
    played.add(url);
    _playing.add(true);
  }

  @override
  Future<void> resume() async => _playing.add(true);
  @override
  Future<void> pause() async => _playing.add(false);
  @override
  Future<void> seek(Duration position) async {}
  @override
  Future<void> dispose() async => _playing.close();
}

class _FakeUploads extends UploadService {
  _FakeUploads(super.api, super.storage);

  final names = <String>[];

  @override
  Future<int> upload({required int taskId, required UploadPurpose purpose, required UploadSource source, void Function(double progress)? onProgress, CancelToken? cancel}) async {
    names.add('${purpose.wire}:${source.name}');
    return 77;
  }
}

void main() {
  late _FakeFiles files;
  late _FakeRecorder recorder;
  late _FakeUploads uploads;
  late FakeAdapter api;

  Map<String, dynamic> file(String name, String kind, {String? ext}) =>
      {'name': name, 'extension': ext ?? name.split('.').last.toUpperCase(), 'kind': kind, 'url': 'https://cdn.test/$name'};

  Future<void> boot(WidgetTester tester, {Map<String, dynamic>? filesJson, List<Map<String, dynamic>> comments = const [], String role = 'designer'}) async {
    tester.view.physicalSize = const Size(800, 1800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    files = _FakeFiles();
    recorder = _FakeRecorder();
    _FakePlayer.played.clear();

    final prefix = role == 'bd' ? '/bd' : '/designer';
    api = FakeAdapter({
      'GET /me': (_) => (status: 200, body: {'user': userJson(role: role)}),
      'GET /notifications': (_) => (status: 200, body: {'unread_count': 0, 'data': []}),
      'GET $prefix/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': []}),
      'GET $prefix/tasks/1': (_) => (status: 200, body: {
            'task': taskJson(status: 'in_progress'),
            'access': {'current': true, 'read_only': false, 'reason': null},
            'progress': {'completed': 1, 'total': 3, 'remaining': 2, 'percentage': 33},
            'history': [],
            'requests': [],
            'files': ?filesJson,
          }),
      'GET $prefix/tasks/1/comments': (_) => (status: 200, body: {'data': comments}),
      'POST $prefix/tasks/1/comments': (o) => (status: 201, body: {
            'comment': {'id': 99, 'comment': (o.data as Map)['comment'], 'created_at': '2026-11-09T10:00:00+00:00', 'user': {'id': 1, 'name': 'Test User', 'role': role}, 'attachments': []},
          }),
    });

    await tester.pumpWidget(ProviderScope(
      retry: (_, _) => null,
      overrides: [
        sessionStoreProvider.overrideWithValue(MemoryStore(token: 'tok', user: AppUser.fromJson(userJson(role: role)))),
        apiClientProvider.overrideWith((ref) => fakeClient(api, token: () => ref.read(authProvider).token)),
        uploadServiceProvider.overrideWith((ref) => uploads = _FakeUploads(ref.watch(apiClientProvider), Dio())),
        fileAccessProvider.overrideWithValue(files),
        voiceRecorderProvider.overrideWithValue(recorder),
        audioPlayingFactoryProvider.overrideWithValue(_FakePlayer.new),
      ],
      child: const AdinnApp(),
    ));
    await tester.pumpAndSettle();

    ProviderScope.containerOf(tester.element(find.byType(AdinnApp))).read(routerProvider).push('/task/1');
    await tester.pumpAndSettle();
  }

  Future<void> openChat(WidgetTester tester) async {
    await tester.tap(find.byKey(const Key('open-chat')));
    await tester.pumpAndSettle();
  }

  group('Overview files', () {
    final sample = {
      'requirement_files': [
        {
          'key': 'reference_images',
          'label': 'Reference Images',
          'files': [file('look-a.jpg', 'image'), file('look-b.png', 'image'), file('brief.pdf', 'file')],
        },
        {
          'key': 'client_audio',
          'label': 'Client Audio',
          'files': [file('call.m4a', 'audio')],
        },
      ],
      'submissions': [
        {
          'id': 1,
          'update_type': 'progress',
          'completed_count': 2,
          'cumulative_completed': 2,
          'submitted_at': '2026-11-05T10:00:00+00:00',
          'files': [file('round1.zip', 'file')],
        },
      ],
    };

    testWidgets('shows reference files by field and the designer\'s submitted work', (tester) async {
      await boot(tester, filesJson: sample);

      expect(find.byKey(const Key('attachments-card')), findsOneWidget);
      expect(find.text('SUBMITTED WORK'), findsOneWidget);
      expect(find.text('REFERENCE FILES'), findsOneWidget);
      expect(find.text('Reference Images'), findsOneWidget);
      expect(find.text('Client Audio'), findsOneWidget);
      expect(find.byKey(const Key('file-look-a.jpg')), findsOneWidget);
      expect(find.byKey(const Key('file-brief.pdf')), findsOneWidget);
      expect(find.byKey(const Key('file-round1.zip')), findsOneWidget);
      expect(find.textContaining('2 creatives done'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('a document downloads to the phone and opens in another app', (tester) async {
      await boot(tester, filesJson: sample);

      await tester.tap(find.byKey(const Key('file-brief.pdf')));
      await tester.pumpAndSettle();

      expect(files.downloads, ['https://cdn.test/brief.pdf']);
      expect(files.opened, ['/tmp/brief.pdf']);
    });

    testWidgets('an image opens full screen and swipes to the next picture', (tester) async {
      await boot(tester, filesJson: sample);

      await tester.tap(find.byKey(const Key('file-look-a.jpg')));
      await tester.pumpAndSettle();
      expect(find.text('1 of 2'), findsOneWidget);

      await tester.drag(find.byType(PageView), const Offset(-600, 0));
      await tester.pumpAndSettle();
      expect(find.text('2 of 2'), findsOneWidget);

      await tester.tap(find.byKey(const Key('image-download')));
      await tester.pumpAndSettle();
      expect(files.downloads, ['https://cdn.test/look-b.png']);
    });

    testWidgets('an audio reference plays inline', (tester) async {
      await boot(tester, filesJson: sample);

      await tester.tap(find.descendant(of: find.byKey(const Key('file-call.m4a')), matching: find.byKey(const Key('audio-toggle'))));
      await tester.pump();

      expect(_FakePlayer.played, ['https://cdn.test/call.m4a']);
    });

    testWidgets('a task with no files shows no attachments card, and an older server is fine', (tester) async {
      await boot(tester);

      expect(find.byKey(const Key('attachments-card')), findsNothing);
      expect(tester.takeException(), isNull);
    });
  });

  group('Voice notes', () {
    Future<TestGesture> press(WidgetTester tester) async {
      final gesture = await tester.startGesture(tester.getCenter(find.byKey(const Key('chat-mic'))));
      await tester.pump();
      return gesture;
    }

    List<RequestOptions> sent() => api.requests.where((r) => r.method == 'POST' && r.path.endsWith('/comments')).toList();

    testWidgets('hold the mic, release: uploads the recording and posts it as a message', (tester) async {
      await boot(tester);
      await openChat(tester);

      final gesture = await press(tester);
      expect(find.byKey(const Key('recording-bar')), findsOneWidget);
      await tester.pump(const Duration(seconds: 2));
      expect(find.text('0:02'), findsOneWidget);

      await gesture.up();
      await tester.pumpAndSettle();

      expect(recorder.started, 1);
      expect(recorder.stopped, 1);
      expect(uploads.names, ['comment:voice_test.m4a']);
      final body = sent().single.data as Map;
      expect(body['comment'], voiceNoteText);
      expect(body['upload_ids'], '77');
      expect(find.byKey(const Key('recording-bar')), findsNothing);
    });

    testWidgets('sliding left cancels: nothing is uploaded or sent', (tester) async {
      await boot(tester);
      await openChat(tester);

      final gesture = await press(tester);
      await tester.pump(const Duration(seconds: 2));
      await gesture.moveBy(const Offset(-160, 0));
      await tester.pump();
      expect(find.text('Release to cancel'), findsOneWidget);

      await gesture.up();
      await tester.pumpAndSettle();

      expect(recorder.cancelled, 1);
      expect(recorder.stopped, 0);
      expect(uploads.names, isEmpty);
      expect(sent(), isEmpty);
    });

    testWidgets('a quick tap is discarded with a hint', (tester) async {
      await boot(tester);
      await openChat(tester);

      final gesture = await press(tester);
      await tester.pump(const Duration(milliseconds: 500));
      await gesture.up();
      await tester.pumpAndSettle();

      expect(recorder.cancelled, 1);
      expect(sent(), isEmpty);
      expect(find.textContaining('Hold the mic button'), findsOneWidget);
    });

    testWidgets('refused microphone permission says so and records nothing', (tester) async {
      await boot(tester);
      await openChat(tester);
      recorder.allowed = false;

      final gesture = await press(tester);
      await gesture.up();
      await tester.pumpAndSettle();

      expect(find.textContaining('Allow microphone access'), findsOneWidget);
      expect(find.byKey(const Key('recording-bar')), findsNothing);
      expect(sent(), isEmpty);
    });

    testWidgets('typing turns the mic into the send button', (tester) async {
      await boot(tester);
      await openChat(tester);

      expect(find.byKey(const Key('chat-mic')), findsOneWidget);
      expect(find.byKey(const Key('chat-send')), findsNothing);

      await tester.enterText(find.byKey(const Key('chat-input')), 'hello');
      await tester.pump();

      expect(find.byKey(const Key('chat-mic')), findsNothing);
      expect(find.byKey(const Key('chat-send')), findsOneWidget);
    });

    testWidgets('a voice note in the chat is a player, not the placeholder text', (tester) async {
      await boot(tester, comments: [
        {
          'id': 5,
          'comment': voiceNoteText,
          'created_at': '2026-11-09T09:00:00+00:00',
          'user': {'id': 2, 'name': 'Bela BD', 'role': 'bd'},
          'attachments': [
            {'id': 1, 'name': 'voice_1.m4a', 'mime_type': 'audio/mp4', 'size': 1000, 'url': 'https://cdn.test/voice_1.m4a'},
          ],
        },
        {
          'id': 6,
          'comment': 'See the layout',
          'created_at': '2026-11-09T09:05:00+00:00',
          'user': {'id': 2, 'name': 'Bela BD', 'role': 'bd'},
          'attachments': [
            {'id': 2, 'name': 'layout.pdf', 'mime_type': 'application/pdf', 'size': 1000, 'url': 'https://cdn.test/layout.pdf'},
          ],
        },
      ]);
      await openChat(tester);

      expect(find.text(voiceNoteText), findsNothing);
      expect(find.text('See the layout'), findsOneWidget);

      await tester.tap(find.byKey(const Key('audio-toggle')));
      await tester.pump();
      expect(_FakePlayer.played, ['https://cdn.test/voice_1.m4a']);

      await tester.tap(find.text('layout.pdf'));
      await tester.pumpAndSettle();
      expect(files.downloads, ['https://cdn.test/layout.pdf']);
    });

    testWidgets('a BD gets the mic too', (tester) async {
      await boot(tester, role: 'bd');
      await openChat(tester);
      expect(find.byKey(const Key('chat-mic')), findsOneWidget);
    });
  });
}
