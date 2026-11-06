import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/theme.dart';
import '../tasks/providers.dart';
import 'board_toolbar.dart';
import 'kanban_card.dart';
import 'kanban_models.dart';
import 'stage3d/stage_card.dart';
import 'stage3d/stage_meta.dart';
import 'ticket_actions.dart';

/// Opens a stage's tickets. The tapped stage card flies up into this screen's header (a Hero) while the overview behind
/// it lifts away and fades; going back plays the same thing in reverse.
Future<void> openStage(BuildContext context, String stageKey, {int? flashCard}) => Navigator.of(context).push<void>(
      PageRouteBuilder<void>(
        settings: RouteSettings(name: 'stage/$stageKey'),
        transitionDuration: const Duration(milliseconds: 500),
        reverseTransitionDuration: const Duration(milliseconds: 450),
        pageBuilder: (_, _, _) => StageTicketsScreen(stageKey: stageKey, flashCardId: flashCard),
        transitionsBuilder: (_, animation, _, child) => FadeTransition(
          opacity: CurvedAnimation(parent: animation, curve: const Interval(0.2, 1.0, curve: Curves.easeOut), reverseCurve: const Interval(0.0, 0.7, curve: Curves.easeIn)),
          child: child,
        ),
      ),
    );

/// One stage's tickets (or every ticket, for [allStageKey]).
class StageTicketsScreen extends ConsumerStatefulWidget {
  const StageTicketsScreen({super.key, required this.stageKey, this.flashCardId});

  final String stageKey;

  /// A ticket to ring briefly and scroll to (the dashboard's "Open on board").
  final int? flashCardId;

  @override
  ConsumerState<StageTicketsScreen> createState() => _StageTicketsScreenState();
}

class _StageTicketsScreenState extends ConsumerState<StageTicketsScreen> {
  final _flashKey = GlobalKey();
  bool _searchOpen = false;
  bool _scrolledToFlash = false;

  @override
  void initState() {
    super.initState();
    _searchOpen = ref.read(kanbanFiltersProvider).search.isNotEmpty;
  }

  StageInfo _info(KanbanBoard b) {
    if (widget.stageKey == allStageKey) return StageInfo(key: allStageKey, label: 'All Tickets', count: b.cards.length);
    final c = b.columns.where((c) => c.key == widget.stageKey).firstOrNull;
    return StageInfo(key: widget.stageKey, label: c?.label ?? widget.stageKey, count: c == null ? 0 : b.countFor(c));
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(kanbanBoardProvider);
    final board = async.asData?.value;
    final actions = TicketActions(ref);

    return Scaffold(
      appBar: AppBar(
        title: const Text('My Tasks'),
        actions: [
          IconButton(
            key: const Key('kanban-search-toggle'),
            tooltip: 'Search',
            icon: Icon(_searchOpen ? Icons.search_off : Icons.search),
            onPressed: () => setState(() => _searchOpen = !_searchOpen || ref.read(kanbanFiltersProvider).search.isNotEmpty),
          ),
          BoardFilterButton(board: board),
        ],
      ),
      body: Column(children: [
        if (_searchOpen) const BoardSearchField(),
        if (board != null) AppliedFilters(board: board),
        if (board != null) SelectedStageHeader(info: _info(board), overdue: _overdue(board)),
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
                  OutlinedButton(onPressed: actions.refresh, child: const Text('Try again')),
                ]),
              ),
            ),
            data: (b) => _TicketsEntrance(child: _list(context, b, actions)),
          ),
        ),
      ]),
    );
  }

  int _overdue(KanbanBoard b) {
    final now = DateTime.now();
    bool overdue(KanbanCard c) => c.status != 'completed' && c.duePill(now) == DuePill.overdue;
    return widget.stageKey == allStageKey ? b.cards.where(overdue).length : b.inColumn(widget.stageKey).where(overdue).length;
  }

  Widget _list(BuildContext context, KanbanBoard b, TicketActions actions) {
    final isSplit = b.columns.any((c) => c.key == widget.stageKey && c.isSplitLog);
    final List<Widget> items;

    if (isSplit) {
      items = [
        if (b.splitLog.isEmpty)
          Padding(
            padding: const EdgeInsets.all(24),
            child: Text('No split requests in ${b.periodLabel}.', key: const Key('split-empty'), textAlign: TextAlign.center, style: const TextStyle(color: Colors.black45)),
          ),
        for (final s in b.splitLog) SplitLogCard(key: Key('split-${s.id}'), entry: s, onOpen: () => context.push('/task/${s.id}')),
      ];
    } else {
      var cards = widget.stageKey == allStageKey ? _pipelineOrder(b) : b.inColumn(widget.stageKey);
      if (widget.stageKey == 'assigned_tasks') {
        cards = [...cards]..sort((a, x) => (x.createdAt ?? DateTime(0)).compareTo(a.createdAt ?? DateTime(0)));
      }
      final now = DateTime.now();
      items = [
        if (cards.isEmpty) const Padding(padding: EdgeInsets.all(32), child: Center(child: Text('No tickets here.', key: Key('stage-empty'), style: TextStyle(color: Colors.black45)))),
        for (final c in cards) _ticket(context, c, b, now, actions),
      ];
    }

    if (widget.flashCardId != null && !_scrolledToFlash) {
      _scrolledToFlash = true;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        final ctx = _flashKey.currentContext;
        if (ctx != null) Scrollable.ensureVisible(ctx, duration: const Duration(milliseconds: 300), alignment: 0.2);
      });
    }

    return RefreshIndicator(
      onRefresh: () async {
        actions.refresh();
        await ref.read(kanbanBoardProvider.future);
      },
      child: SingleChildScrollView(
        key: const Key('ticket-list'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [for (final w in items) Padding(padding: const EdgeInsets.only(bottom: 8), child: w)]),
      ),
    );
  }

  /// Pipeline order first (so the list reads like the board), then the soonest deadline.
  List<KanbanCard> _pipelineOrder(KanbanBoard b) {
    final order = {for (var i = 0; i < b.columns.length; i++) b.columns[i].key: i};
    return [...b.cards]..sort((x, y) {
        final byStage = (order[x.status] ?? 999).compareTo(order[y.status] ?? 999);
        return byStage != 0 ? byStage : (x.dueAt ?? DateTime(9999)).compareTo(y.dueAt ?? DateTime(9999));
      });
  }

  Widget _ticket(BuildContext context, KanbanCard card, KanbanBoard b, DateTime now, TicketActions actions) {
    Widget tile = KanbanTaskCard(
      card: card,
      now: now,
      stageLabel: widget.stageKey == allStageKey ? actions.label(b, card.status) : null,
      onOpen: () => context.push('/task/${card.id}'),
      onMove: card.canMove ? () => actions.openMoveSheet(context, card, b) : null,
      onLongPress: () => actions.openMoveSheet(context, card, b),
    );

    if (card.id == widget.flashCardId) tile = _FlashRing(key: _flashKey, child: tile);

    final next = actions.nextStage(card, b);
    final canBack = card.backwardTo.isNotEmpty;
    if (next == null && !canBack) return KeyedSubtree(key: Key('card-${card.id}'), child: tile);

    // Right = move forward, left = request a move back. The action runs, then the card slides back; the board reload
    // is what actually moves it.
    return Dismissible(
      key: Key('card-${card.id}'),
      direction: next != null && canBack ? DismissDirection.horizontal : (next != null ? DismissDirection.startToEnd : DismissDirection.endToStart),
      dismissThresholds: const {DismissDirection.startToEnd: 0.3, DismissDirection.endToStart: 0.3},
      background: _SwipeHint(color: const Color(0xFF067647), icon: Icons.check_circle_outline, text: next == null ? '' : 'Move to ${actions.label(b, next)}', alignment: Alignment.centerLeft),
      secondaryBackground: const _SwipeHint(color: Color(0xFFB54708), icon: Icons.undo, text: 'Request move back', alignment: Alignment.centerRight),
      confirmDismiss: (direction) async {
        direction == DismissDirection.startToEnd ? actions.swipeAdvance(context, card, b) : actions.swipeBack(context, card, b);
        return false;
      },
      child: tile,
    );
  }
}

/// The selected stage as a card across the top: the other end of the Hero the stage card flew in as.
class SelectedStageHeader extends StatelessWidget {
  const SelectedStageHeader({super.key, required this.info, this.overdue = 0});

  final StageInfo info;
  final int overdue;

  @override
  Widget build(BuildContext context) {
    final accent = stageAccent(info.key);

    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
      child: Hero(
        tag: 'stage-card-${info.key}',
        flightShuttleBuilder: stageHeroShuttle(info),
        child: Material(
          color: Colors.transparent,
          child: Container(
            key: const Key('stage-header'),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: stageCardDecoration(accent, radius: 18),
            child: Row(children: [
              Container(width: 44, height: 44, decoration: BoxDecoration(color: accent.withValues(alpha: 0.12), shape: BoxShape.circle), child: Icon(stageIcon(info.key), color: accent)),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(info.label, key: const Key('stage-title'), maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF111111))),
                  Text(overdue > 0 ? '$overdue overdue' : 'Nothing overdue', style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600, color: overdue > 0 ? const Color(0xFFB42318) : const Color(0xFF667085))),
                ]),
              ),
              Text('${info.count}', key: const Key('stage-total'), style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w900, color: adinnRed)),
            ]),
          ),
        ),
      ),
    );
  }
}

/// The ticket list rises into place once the stage card has landed (400 ms), and sinks away on the way back.
class _TicketsEntrance extends StatelessWidget {
  const _TicketsEntrance({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final route = ModalRoute.of(context)?.animation ?? kAlwaysCompleteAnimation;

    return AnimatedBuilder(
      animation: route,
      builder: (context, child) {
        final t = const Interval(0.45, 1.0, curve: Curves.easeOutCubic).transform(route.value);
        return Opacity(opacity: t, child: Transform.translate(offset: Offset(0, (1 - t) * 36), child: child));
      },
      child: child,
    );
  }
}

/// What a swiped ticket reveals behind it.
class _SwipeHint extends StatelessWidget {
  const _SwipeHint({required this.color, required this.icon, required this.text, required this.alignment});

  final Color color;
  final IconData icon;
  final String text;
  final Alignment alignment;

  @override
  Widget build(BuildContext context) => Container(
        alignment: alignment,
        padding: const EdgeInsets.symmetric(horizontal: 18),
        decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(14)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon, color: Colors.white),
          const SizedBox(width: 8),
          Flexible(child: Text(text, maxLines: 2, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12))),
        ]),
      );
}

/// A ring that shows for a moment around one ticket, then fades (one controller, no timer).
class _FlashRing extends StatefulWidget {
  const _FlashRing({super.key, required this.child});

  final Widget child;

  @override
  State<_FlashRing> createState() => _FlashRingState();
}

class _FlashRingState extends State<_FlashRing> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 2500))..forward();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
        animation: _c,
        child: widget.child,
        builder: (context, child) {
          final show = _c.value < 0.85;
          return Container(
            foregroundDecoration: show ? BoxDecoration(borderRadius: BorderRadius.circular(14), border: Border.all(color: Theme.of(context).colorScheme.primary, width: 3)) : null,
            child: child,
          );
        },
      );
}
