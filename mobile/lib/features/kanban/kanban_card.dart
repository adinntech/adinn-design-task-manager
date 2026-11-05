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

/// One card, with the same content as the web board card.
class KanbanTaskCard extends StatelessWidget {
  const KanbanTaskCard({super.key, required this.card, required this.now, required this.onOpen, this.onMove, this.stageLabel});

  final KanbanCard card;
  final DateTime now;
  final VoidCallback onOpen;

  /// Opens the "Move to…" sheet. The main way to change a status on a phone; dragging is the extra.
  final VoidCallback? onMove;

  /// Set in the All Tickets list, where nothing else says which stage the ticket is in.
  final String? stageLabel;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final due = card.duePill(now);
    final accent = dueColor(due);

    Widget meta(String k, String v) => SizedBox(
          width: 150,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(k, style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: Colors.black45)),
            Text(v, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600), maxLines: 2, overflow: TextOverflow.ellipsis),
          ]),
        );

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onOpen,
        child: Container(
          decoration: BoxDecoration(border: Border(left: BorderSide(color: accent, width: 4))),
          padding: const EdgeInsets.fromLTRB(10, 10, 6, 10),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Expanded(child: Text(card.taskId, style: theme.textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w800, color: Colors.black54))),
              _Pill(due.label, accent),
              const SizedBox(width: 4),
              _Pill(_headline(card.priority).toUpperCase(), priorityColor(card.priority)),
              const SizedBox(width: 4),
            ]),
            if (stageLabel != null)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: _Pill('Stage: $stageLabel', statusColor(card.status), key: Key('stage-${card.id}')),
              ),
            const SizedBox(height: 4),
            Padding(
              padding: const EdgeInsets.only(right: 4),
              child: Text(card.name, style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700), maxLines: 3, overflow: TextOverflow.ellipsis),
            ),
            if (card.tag != null)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Tooltip(message: card.tag!.title, child: _Pill(card.tag!.label, tagColor(card.tag!.tone), tiny: true)),
              ),
            const SizedBox(height: 4),
            Text('${_headline(card.partyType)} · ${card.partyName ?? '—'}', style: theme.textTheme.bodySmall?.copyWith(color: Colors.black54)),
            if (card.nowWith != null) Text('Now with: ${card.nowWith}', style: theme.textTheme.bodySmall?.copyWith(color: Colors.black54)),
            if (card.continuationLabel != null)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: _Pill('${card.continuationLabel}${card.continuationEvent != null ? ' · ${card.continuationEvent}' : ''}', const Color(0xFF175CD3), tiny: true),
              ),
            if (card.previousMonthLabel != null)
              Padding(padding: const EdgeInsets.only(top: 4), child: _Pill(card.previousMonthLabel!, const Color(0xFF6941C6), tiny: true)),
            if (card.progress != null) _Progress(card, now),
            if (card.status == 'completed' && card.rating != null) _Rating(card.rating!),
            const SizedBox(height: 8),
            Wrap(spacing: 8, runSpacing: 6, children: [
              meta('VERTICAL', _headline(card.vertical)),
              meta('CREATIVES', '${card.totalCreatives}'),
              meta('DUE', _dt(card.dueAt, 'd MMM, h:mm a')),
              meta('ASSIGNED BY', card.assigner ?? 'BD'),
              meta('CREATED', _dt(card.createdAt, 'd MMM yyyy • h:mm a')),
              if (card.status == 'completed' && card.completedAt != null) meta('COMPLETED', _dt(card.completedAt, 'd MMM yyyy • h:mm a')),
            ]),
            if (onMove != null)
              Padding(
                padding: const EdgeInsets.only(top: 10, right: 4),
                child: SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(key: Key('card-menu-${card.id}'), onPressed: onMove, icon: const Icon(Icons.swap_horiz, size: 18), label: const Text('Move to…')),
                ),
              ),
          ]),
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
    final tint = switch (card.status) {
      'rework' => const Color(0xFFFFF7F7),
      'waiting_confirmation' => const Color(0xFFF5F3FF),
      _ => const Color(0xFFF8FAFC),
    };
    final bar = p.percentage >= 100 ? const Color(0xFF2E7D32) : (p.percentage <= 0 ? const Color(0xFF98A2B3) : const Color(0xFFD92D20));
    final caption = card.status == 'rework' ? 'Rework active' : (card.status == 'waiting_confirmation' ? 'Awaiting BD' : 'In progress');

    return Container(
      key: Key('progress-${card.id}'),
      margin: const EdgeInsets.only(top: 10, right: 4),
      padding: const EdgeInsets.all(9),
      decoration: BoxDecoration(color: tint, borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFE5E7EB))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          const Expanded(child: Text('CREATIVE PROGRESS', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w900, letterSpacing: 0.5, color: Color(0xFF667085)))),
          Text('${p.percentage}%', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900)),
        ]),
        const SizedBox(height: 6),
        LinearProgressIndicator(value: (p.percentage / 100).clamp(0, 1), minHeight: 7, borderRadius: BorderRadius.circular(8), color: bar, backgroundColor: const Color(0xFFE9EDF3)),
        const SizedBox(height: 5),
        Text(caption, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFF98A2B3))),
        if (card.status == 'waiting_confirmation' && card.waitingSince != null)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text('BD Review Time: ${humanDuration(now.difference(card.waitingSince!))}', style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFF98A2B3))),
          ),
      ]),
    );
  }
}

class _Rating extends StatelessWidget {
  const _Rating(this.rating);

  final double rating;

  @override
  Widget build(BuildContext context) {
    final shown = rating == rating.roundToDouble() ? rating.toStringAsFixed(0) : rating.toStringAsFixed(1);

    return Container(
      margin: const EdgeInsets.only(top: 10, right: 4),
      padding: const EdgeInsets.all(9),
      decoration: BoxDecoration(color: const Color(0xFFFFFAEB), borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFFEDF89))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          const Expanded(child: Text('Overall Rating', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w900, color: Color(0xFF93370D)))),
          Text('$shown / 5', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900)),
        ]),
        const SizedBox(height: 4),
        HalfStars(rating, size: 17),
      ]),
    );
  }
}

/// A Split Tasks log entry: the child task created by an approved split.
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
            const SizedBox(height: 8),
            Wrap(spacing: 8, runSpacing: 6, children: [
              meta('SPLIT FROM', entry.splitFrom ?? '—'),
              meta('DESIGNER', entry.designer ?? '—'),
              meta('CREATIVES', '${entry.creatives}'),
              meta('APPROVED', _dt(entry.approvedAt, 'd MMM yyyy')),
            ]),
            const SizedBox(height: 6),
            Text('Open task', style: TextStyle(color: theme.colorScheme.primary, fontWeight: FontWeight.w700, fontSize: 12)),
          ]),
        ),
      ),
    );
  }
}
