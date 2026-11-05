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
  const SectionCard({super.key, required this.title, this.subtitle, this.trailing, required this.child});

  final String title;
  final String? subtitle;
  final Widget? trailing;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(title, style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                if (subtitle != null) Text(subtitle!, style: theme.textTheme.bodySmall?.copyWith(color: Colors.black54)),
              ]),
            ),
            ?trailing,
          ]),
          const SizedBox(height: 12),
          child,
        ]),
      ),
    );
  }
}

/// A single stat tile. Tappable when [onTap] is given (the web links these to the matching board column).
class StatTile extends StatelessWidget {
  const StatTile({super.key, required this.label, required this.value, this.hint, this.accent, this.onTap, this.child});

  final String label;
  final String value;
  final String? hint;
  final Color? accent;
  final VoidCallback? onTap;
  final Widget? child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final color = accent ?? theme.colorScheme.primary;

    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
            Text(label, style: theme.textTheme.labelMedium?.copyWith(color: Colors.black54), maxLines: 2, overflow: TextOverflow.ellipsis),
            const SizedBox(height: 4),
            Text(value, style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800, color: color)),
            ?child,
            if (hint != null) Text(hint!, style: theme.textTheme.bodySmall?.copyWith(color: Colors.black45, fontSize: 11), maxLines: 2, overflow: TextOverflow.ellipsis),
          ]),
        ),
      ),
    );
  }
}

/// Two tiles per row; wraps to any number of tiles.
class StatGrid extends StatelessWidget {
  const StatGrid({super.key, required this.tiles});

  final List<Widget> tiles;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: (context, c) {
      final width = (c.maxWidth - 10) / 2;
      return Wrap(spacing: 10, runSpacing: 10, children: [for (final t in tiles) SizedBox(width: width, child: t)]);
    });
  }
}

class Series {
  const Series(this.label, this.color, this.values);

  final String label;
  final Color color;
  final List<int> values;
}

/// Months across, counts up: filled line series with a value on each point (the web's "Assigned · In Progress · Completed").
class TrendLineChart extends StatelessWidget {
  const TrendLineChart({super.key, required this.months, required this.series});

  final List<TrendMonth> months;
  final List<Series> series;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'Line chart of ${series.map((s) => s.label).join(', ')} over the last ${months.length} months',
      child: SizedBox(
        height: 220,
        width: double.infinity,
        child: CustomPaint(painter: _TrendPainter(months.map((m) => m.label).toList(), series, bars: false)),
      ),
    );
  }
}

/// The web's "Overdue & Completed · Declined" grouped bars.
class TrendBarChart extends StatelessWidget {
  const TrendBarChart({super.key, required this.months, required this.series});

  final List<TrendMonth> months;
  final List<Series> series;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'Bar chart of ${series.map((s) => s.label).join(', ')} over the last ${months.length} months',
      child: SizedBox(
        height: 220,
        width: double.infinity,
        child: CustomPaint(painter: _TrendPainter(months.map((m) => m.label).toList(), series, bars: true)),
      ),
    );
  }
}

class _TrendPainter extends CustomPainter {
  _TrendPainter(this.labels, this.series, {required this.bars});

  final List<String> labels;
  final List<Series> series;
  final bool bars;

  static const _left = 28.0;
  static const _bottom = 26.0;
  static const _top = 12.0;

  @override
  void paint(Canvas canvas, Size size) {
    if (labels.isEmpty || series.isEmpty) return;

    final maxValue = series.expand((s) => s.values).fold<int>(0, (a, b) => a > b ? a : b);
    final top = maxValue < 4 ? 4 : maxValue; // a flat chart still gets a readable 0..4 axis
    final plot = Rect.fromLTRB(_left, _top, size.width - 8, size.height - _bottom);

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
  }

  void _text(Canvas canvas, String text, Offset at, TextStyle style, {bool center = false, bool alignRight = false}) {
    final tp = TextPainter(text: TextSpan(text: text, style: style), textDirection: TextDirection.ltr)..layout();
    final dx = center ? at.dx - tp.width / 2 : (alignRight ? at.dx - tp.width : at.dx);
    tp.paint(canvas, Offset(dx, at.dy - tp.height / 2));
  }

  @override
  bool shouldRepaint(_TrendPainter old) => old.labels != labels || old.series != series || old.bars != bars;
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
