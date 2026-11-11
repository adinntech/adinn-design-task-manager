import 'dart:math' as math;

import 'package:flutter/material.dart';

/// The soft corner shapes shared by the sign-in and PIN screens: two rounded cards in the top-right corner (pale behind,
/// strong in front) and a large pale circle bleeding off the bottom-right. With [animate] they sway very slowly.
class BrandShapes extends StatefulWidget {
  const BrandShapes({super.key, this.animate = true});

  final bool animate;

  @override
  State<BrandShapes> createState() => _BrandShapesState();
}

class _BrandShapesState extends State<BrandShapes> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(seconds: 28));

  @override
  void initState() {
    super.initState();
    if (widget.animate) _c.repeat();
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: RepaintBoundary(
        child: AnimatedBuilder(
          animation: _c,
          builder: (context, _) => CustomPaint(size: Size.infinite, painter: _ShapesPainter(phase: _c.value)),
        ),
      ),
    );
  }
}

class _ShapesPainter extends CustomPainter {
  _ShapesPainter({required this.phase});

  final double phase;

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width, h = size.height;
    final sway = math.sin(phase * 2 * math.pi);
    final sway2 = math.cos(phase * 2 * math.pi);

    // Bottom-right bleed.
    canvas.drawCircle(Offset(w * 1.02 + sway * 6, h * 1.02 + sway2 * 6), w * 0.46, Paint()..color = const Color(0x1AE30613));
    canvas.drawCircle(Offset(w * 1.05, h * 0.99), w * 0.30, Paint()..color = const Color(0x1AE30613));

    void card(double cx, double cy, double cw, double ch, double angle, List<Color> colors) {
      canvas.save();
      canvas.translate(cx, cy);
      canvas.rotate(angle);
      final r = RRect.fromRectAndRadius(Rect.fromCenter(center: Offset.zero, width: cw, height: ch), Radius.circular(ch * 0.42));
      canvas.drawRRect(r, Paint()..shader = LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: colors).createShader(r.outerRect));
      canvas.restore();
    }

    // Top-right corner: a pale card behind, a strong one in front.
    card(w * 0.92 + sway * 4, h * 0.035 + sway2 * 3, w * 0.62, w * 0.34, -0.42 + sway * 0.02, const [Color(0xFFFFC2C5), Color(0xFFFF8A8F)]);
    card(w * 0.97 + sway2 * 3, h * 0.015 + sway * 3, w * 0.58, w * 0.30, -0.30 + sway2 * 0.02, const [Color(0xFFFF5A5F), Color(0xFFE30613)]);
  }

  @override
  bool shouldRepaint(_ShapesPainter old) => old.phase != phase;
}
