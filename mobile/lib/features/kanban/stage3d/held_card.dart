import 'package:flutter/material.dart';

import '../../../core/theme.dart';
import 'stage_card.dart';
import 'stage_meta.dart';

/// What the number counts. Split Tasks lists split requests (each one is about a ticket already counted in its own stage), so
/// it says "Splits" rather than claiming more tickets.
String _unit(StageInfo s) => s.key == 'split_log' ? (s.count == 1 ? 'Split' : 'Splits') : (s.count == 1 ? 'Ticket' : 'Tickets');

Color _accentFor(String key) => key == allStageKey ? adinnRed : stageAccent(key);

/// The square card the representative holds: the current stage's icon, name and ticket count, in that stage's colour.
/// All Tickets (red) is the first card; swiping the card turns to the next stage.
class HeldStageCard extends StatelessWidget {
  const HeldStageCard({super.key, required this.stage, required this.side, this.count = const AlwaysStoppedAnimation(1), this.onTap});

  final StageInfo stage;

  /// Width and height of the card.
  final double side;

  /// 0 → the number is hidden, 1 → shown (scales in when the screen first appears).
  final Animation<double> count;

  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final accent = _accentFor(stage.key);
    final isAll = stage.key == allStageKey;

    return GestureDetector(
      key: Key('held-card-${stage.key}'),
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Hero(
        tag: 'stage-card-${stage.key}',
        flightShuttleBuilder: stageHeroShuttle(stage),
        child: Container(
          width: side,
          height: side,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(side * 0.2),
            gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [Colors.white, Color.alphaBlend(accent.withValues(alpha: 0.16), Colors.white)]),
            border: Border.all(color: accent, width: side * 0.028),
            boxShadow: [
              BoxShadow(color: accent.withValues(alpha: 0.25), blurRadius: side * 0.1, offset: Offset(0, side * 0.04)),
              BoxShadow(color: Colors.black.withValues(alpha: 0.18), blurRadius: side * 0.08, offset: Offset(0, side * 0.05)),
            ],
          ),
          padding: EdgeInsets.all(side * 0.07),
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: SizedBox(
              width: side * 0.86,
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(stageIcon(stage.key), color: accent, size: side * 0.17),
                SizedBox(height: side * 0.02),
                Text(isAll ? 'ALL TICKETS' : stage.label.toUpperCase(), key: Key('held-title-${stage.key}'), textAlign: TextAlign.center, maxLines: 2, style: TextStyle(fontSize: side * 0.082, height: 1.05, fontWeight: FontWeight.w900, color: const Color(0xFF111111))),
                SizedBox(height: side * 0.02),
                AnimatedBuilder(
                  animation: count,
                  builder: (context, child) {
                    final t = Curves.easeOutBack.transform(count.value.clamp(0.0, 1.0));
                    return Opacity(opacity: count.value.clamp(0.0, 1.0), child: Transform.scale(scale: 0.6 + 0.4 * t, child: child));
                  },
                  child: Text('${stage.count}', key: isAll ? const Key('total-count') : Key('held-count-${stage.key}'), style: TextStyle(fontSize: side * 0.3, height: 1.0, fontWeight: FontWeight.w900, color: stage.count == 0 ? const Color(0xFF98A2B3) : accent)),
                ),
                Text(_unit(stage), style: TextStyle(fontSize: side * 0.075, fontWeight: FontWeight.w700, color: const Color(0xFF667085))),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}

/// The rest of the stack peeking out behind the held card: one edge for each card still to come (three at most, so a
/// long stack does not bury the man), each in that stage's colour. On the last card there are none. The edges open out
/// from behind the card as the screen appears ([open] 0 → 1).
class HeldCardEdges extends StatelessWidget {
  const HeldCardEdges({super.key, required this.side, required this.colors, this.open = const AlwaysStoppedAnimation(1)});

  /// Most edges drawn behind the card.
  static const maxEdges = 3;

  final double side;

  /// Accent colours of the stages still to come, nearest first (up to three are drawn).
  final List<Color> colors;
  final Animation<double> open;

  @override
  Widget build(BuildContext context) {
    final edges = colors.take(maxEdges).toList();

    return AnimatedBuilder(
      animation: open,
      builder: (context, _) {
        final t = Curves.easeOutCubic.transform(open.value.clamp(0.0, 1.0));

        return SizedBox(
          key: const Key('card-edges'),
          width: side,
          height: side,
          child: Stack(clipBehavior: Clip.none, children: [
            // Farthest first, so the nearest edge sits on top.
            for (var i = edges.length - 1; i >= 0; i--)
              Positioned(
                key: Key('card-edge-$i'),
                left: side * 0.034 * (i + 1) * t,
                top: -side * 0.01 * (i + 1) * t,
                child: Transform.rotate(
                  angle: 0.04 * (i + 1) * t,
                  alignment: Alignment.bottomLeft,
                  child: Container(
                    width: side,
                    height: side,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(side * 0.2),
                      gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [Colors.white, Color.alphaBlend(edges[i].withValues(alpha: 0.18), Colors.white)]),
                      border: Border.all(color: edges[i], width: side * 0.026),
                      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.12), blurRadius: side * 0.05, offset: Offset(side * 0.015, side * 0.02))],
                    ),
                  ),
                ),
              ),
          ]),
        );
      },
    );
  }
}
