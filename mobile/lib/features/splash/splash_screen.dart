import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../auth/intro/writing_plan.dart';

/// The animated Adinn wordmark on white: the A grows in, the red dot pops and flies to sit over the i, "dinn" is written in
/// letter by letter, the red rule draws, and "DESIGN TASKS" rises under it. Built from the official logo artwork in
/// `assets/intro` (nothing redrawn), animation only.
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key, required this.onDone});

  final VoidCallback onDone;

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> with SingleTickerProviderStateMixin, WidgetsBindingObserver {
  /// The reveal is laid out on a 3 s timeline and played 1.5x slower (4.5 s), so every beat can be followed.
  static const _seconds = 3.0;
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 4500));
  bool _done = false;
  bool _pausedByLifecycle = false;
  bool _started = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _c.addStatusListener((s) {
      if (s == AnimationStatus.completed) _finish();
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    // Start at once: nothing may hold the reveal back. The artwork decodes alongside (gapless images appear as they land).
    for (final n in const ['A_dark', 's_dot', 's_pencil', 's_g_d', 's_g_i', 's_g_n1', 's_g_n2', 'g_line']) {
      precacheImage(AssetImage('assets/intro/$n.png'), context).catchError((_) {});
    }
    _c.forward();
  }

  void _finish() {
    if (_done) return;
    _done = true;
    widget.onDone();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      if (_pausedByLifecycle) {
        _pausedByLifecycle = false;
        _c.forward();
      }
    } else if (_c.isAnimating) {
      _pausedByLifecycle = true;
      _c.stop();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: _finish,
        child: Stack(
          fit: StackFit.expand,
          children: [
            // Moving backdrop in the brand palette: soft red, coral and pink light drifting over white, 
            // One painter, repainted by the same clock as the reveal (no rebuilds).
            Positioned.fill(
              child: RepaintBoundary(
                child: CustomPaint(painter: _BackdropPainter(clock: _c, seconds: _seconds)),
              ),
            ),
            Center(
              child: FractionallySizedBox(
                widthFactor: 0.8,
                child: RepaintBoundary(
                  child: AnimatedBuilder(
                    animation: _c,
                    builder: (context, _) => AdinnWordmark(t: _c.value * _seconds, tagline: 'DESIGNER TASK TOOL'),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

double _seg(double t, double a, double b, [Curve curve = Curves.linear]) => curve.transform(((t - a) / (b - a)).clamp(0.0, 1.0));

const _pop = Cubic(0.2, 1.0, 0.3, 1.25);

/// The line the printed Adinn logo carries under its red rule. It is part of the logo; the product name ("DESIGN TASKS")
/// stays below the logo as before.
const kLogoTagline = 'Advertising Services Ltd.';

/// The lockup in "logo units": the same coordinates the sign-in intro uses (A at [kARect], letters at their stroke rects).
///
/// [t] is seconds into the 3.6 s reveal: the A grows in beside its pencil, the red dot pops, the pencil steps out, writes
/// "dinn" (the letters ink in behind it), the dot lands on the i, the pencil flies off, the rule draws and "DESIGN TASKS"
/// rises. 3.6 or more is the finished lockup, which is what the sign-in and PIN screens show.
class AdinnWordmark extends StatelessWidget {
  const AdinnWordmark({super.key, required this.t, this.onColour = false, this.tagline = 'DESIGN TASKS'});

  final double t;

  /// True on a coloured background: the whole lockup is drawn in white.
  final bool onColour;

  /// The line under the lockup.
  final String tagline;

  static const _white = ColorFilter.mode(Colors.white, BlendMode.srcIn);
  static final _regAt = wordPoint(1432, 582) - const Offset(27, 27);

  /// Tight crops of the dot and the pencil, where they sit in the logo box.
  static const _dotRect = Rect.fromLTWH(468, 37, 130, 130);
  static const _penRect = Rect.fromLTWH(536, 207, 246, 318);

  Widget _tinted(Widget image) => onColour ? ColorFiltered(colorFilter: _white, child: image) : image;

  static const _pad = 12.0;
  static const _w = 1720.0, _h = 700.0;

  /// How far right the lockup sits while the A stands alone in the middle (logo units).
  static const _centreShift = 480.0;
  static const _dotHome = Offset(532, 102);

  /// Where the pencil's tip sits in the logo box (as in the logo itself).
  static const _tip = Offset(540, 523);

  // Timeline (seconds).
  static const _penOut = 0.55, _write0 = 0.75, _write1 = 1.85, _penAway = 2.2;

  Offset _penAt(WritingPlan plan) {
    if (t < _penOut) return _tip;
    if (t < _write0) return _arc(_tip, plan.start, Curves.easeInOutCubic.transform((t - _penOut) / (_write0 - _penOut)), 70);
    if (t < _write1) return plan.penAt((t - _write0) / (_write1 - _write0)).pos;
    // Work done: it lifts off the page and flies up and to the right.
    final f = Curves.easeInCubic.transform(((t - _write1) / (_penAway - _write1)).clamp(0.0, 1.0));
    final a = plan.end, c = a + const Offset(260, -520), b = a + const Offset(1500, -1500);
    final u = 1 - f;
    return a * (u * u) + c * (2 * u * f) + b * (f * f);
  }

  static Offset _arc(Offset a, Offset b, double f, double lift) => Offset.lerp(a, b, f)! + Offset(0, -lift * math.sin(math.pi * f));

  @override
  Widget build(BuildContext context) {
    final plan = WritingPlan.shared;
    final a = 0.35 + 0.65 * _seg(t, 0.0, 0.45, Curves.easeOutCubic);
    final dotPop = _pop.transform(_seg(t, 0.3, 0.65));
    final fly = Curves.easeInOutCubic.transform(_seg(t, 0.7, 1.3));
    final target = WritingPlan.dotTarget - _dotHome;
    final dotShift = Offset.lerp(Offset.zero, target, fly)! + Offset(0, -90 * (1 - (2 * fly - 1) * (2 * fly - 1)));
    final rule = _seg(t, 1.95, 2.4, Curves.easeInOutCubic);
    final reg = _seg(t, 2.0, 2.3);
    // The A first stands alone in the middle, then the lockup slides left to make room for the word.
    final slideX = _centreShift * (1 - Curves.easeInOutCubic.transform(_seg(t, 0.5, 0.85)));
    final pad = _pad + slideX;
    final tag = _seg(t, 2.1, 2.65, Curves.easeOutCubic);

    // Letters ink in as the pencil writes them (the sign-in intro's own writing plan, mapped onto this timeline).
    final writing = ((t - _write0) / (_write1 - _write0)).clamp(0.0, 1.0);
    final reveal = t >= _write1 ? List<double>.filled(plan.strokes.length, 1) : plan.reveal(3.05 + writing * 1.15);

    final pen = _penAt(plan);
    final penShift = pen - _tip;
    final penOpacity = _seg(t, 0.2, 0.5) * (1 - _seg(t, _write1, _penAway));
    final tilt = -0.10 * _seg(t, _penOut, _write0, Curves.easeOut) - 0.7 * _seg(t, _write1, _penAway, Curves.easeIn);

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        AspectRatio(
          aspectRatio: _w / _h,
          child: FittedBox(
            fit: BoxFit.contain,
            child: SizedBox(
              width: _w,
              height: _h,
              child: Stack(
                clipBehavior: Clip.none,
                children: [
                  // The A grows in from its foot.
                  Positioned(
                    left: pad + kARect.left,
                    top: kARect.top,
                    width: kARect.width,
                    height: kARect.height,
                    child: Opacity(
                      opacity: a,
                      child: Transform.scale(
                        scale: 0.55 + 0.45 * a,
                        alignment: const Alignment(0.55, 0.95),
                        child: _tinted(Image.asset('assets/intro/A_dark.png', fit: BoxFit.fill, filterQuality: FilterQuality.medium, gaplessPlayback: true)),
                      ),
                    ),
                  ),
                  // The red dot pops, then arcs across to become the dot of the i.
                  if (dotPop > 0.001)
                    Positioned(
                      left: pad + dotShift.dx + _dotRect.left,
                      top: dotShift.dy + _dotRect.top,
                      width: _dotRect.width,
                      height: _dotRect.height,
                      child: Transform.scale(
                        scale: dotPop,
                        child: _tinted(Image.asset('assets/intro/s_dot.png', fit: BoxFit.fill, filterQuality: FilterQuality.medium, gaplessPlayback: true)),
                      ),
                    ),
                  // d, i, n, n: inked left to right behind the pencil.
                  for (var i = 0; i < plan.strokes.length; i++) _letter(plan.strokes[i], reveal[i], pad),
                  // The red rule under the word.
                  if (rule > 0)
                    Positioned(
                      left: pad + kLineRect.left,
                      top: kLineRect.top,
                      width: kLineRect.width,
                      height: kLineRect.height,
                      child: ClipRect(
                        child: Align(
                          alignment: Alignment.centerLeft,
                          widthFactor: rule,
                          child: _tinted(Image.asset('assets/intro/g_line.png', width: kLineRect.width, height: kLineRect.height, fit: BoxFit.fill, filterQuality: FilterQuality.medium, gaplessPlayback: true)),
                        ),
                      ),
                    ),
                  // "Advertising Services Ltd." under the rule, where the printed logo has it.
                  if (rule > 0.6) _printedLine(pad, _seg(t, 2.1, 2.6, Curves.easeOutCubic)),
                  // The registered mark at the end of the word.
                  if (reg > 0)
                    Positioned(
                      left: pad + _regAt.dx,
                      top: _regAt.dy,
                      child: Opacity(
                        opacity: reg,
                        child: Container(
                          width: 54,
                          height: 54,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: onColour ? Colors.white : Colors.black, width: 4)),
                          child: Text('R', style: TextStyle(fontSize: 34, height: 1, fontWeight: FontWeight.w500, color: onColour ? Colors.white : Colors.black, decoration: TextDecoration.none)),
                        ),
                      ),
                    ),
                  // The pencil: rests by the A, steps out, writes, flies away.
                  if (penOpacity > 0.01 && !onColour)
                    Positioned(
                      left: pad + penShift.dx + _penRect.left,
                      top: penShift.dy + _penRect.top,
                      width: _penRect.width,
                      height: _penRect.height,
                      child: Opacity(
                        opacity: penOpacity,
                        child: Transform.rotate(
                          angle: tilt,
                          alignment: Alignment(((_tip.dx - _penRect.left) / _penRect.width) * 2 - 1, ((_tip.dy - _penRect.top) / _penRect.height) * 2 - 1),
                          child: Image.asset('assets/intro/s_pencil.png', fit: BoxFit.fill, filterQuality: FilterQuality.medium, gaplessPlayback: true),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: 10),
        Opacity(
          opacity: tag,
          child: Transform.translate(
            offset: Offset(0, (1 - tag) * 14),
            // One line always: it scales down on a narrow lockup instead of wrapping.
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(
                tagline,
                maxLines: 1,
                softWrap: false,
                style: TextStyle(fontSize: 15, letterSpacing: (tagline == tagline.toUpperCase() ? 7 : 2.4) * (0.6 + 0.4 * tag), fontWeight: FontWeight.w600, color: onColour ? Colors.white : const Color(0xFF1B1B1F), decoration: TextDecoration.none),
              ),
            ),
          ),
        ),
      ],
    );
  }

  /// The printed logo's own line, in the slot under the red rule: same left and right edges as the print, spaced to fill it.
  Widget _printedLine(double pad, double k) {
    final fontSize = kTaglineCap / 0.711; // Roboto cap height is about 0.711 em
    final width = kTaglineRight - kTaglineLeft;
    final colour = onColour ? Colors.white : const Color(0xFF1B1B1F);
    final base = TextStyle(color: colour, fontSize: fontSize, fontWeight: FontWeight.w500, height: 1, letterSpacing: 0, decoration: TextDecoration.none);
    final tp = TextPainter(text: TextSpan(text: kLogoTagline, style: base), textDirection: TextDirection.ltr)..layout();
    final spacing = math.max(0.0, (width - tp.width) / (kLogoTagline.length - 1));

    return Positioned(
      left: pad + kTaglineLeft,
      top: kTaglineCentreY - fontSize * 0.445,
      width: width,
      child: Opacity(
        opacity: k,
        // The print's own font is narrower than Roboto: if a line comes out wider than its slot it shrinks to fit.
        child: FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Text(kLogoTagline, maxLines: 1, softWrap: false, style: base.copyWith(letterSpacing: spacing * (0.5 + 0.5 * k))),
        ),
      ),
    );
  }

  Widget _letter(PenStroke st, double k, double pad) {
    if (k <= 0) return const SizedBox.shrink();
    return Positioned(
      left: pad + st.dest.left,
      top: st.dest.top,
      width: st.dest.width,
      height: st.dest.height,
      child: ClipRect(
        child: Align(
          alignment: Alignment.centerLeft,
          widthFactor: k,
          // Dark letters on white, the original white ones on a coloured background: no colour filter in either case.
          child: Image.asset(onColour ? 'assets/intro/${st.asset}.png' : 'assets/intro/s_${st.asset}.png', width: st.dest.width, height: st.dest.height, fit: BoxFit.fill, filterQuality: FilterQuality.medium, gaplessPlayback: true),
        ),
      ),
    );
  }
}

/// Slow colour behind the logo reveal: three big soft lights (red top-right, coral bottom-left, pink behind the logo) and a
/// faint amber one, drifting and swelling a little. No particles.
class _BackdropPainter extends CustomPainter {
  _BackdropPainter({required this.clock, required this.seconds}) : super(repaint: clock);

  final Animation<double> clock;
  final double seconds;

  static const _red = Color(0xFFE30613), _coral = Color(0xFFFF5A5F), _pink = Color(0xFFFFB3B8), _amber = Color(0xFFFFB36B);

  @override
  void paint(Canvas canvas, Size size) {
    final t = clock.value * seconds;
    final w = size.width, h = size.height;
    final fade = 0.55 + 0.45 * (t / 0.6).clamp(0.0, 1.0);
    // The lights swell a little as the A lands, then settle.
    final swell = 1 + 0.10 * math.sin((t / seconds) * math.pi);

    void light(Offset c, double r, Color color, double alpha) {
      final rect = Rect.fromCircle(center: c, radius: r);
      canvas.drawCircle(c, r, Paint()..shader = RadialGradient(colors: [color.withValues(alpha: alpha * fade), color.withValues(alpha: 0)]).createShader(rect));
    }

    light(Offset(w * (0.95 - 0.06 * math.sin(t * 0.9)), h * (0.08 + 0.03 * math.cos(t * 0.8))), w * 0.85 * swell, _red, 0.42);
    light(Offset(w * (0.05 + 0.07 * math.cos(t * 0.7)), h * (0.95 - 0.04 * math.sin(t * 1.1))), w * 0.80 * swell, _coral, 0.34);
    light(Offset(w * (0.55 + 0.12 * math.sin(t * 0.8 + 1)), h * (0.52 + 0.05 * math.cos(t * 0.9))), w * 0.55 * swell, _pink, 0.30);
    light(Offset(w * (0.15 + 0.05 * math.sin(t)), h * (0.22 + 0.04 * math.cos(t * 1.2))), w * 0.40, _amber, 0.16);
  }

  @override
  bool shouldRepaint(_BackdropPainter old) => false;
}
