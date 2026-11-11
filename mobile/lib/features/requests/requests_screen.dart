import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api_client.dart';
import '../../core/auth.dart';
import '../../core/models.dart';
import '../tasks/providers.dart';
import '../widgets/form_sheet.dart';

// No silent back-off retry: the screen has pull-to-refresh and the badge simply stays as it was.
final pendingRequestsProvider = FutureProvider.autoDispose<({List<RequestItem> items, List<Designer> designers})>(
  (ref) => ref.watch(roleApiProvider).pendingRequests(),
  retry: (_, _) => null,
);

/// Designer Head / Admin: pending requests with approve / reject.
class RequestsScreen extends ConsumerWidget {
  const RequestsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(pendingRequestsProvider);
    final isDesigner = ref.watch(authProvider.select((s) => s.user?.role == 'designer'));

    return Scaffold(
      appBar: AppBar(title: Text(isDesigner ? 'Approvals' : 'Requests')),
      body: data.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(28),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.lock_outline_rounded, size: 44, color: Colors.black26),
              const SizedBox(height: 10),
              Text('$e', textAlign: TextAlign.center, style: const TextStyle(color: Color(0xFF475467), fontWeight: FontWeight.w600)),
              const SizedBox(height: 10),
              TextButton(onPressed: () => ref.invalidate(pendingRequestsProvider), child: const Text('Try again')),
            ]),
          ),
        ),
        data: (d) => RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(pendingRequestsProvider);
            await ref.read(pendingRequestsProvider.future);
          },
          child: d.items.isEmpty
              ? ListView(padding: const EdgeInsets.fromLTRB(32, 0, 32, 0), children: [
                  const SizedBox(height: 110),
                  Center(
                    child: Container(
                      width: 92,
                      height: 92,
                      decoration: BoxDecoration(shape: BoxShape.circle, color: const Color(0xFF16A34A).withValues(alpha: 0.1)),
                      child: const Icon(Icons.task_alt_rounded, size: 46, color: Color(0xFF16A34A)),
                    ),
                  ),
                  const SizedBox(height: 18),
                  const Center(child: Text('No pending requests.', key: Key('requests-empty'), style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800))),
                  const SizedBox(height: 6),
                  const Center(child: Text('Swap and split requests from your team will show up here as soon as they are raised.', textAlign: TextAlign.center, style: TextStyle(color: Color(0xFF667085), height: 1.35))),
                ])
              : ListView.separated(
                  padding: const EdgeInsets.fromLTRB(14, 12, 14, 24),
                  itemCount: d.items.length + 1,
                  separatorBuilder: (_, _) => const SizedBox(height: 12),
                  itemBuilder: (_, i) => i == 0
                      ? Row(children: [
                          Text('Waiting for you', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 2),
                            decoration: BoxDecoration(color: const Color(0xFFFEE4E2), borderRadius: BorderRadius.circular(20)),
                            child: Text('${d.items.length}', key: const Key('requests-count'), style: const TextStyle(color: Color(0xFFD92D20), fontWeight: FontWeight.w800, fontSize: 13)),
                          ),
                        ])
                      : _RequestCard(item: d.items[i - 1], designers: d.designers),
                ),
        ),
      ),
    );
  }
}

class _RequestCard extends ConsumerWidget {
  const _RequestCard({required this.item, required this.designers});

  final RequestItem item;
  final List<Designer> designers;

  Future<void> _approve(BuildContext context, WidgetRef ref) async {
    final needsDesigner = const ['decline', 'split', 'swap'].contains(item.type);
    final values = await showFormSheet(
      context,
      title: 'Approve ${item.typeLabel.toLowerCase()} request',
      subtitle: [item.taskRef, if (item.requester != null) 'raised by ${item.requester}'].whereType<String>().join(' · '),
      icon: Icons.check_circle_rounded,
      submitLabel: 'Approve',
      fields: [
        if (needsDesigner)
          FormFieldSpec.choice('designer', 'Assign to designer', options: {for (final d in designers) '${d.id}': d.name}, required: true, availability: ref.read(roleApiProvider).availability, initial: item.targetDesigner == null ? null : designers.where((d) => d.name == item.targetDesigner).map((d) => '${d.id}').firstOrNull),
        if (item.type == 'split') FormFieldSpec.number('count', 'Creatives to move', required: true, initial: item.splitCount),
        FormFieldSpec.text('comment', 'Comment (optional)', lines: 3),
      ],
    );
    if (values == null || !context.mounted) return;
    await _run(context, ref, () => ref.read(roleApiProvider).approveRequest(
          item.id,
          designerId: int.tryParse(values['designer'] ?? ''),
          creativeCount: int.tryParse(values['count'] ?? ''),
          comment: values['comment'],
        ));
  }

  Future<void> _reject(BuildContext context, WidgetRef ref) async {
    final values = await showFormSheet(
      context,
      title: 'Decline ${item.typeLabel.toLowerCase()} request',
      subtitle: [item.taskRef, if (item.requester != null) 'raised by ${item.requester}'].whereType<String>().join(' · '),
      icon: Icons.cancel_rounded,
      submitLabel: 'Decline',
      destructive: true,
      fields: [FormFieldSpec.text('reason', 'Reason', lines: 3, required: true)],
    );
    if (values == null || !context.mounted) return;
    await _run(context, ref, () => ref.read(roleApiProvider).rejectRequest(item.id, values['reason']!));
  }

  Future<void> _run(BuildContext context, WidgetRef ref, Future<void> Function() action) async {
    final messenger = ScaffoldMessenger.of(context);
    try {
      await action();
      ref.invalidate(pendingRequestsProvider);
      ref.invalidate(boardProvider);
    ref.invalidate(kanbanBoardProvider);
      messenger.showSnackBar(const SnackBar(content: Text('Done.')));
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  static Color _tone(String type) => switch (type) {
        'swap' => const Color(0xFF2970FF),
        'split' => const Color(0xFF7C3AED),
        'decline' => const Color(0xFFD92D20),
        _ => const Color(0xFFE65100), // a backward move
      };

  static IconData _icon(String type) => switch (type) {
        'swap' => Icons.swap_horiz_rounded,
        'split' => Icons.call_split_rounded,
        'decline' => Icons.block_rounded,
        _ => Icons.undo_rounded,
      };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tone = _tone(item.type);
    final primary = Theme.of(context).colorScheme.primary;

    Widget chip(IconData icon, String text) => Container(
          padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
          decoration: BoxDecoration(color: const Color(0xFFF2F4F7), borderRadius: BorderRadius.circular(10)),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Icon(icon, size: 14, color: const Color(0xFF475467)),
            const SizedBox(width: 5),
            Flexible(child: Text(text, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF344054)))),
          ]),
        );

    final details = [
      if (item.type == 'split' && item.splitCount != null) chip(Icons.layers_rounded, 'Creatives to split: ${item.splitCount}'),
      if (item.targetDesigner != null) chip(Icons.person_rounded, 'Preferred designer: ${item.targetDesigner}'),
      if (item.toStatus != null) chip(Icons.undo_rounded, 'Move back to: ${statusLabel(item.toStatus!)}'),
    ];

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [BoxShadow(color: tone.withValues(alpha: 0.10), blurRadius: 8, offset: const Offset(0, 6))],
        border: Border.all(color: const Color(0xFFEAECF0)),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(18),
        child: Stack(children: [
          // the request's colour, softly, across the top
          Positioned(top: 0, left: 0, right: 0, height: 70, child: DecoratedBox(decoration: BoxDecoration(gradient: LinearGradient(colors: [tone.withValues(alpha: 0.10), tone.withValues(alpha: 0.0)], begin: Alignment.topCenter, end: Alignment.bottomCenter)))),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(color: tone.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(12)),
                  child: Icon(_icon(item.type), color: tone, size: 21),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('${item.typeLabel} request', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: tone, letterSpacing: -0.2)),
                    if (item.requester != null) Text('by ${item.requester}', style: const TextStyle(fontSize: 12, color: Color(0xFF667085), fontWeight: FontWeight.w600)),
                  ]),
                ),
                if (item.requester != null)
                  CircleAvatar(
                    radius: 15,
                    backgroundColor: tone.withValues(alpha: 0.14),
                    child: Text(item.requester!.trim().isEmpty ? '?' : item.requester!.trim()[0].toUpperCase(), style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: tone)),
                  ),
              ]),
              const SizedBox(height: 12),
              InkWell(
                onTap: item.taskDbId == null ? null : () => context.push('/task/${item.taskDbId}'),
                child: Row(children: [
                  Expanded(
                    child: Text('${item.taskRef ?? ''} — ${item.taskName ?? ''}', maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800, color: item.taskDbId == null ? null : primary)),
                  ),
                  if (item.taskDbId != null) const Icon(Icons.chevron_right_rounded, size: 20, color: Color(0xFF98A2B3)),
                ]),
              ),
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(11),
                decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(12), border: Border(left: BorderSide(color: tone, width: 3))),
                child: Text(item.reason, style: const TextStyle(fontSize: 13.5, height: 1.35, color: Color(0xFF344054))),
              ),
              if (details.isNotEmpty) ...[
                const SizedBox(height: 10),
                Wrap(spacing: 6, runSpacing: 6, children: details),
              ],
              const SizedBox(height: 12),
              Row(children: [
                Expanded(
                  child: OutlinedButton.icon(
                    key: Key('reject-${item.id}'),
                    style: OutlinedButton.styleFrom(minimumSize: const Size(0, 46), foregroundColor: const Color(0xFFD92D20), side: const BorderSide(color: Color(0xFFFDA29B))),
                    onPressed: () => _reject(context, ref),
                    icon: const Icon(Icons.close_rounded, size: 18),
                    label: const Text('Decline', style: TextStyle(fontWeight: FontWeight.w800)),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton.icon(
                    key: Key('approve-${item.id}'),
                    style: FilledButton.styleFrom(minimumSize: const Size(0, 46), backgroundColor: const Color(0xFF16A34A)),
                    onPressed: () => _approve(context, ref),
                    icon: const Icon(Icons.check_rounded, size: 18),
                    label: const Text('Approve', style: TextStyle(fontWeight: FontWeight.w800)),
                  ),
                ),
              ]),
            ]),
          ),
        ]),
      ),
    );
  }
}
