import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api_client.dart';
import '../../core/models.dart';
import '../tasks/providers.dart';
import '../widgets/form_sheet.dart';

final pendingRequestsProvider = FutureProvider.autoDispose<({List<RequestItem> items, List<Designer> designers})>(
  (ref) => ref.watch(roleApiProvider).pendingRequests(),
);

/// Designer Head / Admin: pending requests with approve / reject.
class RequestsScreen extends ConsumerWidget {
  const RequestsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(pendingRequestsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Requests')),
      body: data.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('$e')),
        data: (d) => RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(pendingRequestsProvider);
            await ref.read(pendingRequestsProvider.future);
          },
          child: d.items.isEmpty
              ? ListView(children: const [SizedBox(height: 120), Center(child: Text('No pending requests.'))])
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: d.items.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 10),
                  itemBuilder: (_, i) => _RequestCard(item: d.items[i], designers: d.designers),
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
      submitLabel: 'Approve',
      fields: [
        if (needsDesigner)
          FormFieldSpec.choice('designer', 'Assign to designer', options: {for (final d in designers) '${d.id}': d.name}, required: true, initial: item.targetDesigner == null ? null : designers.where((d) => d.name == item.targetDesigner).map((d) => '${d.id}').firstOrNull),
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

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Chip(label: Text(item.typeLabel), visualDensity: VisualDensity.compact),
            const Spacer(),
            if (item.requester != null) Text('by ${item.requester}', style: theme.textTheme.bodySmall),
          ]),
          InkWell(
            onTap: item.taskDbId == null ? null : () => context.push('/task/${item.taskDbId}'),
            child: Text('${item.taskRef ?? ''} — ${item.taskName ?? ''}', style: theme.textTheme.titleSmall),
          ),
          const SizedBox(height: 6),
          Text(item.reason),
          if (item.type == 'split' && item.splitCount != null) Text('Creatives to split: ${item.splitCount}', style: theme.textTheme.bodySmall),
          if (item.targetDesigner != null) Text('Preferred designer: ${item.targetDesigner}', style: theme.textTheme.bodySmall),
          if (item.toStatus != null) Text('Move back to: ${statusLabel(item.toStatus!)}', style: theme.textTheme.bodySmall),
          const SizedBox(height: 10),
          Row(children: [
            Expanded(child: OutlinedButton(key: Key('reject-${item.id}'), onPressed: () => _reject(context, ref), child: const Text('Decline'))),
            const SizedBox(width: 10),
            Expanded(child: FilledButton(key: Key('approve-${item.id}'), onPressed: () => _approve(context, ref), child: const Text('Approve'))),
          ]),
        ]),
      ),
    );
  }
}
