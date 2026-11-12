import 'package:flutter/material.dart';

import '../../../core/theme.dart';
import 'stage_meta.dart';

/// What a stage card looks like while it flies to / from the stage screen's header: the card surface with its
/// icon and name, so it never has to lay out the full card at an in-between size.
HeroFlightShuttleBuilder stageHeroShuttle(StageInfo stage) => (context, animation, direction, from, to) {
      final accent = stageAccent(stage.key);
      return AnimatedBuilder(
        animation: animation,
        builder: (context, _) => Material(
          color: Colors.transparent,
          child: Container(
            decoration: stageCardDecoration(accent, lifted: true),
            alignment: Alignment.center,
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: Padding(
                padding: const EdgeInsets.all(8),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Icon(stageIcon(stage.key), color: accent, size: 28),
                  const SizedBox(height: 4),
                  Text(stage.label, textAlign: TextAlign.center, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: Color(0xFF111111))),
                ]),
              ),
            ),
          ),
        ),
      );
    };

/// The glossy white card with a red border that every stage is drawn as (the same look as the stack artwork).
BoxDecoration stageCardDecoration(Color accent, {double radius = 22, bool lifted = false}) => BoxDecoration(
      borderRadius: BorderRadius.circular(radius),
      gradient: const LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [Colors.white, Color(0xFFF1F2F5)]),
      border: Border.all(color: accent, width: 3),
      boxShadow: [
        BoxShadow(color: Colors.black.withValues(alpha: lifted ? 0.28 : 0.16), blurRadius: lifted ? 22 : 12, offset: Offset(0, lifted ? 12 : 6)),
        BoxShadow(color: accent.withValues(alpha: 0.10), blurRadius: 18, spreadRadius: -4),
      ],
    );

/// One workflow stage as a physical card: icon, web name and ticket count.
class StageCardWidget extends StatelessWidget {
  const StageCardWidget({super.key, required this.stage, this.dimmed = false, this.width = 132, this.height = 168});

  final StageInfo stage;

  /// A stage with no tickets (shown only when all 14 stages are asked for).
  final bool dimmed;
  final double width;
  final double height;

  @override
  Widget build(BuildContext context) {
    final accent = stageAccent(stage.key);
    final scale = width / 132;

    return Opacity(
      opacity: dimmed ? 0.55 : 1,
      child: Container(
        width: width,
        height: height,
        padding: EdgeInsets.fromLTRB(10 * scale, 12 * scale, 10 * scale, 10 * scale),
        decoration: stageCardDecoration(accent),
        child: Column(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Container(
            width: 44 * scale,
            height: 44 * scale,
            decoration: BoxDecoration(color: accent.withValues(alpha: 0.12), shape: BoxShape.circle),
            child: Icon(stageIcon(stage.key), color: accent, size: 24 * scale),
          ),
          Expanded(
            child: Center(
              child: Text(stage.label, textAlign: TextAlign.center, maxLines: 3, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12.5 * scale, height: 1.15, fontWeight: FontWeight.w800, color: const Color(0xFF111111))),
            ),
          ),
          Text('${stage.count}', key: Key('stage-count-${stage.key}'), style: TextStyle(fontSize: 24 * scale, fontWeight: FontWeight.w900, color: stage.count == 0 ? const Color(0xFF98A2B3) : adinnRed)),
        ]),
      ),
    );
  }
}
