import 'dart:async';
import 'dart:math' as math;

import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'api_client.dart';
import 'auth.dart';

/// Where upload bytes come from. Abstract so tests don't need real files or a native picker.
abstract class UploadSource {
  String get name;
  Future<int> length();

  /// Bytes in `[start, end)` — read lazily so a multi-GB file is never held in memory.
  Stream<List<int>> read(int start, int end);
}

class PlatformUploadSource implements UploadSource {
  PlatformUploadSource(this._file);

  final PlatformFile _file;

  @override
  String get name => _file.name;

  @override
  Future<int> length() async => (await _file.length()) ?? 0;

  @override
  Stream<List<int>> read(int start, int end) => _file.xFile.openRead(start, end);
}

abstract class FilePickerService {
  Future<List<UploadSource>> pick({bool multiple = true});
}

class NativeFilePickerService implements FilePickerService {
  const NativeFilePickerService();

  @override
  Future<List<UploadSource>> pick({bool multiple = true}) async {
    final files = multiple ? await FilePicker.pickFiles() : [?await FilePicker.pickFile()];
    return files.map(PlatformUploadSource.new).toList();
  }
}

/// What the file is for — drives the server's rules (see CloudMultipartUploadService).
enum UploadPurpose {
  progressUpdate('progress_update'),
  rework('rework'),
  comment('comment');

  const UploadPurpose(this.wire);

  final String wire;
}

const _zipOnlyThreshold = 350 * 1024 * 1024; // progress/rework files this big must be a ZIP
const _commentMaxTotal = 350 * 1024 * 1024;
const _commentMaxFiles = 10;

/// Client-side pre-checks that mirror the server, so the user hears about a problem before a long upload.
String? validateUploadSet(UploadPurpose purpose, List<({String name, int size})> files) {
  if (files.any((f) => f.size <= 0)) return 'One of the files is empty.';

  if (purpose == UploadPurpose.comment) {
    if (files.length > _commentMaxFiles) return 'You can attach up to $_commentMaxFiles files to one message.';
    if (files.fold<int>(0, (a, f) => a + f.size) > _commentMaxTotal) return 'Chat attachments can total up to 350 MB.';
    return null;
  }

  final single = files.length == 1;
  final zip = single && files.first.name.toLowerCase().endsWith('.zip');
  if (!zip && files.fold<int>(0, (a, f) => a + f.size) >= _zipOnlyThreshold) {
    return 'Files totalling 350 MB or more must be uploaded as one ZIP.';
  }
  return null;
}

class UploadCancelled implements Exception {
  @override
  String toString() => 'Upload cancelled.';
}

/// Resumable direct-to-storage upload through the API's multipart endpoints.
///
/// initiate (resumes a half-finished upload of the same file) → list uploaded parts → PUT each missing part
/// to its presigned URL → complete. Presigned URLs are called with a *separate* Dio so the API bearer token
/// is never sent to the storage host.
class UploadService {
  UploadService(this._api, this._storage, {this.maxPartAttempts = 3, this.retryDelay = const Duration(seconds: 2)});

  final ApiClient _api;
  final Dio _storage;
  final int maxPartAttempts;
  final Duration retryDelay;

  /// Returns the finished upload's id (what the task/comment/EOD endpoints expect in `upload_ids`).
  Future<int> upload({
    required int taskId,
    required UploadPurpose purpose,
    required UploadSource source,
    void Function(double progress)? onProgress,
    CancelToken? cancel,
  }) async {
    final size = await source.length();

    final init = await _api.post('/uploads/initiate', data: {
      'task_id': taskId,
      'purpose': purpose.wire,
      'filename': source.name,
      'size_bytes': size,
    });

    final id = init['id'] as int;
    final partSize = init['part_size_bytes'] as int;
    final totalParts = math.max(1, (size / partSize).ceil());

    final listed = await _api.get('/uploads/$id/parts');
    final done = {for (final n in (listed['part_numbers'] as List? ?? [])) n as int};

    var sent = 0;
    for (var n = 1; n <= totalParts; n++) {
      final start = (n - 1) * partSize;
      final end = math.min(start + partSize, size);

      if (!done.contains(n)) {
        await _putPart(id, n, source, start, end, cancel);
      }
      sent += end - start;
      onProgress?.call(sent / size);
    }

    await _api.post('/uploads/$id/complete');
    onProgress?.call(1);
    return id;
  }

  Future<void> abort(int uploadId) async {
    try {
      await _api.post('/uploads/$uploadId/abort');
    } catch (_) {
      // Best effort: the server also sweeps abandoned uploads hourly.
    }
  }

  Future<void> _putPart(int id, int part, UploadSource source, int start, int end, CancelToken? cancel) async {
    Object? last;

    for (var attempt = 1; attempt <= maxPartAttempts; attempt++) {
      if (cancel?.isCancelled ?? false) throw UploadCancelled();

      try {
        // A fresh URL per attempt: presigned URLs can expire while a slow part is retried.
        final urlJson = await _api.get('/uploads/$id/part-url', query: {'part_number': part});

        await _storage.put<Object>(
          urlJson['url'] as String,
          data: source.read(start, end),
          cancelToken: cancel,
          options: Options(headers: {Headers.contentLengthHeader: end - start}, sendTimeout: const Duration(minutes: 10)),
        );
        return;
      } on DioException catch (e) {
        if (CancelToken.isCancel(e)) throw UploadCancelled();
        last = e;
      } on ApiException catch (e) {
        // A 4xx from our own API (auth, validation) will not get better by retrying.
        if (e.statusCode != null && e.statusCode! < 500 && e.statusCode != 429) rethrow;
        last = e;
      }

      if (attempt < maxPartAttempts) await Future<void>.delayed(retryDelay * attempt);
    }

    throw ApiException('The upload was interrupted. Check your connection and try again — it will resume where it stopped.',
        statusCode: last is DioException ? last.response?.statusCode : null);
  }
}

final filePickerProvider = Provider<FilePickerService>((ref) => const NativeFilePickerService());

final uploadServiceProvider = Provider<UploadService>((ref) {
  return UploadService(ref.watch(apiClientProvider), Dio(BaseOptions(connectTimeout: const Duration(seconds: 20))));
});
