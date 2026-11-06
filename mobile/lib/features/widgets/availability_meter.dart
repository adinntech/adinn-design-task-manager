import 'package:flutter/material.dart';

import '../../core/models.dart';

Color availabilityColor(String level) => switch (level) {
      'available' => const Color(0xFF067647),
      'moderate' => const Color(0xFF9A6700),
      'busy' => const Color(0xFFC4320A),
      _ => const Color(0xFFB42318), // critical
    };

/// The web's "Designer Availability" meter: informational only, never blocks a choice.
class AvailabilityMeter extends StatelessWidget {
  const AvailabilityMeter({super.key, required this.availability});

  final Availability availability;

  @override
  Widget build(BuildContext context) {
    final a = availability;
    final color = availabilityColor(a.level);
    final style = Theme.of(context).textTheme.bodySmall;

    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Icon(Icons.circle, size: 10, color: color),
          const SizedBox(width: 6),
          Expanded(child: Text('Availability: ${a.label} (${a.percent}% free)', key: const Key('availability'), style: style?.copyWith(fontWeight: FontWeight.w700, color: color))),
        ]),
        const SizedBox(height: 4),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(value: a.percent / 100, minHeight: 6, color: color, backgroundColor: color.withValues(alpha: 0.15)),
        ),
        if (a.activeTasks != null || a.checkedIn != null)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(
              [
                if (a.activeTasks != null) '${a.activeTasks} active task${a.activeTasks == 1 ? '' : 's'}',
                if (a.activeCreatives != null) '${a.activeCreatives} creatives',
                if (a.checkedIn != null) a.checkedIn! ? 'Checked in' : 'Not checked in',
              ].join(' · '),
              key: const Key('availability-detail'),
              style: style?.copyWith(color: Colors.black54),
            ),
          ),
      ]),
    );
  }
}
