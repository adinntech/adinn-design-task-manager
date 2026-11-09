import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../home/navigation.dart';
import '../tasks/providers.dart';
import 'bd_dashboard_models.dart';
import 'dashboard_models.dart';
import 'dashboard_widgets.dart';
import 'designer_dashboard_screen.dart';

/// The month / designer the BD dashboard is scoped to (null month = the current month, as on the web).
final bdDashFilterProvider = NotifierProvider<BdDashFilter, ({String? month, String designer})>(BdDashFilter.new);

class BdDashFilter extends Notifier<({String? month, String designer})> {
  @override
  ({String? month, String designer}) build() => (month: null, designer: 'all');

  void setMonth(String month) => state = (month: month, designer: state.designer);

  void setDesigner(String designer) => state = (month: state.month, designer: designer);
}

final bdDashboardProvider = FutureProvider.autoDispose<BdDashboard>((ref) {
  final f = ref.watch(bdDashFilterProvider);
  return ref.watch(roleApiProvider).bdDashboard(month: f.month, designer: f.designer);
});

final bdDashboardSearchProvider = FutureProvider.autoDispose.family<List<DashRow>, String>((ref, search) {
  final f = ref.watch(bdDashFilterProvider);
  return ref.watch(roleApiProvider).bdDashboardTasks(search, month: f.month, designer: f.designer);
});

void _openBoard(WidgetRef ref, String focus) {
  ref.read(boardFocusProvider.notifier).focus(focus);
  ref.read(homeTabProvider.notifier).goTo(HomeTab.tasks);
}

String _fmt(double v) => v == v.roundToDouble() ? v.toStringAsFixed(0) : v.toStringAsFixed(1);

/// BD home: the same sections, in the same order, as the web "BD Dashboard", laid out for a phone.
class BdDashboardScreen extends ConsumerWidget {
  const BdDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(bdDashboardProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('BD Dashboard')),
      floatingActionButton: FloatingActionButton.extended(
        key: const Key('new-task'),
        onPressed: () => context.push('/create'),
        icon: const Icon(Icons.add),
        label: const Text('New task'),
      ),
      body: data.when(
        skipLoadingOnReload: true,
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Text('$e', textAlign: TextAlign.center),
              const SizedBox(height: 12),
              OutlinedButton(onPressed: () => ref.invalidate(bdDashboardProvider), child: const Text('Try again')),
            ]),
          ),
        ),
        data: (d) => RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(bdDashboardProvider);
            await ref.read(bdDashboardProvider.future);
          },
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 90),
            children: [
              _Hero(d),
              const SizedBox(height: 14),
              _PrimaryStats(d),
              const SizedBox(height: 14),
              _SecondaryStats(d),
              const SizedBox(height: 14),
              _CompletionRate(d),
              const SizedBox(height: 14),
              _ExtraStats(d),
              const SizedBox(height: 14),
              _DesignerAnalytics(d),
              const SizedBox(height: 14),
              _Tickets(d),
              const SizedBox(height: 14),
              _MonthlyAnalytics(d),
              const SizedBox(height: 14),
              PerformanceTrend(contextLabel: d.trendContext, cards: d.trendCards, months: d.trendLine, declinedStage: 'decline_tasks'),
              const SizedBox(height: 14),
              _OverdueTasks(d),
              const SizedBox(height: 14),
              _ReworkAnalytics(d),
              const SizedBox(height: 14),
              _Ratings(d),
              const SizedBox(height: 14),
              _Completions(d),
            ],
          ),
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------------------------
// Hero + month / designer scope
// ---------------------------------------------------------------------------------------------

class _Hero extends ConsumerWidget {
  const _Hero(this.d);

  final BdDashboard d;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);

    Widget scope<T>({required Key key, required IconData icon, required String text, required List<PopupMenuEntry<String>> Function(BuildContext) items, required ValueChanged<String> onSelected}) {
      return PopupMenuButton<String>(
        key: key,
        onSelected: onSelected,
        itemBuilder: items,
        child: Container(
          padding: const EdgeInsets.fromLTRB(10, 6, 6, 6),
          decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(20), border: Border.all(color: Colors.white.withValues(alpha: 0.35))),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Icon(icon, size: 14, color: Colors.white),
            const SizedBox(width: 5),
            Flexible(child: Text(text, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700))),
            const Icon(Icons.keyboard_arrow_down_rounded, size: 17, color: Colors.white),
          ]),
        ),
      );
    }

    return Container(
      key: const Key('dash-hero'),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        gradient: const LinearGradient(colors: [Color(0xFFE30613), Color(0xFF9E030D)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        boxShadow: [BoxShadow(color: const Color(0xFFE30613).withValues(alpha: 0.28), blurRadius: 16, offset: const Offset(0, 7))],
      ),
      child: Stack(children: [
        Positioned(top: -34, right: -26, child: Container(width: 112, height: 112, decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withValues(alpha: 0.08)))),
        Positioned(bottom: -40, right: 70, child: Container(width: 84, height: 84, decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withValues(alpha: 0.06)))),
        Positioned(top: 12, right: 12, child: Container(width: 34, height: 34, decoration: BoxDecoration(borderRadius: BorderRadius.circular(11), color: Colors.white.withValues(alpha: 0.16)), child: const Icon(Icons.business_center_rounded, color: Colors.white, size: 19))),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Padding(padding: const EdgeInsets.only(right: 44), child: Text('BD DASHBOARD', style: theme.textTheme.labelSmall?.copyWith(color: Colors.white70, letterSpacing: 1, fontWeight: FontWeight.w700, fontSize: 10))),
            const SizedBox(height: 3),
            Padding(
              padding: const EdgeInsets.only(right: 44),
              child: Text('Welcome back, ${d.name}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, letterSpacing: -0.3, fontSize: 18, height: 1.15)),
            ),
            const SizedBox(height: 3),
            const Text('Your tickets, designers and reviews in one place.', style: TextStyle(color: Colors.white70, fontSize: 12)),
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [
              scope<String>(
                key: const Key('filter-month'),
                icon: Icons.calendar_month_rounded,
                text: d.monthLabel,
                items: (_) => [for (final m in d.months.reversed) PopupMenuItem(value: m.value, child: Text(m.label))],
                onSelected: (v) => ref.read(bdDashFilterProvider.notifier).setMonth(v),
              ),
              scope<String>(
                key: const Key('filter-designer'),
                icon: Icons.person_rounded,
                text: d.designerName ?? 'All Designers',
                items: (_) => [
                  const PopupMenuItem(value: 'all', child: Text('All Designers')),
                  for (final x in d.designers) PopupMenuItem(value: x.value, child: Text(x.label)),
                ],
                onSelected: (v) => ref.read(bdDashFilterProvider.notifier).setDesigner(v),
              ),
            ]),
            const SizedBox(height: 10),
            Wrap(alignment: WrapAlignment.spaceBetween, crossAxisAlignment: WrapCrossAlignment.center, spacing: 10, runSpacing: 8, children: [
              ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 190),
                child: Text(
                  d.lastLogin == null ? 'This is your first login' : 'Last Login: ${dashDt(d.lastLogin, 'd MMM yyyy • h:mm a')}',
                  key: const Key('last-login'),
                  maxLines: 2,
                  style: const TextStyle(color: Colors.white, fontSize: 11),
                ),
              ),
              FilledButton.icon(
                key: const Key('hero-view-tasks'),
                icon: const Icon(Icons.arrow_forward_rounded, size: 15),
                iconAlignment: IconAlignment.end,
                style: FilledButton.styleFrom(
                  backgroundColor: Colors.white,
                  foregroundColor: const Color(0xFFB5040F),
                  minimumSize: const Size(0, 34),
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  textStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
                onPressed: () => _openBoard(ref, ''),
                label: const Text('View All Tasks'),
              ),
            ]),
          ]),
        ),
      ]),
    );
  }
}

// ---------------------------------------------------------------------------------------------
// KPI tiles
// ---------------------------------------------------------------------------------------------

const _blue = Color(0xFF2970FF);
const _orange = Color(0xFFE65100);
const _green = Color(0xFF12B76A);
const _purple = Color(0xFF7C3AED);
const _teal = Color(0xFF0E9384);
const _amber = Color(0xFFF79009);
const _pink = Color(0xFFE11D48);
const _red = Color(0xFFD92D20);

class _PrimaryStats extends ConsumerWidget {
  const _PrimaryStats(this.d);

  final BdDashboard d;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    Widget tile(String id, String label, String key, String hint, IconData icon, Color accent, String? focus) => StatTile(
          key: Key('tile-$id'),
          label: label,
          value: '${d.stat(key)}',
          hint: hint,
          icon: icon,
          accent: accent,
          onTap: focus == null ? null : () => _openBoard(ref, focus),
        );

    return StatGrid(tiles: [
      StatTile(key: const Key('tile-total'), label: 'Assigned Tasks', value: '${d.stat('total_tasks')}', icon: Icons.layers_rounded, hint: d.scope),
      tile('pending', 'Pending', 'pending', 'Not yet started', Icons.inbox_rounded, _orange, 'assigned_tasks'),
      tile('ready', 'Ready to Start', 'ready_to_start', 'Designer can begin', Icons.play_arrow_rounded, _green, 'yet_to_start'),
      tile('in-progress', 'In Progress', 'in_progress', 'Being worked on now', Icons.autorenew_rounded, _purple, 'in_progress'),
      tile('waiting', 'Waiting for BD Review', 'waiting', 'Submitted, awaiting you', Icons.hourglass_top_rounded, _blue, 'waiting_confirmation'),
      tile('completed', 'Completed', 'completed', 'Finished and closed', Icons.check_circle_rounded, _teal, 'completed'),
    ]);
  }
}

class _SecondaryStats extends ConsumerWidget {
  const _SecondaryStats(this.d);

  final BdDashboard d;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    Widget tile(String id, String label, String key, String hint, IconData icon, Color accent, String? focus) => StatTile(
          key: Key('tile-$id'),
          label: label,
          value: '${d.stat(key)}',
          hint: hint,
          icon: icon,
          accent: accent,
          onTap: focus == null ? null : () => _openBoard(ref, focus),
        );

    return StatGrid(tiles: [
      tile('confirm', 'Waiting for Your Confirmation', 'pending_bd_approval', 'New tickets created by Designers', Icons.verified_user_rounded, _blue, 'pending_bd_approval'),
      tile('clarify', 'Clarification Needed', 'clarification_needed', 'Designer is waiting for your answer', Icons.help_rounded, _amber, 'need_clarification'),
      tile('rework', 'Rework Tasks', 'rework_tasks', 'Sent back, in rework now', Icons.replay_rounded, _pink, 'rework'),
      tile('printing', 'Prepare Printing File', 'prepare_printing_file', 'Moved by you for printing', Icons.print_rounded, _purple, 'prepare_printing_file'),
      tile('overdue', 'Overdue', 'overdue', 'Past deadline, not completed', Icons.warning_amber_rounded, _red, null),
    ]);
  }
}

class _CompletionRate extends StatelessWidget {
  const _CompletionRate(this.d);

  final BdDashboard d;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18), side: const BorderSide(color: Color(0xFFEAECF0))),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            const IconBadge(Icons.task_alt_rounded, color: Color(0xFF2E7D32), size: 34),
            const SizedBox(width: 10),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Completion Rate', style: TextStyle(fontWeight: FontWeight.w800)),
                Text('${d.stat('completed')} of ${d.stat('total_tasks')} tasks', style: const TextStyle(fontSize: 11, color: Color(0xFF667085))),
              ]),
            ),
            Text('${d.completionRate}%', key: const Key('completion-rate'), style: const TextStyle(fontWeight: FontWeight.w800)),
          ]),
          const SizedBox(height: 8),
          LinearProgressIndicator(value: d.completionRate / 100, minHeight: 8, borderRadius: BorderRadius.circular(8), color: const Color(0xFF2E7D32), backgroundColor: const Color(0xFFE4E7EC)),
        ]),
      ),
    );
  }
}

class _ExtraStats extends ConsumerWidget {
  const _ExtraStats(this.d);

  final BdDashboard d;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return StatGrid(tiles: [
      StatTile(key: const Key('tile-declined'), label: 'Declined', value: '${d.stat('declined')}', icon: Icons.block_rounded, accent: _red, hint: 'Approved declines', onTap: () => _openBoard(ref, 'decline_tasks')),
      StatTile(key: const Key('tile-split'), label: 'Split', value: '${d.stat('split')}', icon: Icons.call_split_rounded, accent: _blue, hint: 'Approved splits', onTap: () => _openBoard(ref, 'split_log')),
      StatTile(key: const Key('tile-swapped'), label: 'Swapped', value: '${d.stat('swapped')}', icon: Icons.swap_horiz_rounded, accent: _blue, hint: 'Approved transfers', onTap: () => _openBoard(ref, 'swap_tasks')),
      StatTile(key: const Key('tile-designers'), label: 'Designers Assigned', value: '${d.stat('total_designers')}', icon: Icons.groups_rounded, accent: _teal, hint: 'All-time unique Designers on your tickets'),
    ]);
  }
}

// ---------------------------------------------------------------------------------------------
// Designer Analytics
// ---------------------------------------------------------------------------------------------

class _DesignerAnalytics extends ConsumerWidget {
  const _DesignerAnalytics(this.d);

  final BdDashboard d;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final primary = Theme.of(context).colorScheme.primary;

    Widget cell(String label, int v, {Color? color}) => Expanded(
          child: Column(children: [
            Text('$v', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: v > 0 ? color : const Color(0xFF98A2B3))),
            const SizedBox(height: 1),
            Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 9.5, color: Color(0xFF667085), fontWeight: FontWeight.w600)),
          ]),
        );

    return SectionCard(
      title: 'Designer Analytics',
      icon: Icons.groups_rounded,
      subtitle: 'Tap a Designer to filter the dashboard',
      child: d.workload.isEmpty
          ? const Padding(padding: EdgeInsets.symmetric(vertical: 16), child: Center(child: Text('No Designers found for your tickets in this period.')))
          : Column(children: [
              for (final w in d.workload)
                Builder(builder: (context) {
                  final selected = d.selectedDesigner == '${w.id}';
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: InkWell(
                      key: Key('designer-${w.id}'),
                      borderRadius: BorderRadius.circular(14),
                      onTap: () => ref.read(bdDashFilterProvider.notifier).setDesigner(selected ? 'all' : '${w.id}'),
                      child: Container(
                        padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
                        decoration: BoxDecoration(
                          color: selected ? primary.withValues(alpha: 0.06) : const Color(0xFFF9FAFB),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: selected ? primary : const Color(0xFFEAECF0)),
                        ),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Row(children: [
                            CircleAvatar(radius: 14, backgroundColor: primary.withValues(alpha: 0.12), child: Text(w.name.isEmpty ? '?' : w.name[0].toUpperCase(), style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: primary))),
                            const SizedBox(width: 10),
                            Expanded(child: Text(w.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14))),
                            if (selected) const StatusPill('viewing', Color(0xFF2E7D32)),
                            if (w.rating != null) ...[
                              const SizedBox(width: 6),
                              const Icon(Icons.star_rounded, size: 16, color: Color(0xFFF5A623)),
                              Text(_fmt(w.rating!), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12)),
                            ],
                          ]),
                          const SizedBox(height: 10),
                          Row(children: [
                            cell('Assigned', w['assigned']),
                            cell('Pending', w['pending']),
                            cell('Ready', w['ready_to_start']),
                            cell('Progress', w['in_progress']),
                            cell('Review', w['waiting']),
                            cell('Done', w['completed'], color: const Color(0xFF2E7D32)),
                          ]),
                          const SizedBox(height: 8),
                          Row(children: [
                            cell('Overdue', w['overdue'], color: _red),
                            cell('Rework', w['rework_count'], color: _pink),
                            cell('Rw. Creatives', w['rework_creatives'], color: _pink),
                            cell('Split', w['split']),
                            cell('Swap', w['swap']),
                            cell('Decline', w['decline']),
                          ]),
                        ]),
                      ),
                    ),
                  );
                }),
            ]),
    );
  }
}

// ---------------------------------------------------------------------------------------------
// BD Tickets
// ---------------------------------------------------------------------------------------------

class _Tickets extends ConsumerStatefulWidget {
  const _Tickets(this.d);

  final BdDashboard d;

  @override
  ConsumerState<_Tickets> createState() => _TicketsState();
}

class _TicketsState extends ConsumerState<_Tickets> {
  static const _maxRows = 5;

  final _search = TextEditingController();
  Timer? _debounce;
  String _query = '';

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  void _onSearch(String v) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (mounted) setState(() => _query = v.trim());
    });
  }

  @override
  Widget build(BuildContext context) {
    final searching = _query.isNotEmpty;
    final source = searching ? ref.watch(bdDashboardSearchProvider(_query)) : AsyncData(widget.d.recent);

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Expanded(
          child: Row(children: [
            Text('BD Tickets', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800, letterSpacing: -0.3)),
            const SizedBox(width: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 1),
              decoration: BoxDecoration(color: const Color(0xFFFEE4E2), borderRadius: BorderRadius.circular(20)),
              child: Text('${widget.d.stat('total_tasks')}', key: const Key('overview-total'), style: const TextStyle(color: Color(0xFFD92D20), fontWeight: FontWeight.w800, fontSize: 12)),
            ),
          ]),
        ),
        TextButton(
          key: const Key('overview-view-all'),
          style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 8), minimumSize: const Size(0, 32), tapTargetSize: MaterialTapTargetSize.shrinkWrap),
          onPressed: () => _openBoard(ref, ''),
          child: const Text('View All'),
        ),
      ]),
      Text('Your tickets assigned in ${widget.d.scope}', style: const TextStyle(fontSize: 11.5, color: Color(0xFF667085))),
      const SizedBox(height: 8),
      Container(
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10, offset: const Offset(0, 3))]),
        child: TextField(
          key: const Key('overview-search'),
          controller: _search,
          onChanged: _onSearch,
          decoration: const InputDecoration(
            hintText: 'Search by Task ID, Project Name...',
            hintStyle: TextStyle(fontSize: 14),
            prefixIcon: Icon(Icons.search_rounded, size: 22),
            isDense: true,
            border: InputBorder.none,
            enabledBorder: InputBorder.none,
            focusedBorder: InputBorder.none,
            filled: false,
            contentPadding: EdgeInsets.symmetric(vertical: 12),
          ),
        ),
      ),
      const SizedBox(height: 10),
      source.when(
        loading: () => const Padding(padding: EdgeInsets.all(24), child: Center(child: CircularProgressIndicator())),
        error: (e, _) => Padding(padding: const EdgeInsets.all(12), child: Text('$e')),
        data: (rows) => rows.isEmpty
            ? Padding(padding: const EdgeInsets.symmetric(vertical: 20), child: Center(child: Text(searching ? 'No matching tasks found.' : 'No tickets found for this period.', key: const Key('overview-empty'))))
            : Column(children: [
                for (final r in rows.take(_maxRows)) OverviewRow(r),
                if (rows.length > _maxRows)
                  Center(
                    child: TextButton.icon(
                      key: const Key('overview-more'),
                      onPressed: () => _openBoard(ref, ''),
                      icon: const Icon(Icons.arrow_forward_rounded, size: 18),
                      iconAlignment: IconAlignment.end,
                      label: Text('${rows.length - _maxRows} more · View all tasks'),
                    ),
                  ),
              ]),
      ),
    ]);
  }
}

// ---------------------------------------------------------------------------------------------
// Monthly analytics: bars + status mix donut
// ---------------------------------------------------------------------------------------------

class _MonthlyAnalytics extends ConsumerWidget {
  const _MonthlyAnalytics(this.d);

  final BdDashboard d;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final barMax = math.max(1, d.bar.fold<int>(0, (m, b) => math.max(m, b.value)));

    // Drafts / awaiting-confirmation tickets sit outside the pipeline statuses — keep the donut summing to the KPI total.
    final mix = <(String, int, Color, String)>[
      ('Assigned / In Review', math.max(0, d.stat('pending') - d.stat('ready_to_start') - d.stat('clarification_needed')), const Color(0xFF98A2B3), 'assigned_tasks'),
      ('Clarification', d.stat('clarification_needed'), const Color(0xFFF79009), 'need_clarification'),
      ('Ready to Start', d.stat('ready_to_start'), const Color(0xFF12B76A), 'yet_to_start'),
      ('In Progress', d.stat('in_progress'), const Color(0xFF7C3AED), 'in_progress'),
      ('Waiting for Review', d.stat('waiting'), const Color(0xFF2970FF), 'waiting_confirmation'),
      ('Rework', d.stat('rework_tasks'), const Color(0xFFE11D48), 'rework'),
      ('Printing File', d.stat('prepare_printing_file'), const Color(0xFF0E7490), 'prepare_printing_file'),
      ('Completed', d.stat('completed'), const Color(0xFF027A48), 'completed'),
    ];
    final assigned = mix.fold<int>(0, (s, m) => s + m.$2);
    mix.add(('Draft / Confirmation', math.max(0, d.stat('total_tasks') - assigned), const Color(0xFFCBD5E1), 'pending_bd_approval'));
    final mixSum = mix.fold<int>(0, (s, m) => s + m.$2);

    return SectionCard(
      title: '${d.monthLabel} — Monthly Analytics',
      icon: Icons.bar_chart_rounded,
      subtitle: d.designerName ?? 'All Designers',
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        for (final b in d.bar)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 5),
            child: Row(children: [
              SizedBox(width: 104, child: Text(b.label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF475467)))),
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Stack(children: [
                    Container(height: 10, color: const Color(0xFFF2F4F7)),
                    FractionallySizedBox(widthFactor: b.value > 0 ? math.max(0.03, b.value / barMax) : 0, child: Container(height: 10, color: hexColor(b.colorHex))),
                  ]),
                ),
              ),
              SizedBox(width: 34, child: Text('${b.value}', textAlign: TextAlign.right, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13))),
            ]),
          ),
        const Divider(height: 26),
        Row(children: [
          const Text('Current status mix', style: TextStyle(fontWeight: FontWeight.w800)),
          const Spacer(),
          Text('${d.stat('total_tasks')} tasks', style: const TextStyle(fontSize: 12, color: Color(0xFF667085))),
        ]),
        const SizedBox(height: 12),
        Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
          SizedBox(
            width: 118,
            height: 118,
            child: Stack(alignment: Alignment.center, children: [
              CustomPaint(size: const Size.square(118), painter: _DonutPainter([for (final m in mix) (m.$2, m.$3)], mixSum)),
              Column(mainAxisSize: MainAxisSize.min, children: [
                Text('${d.stat('total_tasks')}', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
                const Text('tasks', style: TextStyle(fontSize: 10, color: Color(0xFF667085))),
              ]),
            ]),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(children: [
              for (final m in mix)
                InkWell(
                  onTap: () => _openBoard(ref, m.$4),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 3),
                    child: Row(children: [
                      Container(width: 9, height: 9, decoration: BoxDecoration(color: m.$3, shape: BoxShape.circle)),
                      const SizedBox(width: 7),
                      Expanded(child: Text(m.$1, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11.5, color: Color(0xFF475467)))),
                      Text('${m.$2}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800)),
                    ]),
                  ),
                ),
            ]),
          ),
        ]),
      ]),
    );
  }
}

class _DonutPainter extends CustomPainter {
  _DonutPainter(this.parts, this.total);

  final List<(int, Color)> parts;
  final int total;

  @override
  void paint(Canvas canvas, Size size) {
    const stroke = 16.0;
    final rect = Rect.fromLTWH(stroke / 2, stroke / 2, size.width - stroke, size.height - stroke);
    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke;

    if (total <= 0) {
      canvas.drawArc(rect, 0, math.pi * 2, false, paint..color = const Color(0xFFEAECF0));
      return;
    }

    var start = -math.pi / 2;
    for (final (value, color) in parts) {
      if (value <= 0) continue;
      final sweep = value / total * math.pi * 2;
      canvas.drawArc(rect, start, math.max(0.0, sweep - 0.02), false, paint..color = color);
      start += sweep;
    }
  }

  @override
  bool shouldRepaint(_DonutPainter old) => old.total != total || old.parts.length != parts.length || !_same(old.parts);

  bool _same(List<(int, Color)> other) {
    for (var i = 0; i < parts.length; i++) {
      if (parts[i] != other[i]) return false;
    }
    return true;
  }
}

// ---------------------------------------------------------------------------------------------
// Overdue / Rework / Ratings / Completions
// ---------------------------------------------------------------------------------------------

class _EmptyNote extends StatelessWidget {
  const _EmptyNote(this.text, {this.keyName});

  final String text;
  final String? keyName;

  @override
  Widget build(BuildContext context) => Padding(padding: const EdgeInsets.symmetric(vertical: 14), child: Center(child: Text(text, key: keyName == null ? null : Key(keyName!), textAlign: TextAlign.center, style: const TextStyle(color: Color(0xFF667085)))));
}

Widget _countBadge(int n) => Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
      decoration: BoxDecoration(color: const Color(0xFFFEE4E2), borderRadius: BorderRadius.circular(20)),
      child: Text('$n', style: const TextStyle(color: Color(0xFFD92D20), fontWeight: FontWeight.w800, fontSize: 12)),
    );

class _TaskLine extends StatelessWidget {
  const _TaskLine({required this.id, required this.taskId, required this.name, required this.child, this.trailing});

  final int id;
  final String taskId;
  final String name;
  final Widget child;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () => context.push('/task/$id'),
        child: Container(
          padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
          decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(14), border: Border.all(color: const Color(0xFFEAECF0))),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Text(taskId, style: TextStyle(color: primary, fontWeight: FontWeight.w800, fontSize: 12.5)),
              const Spacer(),
              ?trailing,
            ]),
            const SizedBox(height: 2),
            Text(name, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, height: 1.2)),
            const SizedBox(height: 6),
            child,
          ]),
        ),
      ),
    );
  }
}

Widget _meta(String k, String? v) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 1.5),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        SizedBox(width: 92, child: Text(k, style: const TextStyle(color: Color(0xFF667085), fontSize: 11.5))),
        Expanded(child: Text(v == null || v.isEmpty ? '—' : v, style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600))),
      ]),
    );

class _OverdueTasks extends StatelessWidget {
  const _OverdueTasks(this.d);

  final BdDashboard d;

  @override
  Widget build(BuildContext context) {
    return SectionCard(
      title: 'Overdue Tasks',
      icon: Icons.warning_amber_rounded,
      subtitle: 'Not completed and past the deadline',
      trailing: d.overdue.isEmpty ? null : _countBadge(d.overdue.length),
      child: d.overdue.isEmpty
          ? _EmptyNote('${d.designerName ?? 'All Designers'} — ${d.monthLabel}: No overdue tasks.', keyName: 'overdue-empty')
          : Column(children: [
              for (final o in d.overdue)
                _TaskLine(
                  id: o.id,
                  taskId: o.taskId,
                  name: o.name,
                  trailing: StatusPill('${o.days}d overdue', pillColor('overdue')),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    _meta('Designer', o.designer),
                    _meta('Deadline', dashDt(o.dueAt)),
                    _meta('Status', pillLabel(o.status)),
                    const SizedBox(height: 4),
                    Row(children: [
                      Expanded(child: LinearProgressIndicator(value: o.percentage / 100, minHeight: 6, borderRadius: BorderRadius.circular(6), color: _red, backgroundColor: const Color(0xFFE4E7EC))),
                      const SizedBox(width: 8),
                      Text('${o.percentage}% · ${o.done}/${o.total}', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700)),
                    ]),
                  ]),
                ),
            ]),
    );
  }
}

class _ReworkAnalytics extends StatelessWidget {
  const _ReworkAnalytics(this.d);

  final BdDashboard d;

  @override
  Widget build(BuildContext context) {
    return SectionCard(
      title: 'Rework Analytics',
      icon: Icons.replay_rounded,
      subtitle: 'Every one of your tickets that entered rework',
      trailing: d.rework.isEmpty ? null : _countBadge(d.rework.length),
      child: d.rework.isEmpty
          ? const _EmptyNote('No rework records found for your tickets.', keyName: 'rework-empty')
          : Column(children: [
              for (final r in d.rework)
                _TaskLine(
                  id: r.id,
                  taskId: r.taskId,
                  name: r.name,
                  trailing: StatusPill('Rework ${r.number}', _pink),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    _meta('Designer', r.designer),
                    _meta('Assigned', dashDt(r.assignedAt)),
                    _meta('Creatives', r.creatives?.toString()),
                    _meta('Rework time', r.spent),
                    _meta('Status', pillLabel(r.status)),
                  ]),
                ),
            ]),
    );
  }
}

class _Ratings extends StatelessWidget {
  const _Ratings(this.d);

  final BdDashboard d;

  static const _tones = [Color(0xFFEFF4FF), Color(0xFFFFF4E5), Color(0xFFF4EBFF), Color(0xFFE8F8EF), Color(0xFFFFEAF3)];

  @override
  Widget build(BuildContext context) {
    return SectionCard(
      title: 'Completed Task Ratings',
      icon: Icons.star_rounded,
      subtitle: 'Your feedback on completed work',
      child: d.ratings.isEmpty
          ? const _EmptyNote('No completed ratings in this period.', keyName: 'ratings-empty')
          : Column(children: [
              for (var i = 0; i < d.ratings.length; i++)
                Builder(builder: (context) {
                  final r = d.ratings[i];
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(14),
                      onTap: r.taskDbId == null ? null : () => context.push('/task/${r.taskDbId}'),
                      child: Container(
                        key: Key('rating-$i'),
                        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                        decoration: BoxDecoration(color: _tones[i % _tones.length], borderRadius: BorderRadius.circular(14)),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Row(children: [
                            HalfStars(r.rating ?? 0, size: 17),
                            const SizedBox(width: 6),
                            Text(r.rating == null ? '—' : _fmt(r.rating!), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: Color(0xFF344054))),
                            const Spacer(),
                            Text(r.taskId ?? '', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFF344054))),
                          ]),
                          if ((r.comment ?? '').isNotEmpty) ...[
                            const SizedBox(height: 6),
                            Text('“${r.comment}”', maxLines: 3, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 14, height: 1.3, fontWeight: FontWeight.w600, color: Color(0xFF101828))),
                          ],
                          const SizedBox(height: 6),
                          Text('${r.taskName ?? ''}  ·  ${r.designer ?? '—'}  ·  ${dashDt(r.reviewedAt, 'd MMM yyyy')}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11, color: Color(0xFF667085))),
                        ]),
                      ),
                    ),
                  );
                }),
            ]),
    );
  }
}

class _Completions extends StatelessWidget {
  const _Completions(this.d);

  final BdDashboard d;

  @override
  Widget build(BuildContext context) {
    return SectionCard(
      title: 'Recent Completion Activity',
      icon: Icons.check_circle_rounded,
      subtitle: 'Your latest finished tickets',
      child: d.completions.isEmpty
          ? const _EmptyNote('No completed tickets yet.', keyName: 'completions-empty')
          : Column(children: [
              for (final c in d.completions)
                _TaskLine(
                  id: c.id,
                  taskId: c.taskId,
                  name: c.name,
                  trailing: c.rating == null ? null : Row(mainAxisSize: MainAxisSize.min, children: [HalfStars(c.rating!, size: 14), const SizedBox(width: 4), Text(_fmt(c.rating!), style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w800))]),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    _meta('Designer', c.designer),
                    _meta('Completed', dashDt(c.completedAt)),
                    _meta('Duration', c.duration),
                    _meta('Rework', c.reworkCount > 0 ? '${c.reworkCount}' : '0'),
                  ]),
                ),
            ]),
    );
  }
}
