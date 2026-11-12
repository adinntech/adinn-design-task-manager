import 'dart:math' as math;
import 'dart:ui';

import 'package:flutter/animation.dart';

/// Maps the printed Adinn logo (a 1600 px wide image, where the A spans x 173..669, y 419..919) into the 800 x 690 box the
/// animated logo lives in. The A's foot lands where the animated A's foot is, so the finished lockup is the printed logo.
const double kWordScale = 1.30;
Offset wordPoint(double x, double y) => Offset(650 + (x - 670) * kWordScale, 675 + (y - 919) * kWordScale);

/// Where the printed A, the red rule under the word and the tagline line (where "Advertising Services Ltd." is printed) sit.
final Rect kARect = Rect.fromPoints(wordPoint(168, 412), wordPoint(676, 926));
final Rect kLineRect = Rect.fromPoints(wordPoint(590, 780), wordPoint(1410, 810));
final double kTaglineLeft = wordPoint(678, 0).dx, kTaglineRight = wordPoint(1407, 0).dx, kTaglineCentreY = wordPoint(0, 878).dy, kTaglineCap = 46 * kWordScale;

/// One letter of the wordmark: its artwork (white on transparent), where it lands in the logo box, and the centre-line the
/// pencil follows while writing it. The ink is revealed by sweeping a brush of [brush] width along [path]; only the part of the
/// letter's real shape under the brush shows, so the true font appears as though the pencil is drawing it.
class PenStroke {
  PenStroke({required this.asset, required Rect source, required this.path, required double brush})
      : metric = path.computeMetrics().first,
        brush = brush * kWordScale,
        dest = Rect.fromPoints(wordPoint(source.left, source.top), wordPoint(source.right, source.bottom));

  final String asset;
  final Rect dest;
  final Path path;
  final PathMetric metric;
  final double brush;

  Offset get startPoint => metric.getTangentForOffset(0)!.position;
  Offset get endPoint => metric.getTangentForOffset(metric.length)!.position;
}

class WritingPlan {
  WritingPlan._() {
    Path make(List<double> p, {required List<List<double>> segs}) {
      final path = Path();
      final s = wordPoint(p[0], p[1]);
      path.moveTo(s.dx, s.dy);
      for (final g in segs) {
        if (g.length == 2) {
          final a = wordPoint(g[0], g[1]);
          path.lineTo(a.dx, a.dy);
        } else {
          final a = wordPoint(g[0], g[1]), b = wordPoint(g[2], g[3]), c = wordPoint(g[4], g[5]);
          path.cubicTo(a.dx, a.dy, b.dx, b.dy, c.dx, c.dy);
        }
      }
      return path;
    }

    List<List<double>> nSegs(double dx) => [
          [1028 + dx, 650],
          [1050 + dx, 628, 1105 + dx, 622, 1140 + dx, 655],
          [1140 + dx, 722],
        ];

    strokes = [
      // d: round the bowl, then up the stem.
      PenStroke(
        asset: 'g_d',
        source: const Rect.fromLTRB(562, 472, 777, 737),
        brush: 125,
        path: make([700, 640], segs: [
          [660, 615, 600, 630, 592, 680],
          [585, 725, 650, 735, 690, 705],
          [705, 690, 712, 670, 714, 640],
          [716, 600, 716, 540, 718, 490],
        ]),
      ),
      // i: down the stem (its dot is the logo's own red dot, which flies across to sit above it).
      PenStroke(asset: 'g_i', source: const Rect.fromLTRB(863, 609, 961, 737), brush: 125, path: make([912, 622], segs: [[912, 724]])),
      // n, n: up the left stem, over the arch, down the right leg.
      PenStroke(asset: 'g_n1', source: const Rect.fromLTRB(975, 609, 1184, 736), brush: 100, path: make([1028, 722], segs: nSegs(0))),
      PenStroke(asset: 'g_n2', source: const Rect.fromLTRB(1197, 609, 1407, 736), brush: 100, path: make([1250, 722], segs: nSegs(222))),
    ];

    // A stroke takes as long as it is; lifting between strokes costs a fixed beat.
    for (var i = 0; i < strokes.length; i++) {
      if (i > 0) _items.add(_Item.lift(i, 110));
      _items.add(_Item.stroke(i, strokes[i].metric.length));
    }
    _total = _items.fold(0, (a, b) => a + b.weight);
    var acc = 0.0;
    for (final it in _items) {
      it.from = acc / _total;
      acc += it.weight;
      it.to = acc / _total;
    }
  }

  static final shared = WritingPlan._();

  /// Where the i's red dot comes to rest (the dot of the printed wordmark).
  static final Offset dotTarget = wordPoint(914, 517);

  late final List<PenStroke> strokes;
  final List<_Item> _items = [];
  late final double _total;

  Offset get start => strokes.first.startPoint;
  Offset get end => strokes.last.endPoint;

  /// Pen position at progress [p] (0..1) through the writing.
  ({Offset pos, bool down}) penAt(double p) {
    p = p.clamp(0.0, 1.0);
    for (final it in _items) {
      if (p > it.to && it != _items.last) continue;
      final f = it.to == it.from ? 1.0 : ((p - it.from) / (it.to - it.from)).clamp(0.0, 1.0);
      if (it.isLift) {
        final a = strokes[it.index - 1].endPoint, b = strokes[it.index].startPoint;
        final e = Curves.easeInOut.transform(f);
        return (pos: Offset.lerp(a, b, e)! + Offset(0, -34 * math.sin(math.pi * e)), down: false);
      }
      final st = strokes[it.index];
      final e = Curves.easeInOutSine.transform(f);
      return (pos: st.metric.getTangentForOffset(st.metric.length * e)!.position, down: f < 1);
    }
    return (pos: end, down: false);
  }

  bool penDown(double t) => t >= 3.05 && t < 4.2 && penAt((t - 3.05) / 1.15).down;

  /// Inked fraction of every stroke at time [t] (seconds in the whole sequence).
  List<double> reveal(double t) {
    final out = List<double>.filled(strokes.length, 0);
    if (t >= 4.2) return List<double>.filled(strokes.length, 1);
    if (t < 3.05) return out;
    final p = (t - 3.05) / 1.15;
    for (final it in _items) {
      if (it.isLift) continue;
      if (p >= it.to) {
        out[it.index] = 1;
      } else if (p > it.from) {
        out[it.index] = Curves.easeInOutSine.transform((p - it.from) / (it.to - it.from));
      }
    }
    return out;
  }
}

class _Item {
  _Item.stroke(this.index, this.weight) : isLift = false;
  _Item.lift(this.index, this.weight) : isLift = true;

  final int index;
  final double weight;
  final bool isLift;
  double from = 0, to = 0;
}
