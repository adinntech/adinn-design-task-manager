import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api_client.dart';
import '../../core/theme.dart';
import '../home/navigation.dart';
import '../tasks/providers.dart';
import 'kanban_card.dart';
import 'kanban_filters_sheet.dart';
import 'kanban_models.dart';

/// Designer "My Tasks". The web board's columns keep their order and names, but on a phone you see one column
/// at a time: swipe between them or tap a tab in the strip. Each card has a "Move to…" button (the main way to
/// change a status); long-press-and-drag onto a tab, or to the screen edge, works as an extra. What may move where
/// is decided by the server's state machine (sent on each card), and the server still has the last word.
class KanbanScreen extends ConsumerStatefulWidget {
  const KanbanScreen({super.key});

  @override
  ConsumerState<KanbanScreen> createState() => _KanbanScreenState();
}

class _KanbanScreenState extends ConsumerState<KanbanScreen> {
  final _search = TextEditingController();
  final _pages = PageController();
  final _tabScroll = ScrollController();
  final _tabKeys = <String, GlobalKey>{};

  Timer? _debounce;
  Timer? _edgeTimer;
  Timer? _highlightTimer;
  Offset? _dragAt;
  DateTime _lastEdgeSwitch = DateTime.fromMillisecondsSinceEpoch(0);

  String? _selected;
  String? _highlight;
  String? _pendingFocus;
  List<KanbanColumn> _visible = const [];

  @override
  void initState() {
    super.initState();
    _search.text = ref.read(kanbanFiltersProvider).search;
    _takeFocus(ref.read(boardFocusProvider));
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _edgeTimer?.cancel();
    _highlightTimer?.cancel();
    _search.dispose();
    _pages.dispose();
    _tabScroll.dispose();
    super.dispose();
  }

  // ---- selection ------------------------------------------------------------------------------

  int get _selectedIndex => _visible.indexWhere((c) => c.key == _selected);

  void _select(String key, {bool animate = true}) {
    final index = _visible.indexWhere((c) => c.key == key);
    if (index < 0) return;
    setState(() => _selected = key);

    if (_pages.hasClients && (_pages.page ?? 0).round() != index) {
      animate ? _pages.animateToPage(index, duration: const Duration(milliseconds: 280), curve: Curves.easeOut) : _pages.jumpToPage(index);
    }
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final ctx = _tabKeys[key]?.currentContext;
      if (ctx != null) Scrollable.ensureVisible(ctx, duration: const Duration(milliseconds: 220), alignment: 0.5);
    });
  }

  /// Keeps the selection valid as data or filters change.
  void _syncSelection(KanbanBoard board) {
    final keys = _visible.map((c) => c.key).toSet();
    if (_selected == null || !keys.contains(_selected)) {
      // Normally land on All Tickets. While searching that tab is hidden, so land on the first stage with a match.
      final firstWithCards = _visible.where((c) => board.countFor(c) > 0).map((c) => c.key).firstOrNull;
      _selected = keys.contains(KanbanColumn.allKey) ? KanbanColumn.allKey : (firstWithCards ?? _visible.firstOrNull?.key);
    }

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || !_pages.hasClients) return;
      final index = _selectedIndex;
      if (index >= 0 && (_pages.page ?? 0).round() != index) _pages.jumpToPage(index);
    });
  }

  /// A dashboard card asked for a status: select that column's tab and flash it.
  void _takeFocus(String? focus) {
    if (focus == null) return;
    _pendingFocus = focus.isEmpty ? KanbanColumn.allKey : focus; // '' = "show everything"
    Future.microtask(() {
      if (mounted) ref.read(boardFocusProvider.notifier).focus(null);
    });
    WidgetsBinding.instance.addPostFrameCallback((_) => _showPendingFocus());
  }

  void _showPendingFocus() {
    final key = _pendingFocus;
    if (!mounted || key == null || !_visible.any((c) => c.key == key)) return; // retried when the data arrives
    _pendingFocus = null;
    _select(key);
    setState(() => _highlight = key);
    _highlightTimer?.cancel();
    _highlightTimer = Timer(const Duration(milliseconds: 1800), () {
      if (mounted) setState(() => _highlight = null);
    });
  }

  // ---- actions --------------------------------------------------------------------------------

  void _onSearch(String v) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (mounted) ref.read(kanbanFiltersProvider.notifier).update(ref.read(kanbanFiltersProvider).copyWith(search: v));
    });
  }

  void _toast(String message) => ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));

  void _refresh() {
    ref.invalidate(kanbanBoardProvider);
    ref.invalidate(boardProvider);
  }

  String _label(KanbanBoard board, String key) => board.columns.where((c) => c.key == key).map((c) => c.label).firstOrNull ?? key;

  Future<void> _move(KanbanCard card, String to) async {
    try {
      await ref.read(roleApiProvider).moveStatus(card.id, to);
      _refresh();
      if (mounted) _toast('Task status updated successfully.');
    } on ApiException catch (e) {
      // e.g. "Creative Work Incomplete: Complete all creatives before BD Review. 1 of 4 completed • 3 remaining."
      if (mounted) _toast(e.message);
    }
  }

  Future<void> _backward(KanbanCard card, String to, KanbanBoard board) async {
    final reason = await showDialog<String>(
      context: context,
      builder: (_) => _BackwardDialog(taskLabel: '${card.taskId} — ${card.name}', from: _label(board, card.status), to: _label(board, to)),
    );
    if (reason == null || !mounted) return;

    try {
      await ref.read(roleApiProvider).requestBackward(card.id, toStatus: to, reason: reason);
      _refresh();
      if (mounted) _toast('Backward status request submitted — waiting for Designer Head approval.');
    } on ApiException catch (e) {
      if (mounted) _toast(e.message);
    }
  }

  /// The "Move to…" sheet: forward moves happen straight away, a move back asks for a reason first.
  Future<void> _openMoveSheet(KanbanCard card, KanbanBoard board) async {
    final choice = await showModalBottomSheet<(String, bool)>(
      context: context,
      showDragHandle: true,
      builder: (_) => SafeArea(
        child: SingleChildScrollView(
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
              child: Text('Move ${card.taskId}', style: Theme.of(context).textTheme.titleMedium),
            ),
            for (final to in card.forwardTo)
              ListTile(key: Key('move-$to'), leading: const Icon(Icons.arrow_forward), title: Text('Move to ${_label(board, to)}'), onTap: () => Navigator.pop(context, (to, false))),
            if (card.backwardTo.isNotEmpty) const Divider(height: 8),
            for (final to in card.backwardTo)
              ListTile(
                key: Key('back-$to'),
                leading: const Icon(Icons.arrow_back),
                title: Text('Request move back to ${_label(board, to)}'),
                subtitle: const Text('Needs Designer Head approval'),
                onTap: () => Navigator.pop(context, (to, true)),
              ),
          ]),
        ),
      ),
    );

    if (choice == null || !mounted) return;
    choice.$2 ? _backward(card, choice.$1, board) : _move(card, choice.$1);
  }

  void _drop(KanbanCard card, KanbanColumn target, KanbanBoard board) {
    if (target.isSplitLog || target.isAll || target.key == card.status) return;

    if (card.forwardTo.contains(target.key)) {
      _move(card, target.key);
    } else if (card.backwardTo.contains(target.key)) {
      _backward(card, target.key, board);
    } else {
      _toast("This task can't be moved to ${target.label}.");
    }
  }

  /// While a card is held near the screen's left/right edge the board turns to the neighbouring column.
  void _trackDrag({required bool active}) {
    if (!active) {
      _dragAt = null;
      _edgeTimer?.cancel();
      _edgeTimer = null;
      return;
    }

    // Until the finger has actually moved there is no position, so a card that is merely held never counts as
    // being "at the edge".
    _edgeTimer ??= Timer.periodic(const Duration(milliseconds: 120), (_) {
      final pos = _dragAt;
      if (pos == null || !mounted || DateTime.now().difference(_lastEdgeSwitch) < const Duration(milliseconds: 800)) return;
      final width = MediaQuery.sizeOf(context).width;
      final i = _selectedIndex;
      if (pos.dx < 48 && i > 0) {
        _lastEdgeSwitch = DateTime.now();
        _select(_visible[i - 1].key);
      } else if (pos.dx > width - 48 && i >= 0 && i < _visible.length - 1) {
        _lastEdgeSwitch = DateTime.now();
        _select(_visible[i + 1].key);
      }
    });
  }

  Future<void> _openFilters(KanbanBoard? board) async {
    final next = await showKanbanFilters(context, current: ref.read(kanbanFiltersProvider), bds: board?.bds ?? const []);
    if (next != null) {
      _search.text = next.search;
      ref.read(kanbanFiltersProvider.notifier).update(next);
    }
  }

  // ---- build ----------------------------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    ref.listen(boardFocusProvider, (_, focus) {
      if (focus != null) _takeFocus(focus);
    });

    final filters = ref.watch(kanbanFiltersProvider);
    final async = ref.watch(kanbanBoardProvider);
    final board = async.asData?.value;

    return Scaffold(
      appBar: AppBar(
        title: const Text('My Tasks'),
        actions: [
          if (board != null)
            Padding(padding: const EdgeInsets.only(right: 4), child: Center(child: Text('${board.cards.length} visible', key: const Key('visible-count'), style: Theme.of(context).textTheme.labelMedium))),
          IconButton(key: const Key('kanban-refresh'), tooltip: 'Refresh', icon: const Icon(Icons.refresh), onPressed: _refresh),
          IconButton(
            key: const Key('kanban-filters'),
            tooltip: 'Filters',
            icon: Badge(isLabelVisible: !filters.copyWith(search: '').isDefault, smallSize: 9, child: const Icon(Icons.tune)),
            onPressed: () => _openFilters(board),
          ),
        ],
      ),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
          child: TextField(
            key: const Key('kanban-search'),
            controller: _search,
            onChanged: _onSearch,
            textInputAction: TextInputAction.search,
            decoration: const InputDecoration(hintText: 'Search Task ID, project number, task, client, vertical, BD…', prefixIcon: Icon(Icons.search), isDense: true),
          ),
        ),
        if (board != null)
          _AppliedFilters(
            filters: filters,
            board: board,
            onClear: () {
              _search.clear();
              ref.read(kanbanFiltersProvider.notifier).clear();
            },
          ),
        Expanded(
          child: async.when(
            skipLoadingOnRefresh: true,
            skipLoadingOnReload: true,
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) => Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Text('$e', textAlign: TextAlign.center),
                  const SizedBox(height: 12),
                  OutlinedButton(onPressed: _refresh, child: const Text('Try again')),
                ]),
              ),
            ),
            data: (b) {
              // While a text search is active the web hides empty columns.
              // "All Tickets" is the overview; while you search, matches are shown under the stage they are in instead,
              // and stages with no match are hidden (as on the web).
              _visible = [
                if (!filters.isSearching) KanbanColumn.all(),
                for (final c in b.columns) if (!filters.isSearching || b.countFor(c) > 0) c,
              ];
              _syncSelection(b);
              WidgetsBinding.instance.addPostFrameCallback((_) => _showPendingFocus());

              if (_visible.isEmpty) return const Center(child: Text('No tasks match.', key: Key('board-empty')));

              return Column(children: [
                _Summary(b),
                _tabs(b),
                Expanded(child: _pager(b)),
              ]);
            },
          ),
        ),
      ]),
    );
  }

  Widget _tabs(KanbanBoard b) {
    return SizedBox(
      height: 52,
      child: ListView.separated(
        key: const Key('kanban-tabs'),
        controller: _tabScroll,
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        itemCount: _visible.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (_, i) {
          final c = _visible[i];
          final accent = c.isAll ? Theme.of(context).colorScheme.primary : (c.isSplitLog ? const Color(0xFF7C3AED) : statusColor(c.key));
          final selected = c.key == _selected;
          final flashing = c.key == _highlight;

          // A card can be dropped straight onto a tab to move it to that column.
          return DragTarget<KanbanCard>(
            key: _tabKeys.putIfAbsent(c.key, GlobalKey.new),
            onWillAcceptWithDetails: (_) => true,
            onAcceptWithDetails: (d) => _drop(d.data, c, b),
            builder: (context, candidates, _) {
              final dragged = candidates.isEmpty ? null : candidates.first;
              final allowed = dragged != null && (dragged.forwardTo.contains(c.key) || dragged.backwardTo.contains(c.key));
              final ring = dragged == null ? (flashing ? accent : null) : (allowed ? const Color(0xFF2E7D32) : const Color(0xFFB00020));

              return ChoiceChip(
                key: Key('tab-${c.key}'),
                selected: selected,
                showCheckmark: false,
                selectedColor: accent.withValues(alpha: 0.16),
                side: BorderSide(color: ring ?? (selected ? accent : Colors.black12), width: ring != null ? 2.5 : 1),
                label: Row(mainAxisSize: MainAxisSize.min, children: [
                  Text(c.label, key: Key('title-${c.key}'), style: TextStyle(fontWeight: selected ? FontWeight.w800 : FontWeight.w600)),
                  const SizedBox(width: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                    decoration: BoxDecoration(color: accent.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(10)),
                    child: Text('${b.countFor(c)}', key: Key('count-${c.key}'), style: TextStyle(color: accent, fontWeight: FontWeight.w800, fontSize: 11)),
                  ),
                ]),
                onSelected: (_) => _select(c.key),
              );
            },
          );
        },
      ),
    );
  }

  Widget _pager(KanbanBoard b) {
    final now = DateTime.now();

    return PageView.builder(
      key: const Key('kanban-pages'),
      controller: _pages,
      itemCount: _visible.length,
      onPageChanged: (i) {
        if (i < _visible.length && _visible[i].key != _selected) {
          setState(() => _selected = _visible[i].key);
          final ctx = _tabKeys[_selected]?.currentContext;
          if (ctx != null) Scrollable.ensureVisible(ctx, duration: const Duration(milliseconds: 220), alignment: 0.5);
        }
      },
      itemBuilder: (_, i) {
        final column = _visible[i];

        return _KeepAlive(
            child: DragTarget<KanbanCard>(
          onWillAcceptWithDetails: (_) => true,
          onAcceptWithDetails: (d) => _drop(d.data, column, b),
          builder: (context, candidates, _) {
            final dragged = candidates.isEmpty ? null : candidates.first;
            final allowed = dragged != null && (dragged.forwardTo.contains(column.key) || dragged.backwardTo.contains(column.key));

            return AnimatedContainer(
              key: Key('page-${column.key}'),
              duration: const Duration(milliseconds: 150),
              margin: const EdgeInsets.fromLTRB(12, 0, 12, 12),
              decoration: BoxDecoration(
                color: dragged == null ? Colors.transparent : (allowed ? const Color(0xFFEFFAF1) : const Color(0xFFFFF1F0)),
                borderRadius: BorderRadius.circular(16),
                border: dragged == null ? null : Border.all(color: allowed ? const Color(0xFF2E7D32) : const Color(0xFFB00020), width: 2),
              ),
              child: _columnBody(context, column, b, now),
            );
          },
        ));
      },
    );
  }

  Widget _columnBody(BuildContext context, KanbanColumn column, KanbanBoard b, DateTime now) {
    if (column.isSplitLog) {
      if (b.splitLog.isEmpty) {
        return Center(child: Padding(padding: const EdgeInsets.all(24), child: Text('No splits approved in ${b.periodLabel}.', key: const Key('split-empty'), textAlign: TextAlign.center, style: const TextStyle(color: Colors.black45))));
      }
      return ListView.separated(
        padding: const EdgeInsets.only(top: 4, bottom: 16),
        itemCount: b.splitLog.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, i) => SplitLogCard(key: Key('split-${b.splitLog[i].id}'), entry: b.splitLog[i], onOpen: () => context.push('/task/${b.splitLog[i].id}')),
      );
    }

    if (column.isAll) {
      if (b.cards.isEmpty) {
        return const Center(child: Padding(padding: EdgeInsets.all(24), child: Text('No tasks', style: TextStyle(color: Colors.black45))));
      }

      // Pipeline order first (so the list reads like the board), then the soonest deadline.
      final order = {for (var i = 0; i < b.columns.length; i++) b.columns[i].key: i};
      final all = [...b.cards]..sort((x, y) {
          final byStage = (order[x.status] ?? 999).compareTo(order[y.status] ?? 999);
          return byStage != 0 ? byStage : (x.dueAt ?? DateTime(9999)).compareTo(y.dueAt ?? DateTime(9999));
        });

      return ListView.separated(
        padding: const EdgeInsets.only(top: 4, bottom: 16),
        itemCount: all.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, i) => _draggable(context, all[i], b, now, stageLabel: _label(b, all[i].status)),
      );
    }

    var cards = b.inColumn(column.key);
    if (column.key == 'assigned_tasks') {
      cards = [...cards]..sort((a, c) => (c.createdAt ?? DateTime(0)).compareTo(a.createdAt ?? DateTime(0)));
    }

    if (cards.isEmpty) {
      return const Center(child: Padding(padding: EdgeInsets.all(24), child: Text('No tasks', style: TextStyle(color: Colors.black45))));
    }

    return ListView.separated(
      padding: const EdgeInsets.only(top: 4, bottom: 16),
      itemCount: cards.length,
      separatorBuilder: (_, _) => const SizedBox(height: 10),
      itemBuilder: (_, i) => _draggable(context, cards[i], b, now),
    );
  }

  Widget _draggable(BuildContext context, KanbanCard card, KanbanBoard b, DateTime now, {String? stageLabel}) {
    final tile = KanbanTaskCard(card: card, now: now, stageLabel: stageLabel, onOpen: () => context.push('/task/${card.id}'), onMove: card.canMove ? () => _openMoveSheet(card, b) : null);

    if (!card.canMove) return KeyedSubtree(key: Key('card-${card.id}'), child: tile);

    return LongPressDraggable<KanbanCard>(
      key: Key('card-${card.id}'),
      data: card,
      onDragStarted: () => _trackDrag(active: true),
      onDragUpdate: (d) => _dragAt = d.globalPosition,
      onDragEnd: (_) => _trackDrag(active: false),
      onDraggableCanceled: (_, _) => _trackDrag(active: false),
      feedback: Material(
        color: Colors.transparent,
        child: SizedBox(width: MediaQuery.sizeOf(context).width - 40, child: Opacity(opacity: 0.92, child: tile)),
      ),
      childWhenDragging: Opacity(opacity: 0.35, child: tile),
      child: tile,
    );
  }
}

class _Summary extends StatelessWidget {
  const _Summary(this.board);

  final KanbanBoard board;

  @override
  Widget build(BuildContext context) {
    Widget metric(String key, String label, int value, {Widget? extra, double width = 118}) => SizedBox(
          width: width,
          child: Card(
            child: Padding(
              padding: const EdgeInsets.all(10),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                Text(label, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Colors.black54)),
                Text('$value', key: Key('metric-$key'), style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
                ?extra,
              ]),
            ),
          ),
        );

    final chips = board.activeBreakdown.where((r) => r.count > 0);

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.fromLTRB(12, 4, 12, 4),
      // IntrinsicHeight: every card takes the height of the tallest, however many status chips the Active card wraps to.
      child: IntrinsicHeight(
        child: Row(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          metric('total', 'Total Tasks', board.stats['total'] ?? 0),
          const SizedBox(width: 8),
          metric(
            'active',
            'Active',
            board.stats['active'] ?? 0,
            width: chips.isEmpty ? 118 : 300,
            extra: chips.isEmpty
                ? null
                : Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Wrap(spacing: 4, runSpacing: 4, children: [
                      for (final r in chips)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(color: const Color(0xFFF2F4F7), borderRadius: BorderRadius.circular(10)),
                          child: Text('${r.label}: ${r.count}', style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w700)),
                        ),
                    ]),
                  ),
          ),
          const SizedBox(width: 8),
          metric('waiting', 'Waiting Confirmation', board.stats['waiting'] ?? 0, width: 140),
          const SizedBox(width: 8),
          metric('completed', 'Completed', board.stats['completed'] ?? 0),
        ]),
      ),
    );
  }
}

class _AppliedFilters extends StatelessWidget {
  const _AppliedFilters({required this.filters, required this.board, required this.onClear});

  final BoardFilters filters;
  final KanbanBoard board;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final chips = appliedChips(filters, board.bds, board.periodLabel);
    if (chips.isEmpty) return const SizedBox.shrink();

    return Padding(
      key: const Key('applied-filters'),
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 4),
      child: Wrap(spacing: 6, runSpacing: 0, crossAxisAlignment: WrapCrossAlignment.center, children: [
        const Text('Applied Filters:', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800)),
        for (final c in chips) Chip(label: Text('${c.label}: ${c.value}', style: const TextStyle(fontSize: 11)), visualDensity: VisualDensity.compact, materialTapTargetSize: MaterialTapTargetSize.shrinkWrap),
        TextButton(key: const Key('clear-filters-chip'), onPressed: onClear, child: const Text('Clear Filters')),
      ]),
    );
  }
}

/// The web's "Are you sure you want to move this ticket?" modal for a backward move.
class _BackwardDialog extends StatefulWidget {
  const _BackwardDialog({required this.taskLabel, required this.from, required this.to});

  final String taskLabel;
  final String from;
  final String to;

  @override
  State<_BackwardDialog> createState() => _BackwardDialogState();
}

class _BackwardDialogState extends State<_BackwardDialog> {
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


/// Keeps a column page mounted after you leave it. A card being dragged lives in its page, and turning the page at
/// the screen edge would otherwise dispose the page — and drop the card — mid-drag.
class _KeepAlive extends StatefulWidget {
  const _KeepAlive({required this.child});

  final Widget child;

  @override
  State<_KeepAlive> createState() => _KeepAliveState();
}

class _KeepAliveState extends State<_KeepAlive> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return widget.child;
  }
}
