import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../core/uploads.dart';

enum UploadStatus { uploading, done, failed }

class UploadEntry {
  UploadEntry(this.source, this.size) : name = source.name;

  final UploadSource source;
  final String name;
  final int size;
  final CancelToken cancel = CancelToken();
  UploadStatus status = UploadStatus.uploading;
  double progress = 0;
  int? id;
  String? error;
}

/// Holds the files attached to one form (EOD, rework or a chat message) and uploads them one at a time.
class UploadsController extends ChangeNotifier {
  UploadsController({required this.service, required this.taskId, required this.purpose});

  final UploadService service;
  final int taskId;
  final UploadPurpose purpose;

  final List<UploadEntry> entries = [];
  String? notice;
  bool _disposed = false;
  Future<void> _queue = Future.value();

  bool get hasFiles => entries.isNotEmpty;
  bool get busy => entries.any((e) => e.status == UploadStatus.uploading);
  bool get failed => entries.any((e) => e.status == UploadStatus.failed);
  bool get allDone => entries.isNotEmpty && entries.every((e) => e.status == UploadStatus.done);

  /// Comma-separated ids, the format the API's `upload_ids` field takes.
  String get idList => entries.where((e) => e.id != null).map((e) => e.id).join(',');

  Future<void> add(List<UploadSource> sources) async {
    if (sources.isEmpty) return;

    final added = <UploadEntry>[];
    for (final s in sources) {
      added.add(UploadEntry(s, await s.length()));
    }

    final error = validateUploadSet(purpose, [for (final e in [...entries, ...added]) (name: e.name, size: e.size)]);
    if (error != null) {
      notice = error;
      _notify();
      return;
    }

    notice = null;
    entries.addAll(added);
    _notify();
    for (final e in added) {
      _queue = _queue.then((_) => _run(e));
    }
  }

  Future<void> retry(UploadEntry entry) {
    entry
      ..status = UploadStatus.uploading
      ..error = null;
    _notify();
    return _queue = _queue.then((_) => _run(entry));
  }

  Future<void> remove(UploadEntry entry) async {
    entries.remove(entry);
    notice = null;
    if (entry.status == UploadStatus.uploading) {
      entry.cancel.cancel();
    }
    _notify();
  }

  Future<void> _run(UploadEntry e) async {
    if (e.cancel.isCancelled || !entries.contains(e)) return;

    try {
      e.id = await service.upload(
        taskId: taskId,
        purpose: purpose,
        source: e.source,
        cancel: e.cancel,
        onProgress: (p) {
          e.progress = p;
          _notify();
        },
      );
      e.status = UploadStatus.done;
    } on UploadCancelled {
      return;
    } on ApiException catch (ex) {
      e.status = UploadStatus.failed;
      e.error = ex.message;
    } catch (_) {
      e.status = UploadStatus.failed;
      e.error = 'Upload failed.';
    }
    _notify();
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    for (final e in entries) {
      if (e.status == UploadStatus.uploading) e.cancel.cancel();
    }
    super.dispose();
  }
}

String formatBytes(int bytes) {
  if (bytes >= 1 << 30) return '${(bytes / (1 << 30)).toStringAsFixed(1)} GB';
  if (bytes >= 1 << 20) return '${(bytes / (1 << 20)).toStringAsFixed(1)} MB';
  if (bytes >= 1 << 10) return '${(bytes / (1 << 10)).toStringAsFixed(0)} KB';
  return '$bytes B';
}

/// Add-files button plus a progress row per file.
class UploadPanel extends ConsumerWidget {
  const UploadPanel({super.key, required this.controller, this.label = 'Add files'});

  final UploadsController controller;
  final String label;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return ListenableBuilder(
      listenable: controller,
      builder: (context, _) => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          OutlinedButton.icon(
            key: const Key('add-files'),
            icon: const Icon(Icons.attach_file),
            label: Text(label),
            onPressed: () async {
              final picked = await ref.read(filePickerProvider).pick();
              await controller.add(picked);
            },
          ),
          if (controller.notice != null)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(controller.notice!, key: const Key('upload-notice'), style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ),
          for (final e in controller.entries) _Row(entry: e, controller: controller),
        ],
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.entry, required this.controller});

  final UploadEntry entry;
  final UploadsController controller;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        children: [
          Icon(
            switch (entry.status) {
              UploadStatus.done => Icons.check_circle,
              UploadStatus.failed => Icons.error_outline,
              UploadStatus.uploading => Icons.upload_file,
            },
            color: switch (entry.status) {
              UploadStatus.done => Colors.green,
              UploadStatus.failed => theme.colorScheme.error,
              UploadStatus.uploading => theme.colorScheme.primary,
            },
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(entry.name, maxLines: 1, overflow: TextOverflow.ellipsis),
                if (entry.status == UploadStatus.uploading) LinearProgressIndicator(value: entry.progress == 0 ? null : entry.progress),
                Text(
                  entry.status == UploadStatus.failed ? (entry.error ?? 'Failed') : formatBytes(entry.size),
                  style: theme.textTheme.bodySmall?.copyWith(color: entry.status == UploadStatus.failed ? theme.colorScheme.error : null),
                ),
              ],
            ),
          ),
          if (entry.status == UploadStatus.failed) IconButton(tooltip: 'Retry', icon: const Icon(Icons.refresh), onPressed: () => controller.retry(entry)),
          IconButton(tooltip: 'Remove', icon: const Icon(Icons.close), onPressed: () => controller.remove(entry)),
        ],
      ),
    );
  }
}
