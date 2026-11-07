import 'package:flutter/material.dart';

import 'dashboard_models.dart';

Color hexColor(String hex) {
  final h = hex.replaceFirst('#', '');
  return Color(int.parse(h.length == 6 ? 'FF$h' : h, radix: 16));
}

/// Half-star rating, e.g. 4.5 → ★★★★☆ with the fifth half filled.
class HalfStars extends StatelessWidget {
  const HalfStars(this.rating, {super.key, this.size = 18});

  final double rating;
  final double size;

  @override
  Widget build(BuildContext context) {
    const gold = Color(0xFFF5A623);
    return Semantics(
      label: '$rating out of 5 stars',
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        for (var i = 1; i <= 5; i++)
          Icon(
            rating >= i ? Icons.star_rounded : (rating >= i - 0.5 ? Icons.star_half_rounded : Icons.star_border_rounded),
            size: size,
            color: rating >= i - 0.5 ? gold : const Color(0xFFD0D5DD),
          ),
      ]),
    );
  }
}

/// White rounded card with a title/subtitle header, matching the web dashboard's sections.
class SectionCard extends StatelessWidget {
  const SectionCard({super.key, required this.title, this.subtitle, this.trailing, this.icon, required this.child});

  final IconData? icon;
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return RepaintBoundary(child: Card(
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18), side: const BorderSide(color: Color(0xFFEAECF0))),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            if (icon != null) ...[IconBadge(icon!, color: theme.colorScheme.primary), const SizedBox(width: 12)],
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(title, style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800, letterSpacing: -0.2)),
                if (subtitle != null) Text(subtitle!, style: theme.textTheme.bodySmall?.copyWith(color: Colors.black54)),
              ]),
            ),
            ?trailing,
          ]),
          const SizedBox(height: 12),
          child,
        ]),
      ),
    ));
  }
}

/// A soft gradient rounded square holding an icon; the dashboard's one icon treatment.
class IconBadge extends StatelessWidget {
  const IconBadge(this.icon, {super.key, required this.color, this.size = 38});

  final IconData icon;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(size * 0.32),
          gradient: LinearGradient(colors: [color.withValues(alpha: 0.18), color.withValues(alpha: 0.07)], begin: Alignment.topLeft, end: Alignment.bottomRight),
          border: Border.all(color: color.withValues(alpha: 0.14)),
        ),
        child: Icon(icon, size: size * 0.55, color: color),
      );
}

/// A single stat tile. Tappable when [onTap] is given (the web links these to the matching board column).
class StatTile extends StatelessWidget {
  const StatTile({super.key, required this.label, required this.value, this.hint, this.accent, this.icon, this.onTap, this.child});

  final String label;
  final String value;
  final String? hint;
  final Color? accent;
  final IconData? icon;
  final VoidCallback? onTap;
  final Widget? child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final color = accent ?? theme.colorScheme.primary;
    final radius = BorderRadius.circular(18);

    return RepaintBoundary(child: Container(
      decoration: BoxDecoration(
        borderRadius: radius,
        color: Colors.white,
        border: Border.all(color: const Color(0xFFEAECF0)),
        boxShadow: [BoxShadow(color: color.withValues(alpha: 0.08), blurRadius: 14, offset: const Offset(0, 6))],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: radius,
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 88),
            child: Stack(children: [
              // a quiet accent wash in the corner
              Positioned(top: -22, right: -22, child: Container(width: 66, height: 66, decoration: BoxDecoration(shape: BoxShape.circle, color: color.withValues(alpha: 0.07)))),
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
                  Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.only(top: 2),
                        child: Text(label, style: const TextStyle(fontSize: 12, color: Color(0xFF667085), fontWeight: FontWeight.w600), maxLines: 2, overflow: TextOverflow.ellipsis),
                      ),
                    ),
                    if (icon != null) ...[const SizedBox(width: 8), IconBadge(icon!, color: color, size: 28)],
                  ]),
                  const SizedBox(height: 2),
                  Text(value, style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800, color: color, letterSpacing: -0.4)),
                  ?child,
                  if (hint != null) ...[
                    const SizedBox(height: 2),
                    Text(hint!, style: theme.textTheme.bodySmall?.copyWith(color: const Color(0xFF98A2B3), fontSize: 10.5), maxLines: 2, overflow: TextOverflow.ellipsis),
                  ],
                ]),
              ),
            ]),
          ),
        ),
      ),
    ));
  }
}

/// Two tiles per row, each row as tall as its tallest tile; a last tile with no partner spans the full width.
class StatGrid extends StatelessWidget {
  const StatGrid({super.key, required this.tiles});

  final List<Widget> tiles;

  @override
  Widget build(BuildContext context) {
    final rows = <Widget>[];
    for (var i = 0; i < tiles.length; i += 2) {
      final pair = tiles.skip(i).take(2).toList();
      if (rows.isNotEmpty) rows.add(const SizedBox(height: 12));
      rows.add(IntrinsicHeight(
        child: Row(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          for (var j = 0; j < pair.length; j++) ...[
            if (j > 0) const SizedBox(width: 12),
            Expanded(child: pair[j]),
          ],
        ]),
      ));
    }
    return Column(children: rows);
  }
}

class Series {
  const Series(this.label, this.color, this.values);

  final String label;
  final Color color;
  final List<int> values;
}

/// Months across, counts up: filled line series with a value on each point (the web's "Assigned · In Progress · Completed").
/// Touch or drag along it and a tooltip shows every series for that month.
class TrendLineChart extends StatelessWidget {
  const TrendLineChart({super.key, required this.months, required this.series, this.height = 220});

  final List<TrendMonth> months;
  final List<Series> series;
  final double height;

  @override
  Widget build(BuildContext context) => _InteractiveTrend(months: months, series: series, bars: false, height: height);
}

/// The web's "Overdue & Completed · Declined" grouped bars, also touchable.
class TrendBarChart extends StatelessWidget {
  const TrendBarChart({super.key, required this.months, required this.series, this.height = 220});

  final List<TrendMonth> months;
  final List<Series> series;
  final double height;

  @override
  Widget build(BuildContext context) => _InteractiveTrend(months: months, series: series, bars: true, height: height);
}

class _InteractiveTrend extends StatefulWidget {
  const _InteractiveTrend({required this.months, required this.series, required this.bars, required this.height});

  final List<TrendMonth> months;
  final List<Series> series;
  final bool bars;
  final double height;

  @override
  State<_InteractiveTrend> createState() => _InteractiveTrendState();
}

class _InteractiveTrendState extends State<_InteractiveTrend> {
  int? _selected;

  int _indexAt(double dx, double width) {
    final n = widget.months.length;
    final plotWidth = width - _TrendPainter.left - _TrendPainter.right;
    if (widget.bars) return ((dx - _TrendPainter.left) / (plotWidth / n)).floor().clamp(0, n - 1);
    if (n == 1) return 0;
    return ((dx - _TrendPainter.left) / (plotWidth / (n - 1))).round().clamp(0, n - 1);
  }

  @override
  Widget build(BuildContext context) {
    final kind = widget.bars ? 'Bar' : 'Line';
    return Semantics(
      label: '$kind chart of ${widget.series.map((s) => s.label).join(', ')} over the last ${widget.months.length} months. Touch a month for its figures.',
      child: LayoutBuilder(builder: (context, box) {
        final width = box.maxWidth;
        return GestureDetector(
          key: const Key('trend-touch'),
          behavior: HitTestBehavior.opaque,
          // a tap picks a month (tapping the same one again hides it); dragging sideways scrubs across them
          onTapDown: (d) {
            final i = _indexAt(d.localPosition.dx, width);
            setState(() => _selected = _selected == i ? null : i);
          },
          onHorizontalDragStart: (d) => setState(() => _selected = _indexAt(d.localPosition.dx, width)),
          onHorizontalDragUpdate: (d) {
            final i = _indexAt(d.localPosition.dx, width);
            if (i != _selected) setState(() => _selected = i);
          },
          child: SizedBox(
            height: widget.height,
            width: double.infinity,
            child: CustomPaint(painter: _TrendPainter(widget.months.map((m) => m.label).toList(), widget.series, bars: widget.bars, selected: _selected)),
          ),
        );
      }),
    );
  }
}

class _TrendPainter extends CustomPainter {
  _TrendPainter(this.labels, this.series, {required this.bars, this.selected});

  final List<String> labels;
  final List<Series> series;
  final bool bars;
  final int? selected;

  static const left = 28.0;
  static const right = 16.0;
  static const _bottom = 26.0;
  static const _top = 12.0;

  @override
  void paint(Canvas canvas, Size size) {
    if (labels.isEmpty || series.isEmpty) return;

    final maxValue = series.expand((s) => s.values).fold<int>(0, (a, b) => a > b ? a : b);
    final top = maxValue < 4 ? 4 : maxValue; // a flat chart still gets a readable 0..4 axis
    final plot = Rect.fromLTRB(left, _top, size.width - right, size.height - _bottom);

    final grid = Paint()
      ..color = const Color(0xFFE4E7EC)
      ..strokeWidth = 1;

    for (var i = 0; i <= 4; i++) {
      final y = plot.bottom - plot.height * i / 4;
      canvas.drawLine(Offset(plot.left, y), Offset(plot.right, y), grid);
      _text(canvas, '${(top * i / 4).round()}', Offset(plot.left - 6, y), const TextStyle(fontSize: 10, color: Color(0xFF667085)), alignRight: true);
    }

    final step = labels.length == 1 ? plot.width : plot.width / (labels.length - 1);
    final slot = plot.width / labels.length;

    for (var i = 0; i < labels.length; i++) {
      final x = bars ? plot.left + slot * (i + 0.5) : plot.left + step * i;
      _text(canvas, labels[i], Offset(x, plot.bottom + 12), const TextStyle(fontSize: 10, color: Color(0xFF667085)), center: true);
    }

    if (bars) {
      final groupWidth = slot * 0.62;
      final barWidth = groupWidth / series.length;

      for (var s = 0; s < series.length; s++) {
        for (var i = 0; i < labels.length; i++) {
          final v = series[s].values[i];
          final h = plot.height * v / top;
          final x = plot.left + slot * i + (slot - groupWidth) / 2 + barWidth * s;
          final rect = RRect.fromRectAndCorners(Rect.fromLTWH(x, plot.bottom - h, barWidth - 3, h), topLeft: const Radius.circular(4), topRight: const Radius.circular(4));
          canvas.drawRRect(rect, Paint()..color = series[s].color);
          if (v > 0) _text(canvas, '$v', Offset(x + (barWidth - 3) / 2, plot.bottom - h - 8), TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: series[s].color), center: true);
        }
      }
      _drawSelection(canvas, size, plot, top, step, slot);
      return;
    }

    for (final s in series) {
      final points = [for (var i = 0; i < labels.length; i++) Offset(plot.left + step * i, plot.bottom - plot.height * s.values[i] / top)];
      final line = Path()..moveTo(points.first.dx, points.first.dy);
      for (final p in points.skip(1)) {
        line.lineTo(p.dx, p.dy);
      }
      final area = Path.from(line)
        ..lineTo(points.last.dx, plot.bottom)
        ..lineTo(points.first.dx, plot.bottom)
        ..close();

      canvas.drawPath(area, Paint()..color = s.color.withValues(alpha: 0.10));
      canvas.drawPath(line, Paint()
        ..color = s.color
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2.5
        ..strokeJoin = StrokeJoin.round);

      for (var i = 0; i < points.length; i++) {
        canvas.drawCircle(points[i], 4, Paint()..color = Colors.white);
        canvas.drawCircle(points[i], 3, Paint()..color = s.color);
      }
    }

    // Value labels last so no line crosses them; skip zeros to keep a quiet month readable.
    for (final s in series) {
      for (var i = 0; i < labels.length; i++) {
        if (s.values[i] == 0) continue;
        final p = Offset(plot.left + step * i, plot.bottom - plot.height * s.values[i] / top);
        _text(canvas, '${s.values[i]}', Offset(p.dx, p.dy - 11), TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: s.color), center: true);
      }
    }

    _drawSelection(canvas, size, plot, top, step, slot);
  }

  /// The touched month: a guide line (or highlighted band for bars), ringed points, and a box listing every series.
  void _drawSelection(Canvas canvas, Size size, Rect plot, int top, double step, double slot) {
    final i = selected;
    if (i == null || i < 0 || i >= labels.length) return;

    final x = bars ? plot.left + slot * (i + 0.5) : plot.left + step * i;

    if (bars) {
      canvas.drawRRect(RRect.fromRectAndRadius(Rect.fromLTRB(x - slot / 2, plot.top, x + slot / 2, plot.bottom), const Radius.circular(8)), Paint()..color = const Color(0xFF101828).withValues(alpha: 0.05));
    } else {
      canvas.drawLine(Offset(x, plot.top), Offset(x, plot.bottom), Paint()
        ..color = const Color(0xFF98A2B3)
        ..strokeWidth = 1.2);
      for (final s in series) {
        final p = Offset(x, plot.bottom - plot.height * s.values[i] / top);
        canvas.drawCircle(p, 7, Paint()..color = s.color.withValues(alpha: 0.2));
        canvas.drawCircle(p, 5, Paint()..color = Colors.white);
        canvas.drawCircle(p, 3.6, Paint()..color = s.color);
      }
    }

    // the tooltip
    const titleStyle = TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Colors.white);
    TextPainter measure(String text, TextStyle style) => TextPainter(text: TextSpan(text: text, style: style), textDirection: TextDirection.ltr)..layout();

    final title = measure(labels[i], titleStyle);
    final names = [for (final s in series) measure(s.label, const TextStyle(fontSize: 11, color: Color(0xFFD0D5DD)))];
    final values = [for (final s in series) measure('${s.values[i]}', const TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Colors.white))];

    final nameWidth = names.fold<double>(0, (a, t) => t.width > a ? t.width : a);
    final valueWidth = values.fold<double>(0, (a, t) => t.width > a ? t.width : a);
    final inner = [title.width, 12 + nameWidth + 10 + valueWidth].reduce((a, b) => a > b ? a : b);
    final box = Size(inner + 20, 14 + 16 + series.length * 16.0);

    final left = (x - box.width / 2).clamp(2.0, size.width - box.width - 2);
    final rect = Rect.fromLTWH(left, plot.top - 4, box.width, box.height);
    final rrect = RRect.fromRectAndRadius(rect, const Radius.circular(10));

    canvas.drawShadow(Path()..addRRect(rrect), Colors.black.withValues(alpha: 0.4), 6, false);
    canvas.drawRRect(rrect, Paint()..color = const Color(0xFF1D2939));

    // a little pointer toward the month
    final tip = Path()
      ..moveTo(x - 5, rect.bottom - 0.5)
      ..lineTo(x + 5, rect.bottom - 0.5)
      ..lineTo(x, rect.bottom + 5)
      ..close();
    canvas.drawPath(tip, Paint()..color = const Color(0xFF1D2939));

    title.paint(canvas, Offset(rect.left + 10, rect.top + 7));
    for (var r = 0; r < series.length; r++) {
      final y = rect.top + 7 + 16 + 4 + r * 16.0;
      canvas.drawCircle(Offset(rect.left + 14, y + 6), 3.5, Paint()..color = series[r].color);
      names[r].paint(canvas, Offset(rect.left + 10 + 12, y));
      values[r].paint(canvas, Offset(rect.right - 10 - values[r].width, y));
    }
  }

  void _text(Canvas canvas, String text, Offset at, TextStyle style, {bool center = false, bool alignRight = false}) {
    final tp = TextPainter(text: TextSpan(text: text, style: style), textDirection: TextDirection.ltr)..layout();
    final dx = center ? at.dx - tp.width / 2 : (alignRight ? at.dx - tp.width : at.dx);
    tp.paint(canvas, Offset(dx, at.dy - tp.height / 2));
  }

  @override
  bool shouldRepaint(_TrendPainter old) => old.labels != labels || old.series != series || old.bars != bars || old.selected != selected;
}

class Legend extends StatelessWidget {
  const Legend(this.items, {super.key});

  final List<Series> items;

  @override
  Widget build(BuildContext context) => Wrap(spacing: 14, runSpacing: 4, children: [
        for (final s in items)
          Row(mainAxisSize: MainAxisSize.min, children: [
            Container(width: 10, height: 10, decoration: BoxDecoration(color: s.color, borderRadius: BorderRadius.circular(3))),
            const SizedBox(width: 6),
            Text(s.label, style: Theme.of(context).textTheme.bodySmall),
          ]),
      ]);
}
