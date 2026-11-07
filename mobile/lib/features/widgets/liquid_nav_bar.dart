import 'dart:math' as math;

import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart';
import 'package:flutter/services.dart';

/// One tab of the [LiquidNavBar].
class LiquidNavItem {
  const LiquidNavItem({required this.icon, required this.selectedIcon, required this.label});

  final Widget icon;
  final Widget selectedIcon;
  final String label;
}

/// Bottom navigation where the active tab doesn't slide: the bar melts around it. The notch in the top edge is one smooth
/// curve (horizontal tangents at both ends and at the bottom, so it never creases), and while it travels between tabs it
/// stretches like a drop of liquid before settling under the new tab.
///
/// Tap a tab to go to it. Or press and hold anywhere on the bar and drag: the bubble lifts off the bar and follows the
/// finger across the icons (a light tick marks each one it passes), and the tab it is over when you let go is selected.
///
/// Only the moving layers (the bar's shape, and the bubble with its icon) repaint on each frame; the labels and icons
/// underneath only change when the highlighted tab does.
class LiquidNavBar extends StatefulWidget {
  const LiquidNavBar({
    super.key,
    required this.items,
    required this.selectedIndex,
    required this.onSelected,
    this.color = Colors.white,
    this.accent,
    this.duration = const Duration(milliseconds: 560),
  });

  final List<LiquidNavItem> items;
  final int selectedIndex;
  final ValueChanged<int> onSelected;
  final Color color;
  final Color? accent;
  final Duration duration;

  /// How long a press has to be held before it becomes a drag across the icons.
  static const holdToDrag = Duration(milliseconds: 220);

  static const double _headroom = 36; // the bubble rises above the bar
  static const double _barHeight = 64;
  static const double _bubble = 52;

  @override
  State<LiquidNavBar> createState() => _LiquidNavBarState();
}

class _LiquidNavBarState extends State<LiquidNavBar> with TickerProviderStateMixin {
  // Travelling to a tab after a tap or a release.
  late final AnimationController _travel = AnimationController(vsync: this, duration: widget.duration, value: 1);

  // 0 → resting, 1 → held: the bubble lifts and glows.
  late final AnimationController _grab = AnimationController(vsync: this, duration: const Duration(milliseconds: 180));

  // While held, the bubble glides after the finger rather than snapping to it.
  late final Ticker _follow = createTicker(_followFinger);
  // (Ticker comes from flutter/scheduler.dart)
  final _tick = ValueNotifier<int>(0);
  late final Listenable _repaint = Listenable.merge([_travel, _grab, _tick]);

  late double _from = widget.selectedIndex.toDouble();
  late double _to = widget.selectedIndex.toDouble();
  double? _dragPos; // where the bubble is while held (tab units)
  double _dragTarget = 0; // where the finger is (tab units)
  int? _pending; // a tab we selected ourselves on release, so the animation is not restarted
  double _itemWidth = 1;

  /// The tab highlighted right now: the one under the bubble while held, else the selected one.
  late int _shown = widget.selectedIndex;

  // Eases out past the target and settles back, so the bubble lands with a little squish.
  static const _spring = Cubic(0.3, 1.35, 0.5, 1);

  double get _position => _dragPos ?? _from + (_to - _from) * _spring.transform(_travel.value);

  /// 0 at rest; up to 1 while the liquid is in motion (travelling, or being dragged faster than it can follow).
  double get _stretch {
    if (_dragPos != null) return math.min(1.0, (_dragTarget - _dragPos!).abs() * 0.9);
    return math.sin(math.pi * _travel.value) * ((_to - _from).abs().clamp(0, 3) / 3 * 0.6 + 0.4);
  }

  @override
  void didUpdateWidget(LiquidNavBar old) {
    super.didUpdateWidget(old);
    if (old.selectedIndex != widget.selectedIndex) {
      if (_pending == widget.selectedIndex) {
        _pending = null; // we moved the bubble there ourselves on release
      } else {
        _from = _position;
        _to = widget.selectedIndex.toDouble();
        _travel.forward(from: 0);
      }
      if (_dragPos == null && _shown != widget.selectedIndex) setState(() => _shown = widget.selectedIndex);
    }
  }

  @override
  void dispose() {
    _follow.dispose();
    _travel.dispose();
    _grab.dispose();
    _tick.dispose();
    super.dispose();
  }

  // ---- hold and drag ------------------------------------------------------------------------------

  double _tabAt(double dx) => (dx / _itemWidth - 0.5).clamp(0.0, widget.items.length - 1.0);

  void _holdStart(LongPressStartDetails d) {
    HapticFeedback.lightImpact();
    _travel.stop();
    _dragPos = _position;
    _dragTarget = _tabAt(d.localPosition.dx);
    _grab.forward();
    if (!_follow.isActive) _follow.start();
    _updateShown();
  }

  void _holdMove(LongPressMoveUpdateDetails d) {
    if (_dragPos == null) return;
    final before = _dragTarget.round();
    _dragTarget = _tabAt(d.localPosition.dx);
    if (_dragTarget.round() != before) HapticFeedback.selectionClick(); // a tick for each icon passed
  }

  void _followFinger(Duration _) {
    final p = _dragPos;
    if (p == null) return;
    final d = _dragTarget - p;
    _dragPos = d.abs() < 0.002 ? _dragTarget : p + d * 0.3;
    _updateShown();
    _tick.value++;
  }

  void _updateShown() {
    final i = (_dragPos ?? _dragTarget).round().clamp(0, widget.items.length - 1);
    if (i != _shown) setState(() => _shown = i);
  }

  /// Let go (or the touch is taken away): the bubble settles on the tab it is over and that tab is selected.
  void _holdEnd({required bool cancelled}) {
    final pos = _dragPos;
    if (pos == null) return;
    final index = cancelled ? widget.selectedIndex : _dragTarget.round().clamp(0, widget.items.length - 1);

    _follow.stop();
    _from = pos;
    _to = index.toDouble();
    _dragPos = null;
    _grab.reverse();
    _travel.forward(from: 0);
    setState(() => _shown = index);

    if (index != widget.selectedIndex) {
      _pending = index;
      widget.onSelected(index);
    }
  }

  // ---- paint --------------------------------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    final accent = widget.accent ?? Theme.of(context).colorScheme.primary;
    final inset = MediaQuery.paddingOf(context).bottom;
    final count = widget.items.length;

    return RawGestureDetector(
      behavior: HitTestBehavior.translucent,
      gestures: {
        LongPressGestureRecognizer: GestureRecognizerFactoryWithHandlers<LongPressGestureRecognizer>(
          () => LongPressGestureRecognizer(duration: LiquidNavBar.holdToDrag),
          (r) => r
            ..onLongPressStart = _holdStart
            ..onLongPressMoveUpdate = _holdMove
            ..onLongPressEnd = ((_) => _holdEnd(cancelled: false))
            ..onLongPressCancel = (() => _holdEnd(cancelled: true)),
        ),
      },
      child: SizedBox(
        // Only the bar itself takes layout space; the bubble rises into the screen above it instead of reserving a strip.
        height: LiquidNavBar._barHeight + inset,
        child: LayoutBuilder(builder: (context, box) {
          _itemWidth = box.maxWidth / count;

          return Stack(clipBehavior: Clip.none, children: [
            // The melting bar and the bubble: the only part that repaints every frame.
            Positioned(
              left: 0,
              right: 0,
              top: -LiquidNavBar._headroom,
              height: LiquidNavBar._headroom + LiquidNavBar._barHeight + inset,
              child: RepaintBoundary(
                child: CustomPaint(
                  painter: _LiquidBarPainter(
                    repaint: _repaint,
                    geometry: () => (cx: (_position + 0.5) * _itemWidth, stretch: _stretch, grab: Curves.easeOut.transform(_grab.value)),
                    color: widget.color,
                    accent: accent,
                    headroom: LiquidNavBar._headroom,
                  ),
                ),
              ),
            ),
            // Labels and the icons still in the bar: they only change when the highlighted tab does.
            Positioned(
              left: 0,
              right: 0,
              top: 0,
              height: LiquidNavBar._barHeight,
              child: RepaintBoundary(
                child: Row(children: [
                  for (var i = 0; i < count; i++)
                    Expanded(
                      child: _Tab(
                        key: Key('nav-$i'),
                        item: widget.items[i],
                        index: i,
                        position: _repaint,
                        positionOf: () => _position,
                        heldOf: () => Curves.easeOut.transform(_grab.value),
                        selected: i == _shown,
                        semanticSelected: i == widget.selectedIndex,
                        accent: accent,
                        onTap: () => widget.onSelected(i),
                      ),
                    ),
                ]),
              ),
            ),
            // The highlighted tab's icon, riding in the bubble.
            AnimatedBuilder(
              animation: _repaint,
              builder: (context, child) {
                final stretch = _stretch;
                final grab = Curves.easeOut.transform(_grab.value);
                final cx = (_position + 0.5) * _itemWidth;
                return Positioned(
                  left: cx - LiquidNavBar._bubble / 2,
                  top: -LiquidNavBar._bubble / 2 - 1 - 7 * grab,
                  width: LiquidNavBar._bubble,
                  height: LiquidNavBar._bubble,
                  child: IgnorePointer(
                    child: Transform.scale(
                      scaleX: (1 + 0.22 * stretch) * (1 + 0.12 * grab),
                      scaleY: (1 - 0.08 * stretch) * (1 + 0.12 * grab),
                      child: child,
                    ),
                  ),
                );
              },
              child: Center(
                child: AnimatedSwitcher(
                  duration: const Duration(milliseconds: 140),
                  transitionBuilder: (child, animation) => FadeTransition(opacity: animation, child: ScaleTransition(scale: Tween(begin: 0.7, end: 1.0).animate(animation), child: child)),
                  child: IconTheme(
                    key: ValueKey(_shown),
                    data: const IconThemeData(color: Colors.white, size: 26),
                    child: widget.items[_shown].selectedIcon,
                  ),
                ),
              ),
            ),
          ]);
        }),
      ),
    );
  }
}

/// One tab: its icon (which hands over to the bubble when it is the highlighted one) and its label.
class _Tab extends StatelessWidget {
  const _Tab({super.key, required this.index, required this.position, required this.positionOf, required this.heldOf, required this.item, required this.selected, required this.semanticSelected, required this.accent, required this.onTap});

  final int index;
  final Listenable position;
  final double Function() positionOf;
  final double Function() heldOf;
  final LiquidNavItem item;
  final bool selected;
  final bool semanticSelected;
  final Color accent;
  final VoidCallback onTap;

  static const _idle = Color(0xFF667085);

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: semanticSelected,
      label: item.label,
      child: InkResponse(
        radius: 36,
        onTap: onTap,
        child: Column(mainAxisAlignment: MainAxisAlignment.end, children: [
          SizedBox(
            height: 30,
            // The bubble swallows the icon as it arrives: the icon rises into it, turning white on the way, and hands over to
            // the bubble's own icon; as the bubble leaves, the icon drops back out and returns to grey.
            child: AnimatedBuilder(
              animation: position,
              builder: (context, child) {
                final near = Curves.easeInOut.transform((1 - (positionOf() - index).abs()).clamp(0.0, 1.0));
                // while the bubble is held over this tab, its grey icon is gone entirely
                final handOver = math.max(((near - 0.75) / 0.25).clamp(0.0, 1.0), selected ? heldOf() : 0.0);
                return Opacity(
                  opacity: 1 - handOver,
                  child: Transform.translate(
                    offset: Offset(0, -26 * near),
                    child: Transform.scale(
                      scale: 1 - 0.1 * near,
                      child: IconTheme(data: IconThemeData(color: Color.lerp(_idle, Colors.white, near), size: 24), child: child!),
                    ),
                  ),
                );
              },
              child: Center(child: item.icon),
            ),
          ),
          const SizedBox(height: 2),
          Padding(
            padding: const EdgeInsets.only(bottom: 9),
            child: AnimatedDefaultTextStyle(
              duration: const Duration(milliseconds: 200),
              curve: Curves.easeOut,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: selected ? 12 : 11.5,
                fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                color: selected ? accent : _idle,
              ),
              child: Text(item.label),
            ),
          ),
        ]),
      ),
    );
  }
}

typedef _Geometry = ({double cx, double stretch, double grab});

class _LiquidBarPainter extends CustomPainter {
  _LiquidBarPainter({required Listenable repaint, required this.geometry, required this.color, required this.accent, required this.headroom}) : super(repaint: repaint);

  final _Geometry Function() geometry;
  final Color color;
  final Color accent;
  final double headroom;

  @override
  void paint(Canvas canvas, Size size) {
    final g = geometry();
    final top = headroom;
    final depth = 31 + 3 * g.stretch; // how far the bar scoops down
    final half = 44 + 26 * g.stretch; // half-width of the scoop; wider while the liquid is in motion
    final pit = 30.0 + 6 * g.stretch; // roundness at the bottom of the scoop
    final cx = g.cx;

    final path = Path()
      ..moveTo(0, top)
      ..lineTo(math.max(0, cx - half), top)
      // Down into the scoop and out again: every join has a horizontal tangent, so there is no crease.
      ..cubicTo(cx - half * 0.46, top, cx - pit, top + depth, cx, top + depth)
      ..cubicTo(cx + pit, top + depth, cx + half * 0.46, top, math.min(size.width, cx + half), top)
      ..lineTo(size.width, top)
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();

    canvas.drawShadow(path, Colors.black.withValues(alpha: 0.35), 6, false);
    canvas.drawPath(path, Paint()..color = color);

    // The bubble that sits in the scoop, stretching with the liquid; held, it lifts, grows and glows.
    final w = 52 * (1 + 0.22 * g.stretch) * (1 + 0.12 * g.grab);
    final h = 52 * (1 - 0.08 * g.stretch) * (1 + 0.12 * g.grab);
    final bubble = Rect.fromCenter(center: Offset(cx, top - 1 - 7 * g.grab), width: w, height: h);
    final bubblePath = Path()..addOval(bubble);

    canvas.drawShadow(bubblePath, accent.withValues(alpha: 0.5 + 0.3 * g.grab), 5 + 6 * g.grab, true);
    canvas.drawOval(bubble, Paint()..color = accent);
  }

  // Repainting is driven by the Listenable; only a change of colour needs a new paint by itself.
  @override
  bool shouldRepaint(_LiquidBarPainter old) => old.color != color || old.accent != accent;
}
