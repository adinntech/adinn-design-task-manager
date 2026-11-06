import 'package:flutter/material.dart';

import 'stage3d/stage_meta.dart';

/// Short names for the tiles, as in the design.
const _tileNames = {
  'pending_bd_approval': 'Awaiting BD',
  'bd_rejected': 'Rejected',
  'assigned_tasks': 'New',
  'review_analysis': 'Review',
  'need_clarification': 'Clarify',
  'yet_to_start': 'Start',
  'in_progress': 'Progress',
  'waiting_confirmation': 'BD Review',
  'rework': 'Rework',
  'prepare_printing_file': 'Print File',
  'completed': 'Completed',
  'split_log': 'Split',
  'swap_tasks': 'Swapped',
  'self_declined': 'Declined',
};

/// One small tile per workflow stage, empty ones included: icon, short name and count. They sit straight on the page
/// (no sheet behind them). The tile of the stage whose card the man is holding is outlined.
class StageTiles extends StatelessWidget {
  const StageTiles({super.key, required this.stages, required this.onTap, this.selectedKey, this.columns = 5});

  final List<StageInfo> stages;
  final ValueChanged<StageInfo> onTap;
  final String? selectedKey;
  final int columns;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: (context, box) {
      const gap = 7.0;
      final tileW = (box.maxWidth - gap * (columns - 1)) / columns;

      return Wrap(key: const Key('stage-tiles'), spacing: gap, runSpacing: gap, alignment: WrapAlignment.center, children: [
        for (final s in stages)
          SizedBox(
            width: tileW,
            child: _Tile(stage: s, selected: s.key == selectedKey, onTap: () => onTap(s)),
          ),
      ]);
    });
  }
}

class _Tile extends StatelessWidget {
  const _Tile({required this.stage, required this.selected, required this.onTap});

  final StageInfo stage;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final accent = stageAccent(stage.key);
    final empty = stage.count == 0;

    return GestureDetector(
      key: Key('tile-${stage.key}'),
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(vertical: 7),
        decoration: BoxDecoration(
          color: accent.withValues(alpha: empty ? 0.07 : 0.14),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: selected ? accent : accent.withValues(alpha: 0.25), width: selected ? 2.2 : 1),
          boxShadow: selected ? [BoxShadow(color: accent.withValues(alpha: 0.35), blurRadius: 10, offset: const Offset(0, 3))] : null,
        ),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(
            width: 24,
            height: 24,
            decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.85), borderRadius: BorderRadius.circular(8)),
            child: Icon(stageIcon(stage.key), size: 15, color: empty ? accent.withValues(alpha: 0.5) : accent),
          ),
          const SizedBox(height: 3),
          Text(_tileNames[stage.key] ?? stage.label, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 9.5, fontWeight: FontWeight.w700, color: empty ? Colors.black45 : const Color(0xFF111111))),
          Text('${stage.count}', key: Key('tile-count-${stage.key}'), style: TextStyle(fontSize: 17, height: 1.1, fontWeight: FontWeight.w900, color: empty ? Colors.black38 : const Color(0xFF111111))),
        ]),
      ),
    );
  }
}
