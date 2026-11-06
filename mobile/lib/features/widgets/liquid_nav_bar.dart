import 'dart:math' as math;

import 'package:flutter/material.dart';

/// One tab of the [LiquidNavBar].
class LiquidNavItem {
  const LiquidNavItem({required this.icon, required this.selectedIcon, required this.label});

  final Widget icon;
  final Widget selectedIcon;
  final String label;
}

/// Bottom navigation where the active tab doesn't slide: the bar melts around it. The notch in the top edge is
/// one smooth curve (horizontal tangents at both ends and at the bottom, so it never creases), and while it
/// travels between tabs it stretches like a drop of liquid before settling under the new tab.
class LiquidNavBar extends StatefulWidget {
  const LiquidNavBar({
    super.key,
    required this.items,
    required this.selectedIndex,
    required this.onSelected,
    this.color = Colors.white,
    this.accent,
    this.duration = const Duration(milliseconds: 520),
  });

  final List<LiquidNavItem> items;
  final int selectedIndex;
  final ValueChanged<int> onSelected;
  final Color color;
  final Color? accent;
  final Duration duration;

  static const double _headroom = 30; // the bubble rises above the bar
  static const double _barHeight = 64;

  @override
  State<LiquidNavBar> createState() => _LiquidNavBarState();
}

class _LiquidNavBarState extends State<LiquidNavBar> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(vsync: this, duration: widget.duration, value: 1);
  late double _from = widget.selectedIndex.toDouble();
  late double _to = widget.selectedIndex.toDouble();

  /// Position (in tab units) of the notch right now, eased from the old tab to the new one.
  double get _position => _from + (_to - _from) * Curves.easeInOutCubic.transform(_controller.value);

  /// 0 at rest, up to 1 mid-travel: how much the liquid stretches.
  double get _stretch => math.sin(math.pi * _controller.value) * ((_to - _from).abs().clamp(0, 3) / 3 * 0.6 + 0.4);

  @override
  void didUpdateWidget(LiquidNavBar old) {
    super.didUpdateWidget(old);
    if (old.selectedIndex != widget.selectedIndex) {
      _from = _position;
      _to = widget.selectedIndex.toDouble();
      _controller.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final accent = widget.accent ?? Theme.of(context).colorScheme.primary;
    final inset = MediaQuery.paddingOf(context).bottom;
    final count = widget.items.length;

    return SizedBox(
      height: LiquidNavBar._headroom + LiquidNavBar._barHeight + inset,
      child: LayoutBuilder(builder: (context, box) {
        final itemWidth = box.maxWidth / count;

        return AnimatedBuilder(
          animation: _controller,
          builder: (context, _) {
            final cx = (_position + 0.5) * itemWidth;
            final stretch = _stretch;

            return Stack(clipBehavior: Clip.none, children: [
              // The melting bar.
              Positioned.fill(
                child: CustomPaint(
                  painter: _LiquidBarPainter(centerX: cx, stretch: stretch, color: widget.color, accent: accent, headroom: LiquidNavBar._headroom),
                ),
              ),
              // The selected icon rides in the liquid bubble.
              Positioned(
                left: cx - 26,
                top: LiquidNavBar._headroom - 27 + 2,
                width: 52,
                height: 52,
                child: IgnorePointer(
                  child: Transform.scale(
                    scaleX: 1 + 0.22 * stretch,
                    scaleY: 1 - 0.08 * stretch,
                    child: Center(
                      child: IconTheme(data: const IconThemeData(color: Colors.white, size: 26), child: widget.items[widget.selectedIndex].selectedIcon),
                    ),
                  ),
                ),
              ),
              // Tabs: unselected ones show their icon; every tab keeps its label.
              Positioned(
                left: 0,
                right: 0,
                top: LiquidNavBar._headroom,
                height: LiquidNavBar._barHeight,
                child: Row(children: [
                  for (var i = 0; i < count; i++)
                    Expanded(
                      child: Semantics(
                        button: true,
                        selected: i == widget.selectedIndex,
                        label: widget.items[i].label,
                        child: InkResponse(
                          key: Key('nav-$i'),
                          radius: 36,
                          onTap: () => widget.onSelected(i),
                          child: Column(mainAxisAlignment: MainAxisAlignment.end, children: [
                            SizedBox(
                              height: 30,
                              child: i == widget.selectedIndex
                                  ? null // its icon is in the bubble above
                                  : IconTheme(data: const IconThemeData(color: Color(0xFF667085), size: 24), child: Center(child: widget.items[i].icon)),
                            ),
                            const SizedBox(height: 2),
                            Padding(
                              padding: const EdgeInsets.only(bottom: 9),
                              child: Text(
                                widget.items[i].label,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontSize: 11.5,
                                  fontWeight: i == widget.selectedIndex ? FontWeight.w800 : FontWeight.w600,
                                  color: i == widget.selectedIndex ? accent : const Color(0xFF667085),
                                ),
                              ),
                            ),
                          ]),
                        ),
                      ),
                    ),
                ]),
              ),
            ]);
          },
        );
      }),
    );
  }
}

class _LiquidBarPainter extends CustomPainter {
  _LiquidBarPainter({required this.centerX, required this.stretch, required this.color, required this.accent, required this.headroom});

  final double centerX;
  final double stretch;
  final Color color;
  final Color accent;
  final double headroom;

  @override
  void paint(Canvas canvas, Size size) {
    final top = headroom;
    final depth = 31 + 3 * stretch; // how far the bar scoops down
    final half = 44 + 26 * stretch; // half-width of the scoop; wider while the liquid is in motion
    final pit = 30.0 + 6 * stretch; // roundness at the bottom of the scoop
    final cx = centerX;

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

    // The bubble that sits in the scoop, stretching with the liquid.
    final bubble = Rect.fromCenter(center: Offset(cx, top - 1), width: 52 * (1 + 0.22 * stretch), height: 52 * (1 - 0.08 * stretch));
    final bubblePath = Path()..addOval(bubble);
    canvas.drawShadow(bubblePath, accent.withValues(alpha: 0.5), 5, true);
    canvas.drawOval(bubble, Paint()..color = accent);
  }

  @override
  bool shouldRepaint(_LiquidBarPainter old) =>
      old.centerX != centerX || old.stretch != stretch || old.color != color || old.accent != accent;
}
