import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../core/auth.dart';
import '../../core/models.dart';
import '../dashboard/dashboard_widgets.dart';
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
      children: [for (var i = 0; i < buttons.length; i++) Padding(padding: EdgeInsets.only(top: i == 0 ? 0 : 8), child: buttons[i])],
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
  String? _error;

  /// Mirrors the server: average snapped to the nearest half star.
  double get _overall => ((_values.values.reduce((a, b) => a + b) / _values.length) * 2).round() / 2;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  /// One of the two finishing buttons: [printing] true sends the task on to Prepare Printing File, false marks it completed.
  void _submit({required bool printing}) {
    if (_overall < 3 && _comment.text.trim().isEmpty) {
      setState(() => _error = 'Add a comment — it is required when the overall rating is below 3 stars.');
      return;
    }
    Navigator.pop(context, RatingResult(Map.of(_values), _comment.text.trim(), printing));
  }

  Color _tone(double v) => v >= 4 ? const Color(0xFF12B76A) : v >= 3 ? const Color(0xFFF79009) : const Color(0xFFD92D20);

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    final overall = _overall;
    OutlineInputBorder border(Color c, [double w = 1]) => OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: c, width: w));

    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, math.max(MediaQuery.viewInsetsOf(context).bottom, MediaQuery.viewPaddingOf(context).bottom) + 22),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(color: primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
                child: Icon(Icons.star_rate_rounded, color: primary, size: 24),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Rate the designer', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, letterSpacing: -0.3, color: Color(0xFF101828))),
                  Text('Slide each point, then finish the task', style: TextStyle(fontSize: 12, color: Color(0xFF667085))),
                ]),
              ),
            ]),
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: BoxDecoration(color: _tone(overall).withValues(alpha: 0.08), borderRadius: BorderRadius.circular(16), border: Border.all(color: _tone(overall).withValues(alpha: 0.25))),
              child: Row(children: [
                Text('$overall', style: TextStyle(fontSize: 34, height: 1, fontWeight: FontWeight.w900, color: _tone(overall))),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    HalfStars(overall, size: 20),
                    const SizedBox(height: 4),
                    Text('Overall: $overall / 5', key: const Key('overall'), style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: Color(0xFF475467))),
                  ]),
                ),
              ]),
            ),
            const SizedBox(height: 10),
            for (final e in _criteria.entries)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Column(children: [
                  Row(children: [
                    Expanded(child: Text(e.value, style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700, color: Color(0xFF344054)))),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
                      decoration: BoxDecoration(color: _tone(_values[e.key]!).withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                      child: Text('${_values[e.key]}', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: _tone(_values[e.key]!))),
                    ),
                  ]),
                  SliderTheme(
                    data: SliderTheme.of(context).copyWith(trackHeight: 5, activeTrackColor: _tone(_values[e.key]!), inactiveTrackColor: const Color(0xFFEAECF0), thumbColor: _tone(_values[e.key]!), overlayShape: const RoundSliderOverlayShape(overlayRadius: 16)),
                    child: Slider(
                      key: Key('rate-${e.key}'),
                      value: _values[e.key]!,
                      min: 0.5,
                      max: 5,
                      divisions: 9,
                      label: '${_values[e.key]}',
                      onChanged: (v) => setState(() => _values[e.key] = v),
                    ),
                  ),
                ]),
              ),
            const SizedBox(height: 6),
            TextField(
              key: const Key('rating-comment'),
              controller: _comment,
              minLines: 2,
              maxLines: 4,
              style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600),
              decoration: InputDecoration(
                hintText: 'Add a comment (required below 3 stars)',
                hintStyle: const TextStyle(fontSize: 14, color: Color(0xFF98A2B3)),
                errorText: _error,
                filled: true,
                fillColor: const Color(0xFFF9FAFB),
                contentPadding: const EdgeInsets.all(14),
                border: border(const Color(0xFFEAECF0)),
                enabledBorder: border(const Color(0xFFEAECF0)),
                focusedBorder: border(primary, 1.4),
                errorBorder: border(const Color(0xFFFDA29B)),
                focusedErrorBorder: border(const Color(0xFFD92D20), 1.4),
              ),
            ),
            const SizedBox(height: 16),
            // Two clear choices instead of a switch: finish the task, or send it on for the printing file.
            FilledButton.icon(
              key: const Key('rating-submit'),
              style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(50), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
              onPressed: () => _submit(printing: false),
              icon: const Icon(Icons.check_circle_outline_rounded, size: 20),
              label: const Text('Submit & complete', style: TextStyle(fontWeight: FontWeight.w800)),
            ),
            const SizedBox(height: 10),
            OutlinedButton.icon(
              key: const Key('rating-submit-printing'),
              style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(50), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
              onPressed: () => _submit(printing: true),
              icon: const Icon(Icons.print_outlined, size: 20),
              label: const Text('Submit & prepare printing file', style: TextStyle(fontWeight: FontWeight.w800)),
            ),
          ],
        ),
      ),
    );
  }
}
