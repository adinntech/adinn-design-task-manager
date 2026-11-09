import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/auth.dart';
import '../../core/models.dart';
import '../../core/theme.dart';
import '../home/navigation.dart';
import 'providers.dart';

class BoardScreen extends ConsumerStatefulWidget {
  const BoardScreen({super.key});

  @override
  ConsumerState<BoardScreen> createState() => _BoardScreenState();
}

class _BoardScreenState extends ConsumerState<BoardScreen> {
  String _search = '';
  String? _status; // null = all

  @override
  void initState() {
    super.initState();
    // A dashboard card may have asked for a particular status before this screen existed.
    _applyFocus(ref.read(boardFocusProvider));
  }

  void _applyFocus(String? focus) {
    if (focus == null) return;
    _status = focus.isEmpty ? null : focus;
    // Consume the request so tapping the same card again later still registers as a change.
    Future.microtask(() {
      if (mounted) ref.read(boardFocusProvider.notifier).focus(null);
    });
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(boardFocusProvider, (_, focus) {
      if (focus != null) setState(() => _applyFocus(focus));
    });
    final board = ref.watch(boardProvider(_search));

    final isBd = ref.watch(authProvider.select((s) => s.user?.role)) == 'bd';

    return Scaffold(
      floatingActionButton: isBd
          ? FloatingActionButton.extended(
              key: const Key('new-task'),
              onPressed: () => context.push('/create'),
              icon: const Icon(Icons.add),
              label: const Text('New task'),
            )
          : null,
      appBar: AppBar(
        title: Text(ref.watch(authProvider.select((u) => u.user?.role)) == 'bd' ? 'Assigned Tasks' : 'Tasks'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(60),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
            child: TextField(
              key: const Key('task-search'),
              textInputAction: TextInputAction.search,
              decoration: const InputDecoration(hintText: 'Search tasks, clients, IDs', prefixIcon: Icon(Icons.search), isDense: true),
              onSubmitted: (v) => setState(() => _search = v.trim()),
            ),
          ),
        ),
      ),
      body: board.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => _ErrorView(message: '$e', onRetry: () => ref.invalidate(boardProvider(_search))),
        data: (data) {
          final visible = _status == null ? data.tasks : data.inStatus(_status!);
          final columns = data.statuses.where((c) => data.inStatus(c.key).isNotEmpty).toList();

          return Column(
            children: [
              SizedBox(
                height: 52,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  children: [
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(label: Text('All (${data.tasks.length})'), selected: _status == null, onSelected: (_) => setState(() => _status = null)),
                    ),
                    for (final c in columns)
                      Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: ChoiceChip(
                          label: Text('${c.label} (${data.inStatus(c.key).length})'),
                          selected: _status == c.key,
                          onSelected: (_) => setState(() => _status = c.key),
                        ),
                      ),
                  ],
                ),
              ),
              Expanded(
                child: RefreshIndicator(
                  onRefresh: () async {
                    ref.invalidate(boardProvider(_search));
                    await ref.read(boardProvider(_search).future);
                  },
                  child: visible.isEmpty
                      ? ListView(children: const [SizedBox(height: 120), Center(child: Text('No tasks here.'))])
                      : ListView.separated(
                          padding: const EdgeInsets.all(16),
                          itemCount: visible.length,
                          separatorBuilder: (_, _) => const SizedBox(height: 10),
                          itemBuilder: (_, i) => TaskCard(task: visible[i]),
                        ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class TaskCard extends StatelessWidget {
  const TaskCard({super.key, required this.task});

  final TaskSummary task;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final due = task.dueAt == null ? null : DateFormat('d MMM, h:mm a').format(task.dueAt!);

    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () => context.push('/task/${task.id}'),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text(task.taskId, style: theme.textTheme.labelMedium?.copyWith(color: Colors.black54)),
                  const Spacer(),
                  _Pill(task.priority.toUpperCase(), priorityColor(task.priority)),
                ],
              ),
              const SizedBox(height: 4),
              Text(task.name, style: theme.textTheme.titleMedium, maxLines: 2, overflow: TextOverflow.ellipsis),
              if (task.party != null) Text(task.party!, style: theme.textTheme.bodySmall),
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 6,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  _Pill(statusLabel(task.status), statusColor(task.status)),
                  if (due != null)
                    Row(mainAxisSize: MainAxisSize.min, children: [
                      Icon(Icons.schedule, size: 14, color: task.isOverdue ? theme.colorScheme.error : Colors.black54),
                      const SizedBox(width: 4),
                      Text(due, style: theme.textTheme.bodySmall?.copyWith(color: task.isOverdue ? theme.colorScheme.error : null)),
                    ]),
                  if (task.designerName != null)
                    Row(mainAxisSize: MainAxisSize.min, children: [
                      const Icon(Icons.person_outline, size: 14, color: Colors.black54),
                      const SizedBox(width: 4),
                      Text(task.designerName!, style: theme.textTheme.bodySmall),
                    ]),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill(this.text, this.color);

  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
        child: Text(text, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600)),
      );
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: onRetry, child: const Text('Try again')),
          ]),
        ),
      );
}
