part of 'adinn_intro.dart';

/// The ten ways the logo can come into view (0 - 1.5 s, sharpening by 2.2 s). Everything after that (the pencil writing
/// "dinn", the printed logo, the skyline and the sign-in module) is the same in every one.
enum IntroOpening {
  /// Glowing particles converge and assemble the logo.
  particles,

  /// A beam of light sweeps left to right; the logo exists behind it.
  beam,

  /// A ring of light expands from the centre, revealing the logo inside it.
  ring,

  /// The logo starts huge and soft, and flies back into focus.
  zoom,

  /// A glowing line rises from the bottom, uncovering the logo.
  riseUp,

  /// Horizontal slats open one after another.
  blinds,

  /// A screen glitch: the logo flickers with red/cyan colour split, then locks in.
  glitch,

  /// A spotlight sweeps in from a corner and grows until the whole logo is lit.
  spotlight,

  /// The logo appears tile by tile, like a digital mosaic.
  mosaic,

  /// The logo rises out of a flash of light with a lens streak.
  bloom,
}

/// Per-opening look: the logo's opacity/blur, how it is revealed ([wrap]) and the light effects drawn over it ([overlays]).
class _OpeningFx {
  _OpeningFx(this.style, this.t, this.o, this.s);

  final IntroOpening style;
  final double t;
  final Offset o;
  final double s;

  bool get particles => style == IntroOpening.particles;
  bool get _masking => t < 1.5;
  Offset get _c => o + const Offset(395, 342) * s;
  Rect get _box => Rect.fromLTWH(o.dx - 20 * s, o.dy, 840 * s, 690 * s);
  double get _p => _seg(t, 0.25, 1.45, Curves.easeInOutCubic);
  double get _lightA => _seg(t, 0.2, 0.4) * (1 - _seg(t, 1.3, 1.55));

  /// Opacity of the logo; null keeps the default (fade in with the particles).
  double? get opacity => switch (style) {
        IntroOpening.particles => null,
        IntroOpening.zoom => _seg(t, 0.1, 0.8),
        IntroOpening.bloom => _seg(t, 0.3, 1.2),
        _ => 1,
      };

  /// Blur of the logo; null keeps the default (soft until 1.5 s, then sharpening).
  double? get sigma => switch (style) {
        IntroOpening.zoom || IntroOpening.bloom => 18.0 * (1 - _seg(t, 0.1, 1.8, Curves.easeOutCubic)),
        IntroOpening.glitch => 0,
        _ => null,
      };

  /// Glow behind the logo; null keeps the default pulse.
  double? get glow => style == IntroOpening.bloom ? math.sin(math.pi * _seg(t, 0.5, 2.0)) * 0.9 * (1 - _seg(t, 2.4, 3.4)) : null;

  Widget _mask(Widget child, Shader Function(Rect) shader) => ShaderMask(blendMode: BlendMode.dstIn, shaderCallback: shader, child: child);

  Alignment _align(Offset p, Rect b) => Alignment((p.dx - b.left) / (b.width / 2) - 1, (p.dy - b.top) / (b.height / 2) - 1);

  Widget wrap(Widget Function() build) {
    switch (style) {
      case IntroOpening.particles:
        return build();

      case IntroOpening.beam:
        if (!_masking) return build();
        final from = o.dx - 40 * s, to = o.dx + 720 * s, f = 150 * s;
        final edge = from + (to - from + f) * _p;
        return _mask(build(), (b) {
          final w = math.max(1.0, b.width);
          final a = ((edge - f) / w).clamp(0.0, 1.0), e = (edge / w).clamp(0.0, 1.0);
          return LinearGradient(colors: const [Colors.white, Colors.white, Color(0x00FFFFFF), Color(0x00FFFFFF)], stops: [0, a, e, 1]).createShader(b);
        });

      case IntroOpening.ring:
        if (!_masking) return build();
        final f = 130 * s, rr = math.max(1.0, (560 * s + f) * _p);
        return _mask(build(), (b) {
          return RadialGradient(
            center: _align(_c, b),
            radius: rr / b.shortestSide,
            colors: const [Colors.white, Colors.white, Color(0x00FFFFFF)],
            stops: [0, ((rr - f) / rr).clamp(0.0, 1.0), 1],
          ).createShader(b);
        });

      case IntroOpening.riseUp:
        if (!_masking) return build();
        final f = 170 * s, e = ui.lerpDouble(_box.bottom + f, _box.top - 1, _p)!;
        return _mask(build(), (b) {
          final h = math.max(1.0, b.height);
          return LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: const [Color(0x00FFFFFF), Color(0x00FFFFFF), Colors.white, Colors.white],
            stops: [0, ((e - f) / h).clamp(0.0, 1.0), (e / h).clamp(0.0, 1.0), 1],
          ).createShader(b);
        });

      case IntroOpening.blinds:
        if (!_masking) return build();
        return ClipPath(clipper: _BlindsClipper(_box, _p), child: build());

      case IntroOpening.mosaic:
        if (!_masking) return build();
        return ClipPath(clipper: _MosaicClipper(_box, _p), child: build());

      case IntroOpening.spotlight:
        if (!_masking) return build();
        final centre = Offset.lerp(Offset(o.dx - 120 * s, o.dy + 760 * s), _c, _p)!;
        final rr = ui.lerpDouble(140 * s, 1100 * s, _p)!;
        return _mask(build(), (b) {
          return RadialGradient(
            center: _align(centre, b),
            radius: rr / b.shortestSide,
            colors: const [Colors.white, Colors.white, Color(0x00FFFFFF)],
            stops: const [0, 0.5, 1],
          ).createShader(b);
        });

      case IntroOpening.zoom:
        if (t >= 1.6) return build();
        final z = ui.lerpDouble(2.4, 1, Curves.easeOutCubic.transform(_seg(t, 0.1, 1.5)))!;
        return Transform.scale(scale: z, origin: _c, child: build());

      case IntroOpening.bloom:
        if (t >= 1.7) return build();
        final e = Curves.easeOutCubic.transform(_seg(t, 0.3, 1.6));
        return Transform.translate(offset: Offset(0, (1 - e) * 90 * s), child: Transform.scale(scale: ui.lerpDouble(0.86, 1, e)!, origin: _c, child: build()));

      case IntroOpening.glitch:
        if (t >= 1.6) return build();
        double h(int n) {
          final v = math.sin(n * 12.9898) * 43758.5453;
          return v - v.floor();
        }

        final frame = (t * 24).floor();
        final amount = 1 - _seg(t, 0.4, 1.5);
        final visible = t > 0.2 && (h(frame) > 0.4 || t > 1.3);
        if (!visible) return const SizedBox.shrink();
        final dx = (h(frame + 7) - 0.5) * 34 * s * amount;
        Widget tint(List<double> m, double off) => Opacity(
              opacity: 0.9 * amount,
              child: Transform.translate(offset: Offset(off, 0), child: ColorFiltered(colorFilter: ColorFilter.matrix(m), child: build())),
            );
        return Stack(fit: StackFit.expand, children: [
          if (amount > 0.02) tint(const [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0], -dx),
          if (amount > 0.02) tint(const [0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1, 0], dx),
          Opacity(opacity: 1 - 0.35 * amount, child: build()),
        ]);
    }
  }

  /// Light effects drawn above the logo layers.
  List<Widget> overlays() {
    final a = _lightA;
    switch (style) {
      case IntroOpening.beam:
        if (a <= 0.01) return const [];
        final from = o.dx - 40 * s, to = o.dx + 720 * s, f = 150 * s;
        final edge = from + (to - from + f) * _p;
        return [
          Positioned(
            left: edge - f * 0.85 - 70 * s,
            top: o.dy - 60 * s,
            width: 140 * s,
            height: 810 * s,
            child: _fadeVertical(DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(colors: [
                  const Color(0x00FF1F2E),
                  adinnRed.withValues(alpha: 0.35 * a),
                  Colors.white.withValues(alpha: 0.95 * a),
                  adinnRed.withValues(alpha: 0.35 * a),
                  const Color(0x00FF1F2E),
                ], stops: const [0, 0.35, 0.5, 0.65, 1]),
              ),
            )),
          ),
        ];

      case IntroOpening.ring:
        if (a <= 0.01) return const [];
        final f = 130 * s, rr = (560 * s + f) * _p;
        return [Positioned.fill(child: CustomPaint(painter: _RingPainter(_c, math.max(1.0, rr - f * 0.35), a, s)))];

      case IntroOpening.riseUp:
        if (a <= 0.01) return const [];
        final f = 170 * s, e = ui.lerpDouble(_box.bottom + f, _box.top - 1, _p)!;
        return [
          Positioned(
            left: _box.left,
            width: _box.width,
            top: e - f * 0.85 - 40 * s,
            height: 80 * s,
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [
                  const Color(0x00FF1F2E),
                  adinnRed.withValues(alpha: 0.4 * a),
                  Colors.white.withValues(alpha: 0.95 * a),
                  adinnRed.withValues(alpha: 0.4 * a),
                  const Color(0x00FF1F2E),
                ], stops: const [0, 0.35, 0.5, 0.65, 1]),
              ),
            ),
          ),
        ];

      case IntroOpening.spotlight:
        if (!_masking) return const [];
        final centre = Offset.lerp(Offset(o.dx - 120 * s, o.dy + 760 * s), _c, _p)!;
        final rr = ui.lerpDouble(140 * s, 1100 * s, _p)! * 0.8;
        final k = _seg(t, 0.15, 0.4) * (1 - _seg(t, 1.0, 1.5));
        return [
          Positioned(
            left: centre.dx - rr,
            top: centre.dy - rr,
            width: rr * 2,
            height: rr * 2,
            child: DecoratedBox(decoration: BoxDecoration(gradient: RadialGradient(colors: [Colors.white.withValues(alpha: 0.30 * k), adinnRed.withValues(alpha: 0.10 * k), const Color(0x00FF1F2E)], stops: const [0, 0.4, 1]))),
          ),
        ];

      case IntroOpening.bloom:
        final k = math.sin(math.pi * _seg(t, 0.55, 1.9));
        if (k <= 0.01) return const [];
        return [
          Positioned(
            left: _c.dx - 520 * s * (0.5 + k * 0.5),
            top: _c.dy - 520 * s * (0.5 + k * 0.5),
            width: 1040 * s * (0.5 + k * 0.5),
            height: 1040 * s * (0.5 + k * 0.5),
            child: DecoratedBox(decoration: BoxDecoration(gradient: RadialGradient(colors: [Colors.white.withValues(alpha: 0.55 * k), adinnRed.withValues(alpha: 0.28 * k), const Color(0x00FF1F2E)], stops: const [0, 0.35, 1]))),
          ),
          Positioned(
            left: 0,
            right: 0,
            top: _c.dy - 3 * s,
            height: 6 * s,
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(colors: [const Color(0x00FFFFFF), Colors.white.withValues(alpha: 0.9 * k), const Color(0x00FFFFFF)], stops: const [0.05 + 0.0, 0.5, 0.95]),
              ),
            ),
          ),
        ];

      default:
        return const [];
    }
  }

  Widget _fadeVertical(Widget child) => ShaderMask(
        blendMode: BlendMode.dstIn,
        shaderCallback: (r) => const LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Color(0x00FFFFFF), Colors.white, Colors.white, Color(0x00FFFFFF)], stops: [0, 0.3, 0.7, 1]).createShader(r),
        child: child,
      );
}

class _RingPainter extends CustomPainter {
  _RingPainter(this.c, this.r, this.a, this.s);

  final Offset c;
  final double r, a, s;

  @override
  void paint(Canvas canvas, Size size) {
    canvas.drawCircle(c, r, Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 16 * s
      ..color = adinnRed.withValues(alpha: 0.55 * a)
      ..maskFilter = MaskFilter.blur(BlurStyle.normal, 14 * s));
    canvas.drawCircle(c, r, Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3.2 * s
      ..color = Colors.white.withValues(alpha: 0.95 * a));
  }

  @override
  bool shouldRepaint(_RingPainter old) => true;
}

class _BlindsClipper extends CustomClipper<Path> {
  _BlindsClipper(this.box, this.p);

  final Rect box;
  final double p;

  @override
  Path getClip(Size size) {
    const n = 8;
    final h = box.height / n, path = Path();
    for (var i = 0; i < n; i++) {
      final open = Curves.easeOut.transform(((p - i * 0.06) / 0.5).clamp(0.0, 1.0));
      if (open <= 0) continue;
      path.addRect(Rect.fromCenter(center: Offset(box.center.dx, box.top + (i + 0.5) * h), width: box.width, height: h * open + (open >= 1 ? 1 : 0)));
    }
    return path;
  }

  @override
  bool shouldReclip(_BlindsClipper old) => true;
}

class _MosaicClipper extends CustomClipper<Path> {
  _MosaicClipper(this.box, this.p);

  final Rect box;
  final double p;

  static const cols = 14, rows = 12;
  static final ranks = () {
    final r = math.Random(5);
    return List<double>.generate(cols * rows, (_) => r.nextDouble());
  }();

  @override
  Path getClip(Size size) {
    final cw = box.width / cols, ch = box.height / rows, path = Path();
    for (var i = 0; i < rows; i++) {
      for (var j = 0; j < cols; j++) {
        final open = ((p - ranks[i * cols + j] * 0.65) / 0.35).clamp(0.0, 1.0);
        if (open <= 0) continue;
        final centre = Offset(box.left + (j + 0.5) * cw, box.top + (i + 0.5) * ch);
        path.addRect(Rect.fromCenter(center: centre, width: cw * open + (open >= 1 ? 1.2 : 0), height: ch * open + (open >= 1 ? 1.2 : 0)));
      }
    }
    return path;
  }

  @override
  bool shouldReclip(_MosaicClipper old) => true;
}
