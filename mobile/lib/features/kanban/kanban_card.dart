import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/theme.dart';
import '../dashboard/dashboard_widgets.dart';
import 'kanban_models.dart';

String _dt(DateTime? d, String pattern) => d == null ? '—' : DateFormat(pattern).format(d);

String _headline(String? s) => (s ?? '—').replaceAll('_', ' ').split(' ').map((w) => w.isEmpty ? w : w[0].toUpperCase() + w.substring(1)).join(' ');

/// "3d 4h", "5h 12m", "12m" — how long a task has been with the BD.
String humanDuration(Duration d) {
  if (d.inMinutes < 1) return 'less than a minute';
  final days = d.inDays;
  final hours = d.inHours % 24;
  final minutes = d.inMinutes % 60;
  if (days > 0) return '${days}d ${hours}h';
  if (d.inHours > 0) return '${d.inHours}h ${minutes}m';
  return '${d.inMinutes}m';
}

Color dueColor(DuePill p) => switch (p) {
      DuePill.completed => const Color(0xFF2E7D32),
      DuePill.overdue => const Color(0xFFB00020),
      DuePill.dueToday => const Color(0xFFE65100),
      DuePill.dueSoon => const Color(0xFFB54708),
      DuePill.onTrack => const Color(0xFF027A48),
    };

Color tagColor(String tone) => switch (tone) {
      'approved' => const Color(0xFF067647),
      'declined' => const Color(0xFFB42318),
      _ => const Color(0xFF9A6700),
    };

class _Pill extends StatelessWidget {
  const _Pill(this.text, this.color, {super.key, this.tiny = false});

  final String text;
  final Color color;
  final bool tiny;

  @override
  Widget build(BuildContext context) => Container(
        padding: EdgeInsets.symmetric(horizontal: tiny ? 6 : 8, vertical: 3),
        decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20), border: Border.all(color: color.withValues(alpha: 0.25))),
        child: Text(text, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: color, fontSize: tiny ? 9 : 10, fontWeight: FontWeight.w800, letterSpacing: 0.2)),
      );
}

/// One ticket, kept to a few lines so a screen shows several of them. Everything else is on the task page.
class KanbanTaskCard extends StatelessWidget {
  const KanbanTaskCard({super.key, required this.card, required this.now, required this.onOpen, this.onMove, this.onLongPress, this.stageLabel});

  final KanbanCard card;
  final DateTime now;
  final VoidCallback onOpen;

  /// Opens the "Move to…" sheet: the way to change a status on a phone.
  final VoidCallback? onMove;

  /// The same actions from a long press.
  final VoidCallback? onLongPress;

  /// Set in the All Tickets list, where nothing else says which stage the ticket is in.
  final String? stageLabel;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final due = card.duePill(now);
    final accent = dueColor(due);
    const muted = TextStyle(fontSize: 12.5, color: Color(0xFF475467));

    final p = card.progress;
    final creatives = p == null ? '${card.totalCreatives} creative${card.totalCreatives == 1 ? '' : 's'}' : '${p.completed} / ${card.totalCreatives} creatives';

    Widget chip(IconData icon, String text, {Color? tint}) => Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(color: tint == null ? const Color(0xFFF2F4F7) : tint.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Icon(icon, size: 13, color: tint ?? const Color(0xFF475467)),
            const SizedBox(width: 5),
            Text(text, style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: tint ?? const Color(0xFF344054))),
          ]),
        );

    // The client sits under the title; the date, time, creatives and vertical are chips, so nothing is cut off.
    final chips = [
      if (card.status != 'completed' && card.dueAt != null) chip(Icons.calendar_today_rounded, _dt(card.dueAt, 'd MMM yyyy')),
      if (card.status != 'completed' && card.dueAt != null) chip(Icons.access_time_rounded, _dt(card.dueAt, 'h:mm a'), tint: due == DuePill.overdue || due == DuePill.dueToday ? accent : null),
      chip(Icons.layers_rounded, creatives),
      chip(Icons.sell_rounded, _headline(card.vertical)),
    ];

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [BoxShadow(color: accent.withValues(alpha: 0.10), blurRadius: 8, offset: const Offset(0, 6))],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(18),
        child: Material(
          color: Colors.white,
          child: InkWell(
            onTap: onOpen,
            onLongPress: onLongPress,
            child: Stack(children: [
              // a quiet wash of the due colour across the top, instead of a hard stripe down the side
              Positioned(top: 0, left: 0, right: 0, height: 64, child: DecoratedBox(decoration: BoxDecoration(gradient: LinearGradient(colors: [accent.withValues(alpha: 0.10), accent.withValues(alpha: 0.0)], begin: Alignment.topCenter, end: Alignment.bottomCenter)))),
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    Expanded(child: Text(card.taskId, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w800, color: theme.colorScheme.primary, letterSpacing: 0.2))),
                    _Pill(due.label, accent, tiny: true),
                    const SizedBox(width: 4),
                    _Pill(_headline(card.priority).toUpperCase(), priorityColor(card.priority), tiny: true),
                    if (onMove != null) ...[
                      const SizedBox(width: 6),
                      SizedBox(
                        width: 30,
                        height: 30,
                        child: IconButton(
                          key: Key('card-menu-${card.id}'),
                          tooltip: 'Move to…',
                          padding: EdgeInsets.zero,
                          style: IconButton.styleFrom(backgroundColor: const Color(0xFFF2F4F7)),
                          icon: const Icon(Icons.swap_horiz_rounded, size: 18, color: Color(0xFF344054)),
                          onPressed: onMove,
                        ),
                      ),
                    ],
                  ]),
                  if (stageLabel != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Align(alignment: Alignment.centerLeft, child: _Pill('Stage: $stageLabel', statusColor(card.status), key: Key('stage-${card.id}'), tiny: true)),
                    ),
                  const SizedBox(height: 8),
                  Text(card.name, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, letterSpacing: -0.3, height: 1.15), maxLines: 2, overflow: TextOverflow.ellipsis),
                  const SizedBox(height: 4),
                  Row(children: [
                    const Icon(Icons.business_center_rounded, size: 14, color: Color(0xFF98A2B3)),
                    const SizedBox(width: 6),
                    Expanded(child: Text('${_headline(card.partyType)} · ${card.partyName ?? '—'}', style: muted, maxLines: 1, overflow: TextOverflow.ellipsis)),
                  ]),
                  const SizedBox(height: 8),
                  Wrap(spacing: 6, runSpacing: 6, children: chips),
                  if (card.assigner != null || card.nowWith != null) ...[
                    const SizedBox(height: 8),
                    if (card.assigner != null) Text('Assigned by: ${card.assigner}', style: const TextStyle(fontSize: 11.5, color: Color(0xFF667085)), maxLines: 1, overflow: TextOverflow.ellipsis),
                    if (card.nowWith != null) Text('Now with: ${card.nowWith}', style: const TextStyle(fontSize: 11.5, color: Color(0xFF667085))),
                  ],
                  if (card.tag != null || card.continuationLabel != null || card.previousMonthLabel != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Wrap(spacing: 4, runSpacing: 4, children: [
                        if (card.tag != null) Tooltip(message: card.tag!.title, child: _Pill(card.tag!.label, tagColor(card.tag!.tone), tiny: true)),
                        if (card.continuationLabel != null)
                          _Pill('${card.continuationLabel}${card.continuationEvent != null ? ' · ${card.continuationEvent}' : ''}', const Color(0xFF175CD3), tiny: true),
                        if (card.previousMonthLabel != null) _Pill(card.previousMonthLabel!, const Color(0xFF6941C6), tiny: true),
                      ]),
                    ),
                  if (card.progress != null) _Progress(card, now),
                  if (card.status == 'completed') _CompletedLine(card),
                ]),
              ),
            ]),
          ),
        ),
      ),
    );
  }
}

class _Progress extends StatelessWidget {
  const _Progress(this.card, this.now);

  final KanbanCard card;
  final DateTime now;

  @override
  Widget build(BuildContext context) {
    final p = card.progress!;
    final bar = p.percentage >= 100 ? const Color(0xFF2E7D32) : (p.percentage <= 0 ? const Color(0xFF98A2B3) : const Color(0xFFD92D20));
    final caption = card.status == 'rework' ? 'Rework active' : (card.status == 'waiting_confirmation' ? 'Awaiting BD' : 'In progress');
    const small = TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Color(0xFF667085));

    return Padding(
      key: Key('progress-${card.id}'),
      padding: const EdgeInsets.only(top: 6, right: 6),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: LinearProgressIndicator(value: (p.percentage / 100).clamp(0, 1), minHeight: 6, borderRadius: BorderRadius.circular(6), color: bar, backgroundColor: const Color(0xFFE9EDF3))),
          const SizedBox(width: 8),
          Text('${p.percentage}%', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900)),
        ]),
        const SizedBox(height: 3),
        Text(
          card.status == 'waiting_confirmation' && card.waitingSince != null ? '$caption · BD Review Time: ${humanDuration(now.difference(card.waitingSince!))}' : caption,
          style: small,
        ),
      ]),
    );
  }
}

/// A finished ticket: its rating and completion date on one line.
class _CompletedLine extends StatelessWidget {
  const _CompletedLine(this.card);

  final KanbanCard card;

  @override
  Widget build(BuildContext context) {
    final rating = card.rating;
    final shown = rating == null ? null : (rating == rating.roundToDouble() ? rating.toStringAsFixed(0) : rating.toStringAsFixed(1));

    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Row(children: [
        if (rating != null) ...[
          HalfStars(rating, size: 14),
          const SizedBox(width: 6),
          Text('$shown / 5', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900)),
          const SizedBox(width: 10),
        ],
        if (card.completedAt != null)
          Flexible(child: Text('Completed ${_dt(card.completedAt, 'd MMM yyyy')}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Color(0xFF667085)))),
      ]),
    );
  }
}

Color splitStatusColor(String key) => switch (key) {
      'approved' => const Color(0xFF067647),
      'rejected' => const Color(0xFFB42318),
      'completed_all' => const Color(0xFF175CD3),
      _ => const Color(0xFF9A6700), // pending
    };

/// A Split Tasks entry with its Status, as on the web board.
class SplitLogCard extends StatelessWidget {
  const SplitLogCard({super.key, required this.entry, required this.onOpen});

  final SplitEntry entry;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    Widget meta(String k, String v) => SizedBox(
          width: 150,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(k, style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: Colors.black45)),
            Text(v, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600), maxLines: 2, overflow: TextOverflow.ellipsis),
          ]),
        );

    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onOpen,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Expanded(child: Text(entry.taskId, style: theme.textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w800, color: Colors.black54))),
              const _Pill('SPLIT', Color(0xFF7C3AED)),
            ]),
            const SizedBox(height: 4),
            Text(entry.name, style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700), maxLines: 3, overflow: TextOverflow.ellipsis),
            const SizedBox(height: 6),
            _Pill('Status: ${entry.statusLabel}', splitStatusColor(entry.statusKey), key: Key('split-status-${entry.id}')),
            const SizedBox(height: 8),
            Wrap(spacing: 8, runSpacing: 6, children: [
              if (entry.hasChild) ...[
                meta('SPLIT FROM', entry.splitFrom ?? '—'),
                meta('DESIGNER', entry.designer ?? '—'),
                meta('CREATIVES', '${entry.creatives}'),
                meta('APPROVED', _dt(entry.approvedAt, 'd MMM yyyy')),
              ] else ...[
                meta('REQUESTED SPLIT', entry.requestedSplit == null ? '—' : '${entry.requestedSplit}'),
                meta('CREATIVES', '${entry.creatives}'),
                meta('REQUESTED', _dt(entry.requestedAt, 'd MMM yyyy')),
              ],
            ]),
            const SizedBox(height: 6),
            Text('Open task', style: TextStyle(color: theme.colorScheme.primary, fontWeight: FontWeight.w700, fontSize: 12)),
          ]),
        ),
      ),
    );
  }
}
