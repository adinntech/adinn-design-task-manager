import 'package:flutter/material.dart';

import '../../../core/theme.dart';

/// Key of the "All Tickets" pseudo stage (every ticket in one list).
const allStageKey = 'all';

/// One stage on the board: its server key, web name and how many tickets are in it right now.
class StageInfo {
  const StageInfo({required this.key, required this.label, required this.count});

  final String key;
  final String label;
  final int count;
}

/// Which stages go in the fan of cards, in order, when there are more with tickets than there is room for.
const fanPriority = [
  'in_progress',
  'waiting_confirmation',
  'rework',
  'assigned_tasks',
  'yet_to_start',
  'prepare_printing_file',
  'review_analysis',
  'need_clarification',
  'pending_bd_approval',
  'completed',
  'bd_rejected',
  'split_log',
  'swap_tasks',
  'self_declined',
];

IconData stageIcon(String key) => switch (key) {
      allStageKey => Icons.layers,
      'pending_bd_approval' => Icons.hourglass_empty,
      'bd_rejected' => Icons.block,
      'assigned_tasks' => Icons.layers_outlined,
      'review_analysis' => Icons.fact_check_outlined,
      'need_clarification' => Icons.help_outline,
      'yet_to_start' => Icons.play_arrow_rounded,
      'in_progress' => Icons.laptop_mac,
      'waiting_confirmation' => Icons.groups_outlined,
      'rework' => Icons.autorenew,
      'prepare_printing_file' => Icons.print_outlined,
      'completed' => Icons.check_circle_outline,
      'split_log' => Icons.call_split,
      'swap_tasks' => Icons.swap_horiz,
      'self_declined' => Icons.person_off_outlined,
      _ => Icons.circle_outlined,
    };

/// A colour per stage (the All Tickets card and the brand stay Adinn red).
Color stageAccent(String key) => switch (key) {
      'pending_bd_approval' => const Color(0xFFB54708),
      'bd_rejected' => const Color(0xFFB42318),
      'assigned_tasks' => const Color(0xFF175CD3),
      'review_analysis' => const Color(0xFF6941C6),
      'need_clarification' => const Color(0xFFC4320A),
      'yet_to_start' => const Color(0xFF0E9384),
      'in_progress' => const Color(0xFF1A91D6),
      'waiting_confirmation' => const Color(0xFF7C3AED),
      'rework' => const Color(0xFFF57C00),
      'prepare_printing_file' => const Color(0xFF475467),
      'completed' => const Color(0xFF2E9E3E),
      'split_log' => const Color(0xFF7C3AED),
      'swap_tasks' => const Color(0xFF0086C9),
      'self_declined' => const Color(0xFF667085),
      _ => adinnRed,
    };
