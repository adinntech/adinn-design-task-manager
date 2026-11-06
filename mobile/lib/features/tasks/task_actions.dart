import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../core/auth.dart';
import '../../core/models.dart';
import '../requests/requests_screen.dart';
import '../widgets/form_sheet.dart';
import '../../core/uploads.dart';
import '../uploads/progress_sheet.dart';
import 'providers.dart';

/// Role-specific action buttons on the task overview. The server remains the authority: these are
/// only shown when the detail payload says the action is available, and every call can still be refused.
class TaskActions extends ConsumerWidget {
  const TaskActions({super.key, required this.taskId, required this.data});

  final int taskId;
  final Map<String, dynamic> data;

  Future<void> _run(BuildContext context, WidgetRef ref, Future<void> Function() action, String done) async {
    final messenger = ScaffoldMessenger.of(context);
    try {
      await action();
      ref.invalidate(taskDetailProvider(taskId));
      ref.invalidate(boardProvider);
    ref.invalidate(kanbanBoardProvider);
      ref.invalidate(pendingRequestsProvider);
      messenger.showSnackBar(SnackBar(content: Text(done)));
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final role = ref.watch(authProvider).user?.role;
    final api = ref.read(roleApiProvider);
    final actions = (data['actions'] as Map?)?.cast<String, dynamic>() ?? const {};
    final access = (data['access'] as Map?)?.cast<String, dynamic>();
    final total = ((data['progress'] as Map?)?['total'] ?? 1) as int;

    final buttons = <Widget>[];

    if (role == 'bd' && actions['can_review'] == true) {
      buttons.addAll([
        FilledButton.icon(
          key: const Key('bd-complete'),
          icon: const Icon(Icons.star),
          label: const Text('Complete & rate'),
          onPressed: () async {
            final result = await showModalBottomSheet<RatingResult>(
              context: context,
              isScrollControlled: true,
              showDragHandle: true,
              builder: (_) => const RatingSheet(),
            );
            if (result == null || !context.mounted) return;
            await _run(context, ref, () => api.completeWithRating(taskId, ratings: result.ratings, comment: result.comment, printingFile: result.printingFile),
                result.printingFile ? 'Moved to Prepare Printing File.' : 'Task completed.');
          },
        ),
        OutlinedButton.icon(
          key: const Key('bd-rework'),
          icon: const Icon(Icons.replay),
          label: const Text('Request rework'),
          onPressed: () async {
            final v = await showFormSheet(context, title: 'Request rework', submitLabel: 'Send for rework', fields: [
              FormFieldSpec.number('count', 'Creatives needing rework (max $total)', required: true),
              FormFieldSpec.text('comment', 'What needs to change?', lines: 4, required: true),
            ]);
            if (v == null || !context.mounted) return;
            await _run(context, ref, () => api.requestRework(taskId, count: int.parse(v['count']!), comment: v['comment']!), 'Task moved to Rework.');
          },
        ),
      ]);
    }

    if (role == 'bd' && actions['can_decide_confirmation'] == true) {
      buttons.addAll([
        FilledButton.icon(
          key: const Key('bd-approve'),
          icon: const Icon(Icons.check),
          label: const Text('Confirm task'),
          onPressed: () async {
            final v = await showFormSheet(context, title: 'Confirm task', submitLabel: 'Confirm', fields: [FormFieldSpec.text('comment', 'Comment (optional)', lines: 3)]);
            if (v == null || !context.mounted) return;
            await _run(context, ref, () => api.approveNewTask(taskId, comment: v['comment']), 'Task confirmed.');
          },
        ),
        OutlinedButton.icon(
          key: const Key('bd-reject'),
          icon: const Icon(Icons.close),
          label: const Text('Reject task'),
          onPressed: () async {
            final v = await showFormSheet(context, title: 'Reject task', submitLabel: 'Reject', destructive: true, fields: [FormFieldSpec.text('comment', 'Reason', lines: 3, required: true)]);
            if (v == null || !context.mounted) return;
            await _run(context, ref, () => api.rejectNewTask(taskId, v['comment']!), 'Task rejected.');
          },
        ),
      ]);
    }

    final status = ((data['task'] as Map?)?['status'] ?? '') as String;
    final remaining = ((data['progress'] as Map?)?['remaining']) as int?;

    if (role == 'designer' && access?['read_only'] != true && (status == 'in_progress' || status == 'rework')) {
      final isRework = status == 'rework';
      buttons.add(FilledButton.icon(
        key: Key(isRework ? 'submit-rework' : 'submit-eod'),
        icon: const Icon(Icons.cloud_upload),
        label: Text(isRework ? 'Submit rework' : 'Task updation (EOD)'),
        onPressed: () async {
          final result = await showModalBottomSheet<ProgressSubmission>(
            context: context,
            isScrollControlled: true,
            showDragHandle: true,
            builder: (_) => ProgressSheet(
              taskId: taskId,
              purpose: isRework ? UploadPurpose.rework : UploadPurpose.progressUpdate,
              title: isRework ? 'Submit rework' : 'Task updation',
              maxCount: isRework ? null : remaining,
            ),
          );
          if (result == null || !context.mounted) return;
          await _run(
            context,
            ref,
            () => isRework
                ? api.submitRework(taskId, count: result.count, uploadIds: result.uploadIds)
                : api.submitEod(taskId, count: result.count, uploadIds: result.uploadIds),
            isRework ? 'Rework submitted.' : 'Task updation submitted.',
          );
        },
      ));
    }

    if (role == 'designer' && access?['read_only'] != true) {
      buttons.add(OutlinedButton.icon(
        key: const Key('raise-request'),
        icon: const Icon(Icons.outbox),
        label: const Text('Raise a request'),
        onPressed: () => _raiseRequest(context, ref),
      ));
    }

    if (buttons.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [for (final b in buttons) Padding(padding: const EdgeInsets.only(top: 10), child: b)],
    );
  }

  Future<void> _raiseRequest(BuildContext context, WidgetRef ref) async {
    final api = ref.read(roleApiProvider);
    final messenger = ScaffoldMessenger.of(context);

    final ({List<String> types, List<Designer> designers}) options;
    try {
      options = await api.requestOptions(taskId);
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
      return;
    }

    if (options.types.isEmpty) {
      messenger.showSnackBar(const SnackBar(content: Text('No requests are available for this task right now.')));
      return;
    }
    if (!context.mounted) return;

    const labels = {'decline': 'Decline the task', 'split': 'Split the task', 'swap': 'Swap with another designer'};
    final picked = await showFormSheet(context, title: 'What do you need?', submitLabel: 'Next', fields: [
      FormFieldSpec.choice('type', 'Request type', options: {for (final t in options.types) t: labels[t] ?? t}, required: true),
    ]);
    if (picked == null || !context.mounted) return;

    final type = picked['type']!;
    final designers = {for (final d in options.designers) '${d.id}': d.name};

    final v = await showFormSheet(context, title: labels[type] ?? 'Request', submitLabel: 'Submit request', fields: [
      if (type == 'split') ...[
        FormFieldSpec.number('count', 'Creatives to hand over', required: true),
        FormFieldSpec.text('details', 'Split details', lines: 3, required: true),
        FormFieldSpec.choice('designer', 'Preferred designer (optional)', options: designers, availability: api.availability),
      ],
      if (type == 'swap') FormFieldSpec.choice('designer', 'Swap to designer', options: designers, required: true, availability: api.availability),
      FormFieldSpec.text('reason', 'Reason', lines: 3, required: true),
    ]);
    if (v == null || !context.mounted) return;

    await _run(
      context,
      ref,
      () => api.submitRequest(
        taskId,
        type: type,
        reason: v['reason']!,
        targetDesignerId: int.tryParse(v['designer'] ?? ''),
        creativeCount: int.tryParse(v['count'] ?? ''),
        details: v['details'],
      ),
      'Request submitted — waiting for approval.',
    );
  }
}

class RatingResult {
  RatingResult(this.ratings, this.comment, this.printingFile);

  final Map<String, double> ratings;
  final String comment;
  final bool printingFile;
}

/// Five half-star ratings, an optional comment (required when the average is under 3) and the target status.
class RatingSheet extends StatefulWidget {
  const RatingSheet({super.key});

  @override
  State<RatingSheet> createState() => _RatingSheetState();
}

class _RatingSheetState extends State<RatingSheet> {
  static const _criteria = {
    'designer_attitude': 'Designer attitude',
    'design_satisfaction': 'Design satisfaction',
    'rework_iteration': 'Rework iterations',
    'meeting_deadline': 'Meeting the deadline',
    'client_satisfaction': 'Client satisfaction',
  };

  final _values = {for (final k in _criteria.keys) k: 5.0};
  final _comment = TextEditingController();
  bool _printing = false;
  String? _error;

  /// Mirrors the server: average snapped to the nearest half star.
  double get _overall => ((_values.values.reduce((a, b) => a + b) / _values.length) * 2).round() / 2;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  void _submit() {
    if (_overall < 3 && _comment.text.trim().isEmpty) {
      setState(() => _error = 'Add a comment — it is required when the overall rating is below 3 stars.');
      return;
    }
    Navigator.pop(context, RatingResult(Map.of(_values), _comment.text.trim(), _printing));
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.viewInsetsOf(context).bottom + 16),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Rate the designer', style: Theme.of(context).textTheme.titleLarge),
            Text('Overall: $_overall / 5', key: const Key('overall'), style: Theme.of(context).textTheme.titleMedium),
            for (final e in _criteria.entries) ...[
              const SizedBox(height: 8),
              Text('${e.value} — ${_values[e.key]}'),
              Slider(
                key: Key('rate-${e.key}'),
                value: _values[e.key]!,
                min: 0.5,
                max: 5,
                divisions: 9,
                label: '${_values[e.key]}',
                onChanged: (v) => setState(() => _values[e.key] = v),
              ),
            ],
            TextField(
              key: const Key('rating-comment'),
              controller: _comment,
              minLines: 2,
              maxLines: 4,
              decoration: InputDecoration(labelText: 'Comment', errorText: _error),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Send on to Prepare Printing File'),
              subtitle: const Text('Instead of marking the task completed'),
              value: _printing,
              onChanged: (v) => setState(() => _printing = v),
            ),
            FilledButton(key: const Key('rating-submit'), onPressed: _submit, child: Text(_printing ? 'Submit & prepare printing file' : 'Submit & complete')),
          ],
        ),
      ),
    );
  }
}
