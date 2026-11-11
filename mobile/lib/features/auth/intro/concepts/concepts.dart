import 'dart:math' as math;
import 'dart:ui' as ui;

import 'package:flutter/material.dart';

import '../writing_plan.dart';

part 'concepts_ads.dart';

/// Five complete splash-screen concepts for the sign-in screen, each with its own theme, palette, components and motion.
/// They are previews for approval: nothing in the app uses them yet. The existing opening in `adinn_intro.dart` is untouched.

double cseg(double t, double a, double b, [Curve c = Curves.linear]) => c.transform(((t - a) / (b - a)).clamp(0.0, 1.0));
const _smooth = Cubic(0.65, 0, 0.35, 1);

/// Room reserved for the sign-in form at the bottom of the screen.
const double kPanelH = 340;

typedef ConceptFn = Widget Function(BuildContext context, double t, Size size, EdgeInsets pad, Widget panel);

class Concept {
  const Concept(this.name, this.build);
  final String name;
  final ConceptFn build;
}

final List<Concept> kConcepts = [
  const Concept('1-Blueprint', blueprintConcept),
  const Concept('2-Paper-and-Ink', paperConcept),
  const Concept('3-Billboard-Night', billboardConcept),
  const Concept('4-Aurora-Glass', auroraConcept),
  const Concept('5-Neon-Synthwave', synthConcept),
];

/// Runs one concept's 7-second timeline once, and holds the finished frame.
class ConceptPlayer extends StatefulWidget {
  const ConceptPlayer({super.key, required this.concept, required this.panel, this.seconds = 7.0});

  final Concept concept;
  final Widget panel;
  final double seconds;

  @override
  State<ConceptPlayer> createState() => _ConceptPlayerState();
}

class _ConceptPlayerState extends State<ConceptPlayer> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: Duration(milliseconds: (widget.seconds * 1000).round()));

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _c.forward();
    });
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final pad = MediaQuery.paddingOf(context);
    return LayoutBuilder(
      builder: (context, box) => AnimatedBuilder(
        animation: _c,
        builder: (context, _) => widget.concept.build(context, _c.value * widget.seconds, Size(box.maxWidth, box.maxHeight), pad, widget.panel),
      ),
    );
  }
}

// ---------------------------------------------------------------------------------------------------------------------
// The logo lockup, built from the printed logo's own parts so every concept can animate them separately.

enum LockStyle { solid, dark, outline }

enum LetterMode { wipe, pop, fade }

class _FracClipper extends CustomClipper<Rect> {
  _FracClipper(this.f);
  final double f;
  @override
  Rect getClip(Size size) => Rect.fromLTWH(0, 0, size.width * f.clamp(0.0, 1.0), size.height);
  @override
  bool shouldReclip(_FracClipper old) => old.f != f;
}

class Lockup extends StatelessWidget {
  const Lockup({
    super.key,
    required this.scale,
    this.style = LockStyle.solid,
    this.a = 1,
    this.aWipe = false,
    this.letters = const [1, 1, 1, 1],
    this.letterMode = LetterMode.wipe,
    this.dot = 1,
    this.dotDy = 0,
    this.rule = 1,
    this.tagline = 1,
    this.taglineColor,
    this.showTagline = true,
  });

  static const double w = 1630, h = 690, ox = 8;

  final double scale;
  final LockStyle style;
  final double a;
  final bool aWipe;
  final List<double> letters;
  final LetterMode letterMode;
  final double dot;
  final double dotDy;
  final double rule;
  final double tagline;
  final Color? taglineColor;
  final bool showTagline;

  @override
  Widget build(BuildContext context) {
    final s = scale;
    final plan = WritingPlan.shared;
    final outline = style == LockStyle.outline;
    final dark = style == LockStyle.dark;
    const ink = Color(0xFF0E0E12);

    Widget fit(Rect r, Widget child) => Positioned(left: (r.left + ox) * s, top: r.top * s, width: r.width * s, height: r.height * s, child: child);
    Image img(String asset, {Color? tint}) => Image.asset('assets/intro/$asset.png', fit: BoxFit.fill, filterQuality: FilterQuality.high, color: tint, colorBlendMode: tint == null ? null : BlendMode.srcATop, gaplessPlayback: true);

    final children = <Widget>[];

    // A
    if (a > 0) {
      final asset = outline ? 'A_outline' : (dark ? 'A_dark' : 'A');
      Widget aw = img(asset);
      aw = aWipe ? ClipRect(clipper: _FracClipper(a), child: aw) : Opacity(opacity: a.clamp(0.0, 1.0), child: aw);
      children.add(fit(kARect, aw));
    }

    // d i n n
    for (var i = 0; i < plan.strokes.length; i++) {
      final p = letters[i];
      if (p <= 0) continue;
      final st = plan.strokes[i];
      Widget g = img(outline ? '${st.asset}_outline' : st.asset, tint: dark ? ink : null);
      switch (letterMode) {
        case LetterMode.wipe:
          g = ClipRect(clipper: _FracClipper(p), child: g);
        case LetterMode.pop:
          final e = Curves.easeOutBack.transform(p.clamp(0.0, 1.0));
          g = Opacity(opacity: (p * 2).clamp(0.0, 1.0), child: Transform.scale(scale: 0.5 + 0.5 * e, alignment: Alignment.bottomCenter, child: g));
        case LetterMode.fade:
          g = Opacity(opacity: p.clamp(0.0, 1.0), child: g);
      }
      children.add(fit(st.dest, g));
    }

    // the red dot of the i
    if (dot > 0) {
      final d = 112 * s;
      final c = WritingPlan.dotTarget;
      final e = Curves.easeOutBack.transform(dot.clamp(0.0, 1.0));
      children.add(Positioned(
        left: (c.dx + ox) * s - d / 2,
        top: (c.dy + dotDy) * s - d / 2,
        width: d,
        height: d,
        child: Opacity(
          opacity: (dot * 3).clamp(0.0, 1.0),
          child: Transform.scale(
            scale: e,
            child: DecoratedBox(
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: outline ? Colors.transparent : const Color(0xFFE30613),
                border: outline ? Border.all(color: const Color(0xFF5EE6FF), width: 2.5 * s * 3) : null,
              ),
            ),
          ),
        ),
      ));
    }

    // the red rule under the word
    if (rule > 0) {
      final r = kLineRect;
      Widget line = outline ? Align(alignment: Alignment.center, child: Container(height: math.max(1.5, 3 * s * 2), color: const Color(0xFF5EE6FF))) : Image.asset('assets/intro/g_line.png', fit: BoxFit.fill, filterQuality: FilterQuality.high);
      children.add(fit(r, ClipRect(clipper: _FracClipper(rule), child: line)));
    }

    // "DESIGN TASKS" where the print has its tagline
    if (showTagline && tagline > 0) {
      const text = 'DESIGN TASKS';
      final fontSize = kTaglineCap * s / 0.711;
      final width = (kTaglineRight - kTaglineLeft) * s;
      final base = DefaultTextStyle.of(context).style.copyWith(color: taglineColor ?? (dark ? ink : Colors.white), fontSize: fontSize, fontWeight: FontWeight.w500, height: 1, letterSpacing: 0, decoration: TextDecoration.none);
      final tp = TextPainter(text: TextSpan(text: text, style: base), textDirection: TextDirection.ltr)..layout();
      final spacing = math.max(0.0, (width - tp.width) / (text.length - 1));
      children.add(Positioned(
        left: (kTaglineLeft + ox) * s,
        top: kTaglineCentreY * s - fontSize * 0.445,
        child: Opacity(opacity: tagline.clamp(0.0, 1.0), child: Text(text, maxLines: 1, softWrap: false, style: base.copyWith(letterSpacing: spacing * (0.6 + 0.4 * tagline.clamp(0.0, 1.0))))),
      ));
    }

    return SizedBox(width: w * s, height: h * s, child: Stack(clipBehavior: Clip.none, children: children));
  }
}

Widget _placeLockup(Offset centre, double s, Widget lockup) => Positioned(left: centre.dx - Lockup.w * s / 2, top: centre.dy - Lockup.h * s / 2, child: lockup);

double _hash(double n) {
  final v = math.sin(n * 12.9898) * 43758.5453;
  return v - v.floor();
}

// ---------------------------------------------------------------------------------------------------------------------
// 1. BLUEPRINT: navy drafting sheet, cyan line work. The logo is drafted as outlines with dimension marks, then filled in.

Widget blueprintConcept(BuildContext context, double t, Size sz, EdgeInsets pad, Widget panel) {
  final w = sz.width, h = sz.height;
  final move = cseg(t, 4.8, 5.8, _smooth);
  final s0 = w * 0.80 / 1630, s1 = w * 0.70 / 1630;
  final panelTop = h - (kPanelH + pad.bottom);
  final c0 = Offset(w / 2, h * 0.42), c1 = Offset(w / 2, pad.top + (panelTop - pad.top) / 2);
  final s = ui.lerpDouble(s0, s1, move)!;
  final cc = Offset.lerp(c0, c1, move)!;
  final origin = Offset(cc.dx - Lockup.w * s / 2, cc.dy - Lockup.h * s / 2);

  final fill = cseg(t, 3.0, 3.9);
  final outlineFade = 1 - 0.75 * cseg(t, 4.0, 5.0);
  final cardK = cseg(t, 5.0, 6.2, Curves.easeOutCubic);
  final dim = cseg(t, 2.6, 3.4) * (1 - cseg(t, 4.6, 5.4));
  const cyan = Color(0xFF5EE6FF);

  final typed = (cseg(t, 3.7, 4.7) * 12).floor();
  final tagFont = kTaglineCap * s / 0.711 * 0.92;

  return Stack(fit: StackFit.expand, children: [
    const DecoratedBox(decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Color(0xFF06142A), Color(0xFF0B2749)]))),
    CustomPaint(painter: _GridPainter(t)),
    _placeLockup(
      cc,
      s,
      Stack(children: [
        Opacity(
          opacity: outlineFade,
          child: Lockup(
            scale: s,
            style: LockStyle.outline,
            a: cseg(t, 0.8, 2.0, Curves.easeInOut),
            aWipe: true,
            letters: [for (var i = 0; i < 4; i++) cseg(t, 1.6 + i * 0.22, 2.5 + i * 0.22, Curves.easeInOut)],
            dot: cseg(t, 2.5, 3.0),
            rule: cseg(t, 2.6, 3.3, Curves.easeInOut),
            showTagline: false,
          ),
        ),
        Lockup(scale: s, a: fill, letters: [fill, fill, fill, fill], letterMode: LetterMode.fade, dot: fill, rule: 0, showTagline: false),
      ]),
    ),
    // dimension marks
    if (dim > 0.01) Positioned.fill(child: CustomPaint(painter: _DimPainter(Rect.fromLTWH(origin.dx, origin.dy, Lockup.w * s, Lockup.h * s), dim, w))),
    // typed tagline
    Positioned(
      left: origin.dx + (kTaglineLeft + Lockup.ox) * s,
      top: origin.dy + kTaglineCentreY * s - tagFont * 0.5,
      child: Text(
        'DESIGN TASKS'.substring(0, typed.clamp(0, 12)) + (t > 3.7 && t < 5.2 && (t * 3).floor().isEven ? '_' : ''),
        maxLines: 1,
        softWrap: false,
        style: TextStyle(color: Color.lerp(cyan, Colors.white, cseg(t, 4.7, 5.4)), fontSize: tagFont, fontWeight: FontWeight.w600, height: 1, letterSpacing: tagFont * 0.34, decoration: TextDecoration.none),
      ),
    ),
    Align(
      alignment: Alignment.bottomCenter,
      child: FractionalTranslation(
        translation: Offset(0, 1 - cardK),
        child: Container(
          decoration: const BoxDecoration(
            color: Color(0xF2081B33),
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
            border: Border(top: BorderSide(color: cyan, width: 1.5)),
          ),
          padding: EdgeInsets.fromLTRB(24, 8, 24, 20 + pad.bottom),
          child: panel,
        ),
      ),
    ),
  ]);
}

class _GridPainter extends CustomPainter {
  _GridPainter(this.t);
  final double t;

  @override
  void paint(Canvas canvas, Size size) {
    final k = cseg(t, 0, 1.6, Curves.easeOutCubic);
    final c = Offset(size.width / 2, size.height * 0.42);
    const step = 26.0;
    for (var pass = 0; pass < 2; pass++) {
      final major = pass == 1;
      final paint = Paint()
        ..strokeWidth = major ? 1.0 : 0.5
        ..color = const Color(0xFF5EE6FF).withValues(alpha: (major ? 0.20 : 0.09) * k);
      final every = major ? 5 : 1;
      for (var i = 0; i * step <= size.width; i += every) {
        final x = i * step;
        final reach = size.height * k;
        canvas.drawLine(Offset(x, c.dy - reach), Offset(x, c.dy + reach), paint);
      }
      for (var j = 0; j * step <= size.height; j += every) {
        final y = j * step;
        final reach = size.width * k;
        canvas.drawLine(Offset(c.dx - reach, y), Offset(c.dx + reach, y), paint);
      }
    }
  }

  @override
  bool shouldRepaint(_GridPainter old) => old.t != t;
}

class _DimPainter extends CustomPainter {
  _DimPainter(this.r, this.k, this.w);
  final Rect r;
  final double k;
  final double w;

  @override
  void paint(Canvas canvas, Size size) {
    final p = Paint()
      ..color = const Color(0xFF5EE6FF).withValues(alpha: 0.85 * k)
      ..strokeWidth = 1.2;
    void label(String text, Offset at) {
      final tp = TextPainter(
        text: TextSpan(text: text, style: TextStyle(fontSize: 10, color: const Color(0xFF5EE6FF).withValues(alpha: k))),
        textDirection: TextDirection.ltr,
      )..layout();
      tp.paint(canvas, at - Offset(tp.width / 2, tp.height / 2));
    }

    // width, below
    final y = r.bottom + 18;
    final x1 = r.left + (r.width * k), x0 = r.left;
    canvas.drawLine(Offset(x0, y), Offset(x1, y), p);
    for (final x in [x0, x1]) {
      canvas.drawLine(Offset(x, y - 6), Offset(x, y + 6), p);
    }
    canvas.drawLine(Offset(r.left, r.bottom), Offset(r.left, y + 6), p..color = p.color.withValues(alpha: 0.35 * k));
    label('W 1630', Offset(r.center.dx, y + 14));
    // height, left
    final x = r.left - 14;
    p.color = const Color(0xFF5EE6FF).withValues(alpha: 0.85 * k);
    final y1 = r.top + r.height * k;
    canvas.drawLine(Offset(x, r.top), Offset(x, y1), p);
    for (final yy in [r.top, y1]) {
      canvas.drawLine(Offset(x - 6, yy), Offset(x + 6, yy), p);
    }
    label('H 690', Offset(x - 2, r.center.dy - 24));
    // corner crosshairs
    for (final c in [r.topLeft, r.topRight, r.bottomRight]) {
      canvas.drawLine(c - const Offset(8, 0), c + const Offset(8, 0), p);
      canvas.drawLine(c - const Offset(0, 8), c + const Offset(0, 8), p);
    }
  }

  @override
  bool shouldRepaint(_DimPainter old) => true;
}

// ---------------------------------------------------------------------------------------------------------------------
// 2. PAPER & INK: warm paper, black ink and red. The logo bleeds onto the page, the dot drops, "DESIGN TASKS" is rubber-stamped.

Widget paperConcept(BuildContext context, double t, Size sz, EdgeInsets pad, Widget panel) {
  final w = sz.width, h = sz.height;
  final s = w * 0.84 / 1630;
  final c = Offset(w / 2, pad.top + (h - kPanelH - pad.bottom - pad.top) * 0.50);
  final cardK = Curves.easeOutBack.transform(cseg(t, 4.6, 5.8));

  final bleed = cseg(t, 0.3, 1.9, Curves.easeOut);
  final sigma = 14.0 * (1 - cseg(t, 0.3, 1.9, Curves.easeOutCubic));
  final dotF = cseg(t, 2.0, 2.7);
  final dotDy = (1 - Curves.bounceOut.transform(dotF)) * -360;
  final ruleF = cseg(t, 2.7, 3.3, Curves.easeInOut);

  // stamp: slams down, rebounds, then the page shudders
  final stampF = cseg(t, 3.2, 3.62, Curves.easeIn);
  final settle = cseg(t, 3.62, 3.9, Curves.easeOutBack);
  final stampScale = stampF < 1 ? ui.lerpDouble(2.0, 1.0, stampF)! : ui.lerpDouble(1.06, 1.0, settle)!;
  final shake = t > 3.6 && t < 3.9 ? math.sin((t - 3.6) * 120) * 4 * (1 - (t - 3.6) / 0.3) : 0.0;

  Widget lockup = Lockup(scale: s, style: LockStyle.dark, dot: dotF > 0 ? 1 : 0, dotDy: dotDy / 1, rule: ruleF, tagline: 0, showTagline: false);
  if (t < 2.0) {
    lockup = ImageFiltered(
      imageFilter: ui.ImageFilter.blur(sigmaX: math.max(0.01, sigma), sigmaY: math.max(0.01, sigma), tileMode: TileMode.decal),
      child: ShaderMask(
        blendMode: BlendMode.dstIn,
        shaderCallback: (b) {
          final r = (bleed * 0.62 + 0.001) * b.width;
          return RadialGradient(center: Alignment((c.dx / b.width) * 2 - 1, (c.dy / b.height) * 2 - 1), radius: r / b.shortestSide, colors: const [Colors.white, Colors.white, Color(0x00FFFFFF)], stops: const [0, 0.55, 1]).createShader(b);
        },
        child: Lockup(scale: s, style: LockStyle.dark, dot: 0, rule: 0, tagline: 0, showTagline: false),
      ),
    );
    lockup = SizedBox(width: Lockup.w * s, height: Lockup.h * s, child: OverflowBox(maxWidth: double.infinity, maxHeight: double.infinity, child: lockup));
  }

  final tagFont = kTaglineCap * s / 0.711 * 1.05;
  final tagCentre = Offset(c.dx - Lockup.w * s / 2 + (Lockup.ox + (kTaglineLeft + kTaglineRight) / 2) * s, c.dy - Lockup.h * s / 2 + kTaglineCentreY * s);

  return Stack(fit: StackFit.expand, children: [
    const ColoredBox(color: Color(0xFFF3EDE2)),
    CustomPaint(painter: _PaperPainter()),
    Transform.translate(
      offset: Offset(shake, shake * 0.5),
      child: Stack(fit: StackFit.expand, children: [
        _placeLockup(c, s, lockup),
        if (stampF > 0)
          Positioned(
            left: tagCentre.dx - 130,
            top: tagCentre.dy - tagFont,
            width: 260,
            height: tagFont * 2,
            child: Opacity(
              opacity: stampF.clamp(0.0, 1.0),
              child: Transform.rotate(
                angle: -0.05,
                child: Transform.scale(
                  scale: stampScale,
                  child: Center(
                    child: DecoratedBox(
                      decoration: BoxDecoration(border: Border.all(color: const Color(0xFFD0202B), width: 2.2), borderRadius: BorderRadius.circular(4)),
                      child: Padding(
                        padding: EdgeInsets.symmetric(horizontal: tagFont * 0.7, vertical: tagFont * 0.28),
                        child: Text('DESIGN TASKS', maxLines: 1, softWrap: false, style: TextStyle(color: const Color(0xFFD0202B), fontSize: tagFont, fontWeight: FontWeight.w800, letterSpacing: tagFont * 0.22, height: 1, decoration: TextDecoration.none)),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
      ]),
    ),
    // soft vignette
    IgnorePointer(child: DecoratedBox(decoration: BoxDecoration(gradient: RadialGradient(radius: 1.1, colors: [const Color(0x00000000), const Color(0x1F3A2A10)], stops: const [0.6, 1])))),
    Positioned(
      left: 14,
      right: 14,
      bottom: 14 + pad.bottom,
      child: FractionalTranslation(
        translation: Offset(0, (1 - cardK) * 1.35),
        child: Container(
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(28), boxShadow: const [BoxShadow(color: Color(0x2A000000), blurRadius: 34, offset: Offset(0, 12))]),
          padding: const EdgeInsets.fromLTRB(20, 6, 20, 20),
          child: panel,
        ),
      ),
    ),
  ]);
}

class _PaperPainter extends CustomPainter {
  static final _specks = () {
    final r = math.Random(21);
    return List.generate(420, (_) => (r.nextDouble(), r.nextDouble(), r.nextDouble()));
  }();

  @override
  void paint(Canvas canvas, Size size) {
    final p = Paint();
    for (final (x, y, v) in _specks) {
      p.color = const Color(0xFF8A7355).withValues(alpha: 0.05 + 0.10 * v);
      canvas.drawCircle(Offset(x * size.width, y * size.height), 0.4 + v * 0.9, p);
    }
  }

  @override
  bool shouldRepaint(_PaperPainter old) => false;
}

// ---------------------------------------------------------------------------------------------------------------------
// 3. BILLBOARD NIGHT: dusk sky and skyline. Lamps flicker on over a billboard, the logo is printed onto it, the camera pulls back.

Widget billboardConcept(BuildContext context, double t, Size sz, EdgeInsets pad, Widget panel) {
  final w = sz.width, h = sz.height;
  double lamp() {
    if (t < 0.5) return 0;
    if (t > 1.7) return 1;
    return _hash((t * 14).floorToDouble()) > 0.42 ? 1 : 0.12;
  }

  final lampOn = lamp();
  final cardK = cseg(t, 4.6, 5.8, Curves.easeOutCubic);
  final cam = cseg(t, 4.4, 5.6, _smooth);
  final cityH = math.min(w, 720.0) * 667 / 2000;
  final cityTop = h * 0.60;

  final bw = w * 0.88, bh = bw * 0.47;
  final boardTop = pad.top + h * 0.12;
  final pivot = Offset(w / 2, boardTop + bh / 2);
  final ls = (bw - 22) * 0.94 / 1630;
  final lit = lampOn * cseg(t, 0.5, 1.9);

  final board = Stack(clipBehavior: Clip.none, children: [
    // pole
    Positioned(left: w / 2 - w * 0.025, top: boardTop + bh, width: w * 0.05, height: cityTop + cityH * 0.55 - (boardTop + bh), child: const DecoratedBox(decoration: BoxDecoration(gradient: LinearGradient(colors: [Color(0xFF14141c), Color(0xFF2a2a36), Color(0xFF14141c)])))),
    // lamp light
    Positioned.fill(child: CustomPaint(painter: _LampPainter(Rect.fromLTWH((w - bw) / 2, boardTop, bw, bh), lampOn))),
    // board
    Positioned(
      left: (w - bw) / 2,
      top: boardTop,
      width: bw,
      height: bh,
      child: Container(
        decoration: BoxDecoration(color: const Color(0xFF17171F), borderRadius: BorderRadius.circular(8), boxShadow: [BoxShadow(color: const Color(0xFFFFE2A8).withValues(alpha: 0.35 * lit), blurRadius: 60, spreadRadius: 6)]),
        padding: const EdgeInsets.all(8),
        child: Container(
          decoration: BoxDecoration(color: Color.lerp(const Color(0xFF22222C), Colors.white, lit), borderRadius: BorderRadius.circular(4)),
          alignment: Alignment.center,
          child: Lockup(
            scale: ls,
            style: LockStyle.dark,
            a: cseg(t, 1.8, 2.7, Curves.easeOut),
            letters: [for (var i = 0; i < 4; i++) cseg(t, 2.3 + i * 0.2, 3.0 + i * 0.2, Curves.easeInOut)],
            dot: cseg(t, 3.2, 3.7),
            rule: cseg(t, 3.5, 4.0, Curves.easeInOut),
            tagline: cseg(t, 3.9, 4.5),
          ),
        ),
      ),
    ),
  ]);

  return Stack(fit: StackFit.expand, children: [
    const DecoratedBox(decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Color(0xFF0F0828), Color(0xFF3F1A58), Color(0xFFC84B3A), Color(0xFFF08A3C)], stops: [0, 0.45, 0.8, 1]))),
    CustomPaint(painter: _StarsPainter(t)),
    Positioned(left: 0, right: 0, top: cityTop + cityH - 1, bottom: 0, child: const ColoredBox(color: Color(0xFF0B0712))),
    Positioned(left: (w - math.min(w, 720.0)) / 2, width: math.min(w, 720.0), top: cityTop, height: cityH, child: Image.asset('assets/intro/city.webp', fit: BoxFit.fill, filterQuality: FilterQuality.medium, gaplessPlayback: true)),
    Transform.translate(offset: Offset(0, -h * 0.045 * cam), child: Transform.scale(scale: 1 - 0.18 * cam, origin: pivot, child: board)),
    Align(
      alignment: Alignment.bottomCenter,
      child: FractionalTranslation(
        translation: Offset(0, 1 - cardK),
        child: Container(
          decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(28))),
          padding: EdgeInsets.fromLTRB(24, 8, 24, 20 + pad.bottom),
          child: panel,
        ),
      ),
    ),
  ]);
}

class _LampPainter extends CustomPainter {
  _LampPainter(this.board, this.on);
  final Rect board;
  final double on;

  @override
  void paint(Canvas canvas, Size size) {
    for (var i = 0; i < 3; i++) {
      final x = board.left + board.width * (0.2 + i * 0.3);
      final lampY = board.top - 14;
      // lamp head
      canvas.drawRRect(RRect.fromRectAndRadius(Rect.fromCenter(center: Offset(x, lampY), width: 26, height: 7), const Radius.circular(3)), Paint()..color = Color.lerp(const Color(0xFF2B2B35), const Color(0xFFFFF0C2), on)!);
      if (on <= 0.02) continue;
      final cone = Path()
        ..moveTo(x - 8, lampY + 3)
        ..lineTo(x + 8, lampY + 3)
        ..lineTo(x + 70, board.bottom - 6)
        ..lineTo(x - 70, board.bottom - 6)
        ..close();
      canvas.drawPath(cone, Paint()..shader = ui.Gradient.linear(Offset(x, lampY), Offset(x, board.bottom), [const Color(0xFFFFEBB8).withValues(alpha: 0.38 * on), const Color(0x00FFEBB8)]));
    }
  }

  @override
  bool shouldRepaint(_LampPainter old) => true;
}

class _StarsPainter extends CustomPainter {
  _StarsPainter(this.t);
  final double t;
  static final _stars = () {
    final r = math.Random(8);
    return List.generate(70, (_) => (r.nextDouble(), r.nextDouble() * 0.5, r.nextDouble()));
  }();

  @override
  void paint(Canvas canvas, Size size) {
    final p = Paint();
    for (final (x, y, v) in _stars) {
      final tw = 0.5 + 0.5 * math.sin(t * (1.5 + v * 3) + v * 20);
      p.color = Colors.white.withValues(alpha: (0.25 + 0.6 * tw) * cseg(t, 0, 0.8));
      canvas.drawCircle(Offset(x * size.width, y * size.height), 0.6 + v * 1.1, p);
    }
  }

  @override
  bool shouldRepaint(_StarsPainter old) => true;
}

// ---------------------------------------------------------------------------------------------------------------------
// 4. AURORA GLASS: a light, airy theme. Soft colour orbs drift behind; the logo's parts spring in; the sign-in is frosted glass.

Widget auroraConcept(BuildContext context, double t, Size sz, EdgeInsets pad, Widget panel) {
  final w = sz.width, h = sz.height;
  final move = cseg(t, 4.4, 5.4, _smooth);
  final panelTop = h - (kPanelH + pad.bottom + 12);
  final s0 = w * 0.84 / 1630, s1 = w * 0.76 / 1630;
  final c0 = Offset(w / 2, h * 0.44), c1 = Offset(w / 2, pad.top + (panelTop - pad.top) * 0.5);
  final s = ui.lerpDouble(s0, s1, move)!;
  final c = Offset.lerp(c0, c1, move)!;
  final cardK = cseg(t, 4.6, 5.8, Curves.easeOutCubic);

  final aK = cseg(t, 0.4, 1.3, Curves.easeOutCubic);

  return Stack(fit: StackFit.expand, children: [
    const DecoratedBox(decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [Color(0xFFFFFAF6), Color(0xFFFFE9E2)]))),
    CustomPaint(painter: _OrbPainter(t)),
    _placeLockup(
      c + Offset(0, (1 - aK) * 30 * s * 3),
      s,
      Lockup(
        scale: s,
        style: LockStyle.dark,
        a: aK,
        letters: [for (var i = 0; i < 4; i++) cseg(t, 1.0 + i * 0.18, 1.7 + i * 0.18)],
        letterMode: LetterMode.pop,
        dot: cseg(t, 1.9, 2.6),
        dotDy: -(1 - Curves.easeOutBack.transform(cseg(t, 1.9, 2.6))).clamp(0.0, 2.0) * 200,
        rule: cseg(t, 2.5, 3.2, Curves.easeInOut),
        tagline: cseg(t, 3.0, 3.9, Curves.easeOut),
        taglineColor: const Color(0xFF3A3A44),
      ),
    ),
    Positioned(
      left: 12,
      right: 12,
      bottom: 12 + pad.bottom,
      child: FractionalTranslation(
        translation: Offset(0, (1 - cardK) * 1.3),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(30),
          child: BackdropFilter(
            filter: ui.ImageFilter.blur(sigmaX: 22, sigmaY: 22),
            child: Container(
              decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.58), borderRadius: BorderRadius.circular(30), border: Border.all(color: Colors.white.withValues(alpha: 0.85), width: 1.4)),
              padding: const EdgeInsets.fromLTRB(20, 6, 20, 20),
              child: panel,
            ),
          ),
        ),
      ),
    ),
  ]);
}

class _OrbPainter extends CustomPainter {
  _OrbPainter(this.t);
  final double t;

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width, h = size.height;
    void orb(Offset c, double r, Color color) {
      canvas.drawCircle(c, r, Paint()..shader = ui.Gradient.radial(c, r, [color, color.withValues(alpha: 0)]));
    }

    final k = cseg(t, 0, 1.2);
    orb(Offset(w * (0.2 + 0.08 * math.sin(t * 0.7)), h * (0.22 + 0.05 * math.cos(t * 0.5))), w * 0.62, const Color(0xFFFF4B55).withValues(alpha: 0.38 * k));
    orb(Offset(w * (0.85 + 0.06 * math.cos(t * 0.6)), h * (0.35 + 0.06 * math.sin(t * 0.8))), w * 0.58, const Color(0xFFFFB067).withValues(alpha: 0.42 * k));
    orb(Offset(w * (0.4 + 0.1 * math.sin(t * 0.5 + 2)), h * (0.78 + 0.04 * math.cos(t * 0.9))), w * 0.7, const Color(0xFFFF8FB1).withValues(alpha: 0.40 * k));
    orb(Offset(w * (0.9 + 0.05 * math.sin(t * 0.9)), h * (0.88 + 0.03 * math.sin(t))), w * 0.5, const Color(0xFFB9A6FF).withValues(alpha: 0.36 * k));
  }

  @override
  bool shouldRepaint(_OrbPainter old) => true;
}

// ---------------------------------------------------------------------------------------------------------------------
// 5. NEON SYNTHWAVE: purple night, striped sun, a moving grid floor. The logo is a neon sign that flickers on part by part.

double _neon(double t, double start) {
  if (t < start) return 0;
  final u = t - start;
  if (u > 0.7) return 1;
  return _hash((u * 30).floorToDouble() + start * 7.3) > 0.45 ? 1 : 0.08;
}

Widget synthConcept(BuildContext context, double t, Size sz, EdgeInsets pad, Widget panel) {
  final w = sz.width, h = sz.height;
  final s = w * 0.84 / 1630;
  final c = Offset(w / 2, h * 0.28);
  final cardK = cseg(t, 4.8, 6.0, Curves.easeOutCubic);

  final nA = _neon(t, 1.3);
  final nL = [for (var i = 0; i < 4; i++) _neon(t, 1.9 + i * 0.22)];
  final nD = _neon(t, 2.8);
  final nR = _neon(t, 3.1);
  final nT = _neon(t, 3.5);

  Widget lk() => Lockup(scale: s, a: nA, letters: nL, letterMode: LetterMode.fade, dot: nD > 0.5 ? 1 : 0, rule: nR > 0.5 ? 1 : 0, tagline: nT, taglineColor: const Color(0xFF7CF4FF));
  Widget glow(Color col, double sigma, double opacity) => Opacity(opacity: opacity, child: ImageFiltered(imageFilter: ui.ImageFilter.blur(sigmaX: sigma, sigmaY: sigma, tileMode: TileMode.decal), child: ColorFiltered(colorFilter: ColorFilter.mode(col, BlendMode.srcATop), child: lk())));

  return Stack(fit: StackFit.expand, children: [
    const DecoratedBox(decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Color(0xFF0A0220), Color(0xFF2B0A4D), Color(0xFF5B1466)], stops: [0, 0.6, 1]))),
    CustomPaint(painter: _SynthPainter(t, h * 0.52)),
    _placeLockup(c, s, Stack(clipBehavior: Clip.none, children: [glow(const Color(0xFFFF2E93), 20 * s * 4, 0.9), glow(const Color(0xFF39E1FF), 6 * s * 4, 0.8), lk()])),
    Align(
      alignment: Alignment.bottomCenter,
      child: FractionalTranslation(
        translation: Offset(0, 1 - cardK),
        child: Container(
          decoration: BoxDecoration(
            color: const Color(0xEB15062C),
            borderRadius: const BorderRadius.vertical(top: Radius.circular(26)),
            border: Border.all(color: const Color(0xFFFF2E93), width: 1.6),
            boxShadow: [BoxShadow(color: const Color(0xFFFF2E93).withValues(alpha: 0.55), blurRadius: 26, spreadRadius: 1)],
          ),
          padding: EdgeInsets.fromLTRB(24, 8, 24, 20 + pad.bottom),
          child: panel,
        ),
      ),
    ),
  ]);
}

class _SynthPainter extends CustomPainter {
  _SynthPainter(this.t, this.horizon);
  final double t;
  final double horizon;

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width, h = size.height;
    // stars
    final sp = Paint();
    final r = math.Random(3);
    for (var i = 0; i < 60; i++) {
      final x = r.nextDouble() * w, y = r.nextDouble() * horizon * 0.9, v = r.nextDouble();
      sp.color = Colors.white.withValues(alpha: (0.2 + 0.6 * (0.5 + 0.5 * math.sin(t * (1 + v * 2) + v * 9))) * cseg(t, 0, 0.8));
      canvas.drawCircle(Offset(x, y), 0.5 + v, sp);
    }
    // sun, rising from behind the horizon
    final rise = cseg(t, 0.2, 1.6, Curves.easeOutCubic);
    final sunR = w * 0.30;
    final centre = Offset(w / 2, horizon + sunR * (1 - rise) * 1.1);
    canvas.save();
    canvas.clipRect(Rect.fromLTWH(0, 0, w, horizon));
    canvas.drawCircle(centre, sunR, Paint()..shader = ui.Gradient.linear(centre - Offset(0, sunR), centre + Offset(0, sunR), const [Color(0xFFFFD23F), Color(0xFFFF2E93)]));
    // slats cut into the lower half
    final cut = Paint()..color = const Color(0xFF2B0A4D);
    for (var i = 0; i < 7; i++) {
      final y = centre.dy + sunR * (0.05 + i * 0.14);
      canvas.drawRect(Rect.fromLTWH(centre.dx - sunR, y, sunR * 2, 1.5 + i * 1.3), cut);
    }
    canvas.restore();
    // floor
    canvas.drawRect(Rect.fromLTWH(0, horizon, w, h - horizon), Paint()..shader = ui.Gradient.linear(Offset(0, horizon), Offset(0, h), const [Color(0xFF2B0A4D), Color(0xFF14052B)]));
    final line = Paint()
      ..strokeWidth = 1.3
      ..color = const Color(0xFFFF2E93).withValues(alpha: 0.85 * cseg(t, 0.8, 1.8));
    final vp = Offset(w / 2, horizon);
    for (var i = -12; i <= 12; i++) {
      canvas.drawLine(vp, Offset(w / 2 + i * w * 0.32, h), line);
    }
    final phase = (t * 0.55) % 1.0;
    for (var i = 0; i < 12; i++) {
      final f = (i + phase) / 12;
      final y = horizon + (h - horizon) * f * f;
      canvas.drawLine(Offset(0, y), Offset(w, y), line..strokeWidth = 0.6 + 1.6 * f);
    }
    // horizon glow
    canvas.drawRect(Rect.fromLTWH(0, horizon - 3, w, 6), Paint()..color = const Color(0xFFFF2E93).withValues(alpha: 0.9)..maskFilter = const MaskFilter.blur(BlurStyle.normal, 6));
  }

  @override
  bool shouldRepaint(_SynthPainter old) => true;
}
