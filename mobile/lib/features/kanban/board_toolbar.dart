import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../tasks/providers.dart';
import 'kanban_filters_sheet.dart';
import 'kanban_models.dart';

/// The search box for the board. It searches on the server (Task ID, project number, name, client, vertical, BD)
/// a moment after you stop typing, and stays in step with the filters if they are cleared elsewhere.
class BoardSearchField extends ConsumerStatefulWidget {
  const BoardSearchField({super.key});

  @override
  ConsumerState<BoardSearchField> createState() => _BoardSearchFieldState();
}

class _BoardSearchFieldState extends ConsumerState<BoardSearchField> {
  late final _controller = TextEditingController(text: ref.read(kanbanFiltersProvider).search);
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    super.dispose();
  }

  void _onChanged(String v) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (mounted) ref.read(kanbanFiltersProvider.notifier).update(ref.read(kanbanFiltersProvider).copyWith(search: v));
    });
  }

  @override
  Widget build(BuildContext context) {
    // The filter sheet or "Clear Filters" can change the search text.
    ref.listen(kanbanFiltersProvider.select((f) => f.search), (_, search) {
      if (_controller.text != search) _controller.text = search;
    });

    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
      child: TextField(
        key: const Key('kanban-search'),
        controller: _controller,
        onChanged: _onChanged,
        autofocus: _controller.text.isEmpty,
        textInputAction: TextInputAction.search,
        decoration: const InputDecoration(hintText: 'Search Task ID, project number, task, client, vertical, BD…', prefixIcon: Icon(Icons.search), isDense: true),
      ),
    );
  }
}

/// "Applied Filters: …  Clear Filters", as on the web.
class AppliedFilters extends ConsumerWidget {
  const AppliedFilters({super.key, required this.board});

  final KanbanBoard board;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final filters = ref.watch(kanbanFiltersProvider);
    final chips = appliedChips(filters, board.bds, board.periodLabel);
    if (chips.isEmpty) return const SizedBox.shrink();

    return Padding(
      key: const Key('applied-filters'),
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 4),
      child: Wrap(spacing: 6, runSpacing: 0, crossAxisAlignment: WrapCrossAlignment.center, children: [
        const Text('Applied Filters:', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800)),
        for (final c in chips) Chip(label: Text('${c.label}: ${c.value}', style: const TextStyle(fontSize: 11)), visualDensity: VisualDensity.compact, materialTapTargetSize: MaterialTapTargetSize.shrinkWrap),
        TextButton(key: const Key('clear-filters-chip'), onPressed: () => ref.read(kanbanFiltersProvider.notifier).clear(), child: const Text('Clear Filters')),
      ]),
    );
  }
}

Future<void> openBoardFilters(BuildContext context, WidgetRef ref, KanbanBoard? board) async {
  final next = await showKanbanFilters(context, current: ref.read(kanbanFiltersProvider), bds: board?.bds ?? const []);
  if (next != null) ref.read(kanbanFiltersProvider.notifier).update(next);
}

/// The filter button, with a dot when any filter other than the search text is on.
class BoardFilterButton extends ConsumerWidget {
  const BoardFilterButton({super.key, required this.board});

  final KanbanBoard? board;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final filters = ref.watch(kanbanFiltersProvider);

    return IconButton(
      key: const Key('kanban-filters'),
      tooltip: 'Filters',
      icon: Badge(isLabelVisible: !filters.copyWith(search: '').isDefault, smallSize: 9, child: const Icon(Icons.tune)),
      onPressed: () => openBoardFilters(context, ref, board),
    );
  }
}
