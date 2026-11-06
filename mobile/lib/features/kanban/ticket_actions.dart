import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api_client.dart';
import '../tasks/providers.dart';
import 'kanban_models.dart';

/// What a designer can do to a ticket on the board. The rules are the server's (each card carries its allowed forward
/// and backward moves from the status state machine) and the server still has the last word on every call.
class TicketActions {
  TicketActions(this.ref);

  final WidgetRef ref;

  void refresh() {
    ref.invalidate(kanbanBoardProvider);
    ref.invalidate(boardProvider);
  }

  String label(KanbanBoard board, String key) => board.columns.where((c) => c.key == key).map((c) => c.label).firstOrNull ?? key;

  void toast(BuildContext context, String message) {
    if (!context.mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  /// The ticket's next stage: the nearest allowed forward move after its current stage in the board order.
  String? nextStage(KanbanCard card, KanbanBoard b) {
    if (card.forwardTo.isEmpty) return null;
    final order = {for (var i = 0; i < b.columns.length; i++) b.columns[i].key: i};
    final here = order[card.status] ?? -1;
    final sorted = [...card.forwardTo]..sort((a, c) => (order[a] ?? 999).compareTo(order[c] ?? 999));
    return sorted.where((k) => (order[k] ?? 999) > here).firstOrNull ?? sorted.first;
  }

  Future<void> move(BuildContext context, KanbanCard card, String to) async {
    try {
      await ref.read(roleApiProvider).moveStatus(card.id, to);
      refresh();
      if (context.mounted) {
        showSuccessCheck(context);
        toast(context, 'Task status updated successfully.');
      }
    } on ApiException catch (e) {
      // e.g. "Creative Work Incomplete: Complete all creatives before BD Review. 1 of 4 completed • 3 remaining."
      if (context.mounted) toast(context, e.message);
    }
  }

  Future<void> requestBackward(BuildContext context, KanbanCard card, String to, KanbanBoard board) async {
    final reason = await showDialog<String>(
      context: context,
      builder: (_) => BackwardDialog(taskLabel: '${card.taskId} — ${card.name}', from: label(board, card.status), to: label(board, to)),
    );
    if (reason == null || !context.mounted) return;

    try {
      await ref.read(roleApiProvider).requestBackward(card.id, toStatus: to, reason: reason);
      refresh();
      if (context.mounted) toast(context, 'Backward status request submitted — waiting for Designer Head approval.');
    } on ApiException catch (e) {
      if (context.mounted) toast(context, e.message);
    }
  }

  /// Swipe right: move to the next stage straight away.
  void swipeAdvance(BuildContext context, KanbanCard card, KanbanBoard b) {
    final next = nextStage(card, b);
    if (next != null) move(context, card, next);
  }

  /// Swipe left: ask to move back (one choice goes straight to the reason dialog, several open the sheet).
  void swipeBack(BuildContext context, KanbanCard card, KanbanBoard b) {
    if (card.backwardTo.length == 1) {
      requestBackward(context, card, card.backwardTo.first, b);
    } else {
      openMoveSheet(context, card, b);
    }
  }

  /// "Move to…" (and the long-press menu): open the ticket, move it forward, or ask to move it back.
  Future<void> openMoveSheet(BuildContext context, KanbanCard card, KanbanBoard board) async {
    final choice = await showModalBottomSheet<(String, bool)>(
      context: context,
      showDragHandle: true,
      builder: (sheet) => SafeArea(
        child: SingleChildScrollView(
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
              child: Text('Move ${card.taskId}', style: Theme.of(sheet).textTheme.titleMedium),
            ),
            ListTile(key: const Key('open-ticket'), leading: const Icon(Icons.open_in_new), title: const Text('Open ticket'), onTap: () => Navigator.pop(sheet, ('__open', false))),
            if (card.forwardTo.isNotEmpty || card.backwardTo.isNotEmpty) const Divider(height: 8),
            for (final to in card.forwardTo)
              ListTile(key: Key('move-$to'), leading: const Icon(Icons.arrow_forward), title: Text('Move to ${label(board, to)}'), onTap: () => Navigator.pop(sheet, (to, false))),
            if (card.forwardTo.isNotEmpty && card.backwardTo.isNotEmpty) const Divider(height: 8),
            for (final to in card.backwardTo)
              ListTile(
                key: Key('back-$to'),
                leading: const Icon(Icons.arrow_back),
                title: Text('Request move back to ${label(board, to)}'),
                subtitle: const Text('Needs Designer Head approval'),
                onTap: () => Navigator.pop(sheet, (to, true)),
              ),
          ]),
        ),
      ),
    );

    if (choice == null || !context.mounted) return;
    if (choice.$1 == '__open') {
      context.push('/task/${card.id}');
    } else if (choice.$2) {
      await requestBackward(context, card, choice.$1, board);
    } else {
      await move(context, card, choice.$1);
    }
  }
}

/// A green check that pops in over the screen for a moment: the "it worked" beat after a forward move.
void showSuccessCheck(BuildContext context) {
  final overlay = Overlay.maybeOf(context);
  if (overlay == null) return;

  late final OverlayEntry entry;
  entry = OverlayEntry(builder: (_) => _SuccessCheck(onDone: () => entry.remove()));
  overlay.insert(entry);
}

class _SuccessCheck extends StatefulWidget {
  const _SuccessCheck({required this.onDone});

  final VoidCallback onDone;

  @override
  State<_SuccessCheck> createState() => _SuccessCheckState();
}

class _SuccessCheckState extends State<_SuccessCheck> with SingleTickerProviderStateMixin {
  // 300 ms pop in, a short hold, then a fade; one controller, no timers.
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 900))
    ..forward().whenComplete(() {
      if (mounted) widget.onDone();
    });

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => IgnorePointer(
        child: Center(
          child: AnimatedBuilder(
            animation: _c,
            builder: (context, _) {
              final pop = Curves.easeOutBack.transform((_c.value / 0.33).clamp(0.0, 1.0));
              final fade = 1 - ((_c.value - 0.66) / 0.34).clamp(0.0, 1.0);
              return Opacity(
                opacity: fade,
                child: Transform.scale(
                  scale: 0.5 + 0.5 * pop,
                  child: Container(
                    key: const Key('success-check'),
                    width: 84,
                    height: 84,
                    decoration: BoxDecoration(color: const Color(0xFF067647), shape: BoxShape.circle, boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.25), blurRadius: 18, offset: const Offset(0, 8))]),
                    child: const Icon(Icons.check_rounded, color: Colors.white, size: 52),
                  ),
                ),
              );
            },
          ),
        ),
      );
}

/// The web's "Are you sure you want to move this ticket?" modal for a backward move.
class BackwardDialog extends StatefulWidget {
  const BackwardDialog({super.key, required this.taskLabel, required this.from, required this.to});

  final String taskLabel;
  final String from;
  final String to;

  @override
  State<BackwardDialog> createState() => _BackwardDialogState();
}

class _BackwardDialogState extends State<BackwardDialog> {
  final _reason = TextEditingController();
  String? _error;

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  void _submit() {
    final text = _reason.text.trim();
    if (text.isEmpty) {
      setState(() => _error = 'Enter a reason for the backward movement.');
      return;
    }
    Navigator.pop(context, text);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return AlertDialog(
      title: const Text('Are you sure you want to move this ticket?'),
      content: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(widget.taskLabel, style: theme.textTheme.bodySmall),
          const SizedBox(height: 8),
          Text('${widget.from}  ↓  ${widget.to}', key: const Key('backward-transition'), style: theme.textTheme.labelLarge),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: const Color(0xFFFFF7F8), border: Border.all(color: const Color(0xFFF1D5D8)), borderRadius: BorderRadius.circular(10)),
            child: const Text('Backward status movement requires Designer Head approval. You cannot directly move the ticket backward.', style: TextStyle(fontSize: 11)),
          ),
          const SizedBox(height: 12),
          TextField(
            key: const Key('backward-reason'),
            controller: _reason,
            minLines: 3,
            maxLines: 5,
            textCapitalization: TextCapitalization.sentences,
            decoration: InputDecoration(labelText: 'Reason for backward movement *', hintText: 'Explain why this ticket needs to move back...', errorText: _error),
          ),
        ]),
      ),
      actions: [
        TextButton(key: const Key('backward-no'), onPressed: () => Navigator.pop(context), child: const Text('No')),
        FilledButton(key: const Key('backward-yes'), style: FilledButton.styleFrom(minimumSize: const Size(80, 40)), onPressed: _submit, child: const Text('Yes')),
      ],
    );
  }
}
