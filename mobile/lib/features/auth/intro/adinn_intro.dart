import 'dart:math' as math;
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart';

import '../../../core/theme.dart';
import 'logo_particles.g.dart';
import 'writing_plan.dart';

part 'openings.dart';

/// The cinematic opening of the sign-in screen: particles assemble the Adinn "A", the logo sharpens, shifts aside, the pencil
/// lifts out and writes "dinn", returns to its place, "DESIGN TASKS" appears, the lockup rises and the sign-in module slides
/// up from the bottom.
///
/// [panel] is the existing sign-in form, untouched; this widget only decides where it sits and when it can be used.
/// It plays once per app run ([played]), never on rebuilds, and not at all under reduced motion.
class AdinnIntro extends StatefulWidget {
  const AdinnIntro({super.key, required this.panel});

  final Widget panel;

  /// Widget tests switch the animation off, so a screen under test starts in its final, usable state.
  static bool enabled = true;

  /// Set once the sequence has started, so signing out (or any rebuild) shows the finished layout instead of replaying it.
  static bool played = false;

  /// Which of the ten openings plays (see [IntroOpening]).
  static IntroOpening opening = IntroOpening.particles;

  /// Total running time of the sequence, in seconds.
  static const seconds = 6.5;

  @override
  State<AdinnIntro> createState() => _AdinnIntroState();
}

/// The logo artwork is laid out in an 800 x 690 box; every coordinate below is in those "logo units".
const _boxW = 800.0, _boxH = 690.0;

/// Where the pencil's tip sits inside the logo box.
const _tip = Offset(540, 523);

const _cBlack = Colors.black;

class _AdinnIntroState extends State<AdinnIntro> with TickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: Duration(milliseconds: (AdinnIntro.seconds * 1000).round()));
  late final AnimationController _ambient = AnimationController(vsync: this, duration: const Duration(seconds: 40));
  bool _ambientOn = false;
  late final _Particles _field = _Particles.shared;
  int _active = 0;
  int _slowFrames = 0;
  bool _budgetSet = false;
  bool _started = false;
  bool _monitoring = false;
  final Map<String, ui.Image> _glyphs = {};
  final List<(ImageStream, ImageStreamListener)> _streams = [];

  @override
  void initState() {
    super.initState();
    _c.addStatusListener((s) {
      if (s == AnimationStatus.completed) _stopMonitoring();
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    _loadGlyphs();

    final reduced = MediaQuery.disableAnimationsOf(context);
    // Behind everything, from the first frame onwards, a slow field of drifting particles keeps the stage alive.
    _ambientOn = AdinnIntro.enabled && !reduced;
    if (_ambientOn) _ambient.repeat();
    if (!AdinnIntro.enabled || AdinnIntro.played || reduced) {
      _c.value = 1;
      return;
    }
    AdinnIntro.played = true;
    _setBudget();
    SchedulerBinding.instance.addTimingsCallback(_onTimings);
    _monitoring = true;
    // Start after the first frame so the black stage is already on screen and the opening does not skip its first beats.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _c.forward();
    });
  }

  /// Particle count adapts to the screen's pixel load; it is then trimmed further if frames run slow.
  void _setBudget() {
    if (_budgetSet) return;
    _budgetSet = true;
    final view = View.of(context);
    final pixels = view.physicalSize.width * view.physicalSize.height;
    var tier = pixels >= 3.0e6 ? 1.0 : (pixels >= 1.5e6 ? 0.75 : 0.5);
    if (kIsWeb) tier = math.min(tier, 0.6);
    _active = math.max(180, (_field.count * tier).round());
  }

  void _onTimings(List<FrameTiming> timings) {
    if (!mounted || _c.isCompleted) return;
    for (final t in timings) {
      if (t.totalSpan.inMilliseconds > 22) {
        _slowFrames++;
      } else if (_slowFrames > 0) {
        _slowFrames--;
      }
      if (_slowFrames >= 4 && _active > 220) {
        _active = (_active * 0.6).round();
        _slowFrames = 0;
      }
    }
  }

  void _stopMonitoring() {
    if (!_monitoring) return;
    _monitoring = false;
    SchedulerBinding.instance.removeTimingsCallback(_onTimings);
  }

  @override
  void dispose() {
    for (final (stream, listener) in _streams) {
      stream.removeListener(listener);
    }
    _stopMonitoring();
    _c.dispose();
    _ambient.dispose();
    super.dispose();
  }

  void _loadGlyphs() {
    for (final name in const ['g_d', 'g_i', 'g_n1', 'g_n2']) {
      final stream = AssetImage('assets/intro/$name.png').resolve(createLocalImageConfiguration(context));
      final listener = ImageStreamListener((info, _) {
        if (mounted) setState(() => _glyphs[name] = info.image);
      });
      stream.addListener(listener);
      _streams.add((stream, listener));
    }
  }

  void _skip() {
    if (!_c.isCompleted) _c.value = 1;
  }

  @override
  Widget build(BuildContext context) {
    final mq = MediaQuery.of(context);
    return ColoredBox(
      color: _cBlack,
      child: LayoutBuilder(builder: (context, box) {
        final geo = _Geo(Size(box.maxWidth, box.maxHeight), mq.padding);
        final keyboard = mq.viewInsets.bottom;
        return AnimatedBuilder(
          animation: _c,
          builder: (context, _) {
            final t = _c.value * AdinnIntro.seconds;
            final done = _c.isCompleted;
            return Stack(
              fit: StackFit.expand,
              children: [
                if (_ambientOn)
                  Positioned.fill(
                    child: IgnorePointer(
                      child: RepaintBoundary(
                        child: AnimatedBuilder(
                          animation: _ambient,
                          builder: (context, _) => CustomPaint(painter: _AmbientPainter(u: _ambient.value, fade: _seg(t, 0, 0.9), count: math.min(70, math.max(24, _active ~/ 12)))),
                        ),
                      ),
                    ),
                  ),
                // Brand: fades away while the keyboard is up (the module then needs the room).
                Positioned.fill(
                  child: IgnorePointer(
                    ignoring: done,
                    child: GestureDetector(
                      behavior: HitTestBehavior.translucent,
                      onTap: _skip,
                      child: AnimatedOpacity(
                        duration: const Duration(milliseconds: 180),
                        opacity: keyboard > 0 ? 0 : 1,
                        child: Semantics(
                          label: 'Adinn Design Tasks',
                          child: ExcludeSemantics(child: _Brand(t: t, geo: geo, field: _field, active: _active, glyphs: _glyphs)),
                        ),
                      ),
                    ),
                  ),
                ),
                _SignInModule(t: t, geo: geo, keyboard: keyboard, usable: done || t >= 6.2, child: widget.panel),
              ],
            );
          },
        );
      }),
    );
  }
}

// ---------------------------------------------------------------------------------------------------------------------
// Geometry & timeline

typedef _Pose = ({Offset origin, double scale});

class _Geo {
  _Geo(this.size, EdgeInsets pad) {
    final w = size.width, h = size.height;
    cityHeight = _cityHeightFor(w);
    // The skyline's top edge is mostly sky, so the brand may overlap about a quarter of it.
    panelEstimate = math.min(h * 0.70, 410 + pad.bottom + cityHeight * 0.75);
    final areaTop = pad.top;
    final areaH = math.max(160.0, h - panelEstimate - areaTop);

    // Opening pose: the whole logo (A + dot + pencil, about 770 x 690 units) centred.
    sC = math.min(w * 0.60 / 770, h * 0.40 / 690);
    oC = Offset(w / 2 - 395 * sC, h / 2 - 342 * sC);

    // Lockup pose: the printed logo (about 1600 x 650 units) centred; later lifted into the space above the module.
    // The width limit leaves room for the pencil, which overhangs the end of the word while it writes.
    sF = math.min(math.min(w * 0.86 / 1602, (w / 2 - 6) / 986), areaH * 0.72 / 650);
    oF = Offset(w / 2 - 805 * sF, h / 2 - 350 * sF);
    // The skyline's upper part is transparent sky, so the lockup sits a little lower than the middle of the room above it.
    lift = (areaTop + areaH / 2) - h / 2 + cityHeight * 0.33;
  }

  final Size size;
  late final double panelEstimate, cityHeight;
  late final double sC, sF, lift;
  late final Offset oC, oF;

  _Pose pose(double t) {
    final e3 = _seg(t, 2.2, 2.8, _smooth);
    final e6 = _seg(t, 5.0, 5.9, _smooth);
    final o = Offset.lerp(oC, oF, e3)! + Offset(0, lift * e6);
    return (origin: o, scale: ui.lerpDouble(sC, sF, e3)!);
  }
}

const _smooth = Cubic(0.65, 0, 0.35, 1);

/// The skyline picture is 2000 x 667; it spans the screen width (capped on wide screens).
double _cityHeightFor(double w) => math.min(w, 720.0) * 667 / 2000;

class _Cityscape extends StatelessWidget {
  const _Cityscape({required this.width});

  final double width;

  @override
  Widget build(BuildContext context) {
    final w = math.min(width, 720.0);
    final dpr = MediaQuery.devicePixelRatioOf(context);
    return ExcludeSemantics(
      child: Image.asset(
        'assets/intro/city.webp',
        width: w,
        height: _cityHeightFor(width),
        fit: BoxFit.fill,
        cacheWidth: (w * dpr).round(),
        filterQuality: FilterQuality.medium,
        gaplessPlayback: true,
      ),
    );
  }
}

double _seg(double t, double a, double b, [Curve curve = Curves.linear]) => curve.transform(((t - a) / (b - a)).clamp(0.0, 1.0));

Offset _arc(Offset a, Offset b, double f, double liftUnits) => Offset.lerp(a, b, f)! + Offset(0, -liftUnits * math.sin(math.pi * f));

// ---------------------------------------------------------------------------------------------------------------------
// Brand lockup

class _Brand extends StatelessWidget {
  const _Brand({required this.t, required this.geo, required this.field, required this.active, required this.glyphs});

  final double t;
  final _Geo geo;
  final _Particles field;
  final int active;
  final Map<String, ui.Image> glyphs;

  @override
  Widget build(BuildContext context) {
    final plan = WritingPlan.shared;
    final pose = geo.pose(t);
    final s = pose.scale;
    final o = pose.origin;

    // Logo: it comes into view in the chosen opening, soft at first, then sharpens 1.5 -> 2.2.
    final fx = _OpeningFx(AdinnIntro.opening, t, o, s);
    final logoOpacity = fx.opacity ?? _seg(t, 0.75, 1.5, Curves.easeIn);
    final sigma = fx.sigma ?? 15.0 * (1 - _seg(t, 1.5, 2.2, Curves.easeOutCubic));
    // Glow pulse across the reveal, then it settles away as the lockup forms.
    final pulse = math.sin(math.pi * _seg(t, 1.5, 2.6)) * 0.55 + 0.10 * _seg(t, 0.9, 1.5);
    final glow = fx.glow ?? pulse * (1 - _seg(t, 2.4, 3.4));

    // Pencil: rests in the logo, steps out, follows the writing, then glides home along an arc.
    final pen = _penAt(t, plan);
    final pencilShift = pen - _tip;
    final tilt = -0.10 * _seg(t, 2.8, 3.05, Curves.easeOut) - 0.7 * _seg(t, 4.2, 4.95, Curves.easeIn);

    // The logo's red dot becomes the dot of the i: it arcs across to sit above where the i will be written.
    const dotHome = Offset(532, 102);
    final dotF = Curves.easeInOutCubic.transform(_seg(t, 2.85, 3.35));
    final dotShift = _arc(Offset.zero, WritingPlan.dotTarget - dotHome, dotF, 110);

    final pencilOpacity = 1 - _seg(t, 4.45, 4.95);
    final rule = _seg(t, 4.5, 5.0, Curves.easeInOutCubic);
    final subtitle = _seg(t, 4.7, 5.2, Curves.easeOutCubic);
    final sub = _subtitle(context, o, s, subtitle);

    final boxSize = Size(_boxW * s, _boxH * s);
    Widget layer(String name, {double dx = 0, double dy = 0, double angle = 0, double zoom = 1, Offset pivot = _tip}) {
      Widget img = Image.asset('assets/intro/$name.png', width: boxSize.width, height: boxSize.height, filterQuality: FilterQuality.high, gaplessPlayback: true);
      final al = Alignment((pivot.dx / _boxW) * 2 - 1, (pivot.dy / _boxH) * 2 - 1);
      if (zoom != 1) img = Transform.scale(scale: zoom, alignment: al, child: img);
      if (angle != 0) img = Transform.rotate(angle: angle, alignment: al, child: img);
      return Positioned(left: o.dx + dx * s, top: o.dy + dy * s, width: boxSize.width, height: boxSize.height, child: img);
    }

    final blurOn = sigma > 0.05;
    ui.ImageFilter blur() => ui.ImageFilter.blur(sigmaX: sigma, sigmaY: sigma, tileMode: TileMode.decal);

    return Stack(
      fit: StackFit.expand,
      clipBehavior: Clip.none,
      children: [
        if (fx.particles && t < 2.2)
          Positioned.fill(child: RepaintBoundary(child: CustomPaint(painter: _ParticlePainter(t: t, geo: geo, field: field, count: active)))),
        if (glow > 0.005)
          Positioned(
            left: o.dx + 330 * s - 560 * s,
            top: o.dy + 345 * s - 560 * s,
            width: 1120 * s,
            height: 1120 * s,
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: RadialGradient(colors: [adinnRed.withValues(alpha: 0.34 * glow), adinnRed.withValues(alpha: 0.10 * glow), Colors.transparent], stops: const [0, 0.42, 1]),
              ),
            ),
          ),
        // The finished handwriting sits under the pencil, over the glow.
        Positioned.fill(child: CustomPaint(painter: _GlyphPainter(plan: plan, glyphs: glyphs, reveal: plan.reveal(t), origin: o, scale: s, penDown: plan.penDown(t), pen: pen))),
        // A and dot (blurred together while the logo is soft).
        if (logoOpacity > 0)
          Positioned.fill(
            child: fx.wrap(() => Opacity(
              opacity: logoOpacity,
              child: ImageFiltered(
                enabled: blurOn,
                imageFilter: blurOn ? blur() : ui.ImageFilter.blur(),
                child: Stack(clipBehavior: Clip.none, children: [
                  Positioned(
                    left: o.dx + kARect.left * s,
                    top: o.dy + kARect.top * s,
                    width: kARect.width * s,
                    height: kARect.height * s,
                    child: Image.asset('assets/intro/A.png', filterQuality: FilterQuality.high, gaplessPlayback: true),
                  ),
                  layer('dot', dx: dotShift.dx, dy: dotShift.dy, zoom: 1 - 0.1 * dotF, pivot: dotHome),
                ]),
              ),
            )),
          ),
        // The pencil is its own layer so it can leave the logo and come back.
        if (logoOpacity > 0 && pencilOpacity > 0)
          Positioned.fill(
            child: fx.wrap(() => Opacity(
              opacity: logoOpacity * pencilOpacity,
              child: ImageFiltered(
                enabled: blurOn,
                imageFilter: blurOn ? blur() : ui.ImageFilter.blur(),
                child: Stack(clipBehavior: Clip.none, children: [layer('pencil', dx: pencilShift.dx, dy: pencilShift.dy, angle: tilt)]),
              ),
            )),
          ),
        ...fx.overlays(),
        if (rule > 0)
          Positioned(
            left: o.dx + kLineRect.left * s,
            top: o.dy + kLineRect.top * s,
            width: kLineRect.width * s,
            height: kLineRect.height * s,
            child: ClipRect(child: Align(alignment: Alignment.centerLeft, widthFactor: rule, child: Image.asset('assets/intro/g_line.png', width: kLineRect.width * s, height: kLineRect.height * s, fit: BoxFit.fill, filterQuality: FilterQuality.high, gaplessPlayback: true))),
          ),
        ?sub,
      ],
    );
  }

  Offset _penAt(double t, WritingPlan plan) {
    if (t < 2.8) return _tip;
    if (t < 3.05) return _arc(_tip, plan.start, Curves.easeInOutCubic.transform((t - 2.8) / 0.25), 70);
    if (t < 4.2) return plan.penAt((t - 3.05) / 1.15).pos;
    if (t < 4.95) {
      // The pencil's work is done: it lifts off the page and flies away up and to the right.
      final f = Curves.easeInCubic.transform((t - 4.2) / 0.75);
      final a = plan.end, c = a + const Offset(260, -520), b = a + const Offset(1500, -1500);
      final u = 1 - f;
      return a * (u * u) + c * (2 * u * f) + b * (f * f);
    }
    return plan.end;
  }

  /// "DESIGN TASKS" stands where the printed logo has "Advertising Services Ltd.": same left and right edges, same height.
  Widget? _subtitle(BuildContext context, Offset o, double s, double k) {
    if (k <= 0) return null;
    const text = 'DESIGN TASKS';
    final fontSize = kTaglineCap * s / 0.711; // Roboto cap height is about 0.711 em
    final width = (kTaglineRight - kTaglineLeft) * s;
    final base = DefaultTextStyle.of(context).style.copyWith(color: Colors.white, fontSize: fontSize, fontWeight: FontWeight.w500, height: 1, letterSpacing: 0, decoration: TextDecoration.none);
    final tp = TextPainter(text: TextSpan(text: text, style: base), textDirection: TextDirection.ltr)..layout();
    final spacing = math.max(0.0, (width - tp.width) / (text.length - 1));
    return Positioned(
      left: o.dx + kTaglineLeft * s,
      top: o.dy + kTaglineCentreY * s - fontSize * 0.445,
      child: Opacity(
        opacity: k,
        child: Transform.translate(
          offset: Offset(0, (1 - k) * 8),
          child: Text(text, maxLines: 1, softWrap: false, style: base.copyWith(letterSpacing: spacing * (0.5 + 0.5 * k))),
        ),
      ),
    );
  }
}


// ---------------------------------------------------------------------------------------------------------------------
// Handwriting

class _GlyphPainter extends CustomPainter {
  _GlyphPainter({required this.plan, required this.glyphs, required this.reveal, required this.origin, required this.scale, required this.penDown, required this.pen});

  final WritingPlan plan;
  final Map<String, ui.Image> glyphs;
  final List<double> reveal;
  final Offset origin;
  final double scale;
  final bool penDown;
  final Offset pen;

  @override
  void paint(Canvas canvas, Size size) {
    if (reveal.every((r) => r <= 0)) return;
    canvas.save();
    canvas.translate(origin.dx, origin.dy);
    canvas.scale(scale);

    final brush = Paint()
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round
      ..color = Colors.white;
    final keep = Paint()
      ..blendMode = BlendMode.srcIn
      ..filterQuality = FilterQuality.high;
    final plain = Paint()..filterQuality = FilterQuality.high;

    for (var i = 0; i < plan.strokes.length; i++) {
      final f = reveal[i];
      if (f <= 0) continue;
      final st = plan.strokes[i];
      final img = glyphs[st.asset];
      if (img == null) continue;
      final src = Rect.fromLTWH(0, 0, img.width.toDouble(), img.height.toDouble());
      if (f >= 1) {
        canvas.drawImageRect(img, src, st.dest, plain);
        continue;
      }
      // Sweep a brush along the pencil's path; only the part of the real letter under the brush shows.
      canvas.save();
      canvas.clipRect(st.dest); // the brush must not show outside the letter's own box
      canvas.saveLayer(st.dest, Paint());
      canvas.drawPath(st.metric.extractPath(0, st.metric.length * f), brush..strokeWidth = st.brush);
      canvas.drawImageRect(img, src, st.dest, keep);
      canvas.restore();
      canvas.restore();
    }

    // A faint bloom at the point of the pencil while it is touching the page.
    if (penDown) {
      canvas.drawCircle(pen, 34, Paint()
        ..color = Colors.white.withValues(alpha: 0.18)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 12));
    }
    canvas.restore();
  }

  @override
  bool shouldRepaint(_GlyphPainter old) => true;
}

// ---------------------------------------------------------------------------------------------------------------------
// Particles

class _Particles {
  _Particles._() {
    final rnd = math.Random(42);
    count = kLogoParticles.length;
    tx = Float32List(count);
    ty = Float32List(count);
    red = Uint8List(count);
    sx = Float32List(count);
    sy = Float32List(count);
    begin = Float32List(count);
    swirl = Float32List(count);
    trail = Float32List(count);
    for (var i = 0; i < count; i++) {
      final v = kLogoParticles[i];
      tx[i] = (v ~/ 2048).toDouble();
      ty[i] = ((v % 2048) >> 1).toDouble();
      red[i] = v & 1;
      // Scattered in a wide cloud around the centre, as fractions of the screen.
      final a = rnd.nextDouble() * math.pi * 2;
      final r = math.sqrt(rnd.nextDouble());
      sx[i] = math.cos(a) * r * 0.62;
      sy[i] = math.sin(a) * r * 0.48;
      begin[i] = rnd.nextDouble() * 0.55;
      swirl[i] = (rnd.nextDouble() - 0.5) * 90;
      trail[i] = 0.012 + math.pow(rnd.nextDouble(), 3) * 0.07;
    }
  }

  static final shared = _Particles._();

  late final int count;
  late final Float32List tx, ty, sx, sy, begin, swirl, trail;
  late final Uint8List red;
}

class _ParticlePainter extends CustomPainter {
  _ParticlePainter({required this.t, required this.geo, required this.field, required this.count});

  final double t;
  final _Geo geo;
  final _Particles field;
  final int count;

  @override
  void paint(Canvas canvas, Size size) {
    final f = field;
    final n = math.min(count, f.count);
    final intro = (t / 0.35).clamp(0.0, 1.0);
    final fade = 1 - _seg(t, 1.5, 2.1, Curves.easeIn);
    final alpha = intro * fade;
    if (alpha <= 0.003) return;

    final coreW = Float32List(n * 2), coreR = Float32List(n * 2), trW = Float32List(n * 4), trR = Float32List(n * 4);
    var cw = 0, cr = 0, tw = 0, tr = 0;
    final center = Offset(size.width / 2, size.height / 2);

    Offset at(int i, double e, Offset start, Offset target, Offset perp) {
      final base = Offset.lerp(start, target, e)!;
      return base + perp * (f.swirl[i] * math.sin(math.pi * e));
    }

    for (var i = 0; i < n; i++) {
      final start = Offset(center.dx + f.sx[i] * size.width, center.dy + f.sy[i] * size.height);
      final target = Offset(geo.oC.dx + f.tx[i] * geo.sC, geo.oC.dy + f.ty[i] * geo.sC);
      final d = target - start;
      final len = d.distance;
      final perp = len == 0 ? Offset.zero : Offset(-d.dy / len, d.dx / len);
      final b = f.begin[i];
      final u = ((t - b) / (1.42 - b)).clamp(0.0, 1.0);
      final e = Curves.easeInOutCubic.transform(u);
      final p = at(i, e, start, target, perp);

      if (f.red[i] == 1) {
        coreR[cr++] = p.dx;
        coreR[cr++] = p.dy;
      } else {
        coreW[cw++] = p.dx;
        coreW[cw++] = p.dy;
      }
      if (u > 0 && u < 1) {
        final q = at(i, Curves.easeInOutCubic.transform(math.max(0.0, u - f.trail[i])), start, target, perp);
        if (f.red[i] == 1) {
          trR[tr++] = q.dx;
          trR[tr++] = q.dy;
          trR[tr++] = p.dx;
          trR[tr++] = p.dy;
        } else {
          trW[tw++] = q.dx;
          trW[tw++] = q.dy;
          trW[tw++] = p.dx;
          trW[tw++] = p.dy;
        }
      }
    }

    const white = Color(0xFFFFFFFF), red = Color(0xFFFF2233);
    void pass(Float32List pts, int len, ui.PointMode mode, Color c, double width, double a) {
      if (len == 0) return;
      final paint = Paint()
        ..color = c.withValues(alpha: (a * alpha).clamp(0.0, 1.0))
        ..strokeWidth = width
        ..strokeCap = StrokeCap.round
        ..blendMode = BlendMode.plus;
      canvas.drawRawPoints(mode, Float32List.sublistView(pts, 0, len), paint);
    }

    final px = math.max(1.0, geo.size.width / 390);
    // Soft glow, light streaks, then the bright cores.
    pass(coreW, cw, ui.PointMode.points, white, 8 * px, 0.06);
    pass(coreR, cr, ui.PointMode.points, red, 9 * px, 0.09);
    pass(trW, tw, ui.PointMode.lines, white, 1.1 * px, 0.28);
    pass(trR, tr, ui.PointMode.lines, red, 1.3 * px, 0.36);
    pass(coreW, cw, ui.PointMode.points, white, 2.6 * px, 0.95);
    pass(coreR, cr, ui.PointMode.points, red, 3.0 * px, 1.0);
  }

  @override
  bool shouldRepaint(_ParticlePainter old) => true;
}

// ---------------------------------------------------------------------------------------------------------------------
// Ambient field

/// Soft white and red specks drifting slowly upward behind the brand. Everything is a whole number of laps or wobbles over the
/// 40 s loop, so the motion never visibly restarts.
class _AmbientPainter extends CustomPainter {
  _AmbientPainter({required this.u, required this.fade, required this.count});

  final double u;
  final double fade;
  final int count;

  static final _seed = List.generate(70, (i) {
    final r = math.Random(1000 + i * 31);
    return (
      x: r.nextDouble(),
      y: r.nextDouble(),
      depth: 0.35 + r.nextDouble() * 0.65,
      laps: 1 + r.nextInt(2),
      wob: 2 + r.nextInt(4),
      tw: 6 + r.nextInt(9),
      phase: r.nextDouble(),
      red: r.nextDouble() < 0.28,
    );
  });

  @override
  void paint(Canvas canvas, Size size) {
    if (fade <= 0) return;
    final px = math.max(1.0, size.width / 390);
    final paint = Paint()..blendMode = BlendMode.plus;
    for (var i = 0; i < math.min(count, _seed.length); i++) {
      final p = _seed[i];
      final v = p.y - u * p.laps * (0.6 + 0.4 * p.depth);
      final y = v - v.floor();
      final x = p.x + 0.035 * math.sin(2 * math.pi * (u * p.wob + p.phase));
      final twinkle = 0.5 + 0.5 * math.sin(2 * math.pi * (u * p.tw + p.phase));
      final edge = math.sin(math.pi * y); // fade in and out at the top and bottom so nothing pops
      final a = (0.18 + 0.50 * twinkle) * p.depth * edge * fade;
      if (a <= 0.01) continue;
      final c = p.red ? const Color(0xFFFF2233) : Colors.white;
      final at = Offset(x * size.width, y * size.height);
      final r = (1.0 + 2.2 * p.depth) * px;
      canvas.drawCircle(at, r * 3.2, paint..color = c.withValues(alpha: a * 0.16));
      canvas.drawCircle(at, r, paint..color = c.withValues(alpha: a));
    }
  }

  @override
  bool shouldRepaint(_AmbientPainter old) => true;
}

// ---------------------------------------------------------------------------------------------------------------------
// Sign-in module

class _SignInModule extends StatelessWidget {
  const _SignInModule({required this.t, required this.geo, required this.keyboard, required this.usable, required this.child});

  final double t;
  final _Geo geo;
  final double keyboard;
  final bool usable;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final mq = MediaQuery.of(context);
    final k = _seg(t, 5.3, 6.5, Curves.easeOutCubic);
    final maxH = math.max(200.0, geo.size.height - keyboard - mq.padding.top - 12 - (keyboard == 0 ? geo.cityHeight : 0));

    return Align(
      alignment: Alignment.bottomCenter,
      child: Padding(
        padding: EdgeInsets.only(bottom: keyboard),
        child: FractionalTranslation(
          translation: Offset(0, 1 - k),
          // Before it has arrived the module takes no taps, focus or screen-reader attention.
          child: IgnorePointer(
            ignoring: !usable,
            child: ExcludeFocus(
              excluding: !usable,
              child: ExcludeSemantics(
                excluding: k < 0.5,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // The city stands on the top edge of the module and rises with it. It gives way to the keyboard.
                    if (keyboard == 0) _Cityscape(width: geo.size.width),
                    ConstrainedBox(
                  constraints: BoxConstraints(maxWidth: 480, maxHeight: maxH),
                  child: Material(
                    color: Colors.white,
                    elevation: 0,
                    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(28))),
                    clipBehavior: Clip.antiAlias,
                    child: SingleChildScrollView(
                      padding: EdgeInsets.fromLTRB(24, 8, 24, 20 + (keyboard > 0 ? 0 : mq.padding.bottom)),
                      child: child,
                    ),
                  ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
