import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/uploads.dart';
import 'upload_panel.dart';

class ProgressSubmission {
  ProgressSubmission(this.count, this.uploadIds);

  final int count;
  final String uploadIds;
}

/// EOD ("Task updation") and Rework share one form: how many creatives, plus the finished files.
/// Submit stays disabled until every file has finished uploading.
class ProgressSheet extends ConsumerStatefulWidget {
  const ProgressSheet({super.key, required this.taskId, required this.purpose, required this.title, this.maxCount});

  final int taskId;
  final UploadPurpose purpose; // progressUpdate or rework
  final String title;
  final int? maxCount;

  @override
  ConsumerState<ProgressSheet> createState() => _ProgressSheetState();
}

class _ProgressSheetState extends ConsumerState<ProgressSheet> {
  final _count = TextEditingController();
  late final UploadsController _uploads =
      UploadsController(service: ref.read(uploadServiceProvider), taskId: widget.taskId, purpose: widget.purpose);
  String? _error;

  @override
  void dispose() {
    _uploads.dispose();
    _count.dispose();
    super.dispose();
  }

  void _submit() {
    final n = int.tryParse(_count.text.trim()) ?? 0;
    if (n < 1) {
      setState(() => _error = 'Enter how many creatives this covers.');
      return;
    }
    if (widget.maxCount != null && n > widget.maxCount!) {
      setState(() => _error = 'You can add at most ${widget.maxCount} here.');
      return;
    }
    Navigator.pop(context, ProgressSubmission(n, _uploads.idList));
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, math.max(MediaQuery.viewInsetsOf(context).bottom, MediaQuery.viewPaddingOf(context).bottom) + 22),
      child: SingleChildScrollView(
        child: ListenableBuilder(
          listenable: _uploads,
          builder: (context, _) => Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(widget.title, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 16),
              TextField(
                key: const Key('progress-count'),
                controller: _count,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  labelText: widget.maxCount == null ? 'Creatives completed' : 'Creatives completed (up to ${widget.maxCount})',
                  errorText: _error,
                ),
              ),
              const SizedBox(height: 12),
              UploadPanel(controller: _uploads, label: 'Add work files'),
              const SizedBox(height: 16),
              FilledButton(
                key: const Key('progress-submit'),
                onPressed: _uploads.allDone ? _submit : null,
                child: Text(_uploads.busy
                    ? 'Uploading…'
                    : _uploads.failed
                        ? 'Fix failed uploads to continue'
                        : _uploads.hasFiles
                            ? 'Submit'
                            : 'Add at least one file'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
