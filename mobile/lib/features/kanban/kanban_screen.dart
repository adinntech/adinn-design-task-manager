import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/theme.dart';
import '../home/navigation.dart';
import '../tasks/providers.dart';
import 'board_toolbar.dart';
import 'kanban_card.dart';
import 'kanban_models.dart';
import 'stage3d/designer_character.dart';
import 'stage3d/held_card.dart';
import 'stage3d/stage_meta.dart';
import 'stage_tickets_screen.dart';
import 'stage_tiles.dart';
import 'ticket_actions.dart';

/// Designer "My Tasks". The Adinn representative fills the screen holding a square card for one stage (All Tickets
/// first), with the rest of the stack peeking out behind it. Swipe the card, or use the arrows, to turn to the next stage;
/// tap it to see that stage's tickets (the card flies into the header of the list, [StageTicketsScreen]).
///
/// A slight drag up steps the man back and brings a small tile for every workflow stage up into the space under his
/// feet. Tapping a tile puts that stage's card in his hands.
class KanbanScreen extends ConsumerStatefulWidget {
  const KanbanScreen({super.key});

  @override
  ConsumerState<KanbanScreen> createState() => _KanbanScreenState();
}

class _KanbanScreenState extends ConsumerState<KanbanScreen> with TickerProviderStateMixin {
  // The introduction, as one timeline (1400 ms) so there is a single controller:
  //   character fades and scales in   0 – 500 ms
  //   card stack moves into place   140 – 980 ms (600 ms)
  //   camera zoom onto the card      490 – 1190 ms (700 ms)
  //   edges fan out                  700 – 1330 ms (450 ms)
  //   count scales in               1050 – 1400 ms
  static const _introDuration = Duration(milliseconds: 1400);
  late final AnimationController _intro = AnimationController(vsync: this, duration: _introDuration);

  late final Animation<double> _character = CurvedAnimation(parent: _intro, curve: const Interval(0.0, 0.36, curve: Curves.easeOutCubic));
  late final Animation<double> _stackIn = CurvedAnimation(parent: _intro, curve: const Interval(0.10, 0.70, curve: Curves.easeOutCubic));
  late final Animation<double> _camera = CurvedAnimation(parent: _intro, curve: const Interval(0.35, 0.85, curve: Curves.easeInOutCubic));
  late final Animation<double> _fan = CurvedAnimation(parent: _intro, curve: const Interval(0.50, 0.95, curve: Curves.easeOutCubic));
  late final Animation<double> _count = CurvedAnimation(parent: _intro, curve: const Interval(0.75, 1.0));

  /// 0 → the man fills the screen; 1 → he has stepped back and the stage tiles are up (350 ms).
  late final AnimationController _panel = AnimationController(vsync: this, duration: const Duration(milliseconds: 350));
  double _panelDragStart = 0;

  /// Which stage's card is on top of the stack in his hands, and the turn in progress (0 → 1) to the next or the
  /// previous one: the top card slides away while the one beneath rises out of the stack.
  late final AnimationController _turn = AnimationController(vsync: this, duration: const Duration(milliseconds: 320));
  String _currentKey = allStageKey;
  int _turnTo = -1;
  int _turnDir = 1; // 1 = on to the next stage (card slides left), -1 = back (slides right)
  double _dragS = 0;
  List<StageInfo> _pagesNow = const [];

  bool _started = false;
  bool _searchOpen = false;
  bool _opening = false;
  String? _pendingFocus;
  int? _pendingTask;

  @override
  void initState() {
    super.initState();
    _searchOpen = ref.read(kanbanFiltersProvider).search.isNotEmpty;
    _pendingFocus = ref.read(boardFocusProvider);
    if (_pendingFocus != null) Future.microtask(() => ref.read(boardFocusProvider.notifier).focus(null));
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;

    // Decode the artwork once, up front, so the first frame of the introduction is not waiting on an image.
    precacheImage(const AssetImage(DesignerCharacterWidget.asset), context);
    precacheImage(const AssetImage(GrippingHand.leftAsset), context);
    precacheImage(const AssetImage(GrippingHand.rightAsset), context);

    if (ref.read(homeTabProvider) == HomeTab.tasks) _playIntro();
  }

  /// All tabs are built when the app starts, so the introduction waits until My Tasks is on screen, and plays again
  /// every time the tab is switched back to.
  void _playIntro() {
    // Respect "remove animations": show the finished screen at once.
    MediaQuery.disableAnimationsOf(context) ? _intro.value = 1 : _intro.forward(from: 0);
  }

  @override
  void dispose() {
    _intro.dispose();
    _panel.dispose();
    _turn.dispose();
    super.dispose();
  }

  Future<void> _open(String key, {int? flash}) async {
    if (_opening || !mounted) return;
    _opening = true;
    await openStage(context, key, flashCard: flash);
    _opening = false;
  }

  // ---- the drag that brings the tiles up --------------------------------------------------------

  void _dragStart(DragStartDetails d) {
    _panel.stop();
    _panelDragStart = _panel.value;
  }

  void _dragUpdate(DragUpdateDetails d, double panelHeight) {
    _panel.value = (_panel.value - (d.primaryDelta ?? 0) / panelHeight).clamp(0.0, 1.0);
  }

  /// A slight drag is enough: past a small distance (or on a flick) the panel goes the rest of the way.
  void _dragEnd(DragEndDetails d) {
    final v = d.primaryVelocity ?? 0;
    final wasOpen = _panelDragStart > 0.5;
    final open = v < -300 ? true : (v > 300 ? false : (wasOpen ? _panel.value > 0.85 : _panel.value > 0.05));
    _panel.animateTo(open ? 1 : 0, curve: Curves.easeOutCubic);
  }

  // ---- turning the cards -------------------------------------------------------------------------

  int get _currentIndex {
    final i = _pagesNow.indexWhere((p) => p.key == _currentKey);
    return i < 0 ? 0 : i;
  }

  void _commitTurn() {
    if (!mounted) return;
    if (_turnTo >= 0 && _turnTo < _pagesNow.length) _currentKey = _pagesNow[_turnTo].key;
    setState(() => _turnTo = -1);
    _turn.value = 0;
  }

  /// Turn to [page] (arrows, tiles): the top card slides away and that stage's card comes out of the stack.
  Future<void> _goTo(int page) async {
    if (page < 0 || page >= _pagesNow.length || page == _currentIndex) return;
    _turn.stop();
    if (_turnTo >= 0) _commitTurn(); // a turn in progress finishes first
    setState(() {
      _turnDir = page > _currentIndex ? 1 : -1;
      _turnTo = page;
    });
    _turn.value = 0;
    await _turn.animateTo(1, curve: Curves.easeOutCubic);
    _commitTurn();
  }

  // ---- swiping the card ---------------------------------------------------------------------------

  void _swipeStart(DragStartDetails d) {
    _turn.stop();
    if (_turnTo >= 0) _commitTurn();
    _dragS = 0;
  }

  /// Dragging left pulls the top card off towards the next stage; dragging right, back to the previous one.
  void _swipeUpdate(DragUpdateDetails d, double span) {
    _dragS = (_dragS + (d.primaryDelta ?? 0) / span).clamp(-1.0, 1.0);
    final dir = _dragS < 0 ? 1 : -1;
    final to = _currentIndex + dir;
    if (to < 0 || to >= _pagesNow.length) {
      // nothing beyond the first or last card: it only gives a little
      setState(() => _turnTo = -1);
      return;
    }
    setState(() {
      _turnDir = dir;
      _turnTo = to;
    });
    _turn.value = _dragS.abs();
  }

  void _swipeEnd(DragEndDetails d) {
    if (_turnTo < 0) return;
    final v = d.primaryVelocity ?? 0;
    final flick = v * (_turnDir == 1 ? -1 : 1) > 500;
    if (flick || _turn.value > 0.4) {
      _turn.animateTo(1, curve: Curves.easeOutCubic).then((_) => _commitTurn());
    } else {
      _turn.animateTo(0, curve: Curves.easeOut).then((_) {
        if (mounted) setState(() => _turnTo = -1);
      });
    }
  }

  /// A tile: put that stage's card in his hands (a second tap on the card already there opens it). A stage with no
  /// tickets has no card in the stack, so it opens straight away.
  void _onTile(StageInfo s, List<StageInfo> pages) {
    final i = pages.indexWhere((p) => p.key == s.key);
    if (i < 0 || s.key == _currentKey) {
      _open(s.key);
    } else {
      _goTo(i);
    }
  }

  // ---- the dashboard's shortcuts ------------------------------------------------------------------

  /// A dashboard card asked for a stage (or one ticket): open it as soon as the board is loaded.
  void _applyPending(KanbanBoard b) {
    final focus = _pendingFocus;
    final task = _pendingTask;
    if (focus == null && task == null) return;
    _pendingFocus = null;
    _pendingTask = null;

    if (task != null) {
      final card = b.cards.where((c) => c.id == task).firstOrNull;
      if (card != null) {
        WidgetsBinding.instance.addPostFrameCallback((_) => _open(card.status, flash: card.id));
        return;
      }
    }
    if (focus != null && focus.isNotEmpty && (focus == allStageKey || b.columns.any((c) => c.key == focus))) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _open(focus));
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(homeTabProvider, (_, tab) {
      if (tab == HomeTab.tasks) _playIntro();
    });
    // Deleting the last letter of a search puts the man back; so does the back button or the search icon.
    ref.listen(kanbanFiltersProvider.select((f) => f.search), (prev, now) {
      if (_searchOpen && (prev ?? '').isNotEmpty && now.isEmpty) _closeSearch(clear: false);
    });
    ref.listen(boardFocusProvider, (_, focus) {
      if (focus == null) return;
      _pendingFocus = focus;
      Future.microtask(() => ref.read(boardFocusProvider.notifier).focus(null));
      final b = ref.read(kanbanBoardProvider).asData?.value;
      if (b != null) _applyPending(b);
    });
    ref.listen(boardTaskFocusProvider, (_, id) {
      if (id == null) return;
      _pendingTask = id;
      Future.microtask(() => ref.read(boardTaskFocusProvider.notifier).focus(null));
      final b = ref.read(kanbanBoardProvider).asData?.value;
      if (b != null) _applyPending(b);
    });

    final async = ref.watch(kanbanBoardProvider);
    final board = async.asData?.value;
    final unread = ref.watch(notificationsProvider).maybeWhen(data: (d) => d.unread, orElse: () => 0);
    final actions = TicketActions(ref);

    return PopScope(
      canPop: !_searchOpen,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _closeSearch();
      },
      child: Scaffold(
      appBar: AppBar(
        title: const Text('My Tasks'),
        actions: [
          IconButton(
            key: const Key('kanban-search-toggle'),
            tooltip: 'Search',
            icon: Icon(_searchOpen ? Icons.search_off : Icons.search),
            onPressed: () => _searchOpen ? _closeSearch() : setState(() => _searchOpen = true),
          ),
          BoardFilterButton(board: board),
          IconButton(
            key: const Key('kanban-alerts'),
            tooltip: 'Notifications',
            icon: Badge(isLabelVisible: unread > 0, label: Text('$unread'), child: const Icon(Icons.notifications_outlined)),
            onPressed: () => ref.read(homeTabProvider.notifier).goTo(HomeTab.alerts),
          ),
        ],
      ),
      body: Column(children: [
        if (_searchOpen) const BoardSearchField(),
        if (board != null) AppliedFilters(board: board),
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
            data: (b) {
              _applyPending(b);
              return _searchOpen ? _results(context, b, actions) : _overview(context, b);
            },
          ),
        ),
      ]),
    ));
  }

  /// Leaves search: the keyboard goes, the text is cleared (unless it is already empty) and the man comes back.
  void _closeSearch({bool clear = true}) {
    FocusManager.instance.primaryFocus?.unfocus();
    if (clear && ref.read(kanbanFiltersProvider).search.isNotEmpty) {
      ref.read(kanbanFiltersProvider.notifier).update(ref.read(kanbanFiltersProvider).copyWith(search: ''));
    }
    if (mounted) setState(() => _searchOpen = false);
  }

  /// While searching the man steps aside: just the tickets that match, each with the stage it is in.
  Widget _results(BuildContext context, KanbanBoard b, TicketActions actions) {
    final query = ref.watch(kanbanFiltersProvider).search.trim();

    if (query.isEmpty) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(Icons.manage_search_rounded, size: 52, color: Colors.black26),
            SizedBox(height: 10),
            Text('Search by ticket ID, project number or task name', key: Key('search-hint'), textAlign: TextAlign.center, style: TextStyle(color: Colors.black45, fontWeight: FontWeight.w600)),
          ]),
        ),
      );
    }

    final now = DateTime.now();
    if (b.cards.isEmpty) {
      return const Center(child: Text('No matching tickets', key: Key('search-empty'), style: TextStyle(color: Colors.black45, fontWeight: FontWeight.w600)));
    }

    return ListView.separated(
      key: const Key('search-results'),
      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
      itemCount: b.cards.length,
      separatorBuilder: (_, _) => const SizedBox(height: 8),
      itemBuilder: (context, i) {
        final card = b.cards[i];
        return KanbanTaskCard(
          key: Key('result-${card.id}'),
          card: card,
          now: now,
          stageLabel: actions.label(b, card.status),
          onOpen: () => context.push('/task/${card.id}'),
          onMove: card.canMove ? () => actions.openMoveSheet(context, card, b) : null,
          onLongPress: () => actions.openMoveSheet(context, card, b),
        );
      },
    );
  }

  Widget _overview(BuildContext context, KanbanBoard b) {
    final total = b.stats['total'] ?? b.cards.length;
    final secondary = ModalRoute.of(context)?.secondaryAnimation ?? kAlwaysDismissedAnimation;
    final stages = [for (final c in b.columns) StageInfo(key: c.key, label: c.label, count: b.countFor(c))];

    // The cards in his hands: All Tickets, then every stage that has tickets.
    final pages = [StageInfo(key: allStageKey, label: 'All Tickets', count: total), ...stages.where((s) => s.count > 0)];
    _pagesNow = pages;
    final current = pages.indexWhere((p) => p.key == _currentKey);
    if (current < 0) {
      _currentKey = allStageKey; // the stage emptied (or a search narrowed the board): back to All Tickets
      _turnTo = -1;
    }
    final here = pages[current < 0 ? 0 : current];

    return LayoutBuilder(builder: (context, box) {
      final w = box.maxWidth;
      final h = box.maxHeight;
      // The man is as tall as the screen allows, kept centred; the card he holds is as wide as his two hands are apart.
      final u = math.min(h, w * 1.95);
      final side = math.min(0.38 * u, w * 0.6);
      final cardTop = 0.30 * u; // below the Adinn logo on his shirt
      final charLeft = w / 2 - 0.333 * u;

      // The tiles' space, and how far the man has to shrink to leave it free.
      final rows = math.max(1, (stages.where((s) => s.count > 0).length / 5).ceil());
      final panelH = rows * 62.0 + (rows - 1) * 7 + 62;
      final stepBack = math.min(0.72, math.max(0.45, (h - panelH - 8) / u));
      final cur = current < 0 ? 0 : current;
      final next = pages.sublist(cur + 1); // only the cards that are really left, none on the last one

      return GestureDetector(
        behavior: HitTestBehavior.translucent,
        onVerticalDragStart: _dragStart,
        onVerticalDragUpdate: (d) => _dragUpdate(d, panelH),
        onVerticalDragEnd: _dragEnd,
        child: ClipRect(
          child: SizedBox(
            width: w,
            height: h,
            child: Stack(children: [
              // The man and the cards. A drag up pushes them back: smaller, and up to make room underneath.
              Positioned(
                left: 0,
                top: 0,
                width: w,
                height: u,
                child: AnimatedBuilder(
                  animation: Listenable.merge([_intro, secondary, _panel]),
                  builder: (context, child) {
                    final s = Curves.easeInOut.transform(secondary.value.clamp(0.0, 1.0));
                    final v = Curves.easeInOut.transform(_panel.value);
                    final scale = 1 - (1 - stepBack) * v;
                    final camera = 1.12 - 0.12 * _camera.value; // the camera pulls back and settles on the card
                    return Opacity(
                      opacity: (1 - s * 1.25).clamp(0.0, 1.0),
                      child: Transform(
                        alignment: Alignment.topCenter,
                        transform: Matrix4.identity()
                          ..translateByDouble(0.0, -s * u * 0.3, 0.0, 1.0)
                          ..scaleByDouble(scale * camera, scale * camera, 1.0, 1.0),
                        child: child,
                      ),
                    );
                  },
                  child: _hero(u: u, w: w, side: side, cardTop: cardTop, charLeft: charLeft, pages: pages, current: current < 0 ? 0 : current, next: next),
                ),
              ),
              // "Swipe up" hint; it goes as the tiles arrive.
              Positioned(
                left: 0,
                right: 0,
                bottom: 40, // clear of the nav bar's bubble, which rises over the page
                child: Center(
                  child: AnimatedBuilder(
                    animation: Listenable.merge([_count, _panel]),
                    builder: (context, child) => Opacity(opacity: (_count.value * (1 - _panel.value * 2)).clamp(0.0, 1.0), child: child),
                    child: GestureDetector(
                      key: const Key('hero-hint'),
                      onTap: () => _panel.animateTo(1, curve: Curves.easeOutCubic),
                      child: const _SwipeUpHint(),
                    ),
                  ),
                ),
              ),
              // The stage tiles: straight on the page, rising into the space under his feet.
              AnimatedBuilder(
                animation: Listenable.merge([_panel, secondary]),
                builder: (context, child) {
                  final v = Curves.easeOutCubic.transform(_panel.value);
                  final s = Curves.easeInOut.transform(secondary.value.clamp(0.0, 1.0));
                  return Positioned(
                    left: 0,
                    right: 0,
                    bottom: 0,
                    height: panelH,
                    child: IgnorePointer(
                      ignoring: v < 0.5,
                      child: Opacity(
                        opacity: (v * (1 - s * 1.25)).clamp(0.0, 1.0),
                        child: Transform.translate(offset: Offset(0, (1 - v) * 50 + s * 30), child: child),
                      ),
                    ),
                  );
                },
                child: Container(
                  key: const Key('stage-panel'),
                  padding: const EdgeInsets.fromLTRB(14, 0, 14, 10),
                  // On a short screen the tiles shrink to fit instead of overflowing.
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.bottomCenter,
                    child: SizedBox(
                      width: w - 28,
                      child: Column(mainAxisSize: MainAxisSize.min, children: [
                        const Padding(
                          padding: EdgeInsets.only(bottom: 8),
                          child: Align(alignment: Alignment.centerLeft, child: Text('Your stages', key: Key('panel-title'), style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: Color(0xFF475467)))),
                        ),
                        StageTiles(stages: [for (final s in stages) if (s.count > 0) s], selectedKey: here.key, onTap: (s) => _onTile(s, pages)),
                      ]),
                    ),
                  ),
                ),
              ),
            ]),
          ),
        ),
      );
    });
  }

  /// The representative holding the card: the real artwork of the man, with the stack, the pager, arrows and dots laid
  /// out in units of his height.
  Widget _hero({required double u, required double w, required double side, required double cardTop, required double charLeft, required List<StageInfo> pages, required int current, required List<StageInfo> next}) {
    return AnimatedBuilder(
      animation: _intro,
      builder: (context, _) {
        final stackSlide = (1 - _stackIn.value) * u * 0.16;

        return SizedBox(
          width: w,
          height: u,
          child: Stack(clipBehavior: Clip.none, children: [
            Positioned(
              left: charLeft,
              top: 0,
              child: Opacity(
                opacity: _character.value,
                child: Transform.scale(scale: 0.92 + 0.08 * _character.value, alignment: Alignment.bottomCenter, child: DesignerCharacterWidget(height: u)),
              ),
            ),
            // The rest of the stack, peeking out behind the card.
            Positioned(
              left: w / 2 - side / 2,
              top: cardTop + stackSlide,
              child: Opacity(opacity: _stackIn.value, child: HeldCardEdges(side: side, colors: [for (final s in next) s.key == allStageKey ? adinnRed : stageAccent(s.key)], open: _fan)),
            ),
            // The card in his hands, on top of the stack: swipe it away to bring up the next one.
            Positioned(
              left: 0,
              width: w,
              top: cardTop - side * 0.05 + stackSlide,
              height: side * 1.1,
              child: Opacity(
                opacity: _stackIn.value,
                child: GestureDetector(
                  key: const Key('card-pager'),
                  behavior: HitTestBehavior.translucent,
                  onHorizontalDragStart: _swipeStart,
                  onHorizontalDragUpdate: (d) => _swipeUpdate(d, side * 1.1),
                  onHorizontalDragEnd: _swipeEnd,
                  child: AnimatedBuilder(
                    animation: _turn,
                    builder: (context, _) => _deck(w: w, side: side, pages: pages, current: current),
                  ),
                ),
              ),
            ),
            // His hands grip the cards. The left one is on the card's left edge; the right one holds the whole stack, so it
            // sits on the outer edge of the last card peeking out and moves in as the stack thins.
            // While a card is being swiped away or brought back his left hand lets go (fades out and, as it lands, back in),
            // so a card never slides through it. The right hand keeps holding the stack.
            Positioned.fill(
              child: AnimatedBuilder(
                animation: _turn,
                builder: (context, _) {
                  final hide = Curves.easeInOut.transform((math.sin(math.pi * _turn.value.clamp(0.0, 1.0)) * 1.6).clamp(0.0, 1.0));
                  return Stack(clipBehavior: Clip.none, children: _hands(u: u, w: w, side: side, edges: math.min(next.length, HeldCardEdges.maxEdges), hide: hide));
                },
              ),
            ),
          ]),
        );
      },
    );
  }

  /// His two hands, in place over the edges of the cards. They are sized to his arms (the hand and its cuff are about as
  /// tall as his sleeve cuffs) and sit where his arms end, so they join the sleeves.
  List<Widget> _hands({required double u, required double w, required double side, required int edges, double hide = 0}) {
    final hh = u * 0.072;
    final hw = hh * GrippingHand.aspect;
    final top = u * 0.382 - hh / 2;
    final cardLeft = w / 2 - side / 2;
    final cardRight = w / 2 + side / 2;

    return [
      Positioned(
        left: cardLeft - hw * 0.46,
        top: top,
        child: Opacity(opacity: _stackIn.value * (1 - hide), child: GrippingHand(left: true, height: hh)),
      ),
      // each edge still to come puts the outer edge of the stack a little further out, so the hand follows
      TweenAnimationBuilder<double>(
        tween: Tween(end: edges.toDouble()),
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOutCubic,
        builder: (context, n, _) {
          final open = Curves.easeOutCubic.transform(_fan.value.clamp(0.0, 1.0));
          return Positioned(
            left: cardRight + n * 0.06 * side * open - hw * 0.54,
            top: top,
            child: Opacity(opacity: _stackIn.value, child: GrippingHand(left: false, height: hh)),
          );
        },
      ),
    ];
  }

  /// The top card and, while one is being turned to, the card it turns to.
  ///  - On to the next stage: the top card slides off to the side (tilting, fading) and the next one rises out of the
  ///    stack, from where its edge was peeking.
  ///  - Back: the previous card slides in from the side onto the top, and the one it covers drops back into the stack.
  Widget _deck({required double w, required double side, required List<StageInfo> pages, required int current}) {
    final t = _turn.value;
    final top = pages[current];
    final to = _turnTo >= 0 && _turnTo < pages.length ? pages[_turnTo] : null;
    final forward = _turnDir == 1;

    // the card leaving the top, or coming back onto it, travels to / from the side
    Widget travelling(StageInfo s, double progress, {required VoidCallback onTap}) => Opacity(
          opacity: (1 - 0.8 * progress).clamp(0.0, 1.0),
          child: Transform(
            alignment: Alignment.bottomCenter,
            transform: Matrix4.identity()
              ..translateByDouble(-progress * w * 0.55, progress * side * 0.05, 0.0, 1.0)
              ..rotateZ(-progress * 0.28),
            child: HeldStageCard(key: ValueKey('deck-${s.key}'), stage: s, side: side, count: _count, onTap: onTap),
          ),
        );

    // the card in the stack: rising out of it (forward) or dropping back into it (back)
    Widget inStack(StageInfo s, double rise, {required VoidCallback onTap}) => Transform(
          alignment: Alignment.bottomLeft,
          transform: Matrix4.identity()
            ..translateByDouble((1 - rise) * side * 0.034, -(1 - rise) * side * 0.01, 0.0, 1.0)
            ..rotateZ((1 - rise) * 0.04)
            ..scaleByDouble(0.97 + 0.03 * rise, 0.97 + 0.03 * rise, 1.0, 1.0),
          child: HeldStageCard(key: ValueKey('deck-${s.key}'), stage: s, side: side, count: _count, onTap: onTap),
        );

    if (to == null || t == 0) {
      return Center(child: HeldStageCard(key: ValueKey('deck-${top.key}'), stage: top, side: side, count: _count, onTap: () => _open(top.key)));
    }

    return Stack(alignment: Alignment.center, clipBehavior: Clip.none, children: forward
        ? [
            inStack(to, t, onTap: () {}),
            travelling(top, t, onTap: () => _open(top.key)),
          ]
        : [
            inStack(top, 1 - t, onTap: () {}),
            travelling(to, 1 - t, onTap: () {}),
          ]);
  }
}


/// Tests switch this off: a hint that loops forever would keep `pumpAndSettle` from ever settling.
bool swipeHintAnimates = true;

/// No box and no words: three see-through chevrons that light up one after another, bottom to top, forever.
class _SwipeUpHint extends StatefulWidget {
  const _SwipeUpHint();

  @override
  State<_SwipeUpHint> createState() => _SwipeUpHintState();
}

class _SwipeUpHintState extends State<_SwipeUpHint> with SingleTickerProviderStateMixin {
  late final AnimationController _flow = AnimationController(vsync: this, duration: const Duration(milliseconds: 1500));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // With "remove animations" the chevrons stay lit and still.
    if (MediaQuery.disableAnimationsOf(context) || !swipeHintAnimates) {
      _flow.stop();
      _flow.value = 0.5;
    } else if (!_flow.isAnimating) {
      _flow.repeat();
    }
  }

  @override
  void dispose() {
    _flow.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Semantics(label: 'Swipe up for your stages', child: Column(mainAxisSize: MainAxisSize.min, children: [
      AnimatedBuilder(
        animation: _flow,
        builder: (context, _) => Column(mainAxisSize: MainAxisSize.min, children: [
          for (var i = 0; i < 3; i++)
            // the wave starts at the bottom chevron and runs up
            Align(
              heightFactor: 0.55,
              child: Icon(
                Icons.keyboard_arrow_up_rounded,
                size: 28,
                color: adinnRed.withValues(alpha: 0.15 + 0.7 * math.sin(math.pi * ((_flow.value - (2 - i) / 3.5) % 1.0)).clamp(0.0, 1.0)),
              ),
            ),
        ]),
      ),
    ]));
  }
}
