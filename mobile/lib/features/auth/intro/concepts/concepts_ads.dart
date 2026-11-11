part of 'concepts.dart';

/// Five more concepts, all drawn from what an advertising company does: hoardings and posters, a billboard launch, the many
/// ad formats, an LED marquee, and the print press.

final List<Concept> kAdConcepts = [
  const Concept('6-Poster-Paste-up', pasteupConcept),
  const Concept('7-Billboard-Unveil', unveilConcept),
  const Concept('8-Ad-Formats-Burst', formatsConcept),
  const Concept('9-LED-Marquee', ledConcept),
  const Concept('10-Print-Press', pressConcept),
];

Widget _slideCard(double k, EdgeInsets pad, Widget panel, {Color color = Colors.white, Border? border, double radius = 28, List<BoxShadow>? shadow}) => Align(
      alignment: Alignment.bottomCenter,
      child: FractionalTranslation(
        translation: Offset(0, 1 - k),
        child: Container(
          decoration: BoxDecoration(color: color, borderRadius: BorderRadius.vertical(top: Radius.circular(radius)), border: border, boxShadow: shadow),
          padding: EdgeInsets.fromLTRB(24, 8, 24, 20 + pad.bottom),
          child: panel,
        ),
      ),
    );

// ---------------------------------------------------------------------------------------------------------------------
// 6. POSTER PASTE-UP: a street wall; posters are slapped on one after another; the final poster carries the logo and a tape label.

class _PosterSpec {
  const _PosterSpec(this.dx, this.dy, this.pw, this.ph, this.angle, this.color, this.start, this.pattern);
  final double dx, dy, pw, ph, angle, start;
  final Color color;
  final int pattern;
}

Widget pasteupConcept(BuildContext context, double t, Size sz, EdgeInsets pad, Widget panel) {
  final w = sz.width, h = sz.height;
  final areaH = h - kPanelH - pad.bottom - pad.top;
  final c = Offset(w / 2, pad.top + areaH * 0.5 + 6);
  const specs = [
    _PosterSpec(-0.30, -0.10, 0.42, 0.56, -0.06, Color(0xFFE30613), 0.2, 0),
    _PosterSpec(0.31, -0.17, 0.40, 0.50, 0.05, Color(0xFFFFC93C), 0.45, 1),
    _PosterSpec(-0.10, 0.20, 0.50, 0.40, -0.03, Color(0xFF16161A), 0.7, 2),
    _PosterSpec(0.34, 0.17, 0.34, 0.46, 0.07, Color(0xFF1FA8A0), 0.95, 0),
    _PosterSpec(-0.37, 0.23, 0.36, 0.40, 0.04, Color(0xFFF48FB1), 1.2, 1),
    _PosterSpec(0.0, -0.31, 0.50, 0.34, -0.02, Color(0xFF2D5BFF), 1.45, 2),
  ];

  Widget posterAt(_PosterSpec p) {
    final f = cseg(t, p.start, p.start + 0.45, Curves.easeIn);
    if (f <= 0) return const SizedBox.shrink();
    final hit = cseg(t, p.start + 0.45, p.start + 0.7, Curves.easeOut);
    final scale = f < 1 ? 1 + 0.25 * (1 - f) : 1 + 0.04 * (1 - hit);
    final dy = -(1 - f) * h * 0.9;
    return Positioned(
      left: c.dx + p.dx * w - p.pw * w / 2,
      top: c.dy + p.dy * w - p.ph * w / 2 + dy,
      width: p.pw * w,
      height: p.ph * w,
      child: Transform.rotate(
        angle: p.angle,
        child: Transform.scale(
          scale: scale,
          child: Container(
            decoration: BoxDecoration(color: p.color, boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.30 * (f < 1 ? 0.5 : 1)), blurRadius: f < 1 ? 26 : 8, offset: Offset(0, f < 1 ? 20 : 4))]),
            child: CustomPaint(painter: _PosterArtPainter(p.pattern)),
          ),
        ),
      ),
    );
  }

  // the final poster
  final pw = w * 0.88, ph = pw * 0.60;
  final f = cseg(t, 2.3, 2.9, Curves.easeIn);
  final hit = cseg(t, 2.9, 3.15, Curves.easeOut);
  final ps = f < 1 ? 1 + 0.3 * (1 - f) : 1 + 0.035 * (1 - hit);
  final s = pw * 0.90 / 1630;
  final sheen = cseg(t, 3.15, 3.8, Curves.easeInOut);
  final tape = Curves.easeOutBack.transform(cseg(t, 4.0, 4.6));

  final finalPoster = f <= 0
      ? const SizedBox.shrink()
      : Positioned(
          left: c.dx - pw / 2,
          top: c.dy - ph / 2 - (1 - f) * h,
          width: pw,
          height: ph,
          child: Transform.scale(
            scale: ps,
            child: Container(
              decoration: BoxDecoration(color: const Color(0xFFFCFAF5), border: Border.all(color: const Color(0xFF16161A), width: 3), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.35), blurRadius: f < 1 ? 40 : 14, offset: Offset(0, f < 1 ? 28 : 6))]),
              child: ClipRect(
                child: Stack(fit: StackFit.expand, children: [
                  Align(
                    alignment: const Alignment(0, -0.22),
                    child: Lockup(
                      scale: s,
                      style: LockStyle.dark,
                      a: cseg(t, 3.0, 3.5),
                      letters: [for (var i = 0; i < 4; i++) cseg(t, 3.2 + i * 0.15, 3.7 + i * 0.15, Curves.easeInOut)],
                      dot: cseg(t, 3.6, 4.0),
                      rule: cseg(t, 3.8, 4.3, Curves.easeInOut),
                      showTagline: false,
                    ),
                  ),
                  // glue sheen
                  if (sheen > 0 && sheen < 1)
                    Positioned.fill(
                      child: Transform.translate(
                        offset: Offset((sheen * 2.4 - 1.2) * pw, 0),
                        child: DecoratedBox(decoration: BoxDecoration(gradient: LinearGradient(begin: const Alignment(-1, -0.3), end: const Alignment(1, 0.3), colors: [const Color(0x00FFFFFF), Colors.white.withValues(alpha: 0.65), const Color(0x00FFFFFF)], stops: const [0.35, 0.5, 0.65]))),
                      ),
                    ),
                  // tape label
                  Align(
                    alignment: const Alignment(0, 0.78),
                    child: Transform.translate(
                      offset: Offset((1 - tape) * pw, 0),
                      child: Transform.rotate(
                        angle: -0.03,
                        child: Container(
                          padding: EdgeInsets.symmetric(horizontal: pw * 0.06, vertical: pw * 0.018),
                          color: const Color(0xFFE30613),
                          child: Text('DESIGN TASKS', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: pw * 0.052, letterSpacing: pw * 0.012, height: 1, decoration: TextDecoration.none)),
                        ),
                      ),
                    ),
                  ),
                ]),
              ),
            ),
          ),
        );

  return Stack(fit: StackFit.expand, children: [
    const ColoredBox(color: Color(0xFFC6BEB0)),
    CustomPaint(painter: _WallPainter()),
    for (final p in specs) posterAt(p),
    finalPoster,
    _slideCard(cseg(t, 4.8, 6.0, Curves.easeOutCubic), pad, panel),
  ]);
}

class _WallPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final p = Paint()
      ..color = const Color(0xFF5B5246).withValues(alpha: 0.18)
      ..strokeWidth = 1.2;
    const bh = 26.0, bw = 64.0;
    for (var row = 0; row * bh < size.height; row++) {
      final y = row * bh;
      canvas.drawLine(Offset(0, y), Offset(size.width, y), p);
      final off = row.isEven ? 0.0 : bw / 2;
      for (var x = off; x < size.width; x += bw) {
        canvas.drawLine(Offset(x, y), Offset(x, y + bh), p);
      }
    }
    final r = math.Random(4);
    final sp = Paint();
    for (var i = 0; i < 300; i++) {
      sp.color = const Color(0xFF3F382F).withValues(alpha: 0.05 + 0.1 * r.nextDouble());
      canvas.drawCircle(Offset(r.nextDouble() * size.width, r.nextDouble() * size.height), 0.5 + r.nextDouble() * 1.4, sp);
    }
  }

  @override
  bool shouldRepaint(_WallPainter old) => false;
}

class _PosterArtPainter extends CustomPainter {
  _PosterArtPainter(this.kind);
  final int kind;

  @override
  void paint(Canvas canvas, Size size) {
    final light = Paint()..color = Colors.white.withValues(alpha: 0.28);
    final dark = Paint()..color = Colors.black.withValues(alpha: 0.16);
    switch (kind) {
      case 0:
        canvas.drawCircle(Offset(size.width * 0.5, size.height * 0.40), size.width * 0.28, light);
        canvas.drawRect(Rect.fromLTWH(size.width * 0.12, size.height * 0.78, size.width * 0.76, size.height * 0.06), light);
        canvas.drawRect(Rect.fromLTWH(size.width * 0.12, size.height * 0.88, size.width * 0.5, size.height * 0.04), light);
      case 1:
        for (var i = 0; i < 6; i++) {
          canvas.drawRect(Rect.fromLTWH(0, size.height * (0.08 + i * 0.16), size.width, size.height * 0.07), i.isEven ? dark : light);
        }
      default:
        final path = Path()
          ..moveTo(0, size.height)
          ..lineTo(size.width * 0.5, size.height * 0.22)
          ..lineTo(size.width, size.height)
          ..close();
        canvas.drawPath(path, light);
        canvas.drawCircle(Offset(size.width * 0.78, size.height * 0.2), size.width * 0.1, dark);
    }
  }

  @override
  bool shouldRepaint(_PosterArtPainter old) => false;
}

// ---------------------------------------------------------------------------------------------------------------------
// 7. BILLBOARD UNVEIL: a bright day, bunting and a tarp-covered hoarding. The tarp drops, confetti falls, the logo is revealed.

Widget unveilConcept(BuildContext context, double t, Size sz, EdgeInsets pad, Widget panel) {
  final w = sz.width, h = sz.height;
  final bw = w * 0.90, bh = bw * 0.50;
  final boardTop = pad.top + h * 0.15;
  final groundY = h * 0.60;
  final tarpF = cseg(t, 1.3, 2.5, Curves.easeInCubic);
  final s = (bw - 22) * 0.94 / 1630;

  final board = Stack(clipBehavior: Clip.none, children: [
    Positioned(left: w / 2 - w * 0.03, top: boardTop + bh, width: w * 0.06, height: groundY - (boardTop + bh) + 40, child: const DecoratedBox(decoration: BoxDecoration(gradient: LinearGradient(colors: [Color(0xFF50565F), Color(0xFF7C838E), Color(0xFF50565F)])))),
    Positioned(
      left: (w - bw) / 2,
      top: boardTop,
      width: bw,
      height: bh,
      child: Container(
        decoration: BoxDecoration(color: const Color(0xFF3B4048), borderRadius: BorderRadius.circular(8), boxShadow: const [BoxShadow(color: Color(0x33000000), blurRadius: 24, offset: Offset(0, 14))]),
        padding: const EdgeInsets.all(8),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: Stack(fit: StackFit.expand, children: [
            Container(
              color: Colors.white,
              alignment: Alignment.center,
              child: Lockup(
                scale: s,
                style: LockStyle.dark,
                a: cseg(t, 2.3, 3.0),
                letters: [for (var i = 0; i < 4; i++) cseg(t, 2.6 + i * 0.18, 3.2 + i * 0.18, Curves.easeInOut)],
                dot: cseg(t, 3.3, 3.9),
                dotDy: -(1 - Curves.bounceOut.transform(cseg(t, 3.3, 3.9))) * 260,
                rule: cseg(t, 3.7, 4.2, Curves.easeInOut),
                tagline: cseg(t, 4.0, 4.6),
                taglineColor: const Color(0xFF22222A),
              ),
            ),
            // the tarp, dropping away
            if (tarpF < 1)
              Transform.translate(
                offset: Offset(math.sin(t * 6) * 3 * (1 - tarpF), tarpF * bh * 1.15),
                child: CustomPaint(painter: _TarpPainter(tarpF)),
              ),
          ]),
        ),
      ),
    ),
  ]);

  return Stack(fit: StackFit.expand, children: [
    const DecoratedBox(decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Color(0xFF6FC3F5), Color(0xFFCFEBFB), Color(0xFFF3FAFF)], stops: [0, 0.6, 1]))),
    CustomPaint(painter: _CloudPainter(t)),
    board,
    // ground + road
    Positioned(left: 0, right: 0, top: groundY, bottom: 0, child: CustomPaint(painter: _RoadPainter(t))),
    // bunting
    Positioned.fill(child: CustomPaint(painter: _BuntingPainter(t, pad.top + 6))),
    // confetti
    if (t > 2.3 && t < 5.5) Positioned.fill(child: CustomPaint(painter: _ConfettiPainter(t - 2.3, Rect.fromLTWH((w - bw) / 2, boardTop, bw, bh)))),
    _slideCard(cseg(t, 4.8, 6.0, Curves.easeOutCubic), pad, panel),
  ]);
}

class _TarpPainter extends CustomPainter {
  _TarpPainter(this.k);
  final double k;

  @override
  void paint(Canvas canvas, Size size) {
    canvas.drawRect(Offset.zero & size, Paint()..shader = ui.Gradient.linear(Offset.zero, Offset(size.width, size.height), const [Color(0xFF5E6877), Color(0xFF8993A3), Color(0xFF5E6877)], const [0, 0.5, 1]));
    final fold = Paint()
      ..color = Colors.black.withValues(alpha: 0.16)
      ..strokeWidth = 2;
    for (var i = 1; i < 9; i++) {
      final x = size.width * i / 9;
      canvas.drawLine(Offset(x, 0), Offset(x + 14 * math.sin(i * 1.7), size.height), fold);
    }
    // ropes
    final rope = Paint()
      ..color = const Color(0xFFE9DFC8)
      ..strokeWidth = 3;
    canvas.drawLine(Offset.zero, Offset(size.width / 2, size.height * 0.3), rope);
    canvas.drawLine(Offset(size.width, 0), Offset(size.width / 2, size.height * 0.3), rope);
  }

  @override
  bool shouldRepaint(_TarpPainter old) => true;
}

class _CloudPainter extends CustomPainter {
  _CloudPainter(this.t);
  final double t;

  @override
  void paint(Canvas canvas, Size size) {
    final p = Paint()..color = Colors.white.withValues(alpha: 0.85);
    void cloud(double x, double y, double r) {
      for (final (dx, dy, k) in const [(0.0, 0.0, 1.0), (0.9, 0.2, 0.8), (-0.9, 0.25, 0.75), (0.4, -0.35, 0.7)]) {
        canvas.drawCircle(Offset(x + dx * r, y + dy * r), r * k, p);
      }
    }

    final w = size.width;
    cloud(((t * 8) % (w + 200)) - 100, size.height * 0.1, 26);
    cloud(((t * 5 + 160) % (w + 240)) - 120, size.height * 0.28, 34);
    cloud(((t * 11 + 60) % (w + 160)) - 80, size.height * 0.45, 20);
  }

  @override
  bool shouldRepaint(_CloudPainter old) => true;
}

class _RoadPainter extends CustomPainter {
  _RoadPainter(this.t);
  final double t;

  @override
  void paint(Canvas canvas, Size size) {
    canvas.drawRect(Offset.zero & size, Paint()..color = const Color(0xFF3A3F47));
    canvas.drawRect(Rect.fromLTWH(0, 0, size.width, 22), Paint()..color = const Color(0xFF5FA85E));
    final dash = Paint()..color = const Color(0xFFF2F2F2);
    final off = (t * 90) % 80;
    for (var x = -80 + off; x < size.width; x += 80) {
      canvas.drawRect(Rect.fromLTWH(x, 70, 44, 6), dash);
    }
  }

  @override
  bool shouldRepaint(_RoadPainter old) => true;
}

class _BuntingPainter extends CustomPainter {
  _BuntingPainter(this.t, this.top);
  final double t;
  final double top;

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width;
    final line = Paint()
      ..color = const Color(0xFF33363D)
      ..strokeWidth = 1.5
      ..style = PaintingStyle.stroke;
    final path = Path()..moveTo(0, top);
    path.quadraticBezierTo(w / 2, top + 34, w, top);
    canvas.drawPath(path, line);
    const colors = [Color(0xFFE30613), Colors.white, Color(0xFFFFC93C)];
    for (var i = 0; i < 12; i++) {
      final x = w * (i + 0.5) / 12;
      final u = (i + 0.5) / 12;
      final y = top + 34 * 2 * u * (1 - u);
      final sway = math.sin(t * 2.2 + i) * 3;
      final flag = Path()
        ..moveTo(x - 9, y)
        ..lineTo(x + 9, y)
        ..lineTo(x + sway, y + 22)
        ..close();
      canvas.drawPath(flag, Paint()..color = colors[i % 3]);
    }
  }

  @override
  bool shouldRepaint(_BuntingPainter old) => true;
}

class _ConfettiPainter extends CustomPainter {
  _ConfettiPainter(this.u, this.board);
  final double u;
  final Rect board;

  @override
  void paint(Canvas canvas, Size size) {
    const colors = [Color(0xFFE30613), Color(0xFFFFC93C), Color(0xFF1FA8A0), Color(0xFF2D5BFF), Color(0xFFF48FB1), Colors.white];
    final r = math.Random(12);
    final p = Paint();
    for (var i = 0; i < 70; i++) {
      final x0 = board.left + r.nextDouble() * board.width;
      final v = 0.5 + r.nextDouble();
      final delay = r.nextDouble() * 0.6;
      final k = u - delay;
      if (k <= 0) continue;
      final y = board.top - 20 + k * 170 * v;
      final x = x0 + math.sin(k * 5 + i) * 18;
      final alpha = (1 - (k / 2.4)).clamp(0.0, 1.0);
      if (alpha <= 0) continue;
      p.color = colors[i % colors.length].withValues(alpha: alpha);
      canvas.save();
      canvas.translate(x, y);
      canvas.rotate(k * 6 * (i.isEven ? 1 : -1) + i);
      canvas.drawRect(const Rect.fromLTWH(-4, -2.5, 8, 5), p);
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(_ConfettiPainter old) => true;
}

// ---------------------------------------------------------------------------------------------------------------------
// 8. AD FORMATS BURST: the many formats a campaign runs on fly in and orbit, rush into the centre, and burst into the logo.

Widget formatsConcept(BuildContext context, double t, Size sz, EdgeInsets pad, Widget panel) {
  final w = sz.width, h = sz.height;
  final areaH = h - kPanelH - pad.bottom - pad.top;
  final c = Offset(w / 2, pad.top + areaH * 0.5 + 10);
  final s = w * 0.84 / 1630;
  final logoK = cseg(t, 3.0, 3.6, Curves.easeOutBack);

  return Stack(fit: StackFit.expand, children: [
    const ColoredBox(color: Color(0xFFFFF5E1)),
    CustomPaint(painter: _DotsBgPainter()),
    Positioned.fill(child: CustomPaint(painter: _BurstPainter(t, c, h))),
    Positioned.fill(child: CustomPaint(painter: _FormatsPainter(t, c, w))),
    if (t > 2.9)
      _placeLockup(
        c,
        s,
        Transform.scale(
          scale: 0.4 + 0.6 * logoK,
          child: Lockup(
            scale: s,
            style: LockStyle.dark,
            a: cseg(t, 3.0, 3.4),
            letters: [for (var i = 0; i < 4; i++) cseg(t, 3.1 + i * 0.12, 3.5 + i * 0.12)],
            letterMode: LetterMode.pop,
            dot: cseg(t, 3.5, 4.0),
            rule: cseg(t, 3.7, 4.2, Curves.easeInOut),
            tagline: cseg(t, 4.0, 4.6),
            taglineColor: const Color(0xFF16161A),
          ),
        ),
      ),
    _slideCard(cseg(t, 4.8, 6.0, Curves.easeOutCubic), pad, panel, border: const Border(top: BorderSide(color: Color(0xFFE30613), width: 4))),
  ]);
}

class _DotsBgPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final p = Paint()..color = const Color(0xFFE30613).withValues(alpha: 0.07);
    for (var y = 12.0; y < size.height; y += 22) {
      for (var x = (y ~/ 22).isEven ? 12.0 : 23.0; x < size.width; x += 22) {
        canvas.drawCircle(Offset(x, y), 2, p);
      }
    }
  }

  @override
  bool shouldRepaint(_DotsBgPainter old) => false;
}

class _BurstPainter extends CustomPainter {
  _BurstPainter(this.t, this.c, this.h);
  final double t;
  final Offset c;
  final double h;

  @override
  void paint(Canvas canvas, Size size) {
    final k = cseg(t, 2.95, 3.9, Curves.easeOutCubic);
    if (k <= 0) return;
    final fade = 1 - cseg(t, 3.4, 4.6);
    if (fade <= 0) return;
    final r = h * 1.1 * k;
    const n = 20;
    for (var i = 0; i < n; i++) {
      final a0 = (i / n) * math.pi * 2 + t * 0.3, a1 = ((i + 1) / n) * math.pi * 2 + t * 0.3;
      final path = Path()
        ..moveTo(c.dx, c.dy)
        ..lineTo(c.dx + math.cos(a0) * r, c.dy + math.sin(a0) * r)
        ..lineTo(c.dx + math.cos(a1) * r, c.dy + math.sin(a1) * r)
        ..close();
      canvas.drawPath(path, Paint()..color = (i.isEven ? const Color(0xFFE30613) : const Color(0xFFFFC93C)).withValues(alpha: 0.55 * fade));
    }
    final flash = cseg(t, 2.9, 3.15) * (1 - cseg(t, 3.15, 3.7));
    canvas.drawCircle(c, h * 0.5 * flash, Paint()..color = Colors.white.withValues(alpha: 0.9 * flash));
  }

  @override
  bool shouldRepaint(_BurstPainter old) => true;
}

class _FormatsPainter extends CustomPainter {
  _FormatsPainter(this.t, this.c, this.w);
  final double t;
  final Offset c;
  final double w;

  @override
  void paint(Canvas canvas, Size size) {
    const n = 8;
    final ring = w * 0.36;
    for (var i = 0; i < n; i++) {
      final enter = cseg(t, 0.2 + i * 0.12, 1.5 + i * 0.12, Curves.easeOutBack);
      final conv = cseg(t, 2.45, 3.0, Curves.easeInCubic);
      if (enter <= 0 || conv >= 1) continue;
      final a = (i / n) * math.pi * 2 + (t - 0.2) * 0.7;
      final far = Offset(c.dx + math.cos(a) * size.height, c.dy + math.sin(a) * size.height);
      final onRing = Offset(c.dx + math.cos(a) * ring, c.dy + math.sin(a) * ring * 0.9);
      var pos = Offset.lerp(far, onRing, enter)!;
      pos = Offset.lerp(pos, c, conv)!;
      final sc = (1 - 0.85 * conv) * (0.6 + 0.4 * enter);
      canvas.save();
      canvas.translate(pos.dx, pos.dy);
      canvas.rotate(math.sin(i * 2.0) * 0.25 + conv * 5);
      canvas.scale(sc);
      _tile(canvas, i, w * 0.21);
      canvas.restore();
    }
  }

  void _tile(Canvas canvas, int type, double u) {
    final shadow = Paint()
      ..color = Colors.black.withValues(alpha: 0.22)
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 8);
    RRect box(double wd, double ht, double r) => RRect.fromRectAndRadius(Rect.fromCenter(center: Offset.zero, width: wd, height: ht), Radius.circular(r));
    final red = Paint()..color = const Color(0xFFE30613);
    final ink = Paint()..color = const Color(0xFF16161A);
    final white = Paint()..color = Colors.white;
    final yellow = Paint()..color = const Color(0xFFFFC93C);
    switch (type % 8) {
      case 0: // phone
        canvas.drawRRect(box(u * 0.62, u * 1.1, 10).shift(const Offset(0, 6)), shadow);
        canvas.drawRRect(box(u * 0.62, u * 1.1, 10), ink);
        canvas.drawRRect(box(u * 0.52, u * 0.9, 6), red);
        canvas.drawCircle(Offset(0, -u * 0.1), u * 0.12, white);
      case 1: // social square with a heart
        canvas.drawRRect(box(u * 0.9, u * 0.9, 12).shift(const Offset(0, 6)), shadow);
        canvas.drawRRect(box(u * 0.9, u * 0.9, 12), yellow);
        final heart = Path()
          ..moveTo(0, u * 0.2)
          ..cubicTo(-u * 0.38, -u * 0.05, -u * 0.18, -u * 0.32, 0, -u * 0.12)
          ..cubicTo(u * 0.18, -u * 0.32, u * 0.38, -u * 0.05, 0, u * 0.2);
        canvas.drawPath(heart, red);
      case 2: // bus shelter panel
        canvas.drawRRect(box(u * 1.1, u * 0.7, 8).shift(const Offset(0, 6)), shadow);
        canvas.drawRRect(box(u * 1.1, u * 0.7, 8), ink);
        canvas.drawRRect(box(u * 0.98, u * 0.58, 4), white);
        canvas.drawRect(Rect.fromCenter(center: Offset(0, 0), width: u * 0.7, height: u * 0.1), red);
        canvas.drawRect(Rect.fromCenter(center: Offset(0, u * 0.16), width: u * 0.45, height: u * 0.07), ink);
      case 3: // flag banner
        canvas.drawRect(Rect.fromCenter(center: Offset(-u * 0.32, 0), width: u * 0.07, height: u * 1.1), ink);
        final flag = Path()
          ..moveTo(-u * 0.3, -u * 0.5)
          ..lineTo(u * 0.4, -u * 0.38)
          ..lineTo(u * 0.4, u * 0.12)
          ..lineTo(-u * 0.3, u * 0.0)
          ..close();
        canvas.drawPath(flag, red);
        canvas.drawCircle(Offset(u * 0.05, -u * 0.18), u * 0.12, white);
      case 4: // flyer
        canvas.drawRRect(box(u * 0.8, u * 1.0, 4).shift(const Offset(0, 6)), shadow);
        canvas.drawRRect(box(u * 0.8, u * 1.0, 4), white);
        canvas.drawRect(Rect.fromCenter(center: Offset(0, -u * 0.28), width: u * 0.6, height: u * 0.28), red);
        for (var i = 0; i < 4; i++) {
          canvas.drawRect(Rect.fromLTWH(-u * 0.3, u * (0.0 + i * 0.13), u * (0.6 - (i == 3 ? 0.25 : 0)), u * 0.06), ink);
        }
      case 5: // LED screen
        canvas.drawRRect(box(u * 1.1, u * 0.7, 6).shift(const Offset(0, 6)), shadow);
        canvas.drawRRect(box(u * 1.1, u * 0.7, 6), ink);
        for (var i = 0; i < 6; i++) {
          canvas.drawCircle(Offset(-u * 0.4 + i * u * 0.16, 0), u * 0.05, i.isEven ? yellow : red);
        }
        canvas.drawRect(Rect.fromCenter(center: Offset(0, u * 0.5), width: u * 0.12, height: u * 0.2), ink);
      case 6: // bus
        canvas.drawRRect(box(u * 1.1, u * 0.5, 10).shift(const Offset(0, 6)), shadow);
        canvas.drawRRect(box(u * 1.1, u * 0.5, 10), red);
        for (var i = 0; i < 4; i++) {
          canvas.drawRect(Rect.fromCenter(center: Offset(-u * 0.38 + i * u * 0.25, -u * 0.06), width: u * 0.17, height: u * 0.15), white);
        }
        canvas.drawCircle(Offset(-u * 0.3, u * 0.25), u * 0.09, ink);
        canvas.drawCircle(Offset(u * 0.3, u * 0.25), u * 0.09, ink);
      default: // pole banner
        canvas.drawRRect(box(u * 0.5, u * 1.1, 4).shift(const Offset(0, 6)), shadow);
        canvas.drawRRect(box(u * 0.5, u * 1.1, 4), yellow);
        canvas.drawCircle(Offset(0, -u * 0.25), u * 0.14, ink);
        canvas.drawRect(Rect.fromCenter(center: Offset(0, u * 0.18), width: u * 0.34, height: u * 0.08), ink);
        canvas.drawRect(Rect.fromCenter(center: Offset(0, u * 0.34), width: u * 0.24, height: u * 0.06), red);
    }
  }

  @override
  bool shouldRepaint(_FormatsPainter old) => true;
}

// ---------------------------------------------------------------------------------------------------------------------
// 9. LED MARQUEE: an amber LED display scrolls the company's services; it resolves into the logo inside a ring of chasing bulbs.

Widget ledConcept(BuildContext context, double t, Size sz, EdgeInsets pad, Widget panel) {
  final w = sz.width, h = sz.height;
  final panelTop = h - (kPanelH + pad.bottom);
  final disp = Rect.fromLTRB(w * 0.05, pad.top + 18, w * 0.95, panelTop - 18);
  final s = disp.width * 0.84 / 1630;
  final rowsFade = 1 - cseg(t, 2.2, 3.0);
  final logoK = cseg(t, 2.5, 3.6, Curves.easeOut);
  final grid = 0.92 * (1 - cseg(t, 3.8, 4.8));
  const amber = Color(0xFFFFB300);
  const rows = [
    'OUTDOOR  •  ROADSHOW  •  FIXTURES  •  SIGNAGE  •  ',
    'POP OFFSETS  •  DIGITAL MARKETING  •  EVENTS  •  ',
    'ACTIVATIONS  •  HOARDINGS  •  BRANDING  •  ',
  ];
  final rowH = disp.height / 5;

  Widget ticker(int i) {
    final dir = i.isEven ? -1.0 : 1.0;
    final one = rows[i % rows.length];
    final text = one * 4;
    final style = TextStyle(color: amber, fontSize: rowH * 0.52, fontWeight: FontWeight.w800, letterSpacing: 2, height: 1, decoration: TextDecoration.none);
    // one repeat of the phrase is the loop length, so the scroll never jumps
    final period = (TextPainter(text: TextSpan(text: one, style: style), textDirection: TextDirection.ltr, maxLines: 1)..layout()).width;
    final shift = (t * (60 + i * 18)) % period;
    return Positioned(
      left: 0,
      right: 0,
      top: disp.height / 2 + (i - 1) * rowH * 1.25 - rowH * 0.4,
      height: rowH * 0.8,
      child: ClipRect(
        child: OverflowBox(
          maxWidth: double.infinity,
          alignment: Alignment.centerLeft,
          child: Transform.translate(
            offset: Offset(dir < 0 ? -shift : shift - period, 0),
            child: Text(text, maxLines: 1, softWrap: false, style: style),
          ),
        ),
      ),
    );
  }

  final display = ClipRRect(
    borderRadius: BorderRadius.circular(14),
    child: Container(
      color: const Color(0xFF0D0D0D),
      child: Stack(fit: StackFit.expand, children: [
        Opacity(opacity: rowsFade * cseg(t, 0.2, 0.7), child: Stack(children: [for (var i = 0; i < 3; i++) ticker(i)])),
        Center(
          child: Opacity(
            opacity: logoK,
            child: ImageFiltered(
              imageFilter: ui.ImageFilter.blur(sigmaX: 5 * (1 - logoK) + 0.01, sigmaY: 5 * (1 - logoK) + 0.01, tileMode: TileMode.decal),
              child: Lockup(
                scale: s,
                a: 1,
                letters: [for (var i = 0; i < 4; i++) cseg(t, 2.6 + i * 0.1, 3.2 + i * 0.1)],
                letterMode: LetterMode.fade,
                dot: cseg(t, 3.2, 3.7),
                rule: cseg(t, 3.4, 4.0, Curves.easeInOut),
                tagline: cseg(t, 3.8, 4.4),
                taglineColor: amber,
              ),
            ),
          ),
        ),
        CustomPaint(painter: _LedGridPainter(grid)),
      ]),
    ),
  );

  return Stack(fit: StackFit.expand, children: [
    const ColoredBox(color: Color(0xFF050505)),
    Positioned.fromRect(rect: disp, child: display),
    Positioned.fill(child: CustomPaint(painter: _BulbsPainter(disp.inflate(7), t, cseg(t, 0.6, 1.4)))),
    _slideCard(cseg(t, 4.8, 6.0, Curves.easeOutCubic), pad, panel, color: const Color(0xFF111111), border: const Border(top: BorderSide(color: amber, width: 2)), radius: 22, shadow: [BoxShadow(color: amber.withValues(alpha: 0.35), blurRadius: 24)]),
  ]);
}

class _LedGridPainter extends CustomPainter {
  _LedGridPainter(this.a);
  final double a;

  @override
  void paint(Canvas canvas, Size size) {
    if (a <= 0.01) return;
    final p = Paint()
      ..color = Colors.black.withValues(alpha: a)
      ..strokeWidth = 2.2;
    const pitch = 5.0;
    for (var x = 0.0; x < size.width; x += pitch) {
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), p);
    }
    for (var y = 0.0; y < size.height; y += pitch) {
      canvas.drawLine(Offset(0, y), Offset(size.width, y), p);
    }
  }

  @override
  bool shouldRepaint(_LedGridPainter old) => old.a != a;
}

class _BulbsPainter extends CustomPainter {
  _BulbsPainter(this.r, this.t, this.k);
  final Rect r;
  final double t;
  final double k;

  @override
  void paint(Canvas canvas, Size size) {
    if (k <= 0) return;
    final rr = RRect.fromRectAndRadius(r, const Radius.circular(18));
    final path = Path()..addRRect(rr);
    final metric = path.computeMetrics().first;
    const spacing = 17.0;
    final n = (metric.length / spacing).floor();
    final p = Paint();
    for (var i = 0; i < n; i++) {
      final pos = metric.getTangentForOffset(i * spacing)!.position;
      final phase = ((i / n) * 6 - t * 1.6) % 1.0;
      final lit = phase < 0.5 ? 1.0 : 0.22;
      p.color = const Color(0xFFFFD27A).withValues(alpha: (0.25 + 0.75 * lit) * k);
      canvas.drawCircle(pos, 3.2, p);
      if (lit > 0.5) {
        canvas.drawCircle(pos, 8, Paint()..color = const Color(0xFFFFB300).withValues(alpha: 0.22 * k));
      }
    }
  }

  @override
  bool shouldRepaint(_BulbsPainter old) => true;
}

// ---------------------------------------------------------------------------------------------------------------------
// 10. PRINT PRESS: colour separations slide into register on a press sheet, the black key lands, a roller prints the tagline.

Widget pressConcept(BuildContext context, double t, Size sz, EdgeInsets pad, Widget panel) {
  final w = sz.width, h = sz.height;
  final areaH = h - kPanelH - pad.bottom - pad.top;
  final sw = w * 0.88, sh = sw * 0.72;
  final c = Offset(w / 2, pad.top + areaH * 0.5 + 4);
  final s = sw * 0.90 / 1630;
  final reg = cseg(t, 0.9, 2.6, Curves.easeOutCubic);
  final inkIn = cseg(t, 0.9, 1.5);
  final sepFade = 1 - cseg(t, 2.9, 3.7);
  final black = cseg(t, 2.5, 3.2);
  final roller = cseg(t, 3.4, 4.2, Curves.easeInOut);
  final marks = cseg(t, 0.3, 1.0) * (1 - cseg(t, 4.6, 5.4));
  final unit = 1630 * s / 1630;

  Widget sep(Color col, Offset from) {
    final o = Offset.lerp(from, Offset.zero, reg)! * unit;
    return Transform.translate(
      offset: o,
      child: Opacity(
        opacity: 0.80 * inkIn * sepFade,
        child: ColorFiltered(colorFilter: ColorFilter.mode(col, BlendMode.srcATop), child: Lockup(scale: s, rule: 1, showTagline: false)),
      ),
    );
  }

  final sheetRect = Rect.fromCenter(center: c, width: sw, height: sh);
  final rollY = sheetRect.top - 24 + (sh + 48) * roller;

  return Stack(fit: StackFit.expand, children: [
    const DecoratedBox(decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Color(0xFF34373D), Color(0xFF212327)]))),
    Positioned.fromRect(
      rect: sheetRect,
      child: Container(
        decoration: BoxDecoration(color: const Color(0xFFFBFAF6), boxShadow: const [BoxShadow(color: Color(0x66000000), blurRadius: 30, offset: Offset(0, 16))]),
      ),
    ),
    // colour bar and registration marks
    Positioned.fill(child: CustomPaint(painter: _PressMarksPainter(sheetRect, marks))),
    // the separations, then the black key
    _placeLockup(c + Offset(0, -sh * 0.04), s, Stack(clipBehavior: Clip.none, children: [
      sep(const Color(0xFFFFE600), const Offset(0, 80)),
      sep(const Color(0xFF00AEEF), const Offset(-90, -50)),
      sep(const Color(0xFFEC008C), const Offset(90, -40)),
      Lockup(scale: s, style: LockStyle.dark, a: black, letters: [black, black, black, black], letterMode: LetterMode.fade, dot: black, rule: black, showTagline: false),
    ])),
    // tagline printed by the roller
    Positioned(
      left: c.dx - 1630 * s / 2 + (kTaglineLeft + Lockup.ox) * s,
      top: c.dy - sh * 0.04 - Lockup.h * s / 2 + kTaglineCentreY * s - kTaglineCap * s / 0.711 * 0.5,
      width: (kTaglineRight - kTaglineLeft) * s,
      child: Opacity(
        opacity: cseg(t, 3.9, 4.3),
        child: Text('DESIGN TASKS', maxLines: 1, softWrap: false, textAlign: TextAlign.justify, style: TextStyle(color: const Color(0xFF16161A), fontSize: kTaglineCap * s / 0.711, fontWeight: FontWeight.w600, letterSpacing: kTaglineCap * s / 0.711 * 0.38, height: 1, decoration: TextDecoration.none)),
      ),
    ),
    // the roller
    if (roller > 0 && roller < 1)
      Positioned(
        left: sheetRect.left - 14,
        width: sw + 28,
        top: rollY - 11,
        height: 22,
        child: DecoratedBox(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(11),
            gradient: const LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Color(0xFF8A8F99), Color(0xFFE9ECF2), Color(0xFFB5BAC4), Color(0xFF6C717B)], stops: [0, 0.3, 0.6, 1]),
            boxShadow: const [BoxShadow(color: Color(0x55000000), blurRadius: 10, offset: Offset(0, 6))],
          ),
        ),
      ),
    _slideCard(cseg(t, 4.8, 6.0, Curves.easeOutCubic), pad, panel, color: const Color(0xFFFBFAF6)),
  ]);
}

class _PressMarksPainter extends CustomPainter {
  _PressMarksPainter(this.sheet, this.k);
  final Rect sheet;
  final double k;

  @override
  void paint(Canvas canvas, Size size) {
    if (k <= 0.01) return;
    final line = Paint()
      ..color = Colors.white.withValues(alpha: 0.8 * k)
      ..strokeWidth = 1.2;
    // crop marks outside each corner
    for (final (corner, dx, dy) in [(sheet.topLeft, -1.0, -1.0), (sheet.topRight, 1.0, -1.0), (sheet.bottomLeft, -1.0, 1.0), (sheet.bottomRight, 1.0, 1.0)]) {
      canvas.drawLine(corner + Offset(dx * 6, 0), corner + Offset(dx * 22, 0), line);
      canvas.drawLine(corner + Offset(0, dy * 6), corner + Offset(0, dy * 22), line);
    }
    // registration targets
    for (final c in [Offset(sheet.left - 18, sheet.center.dy), Offset(sheet.right + 18, sheet.center.dy)]) {
      canvas.drawCircle(c, 6, line..style = PaintingStyle.stroke);
      canvas.drawLine(c - const Offset(10, 0), c + const Offset(10, 0), line);
      canvas.drawLine(c - const Offset(0, 10), c + const Offset(0, 10), line);
    }
    // CMYK colour bar along the top of the sheet
    const cols = [Color(0xFF00AEEF), Color(0xFFEC008C), Color(0xFFFFF200), Color(0xFF111111), Color(0xFFE30613), Color(0xFF00A651)];
    final sw = sheet.width / (cols.length * 3);
    for (var i = 0; i < cols.length * 3; i++) {
      canvas.drawRect(Rect.fromLTWH(sheet.left + i * sw, sheet.top + 6, sw, 9), Paint()..color = cols[i % cols.length].withValues(alpha: k));
    }
  }

  @override
  bool shouldRepaint(_PressMarksPainter old) => true;
}
