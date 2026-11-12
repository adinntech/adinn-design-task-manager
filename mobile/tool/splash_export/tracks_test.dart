// Export tool, not a real test: samples every animated value of the splash reveal (the same formulas as AdinnWordmark)
// into tracks.json, from which the animated SVG is assembled.
//   $env:SPLASH_OUT = 'C:\some\folder'; flutter test tool/splash_export/tracks_test.dart
import 'dart:convert';
import 'dart:io';
import 'dart:math' as math;

import 'package:adinn_tasks/features/auth/intro/writing_plan.dart';
import 'package:flutter/animation.dart';
import 'package:flutter_test/flutter_test.dart';

double seg(double t, double a, double b, [Curve c = Curves.linear]) => c.transform(((t - a) / (b - a)).clamp(0.0, 1.0));

void main() {
  test('sample splash tracks', () {
    final out = Platform.environment['SPLASH_OUT'];
    if (out == null) return;
    final plan = WritingPlan.shared;
    const pop = Cubic(0.2, 1.0, 0.3, 1.25);
    const tip = Offset(540, 523), dotHome = Offset(532, 102);
    const penOut = 0.55, write0 = 0.75, write1 = 1.85, penAway = 2.2;
    const centreShift = 480.0, total = 3.0;
    final regAt = wordPoint(1432, 582);

    Offset arc(Offset a, Offset b, double f, double lift) => Offset.lerp(a, b, f)! + Offset(0, -lift * math.sin(math.pi * f));

    Offset penAt(double t) {
      if (t < penOut) return tip;
      if (t < write0) return arc(tip, plan.start, Curves.easeInOutCubic.transform((t - penOut) / (write0 - penOut)), 70);
      if (t < write1) return plan.penAt((t - write0) / (write1 - write0)).pos;
      final f = Curves.easeInCubic.transform(((t - write1) / (penAway - write1)).clamp(0.0, 1.0));
      final a = plan.end, c = a + const Offset(260, -520), b = a + const Offset(1500, -1500);
      final u = 1 - f;
      return a * (u * u) + c * (2 * u * f) + b * (f * f);
    }

    final samples = <Map<String, dynamic>>[];
    const n = 90; // 30 per second
    for (var i = 0; i <= n; i++) {
      final t = total * i / n;
      final a = 0.35 + 0.65 * seg(t, 0, 0.45, Curves.easeOutCubic);
      final dotPop = pop.transform(seg(t, 0.3, 0.65));
      final fly = Curves.easeInOutCubic.transform(seg(t, 0.7, 1.3));
      final target = WritingPlan.dotTarget - dotHome;
      final dotShift = Offset.lerp(Offset.zero, target, fly)! + Offset(0, -90 * (1 - (2 * fly - 1) * (2 * fly - 1)));
      final writing = ((t - write0) / (write1 - write0)).clamp(0.0, 1.0);
      final reveal = t >= write1 ? List<double>.filled(plan.strokes.length, 1) : plan.reveal(3.05 + writing * 1.15);
      final pen = penAt(t);
      samples.add({
        't': t,
        'a': a,
        'dotPop': dotPop,
        'dot': [dotShift.dx, dotShift.dy],
        'reveal': reveal,
        'pen': [pen.dx - tip.dx, pen.dy - tip.dy],
        'penOpacity': seg(t, 0.2, 0.5) * (1 - seg(t, write1, penAway)),
        'tilt': -0.10 * seg(t, penOut, write0, Curves.easeOut) - 0.7 * seg(t, write1, penAway, Curves.easeIn),
        'rule': seg(t, 1.95, 2.4, Curves.easeInOutCubic),
        'reg': seg(t, 2.0, 2.3),
        'slide': centreShift * (1 - Curves.easeInOutCubic.transform(seg(t, 0.5, 0.85))),
        'tag': seg(t, 2.1, 2.65, Curves.easeOutCubic),
        'fade': 0.55 + 0.45 * (t / 0.6).clamp(0.0, 1.0),
        'swell': 1 + 0.10 * math.sin((t / total) * math.pi),
      });
    }

    final data = {
      'total': total,
      'samples': samples,
      'aRect': [kARect.left, kARect.top, kARect.width, kARect.height],
      'lineRect': [kLineRect.left, kLineRect.top, kLineRect.width, kLineRect.height],
      'strokes': [for (final s in plan.strokes) {'asset': s.asset, 'rect': [s.dest.left, s.dest.top, s.dest.width, s.dest.height]}],
      'regAt': [regAt.dx, regAt.dy],
    };
    Directory(out).createSync(recursive: true);
    File('$out/tracks.json').writeAsStringSync(jsonEncode(data));
  });
}
