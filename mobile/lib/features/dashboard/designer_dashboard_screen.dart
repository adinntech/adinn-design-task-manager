import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/auth.dart';
import '../../core/theme.dart';
import '../home/navigation.dart';
import '../tasks/providers.dart';
import 'dashboard_models.dart';
import 'dashboard_widgets.dart';

final designerDashboardProvider = FutureProvider.autoDispose<DesignerDashboard>((ref) => ref.watch(roleApiProvider).designerDashboard());

final dashboardSearchProvider =
    FutureProvider.autoDispose.family<List<DashRow>, String>((ref, search) => ref.watch(roleApiProvider).dashboardTasks(search));

String _label(String? key) => (key ?? '—').replaceAll('_', ' ').split(' ').map((w) => w.isEmpty ? w : w[0].toUpperCase() + w.substring(1)).join(' ');

/// The pill wording the web dashboard uses.
String pillLabel(String key) => switch (key) {
      'overdue' => 'Overdue',
      'assigned_tasks' => 'Assigned Tasks',
      'waiting_confirmation' => 'Waiting Confirmation',
      _ => _label(key),
    };

Color pillColor(String key) => key == 'overdue' ? const Color(0xFFB00020) : statusColor(key);

String _dt(DateTime? d, [String pattern = 'd MMM yyyy · h:mm a']) => d == null ? '—' : DateFormat(pattern).format(d);

/// Designer home: the same sections, in the same order, as the web "Designer Dashboard".
class DesignerDashboardScreen extends ConsumerWidget {
  const DesignerDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(designerDashboardProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Designer Dashboard')),
      body: data.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Text('$e', textAlign: TextAlign.center),
              const SizedBox(height: 12),
              OutlinedButton(onPressed: () => ref.invalidate(designerDashboardProvider), child: const Text('Try again')),
            ]),
          ),
        ),
        data: (d) => RefreshIndicator(
          onRefresh: () async {
            ref.read(authProvider.notifier).refreshUser(); // picks up an Approvals right the Admin has just granted
            ref.invalidate(designerDashboardProvider);
            await ref.read(designerDashboardProvider.future);
          },
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _Hero(d),
              const SizedBox(height: 14),
              _PrimaryStats(d),
              const SizedBox(height: 14),
              _SecondaryStats(d),
              const SizedBox(height: 14),
              _CompletionRate(d.completionRate),
              ..._extraStats(context, ref, d),
              const SizedBox(height: 14),
              _TasksOverview(d),
              const SizedBox(height: 14),
              _PerformanceTrend(d),
              const SizedBox(height: 14),
              _Reviews(d.reviews),
              const SizedBox(height: 14),
              _MyRequests(d.myRequests),
              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }

  List<Widget> _extraStats(BuildContext context, WidgetRef ref, DesignerDashboard d) {
    void focus(String status) {
      ref.read(boardFocusProvider.notifier).focus(status);
      ref.read(homeTabProvider.notifier).goTo(HomeTab.tasks);
    }

    final tiles = <Widget>[
      if (d.requestCounts['swap'] != null && d.requestCounts['swap']! > 0)
        StatTile(key: const Key('tile-swap'), label: 'Swapped Tasks', icon: Icons.swap_horiz_rounded, value: '${d.requestCounts['swap']}', hint: 'Swap requests raised by you', onTap: () => focus('swap_tasks')),
      if ((d.requestCounts['decline'] ?? 0) > 0)
        StatTile(key: const Key('tile-decline'), label: 'Declined Tasks', icon: Icons.block_rounded, value: '${d.requestCounts['decline']}', hint: 'Decline requests raised by you', onTap: () => focus('self_declined')),
      if ((d.requestCounts['split'] ?? 0) > 0)
        StatTile(key: const Key('tile-split'), label: 'Split Tasks', icon: Icons.call_split_rounded, value: '${d.requestCounts['split']}', hint: 'Split requests raised by you', onTap: () => focus('split_log')),
      if ((d.requestCounts['approved'] ?? 0) > 0)
        StatTile(key: const Key('tile-approved'), label: 'Approved Requests', icon: Icons.verified_rounded, value: '${d.requestCounts['approved']}', hint: 'Across swap/split/decline'),
      if (d.reworkCycles + d.reworkCreatives > 0)
        StatTile(key: const Key('tile-overall-rework'), label: 'Overall Rework', icon: Icons.published_with_changes_rounded, value: '${d.reworkCycles} tickets | ${d.reworkCreatives} creatives', accent: const Color(0xFFE65100)),
    ];

    return tiles.isEmpty ? const [] : [const SizedBox(height: 14), StatGrid(tiles: tiles)];
  }
}

class _Hero extends StatelessWidget {
  const _Hero(this.d);

  final DesignerDashboard d;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

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
        Positioned(top: 12, right: 12, child: Container(width: 34, height: 34, decoration: BoxDecoration(borderRadius: BorderRadius.circular(11), color: Colors.white.withValues(alpha: 0.16)), child: const Icon(Icons.palette_rounded, color: Colors.white, size: 19))),
        Padding(padding: const EdgeInsets.fromLTRB(16, 14, 16, 14), child: _heroBody(context, theme)),
      ]),
    );
  }

  Widget _heroBody(BuildContext context, ThemeData theme) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Padding(padding: const EdgeInsets.only(right: 44), child: Text('DESIGNER DASHBOARD', style: theme.textTheme.labelSmall?.copyWith(color: Colors.white70, letterSpacing: 1, fontWeight: FontWeight.w700, fontSize: 10))),
      const SizedBox(height: 3),
      Padding(
        padding: const EdgeInsets.only(right: 44),
        child: Text('Welcome back, ${d.name}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, letterSpacing: -0.3, fontSize: 18, height: 1.15)),
      ),
      const SizedBox(height: 3),
      const Text('All important task and request information in one place.', style: TextStyle(color: Colors.white70, fontSize: 12)),
      const SizedBox(height: 10),
      // Side by side when there is room; the button drops under the date on a narrow screen instead of overflowing.
      Wrap(alignment: WrapAlignment.spaceBetween, crossAxisAlignment: WrapCrossAlignment.center, spacing: 10, runSpacing: 8, children: [
        ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 190),
          child: Text(
            d.lastLogin == null ? 'This is your first login' : 'Last Login: ${_dt(d.lastLogin, 'd MMM yyyy • h:mm a')}',
            key: const Key('last-login'),
            maxLines: 2,
            style: const TextStyle(color: Colors.white, fontSize: 11),
          ),
        ),
        Consumer(
          builder: (context, ref, _) => FilledButton.icon(
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
            onPressed: () {
              ref.read(boardFocusProvider.notifier).focus('');
              ref.read(homeTabProvider.notifier).goTo(HomeTab.tasks);
            },
            label: const Text('View All Tasks'),
          ),
        ),
      ]),
    ]);
  }
}

class _PrimaryStats extends ConsumerWidget {
  const _PrimaryStats(this.d);

  final DesignerDashboard d;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    void focus(String status) {
      ref.read(boardFocusProvider.notifier).focus(status);
      ref.read(homeTabProvider.notifier).goTo(HomeTab.tasks);
    }

    // The web shows Total always and every other card only once it has something to report.
    StatTile? tile(String id, String label, int value, String hint, String? status, {Color? accent, IconData? icon}) => value > 0
        ? StatTile(key: Key('tile-$id'), label: label, value: '$value', hint: hint, accent: accent, icon: icon, onTap: status == null ? null : () => focus(status))
        : null;

    final tiles = <Widget>[
      StatTile(key: const Key('tile-total'), label: 'Total Tasks', value: '${d.stat('total')}', icon: Icons.layers_rounded, hint: 'Tasks assigned to you'),
      ?tile('assigned', 'Assigned', d.stat('assigned'), 'Awaiting your action', 'assigned_tasks', icon: Icons.assignment_rounded),
      ?tile('ready', 'Ready to Start', d.stat('ready_to_start'), 'Ready to begin work', 'yet_to_start', icon: Icons.rocket_launch_rounded),
      ?tile('in-progress', 'In Progress', d.stat('in_progress'), 'Currently being worked on', 'in_progress', accent: const Color(0xFF1565C0), icon: Icons.autorenew_rounded),
      ?tile('rework', 'Rework Creatives', d.stat('rework_creatives'), 'Creatives pending resubmission', 'rework', accent: const Color(0xFFE65100), icon: Icons.replay_rounded),
      ?tile('completed', 'Completed', d.stat('completed'), 'Finished tasks', 'completed', accent: const Color(0xFF2E7D32), icon: Icons.check_circle_rounded),
    ];

    return StatGrid(tiles: tiles);
  }
}

class _SecondaryStats extends ConsumerWidget {
  const _SecondaryStats(this.d);

  final DesignerDashboard d;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    void focus(String status) {
      ref.read(boardFocusProvider.notifier).focus(status);
      ref.read(homeTabProvider.notifier).goTo(HomeTab.tasks);
    }

    final tiles = <Widget>[
      if (d.stat('waiting_bd_review') > 0)
        StatTile(key: const Key('tile-waiting'), label: 'Waiting for BD Review', icon: Icons.hourglass_top_rounded, value: '${d.stat('waiting_bd_review')}', hint: 'Completed by you, awaiting BD', accent: const Color(0xFF6A1B9A), onTap: () => focus('waiting_confirmation')),
      if (d.stat('waiting_bd_confirmation') > 0)
        StatTile(key: const Key('tile-bd-approval'), label: 'Awaiting BD Approval', icon: Icons.how_to_reg_rounded, value: '${d.stat('waiting_bd_confirmation')}', hint: 'Tasks you created that are waiting for BD to approve or reject'),
      if (d.stat('prepare_printing_file') > 0)
        StatTile(key: const Key('tile-printing'), label: 'Prepare Printing File Tasks', icon: Icons.print_rounded, value: '${d.stat('prepare_printing_file')}', hint: 'Assigned back to you for printing files', onTap: () => focus('prepare_printing_file')),
      StatTile(
        key: const Key('tile-rating'),
        label: 'Overall Rating', icon: Icons.star_rounded,
        value: d.ratingAverage == null ? '—' : '${_trim(d.ratingAverage!)}/5',
        accent: const Color(0xFFF5A623),
        hint: 'Rated ${d.ratedCount} / ${d.completedTotal} completed',
        child: d.ratingAverage == null ? null : Padding(padding: const EdgeInsets.symmetric(vertical: 2), child: HalfStars(d.ratingAverage!, size: 16)),
      ),
    ];

    return StatGrid(tiles: tiles);
  }

  static String _trim(double v) => v == v.roundToDouble() ? v.toStringAsFixed(0) : v.toStringAsFixed(1);
}

class _CompletionRate extends StatelessWidget {
  const _CompletionRate(this.percent);

  final int percent;

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
            const Text('Completion Rate', style: TextStyle(fontWeight: FontWeight.w800)),
            const Spacer(),
            Text('$percent%', key: const Key('completion-rate'), style: const TextStyle(fontWeight: FontWeight.w800)),
          ]),
          const SizedBox(height: 8),
          LinearProgressIndicator(value: percent / 100, minHeight: 8, borderRadius: BorderRadius.circular(8), color: const Color(0xFF2E7D32), backgroundColor: const Color(0xFFE4E7EC)),
        ]),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill(this.text, this.color);

  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
        child: Text(text, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w700)),
      );
}

// ---------------------------------------------------------------------------------------------
// Tasks Overview + the "Your Task Details" drawer
// ---------------------------------------------------------------------------------------------

class _TasksOverview extends ConsumerStatefulWidget {
  const _TasksOverview(this.d);

  final DesignerDashboard d;

  @override
  ConsumerState<_TasksOverview> createState() => _TasksOverviewState();
}

class _TasksOverviewState extends ConsumerState<_TasksOverview> {
  /// Only the first few tasks sit on the dashboard so the section fits on screen; the rest are one tap away.
  static const _maxRows = 5;

  final _search = TextEditingController();
  Timer? _debounce;
  String _query = '';
  String? _status;
  String? _priority;
  String? _vertical;

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

  List<DashRow> _filtered(List<DashRow> rows) => rows.where((r) {
        if (_status != null && r.pillKey != _status) return false;
        if (_priority != null && r.priority != _priority) return false;
        if (_vertical != null && r.vertical != _vertical) return false;
        return true;
      }).toList();

  @override
  Widget build(BuildContext context) {
    // Empty box shows the 12 most recent; typing searches every task on the server (like the web search fragment).
    final searching = _query.isNotEmpty;
    final source = searching ? ref.watch(dashboardSearchProvider(_query)) : AsyncData(widget.d.recent);

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
         Expanded(
          // scales down rather than overflowing when the title, label and badge do not all fit
          child: FittedBox(
           fit: BoxFit.scaleDown,
           alignment: Alignment.centerLeft,
           child: Row(mainAxisSize: MainAxisSize.min, children: [
          Text('Tasks Overview', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800, letterSpacing: -0.3)),
          const SizedBox(width: 10),
          const Text('Total Tasks', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF667085))),
          const SizedBox(width: 6),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 1),
            decoration: BoxDecoration(color: const Color(0xFFFEE4E2), borderRadius: BorderRadius.circular(20)),
            child: Text('${widget.d.stat('total')}', key: const Key('overview-total'), style: const TextStyle(color: Color(0xFFD92D20), fontWeight: FontWeight.w800, fontSize: 12)),
          ),
           ]),
          ),
         ),
          TextButton(
            style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 8), minimumSize: const Size(0, 32), tapTargetSize: MaterialTapTargetSize.shrinkWrap),
            key: const Key('overview-view-all'),
            onPressed: () {
              ref.read(boardFocusProvider.notifier).focus('');
              ref.read(homeTabProvider.notifier).goTo(HomeTab.tasks);
            },
            child: const Text('View All'),
          ),
        ]),
        const SizedBox(height: 6),
        Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10, offset: const Offset(0, 3))],
          ),
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
          data: (rows) {
            final statuses = {...rows.map((r) => r.pillKey), if (rows.any((r) => r.overdue)) 'overdue'}.toList()..sort();
            final priorities = {...rows.map((r) => r.priority)}.toList()..sort();
            final verticals = {...rows.map((r) => r.vertical).whereType<String>()}.toList()..sort();
            final shown = _filtered(rows);

            return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Expanded(child: _FilterMenu(keyName: 'status', label: 'Status', value: _status, options: {for (final s in statuses) s: pillLabel(s)}, onChanged: (v) => setState(() => _status = v))),
                const SizedBox(width: 8),
                Expanded(child: _FilterMenu(keyName: 'priority', label: 'Priority', value: _priority, options: {for (final p in priorities) p: _label(p)}, onChanged: (v) => setState(() => _priority = v))),
                const SizedBox(width: 8),
                Expanded(child: _FilterMenu(keyName: 'vertical', label: 'Vertical', value: _vertical, options: {for (final v in verticals) v: _label(v)}, onChanged: (v) => setState(() => _vertical = v))),
                if (_status != null || _priority != null || _vertical != null) ...[
                  const SizedBox(width: 6),
                  IconButton(
                    key: const Key('clear-filters'),
                    tooltip: 'Clear filters',
                    visualDensity: VisualDensity.compact,
                    style: IconButton.styleFrom(backgroundColor: Colors.white, side: const BorderSide(color: Color(0xFFD0D5DD))),
                    icon: const Icon(Icons.filter_alt_off_rounded, size: 18),
                    onPressed: () => setState(() => _status = _priority = _vertical = null),
                  ),
                ],
              ]),
              const SizedBox(height: 10),
              if (shown.isEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 20),
                  child: Center(child: Text(rows.isEmpty && !searching ? 'No tasks assigned yet.' : 'No matching tasks found.', key: const Key('overview-empty'))),
                )
              else
                for (final r in shown.take(_maxRows)) _OverviewRow(r),
              if (shown.length > _maxRows)
                Center(
                  child: TextButton.icon(
                    key: const Key('overview-more'),
                    onPressed: () {
                      ref.read(boardFocusProvider.notifier).focus('');
                      ref.read(homeTabProvider.notifier).goTo(HomeTab.tasks);
                    },
                    icon: const Icon(Icons.arrow_forward_rounded, size: 18),
                    iconAlignment: IconAlignment.end,
                    label: Text('${shown.length - _maxRows} more · View all tasks'),
                  ),
                ),
            ]);
          },
        ),
    ]);
  }
}

class _FilterMenu extends StatelessWidget {
  const _FilterMenu({required this.keyName, required this.label, required this.value, required this.options, required this.onChanged});

  final String keyName;
  final String label;
  final String? value;
  final Map<String, String> options;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) => PopupMenuButton<String?>(
        key: Key('filter-$keyName'),
        tooltip: label,
        onSelected: onChanged,
        itemBuilder: (_) => [
          PopupMenuItem<String?>(value: null, child: Text('$label: All')),
          for (final o in options.entries) PopupMenuItem<String?>(value: o.key, child: Text(o.value)),
        ],
        child: Container(
          padding: const EdgeInsets.fromLTRB(10, 7, 4, 7),
          decoration: BoxDecoration(
            color: value == null ? Colors.white : Theme.of(context).colorScheme.primary,
            borderRadius: BorderRadius.circular(24),
            border: Border.all(color: value == null ? const Color(0xFFD0D5DD) : Colors.transparent),
            boxShadow: value == null ? null : [BoxShadow(color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.3), blurRadius: 10, offset: const Offset(0, 4))],
          ),
          child: Row(children: [
            Expanded(child: Text(value == null ? label : '$label: ${options[value] ?? value}', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: value == null ? const Color(0xFF475467) : Colors.white))),
            Icon(Icons.keyboard_arrow_down_rounded, size: 18, color: value == null ? const Color(0xFF475467) : Colors.white),
          ]),
        ),
      );
}

class _OverviewRow extends StatelessWidget {
  const _OverviewRow(this.r);

  final DashRow r;

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    final bar = r.overdue ? pillColor('overdue') : (r.priority == 'urgent' || r.priority == 'high' ? priorityColor(r.priority) : primary);
    final pillKey = r.overdue ? 'overdue' : r.pillKey;
    final meta = [r.party, _label(r.nature)].where((x) => x != null && x.isNotEmpty && x != '—').join('  ·  ');

    Widget chip(IconData icon, String text, {Color? tint}) => Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
          decoration: BoxDecoration(color: tint == null ? const Color(0xFFF2F4F7) : tint.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Icon(icon, size: 12, color: tint ?? const Color(0xFF475467)),
            const SizedBox(width: 4),
            Text(text, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: tint ?? const Color(0xFF344054))),
          ]),
        );

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: RepaintBoundary(
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 14, offset: const Offset(0, 5))],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: Material(
              color: Colors.white,
              child: InkWell(
                key: Key('row-${r.id}'),
                onTap: () => showModalBottomSheet<void>(
                  context: context,
                  isScrollControlled: true,
                  showDragHandle: true,
                  builder: (_) => TaskDrawer(row: r),
                ),
                child: IntrinsicHeight(
                  child: Row(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    Container(width: 5, color: bar),
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(12, 9, 10, 9),
                        child: Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
                          Expanded(
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Row(children: [
                                Flexible(child: Text(r.taskId, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: primary, fontWeight: FontWeight.w800, fontSize: 12.5))),
                                if (r.projectNumber != null && r.projectNumber!.isNotEmpty) Text('  ·  ${r.projectNumber}', style: const TextStyle(color: Color(0xFF667085), fontSize: 12)),
                                const Spacer(),
                                _Pill(pillLabel(pillKey), pillColor(pillKey)),
                              ]),
                              const SizedBox(height: 2),
                              Text(r.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800, letterSpacing: -0.2, height: 1.15)),
                              if (meta.isNotEmpty) ...[
                                const SizedBox(height: 3),
                                Row(children: [
                                  const Icon(Icons.business_center_rounded, size: 14, color: Color(0xFF667085)),
                                  const SizedBox(width: 6),
                                  Expanded(child: Text(meta, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, color: Color(0xFF475467)))),
                                ]),
                              ],
                              const SizedBox(height: 6),
                              Wrap(spacing: 6, runSpacing: 4, crossAxisAlignment: WrapCrossAlignment.center, children: [
                                if (r.dueAt != null) chip(Icons.calendar_today_rounded, _dt(r.dueAt, 'MMM d, yyyy')),
                                if (r.dueAt != null) chip(Icons.access_time_rounded, _dt(r.dueAt, 'h:mm a'), tint: r.overdue ? const Color(0xFFD92D20) : const Color(0xFF1565C0)),
                                if (r.assignedBy != null) Text('${r.assignedBy}', style: const TextStyle(fontSize: 11, color: Color(0xFF667085))),
                              ]),
                            ]),
                          ),
                          const SizedBox(width: 8),
                          Container(
                            width: 32,
                            height: 32,
                            decoration: const BoxDecoration(shape: BoxShape.circle, color: Color(0xFFF2F4F7)),
                            child: const Icon(Icons.chevron_right_rounded, size: 22, color: Color(0xFF344054)),
                          ),
                        ]),
                      ),
                    ),
                  ]),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// "Your Task Details" — progress, creatives and deadlines for one task.
class TaskDrawer extends StatelessWidget {
  const TaskDrawer({super.key, required this.row});

  final DashRow row;

  String get _overdueText => switch (row.completionStatus) {
        'overdue' => '${row.completionDays}d overdue',
        'late' => 'Completed ${row.completionDays}d after due',
        'on_time' => 'On time',
        _ => '—',
      };

  Color get _barColor => row.percentage >= 100
      ? const Color(0xFF2E7D32)
      : row.percentage <= 0
          ? const Color(0xFF98A2B3)
          : const Color(0xFFD92D20);

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    Widget line(String k, Widget v) => Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            SizedBox(width: 110, child: Text(k, style: const TextStyle(color: Colors.black54))),
            Expanded(child: v),
          ]),
        );

    return SafeArea(
      child: SingleChildScrollView(
        key: const Key('task-drawer'),
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Your Task Details', style: theme.textTheme.titleLarge),
          Text('Progress, creatives and deadlines for this task.', style: theme.textTheme.bodySmall?.copyWith(color: Colors.black54)),
          const SizedBox(height: 14),
          Row(children: [
            Text(row.taskId, style: theme.textTheme.titleMedium?.copyWith(color: theme.colorScheme.primary, fontWeight: FontWeight.w700)),
            const SizedBox(width: 8),
            _Pill(pillLabel(row.pillKey), pillColor(row.pillKey)),
          ]),
          Text(row.name, style: theme.textTheme.titleSmall),
          const Divider(height: 24),
          line('Project Number', Text(row.projectNumber?.isNotEmpty == true ? row.projectNumber! : '—')),
          line('Assigned At', Text(_dt(row.assignedAt, 'd MMM yyyy'))),
          line(
            'Progress',
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${row.percentage}%', key: const Key('drawer-percent'), style: const TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 4),
              LinearProgressIndicator(value: row.percentage / 100, minHeight: 7, borderRadius: BorderRadius.circular(8), color: _barColor, backgroundColor: const Color(0xFFE4E7EC)),
            ]),
          ),
          line('Creatives', Text('${row.done} / ${row.total}   ·   ${row.remaining} remaining')),
          line('Rework', Text(row.reworkCount > 0 ? '${row.reworkCount} · ${row.reworkCreatives} creatives' : '0')),
          line('Deadline', Text(_dt(row.dueAt, 'd MMM yyyy'))),
          line('Completed At', Text(_dt(row.completedAt, 'd MMM yyyy'))),
          line('Overdue', Text(_overdueText, key: const Key('drawer-overdue'), style: TextStyle(color: row.completionStatus == 'overdue' || row.completionStatus == 'late' ? theme.colorScheme.error : null))),
          line('Rating', row.rating == null ? const Text('—') : HalfStars(row.rating!)),
          const SizedBox(height: 12),
          Consumer(
            builder: (context, ref, _) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              FilledButton(
                key: const Key('drawer-open'),
                onPressed: () {
                  Navigator.pop(context);
                  context.push('/task/${row.id}');
                },
                child: const Text('Open task →'),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                key: const Key('drawer-board'),
                icon: const Icon(Icons.view_kanban_outlined),
                label: const Text('Open on board'),
                onPressed: () {
                  Navigator.pop(context);
                  ref.read(boardTaskFocusProvider.notifier).focus(row.id);
                  ref.read(boardFocusProvider.notifier).focus(row.status);
                  ref.read(homeTabProvider.notifier).goTo(HomeTab.tasks);
                },
              ),
            ]),
          ),
        ]),
      ),
    );
  }
}

// ---------------------------------------------------------------------------------------------
// Performance Trend
// ---------------------------------------------------------------------------------------------

class _PerformanceTrend extends ConsumerStatefulWidget {
  const _PerformanceTrend(this.d);

  final DesignerDashboard d;

  @override
  ConsumerState<_PerformanceTrend> createState() => _PerformanceTrendState();
}

class _PerformanceTrendState extends ConsumerState<_PerformanceTrend> {
  int _tab = 0;

  static const _blue = Color(0xFF2563EB);
  static const _purple = Color(0xFF9333EA);
  static const _green = Color(0xFF16A34A);
  static const _amber = Color(0xFFF59E0B);
  static const _rose = Color(0xFFE11D48);

  // The cards from the server are matched to a chart by their label; anything else belongs to the progress chart.
  static const _issueLabels = {'Overdue & Completed', 'Declined'};

  // Where each figure leads on the My Tasks board.
  static String _boardStage(String label) => switch (label) {
        'In Progress' => 'in_progress',
        'Completed' || 'Overdue & Completed' => 'completed',
        'Declined' => 'self_declined',
        _ => 'assigned_tasks',
      };

  void _openBoard(String label) {
    ref.read(boardFocusProvider.notifier).focus(_boardStage(label));
    ref.read(homeTabProvider.notifier).goTo(HomeTab.tasks);
  }

  static String _short(String label) => switch (label) {
        'Assigned Tasks' => 'Assigned',
        'Overdue & Completed' => 'Overdue',
        _ => label,
      };

  @override
  Widget build(BuildContext context) {
    final d = widget.d;
    final months = d.trendLine;

    final progress = [
      Series('Assigned', _blue, [for (final m in months) m.assigned]),
      Series('In Progress', _purple, [for (final m in months) m.inProgress]),
      Series('Completed', _green, [for (final m in months) m.completed]),
    ];
    final issues = [
      Series('Overdue & Completed', _amber, [for (final m in months) m.overdueCompleted]),
      Series('Declined', _rose, [for (final m in months) m.declined]),
    ];

    return SectionCard(
      title: 'Performance Trend',
      subtitle: 'Task status over time',
      icon: Icons.show_chart_rounded,
      trailing: Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
        decoration: BoxDecoration(color: const Color(0xFFF2F4F7), borderRadius: BorderRadius.circular(20)),
        child: Text(d.trendContext, key: const Key('trend-context'), style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: Color(0xFF475467))),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // One slim row of figures: the ones for the chart on show are in full colour, the others step back. Tap one to open those tickets in My Tasks.
        Row(children: [
          for (var i = 0; i < d.trendCards.length; i++) ...[
            if (i > 0) const SizedBox(width: 6),
            Expanded(child: _kpi(d.trendCards[i])),
          ],
        ]),
        const SizedBox(height: 12),
        _ChartSwitch(
          selected: _tab,
          onChanged: (v) => setState(() => _tab = v),
        ),
        const SizedBox(height: 8),
        if (months.isEmpty)
          const Padding(padding: EdgeInsets.all(16), child: Center(child: Text('No data yet.')))
        else
          AnimatedSwitcher(
            duration: const Duration(milliseconds: 260),
            switchInCurve: Curves.easeOut,
            child: KeyedSubtree(
              key: ValueKey(_tab),
              child: _tab == 0
                  ? TrendLineChart(key: const Key('chart-line'), months: months, series: progress, height: 150)
                  : TrendBarChart(key: const Key('chart-bars'), months: months, series: issues, height: 150),
            ),
          ),
      ]),
    );
  }

  Widget _kpi(TrendCard c) {
    final color = hexColor(c.colorHex);
    final onShow = _issueLabels.contains(c.label) == (_tab == 1);

    return GestureDetector(
      key: Key('trend-${c.label}'),
      behavior: HitTestBehavior.opaque,
      onTap: () => _openBoard(c.label),
      child: AnimatedOpacity(
        duration: const Duration(milliseconds: 200),
        opacity: onShow ? 1 : 0.45,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: color.withValues(alpha: onShow ? 0.25 : 0.1)),
          ),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text('${c.value}', style: TextStyle(fontSize: 19, fontWeight: FontWeight.w800, color: color, height: 1.1)),
            const SizedBox(height: 2),
            FittedBox(fit: BoxFit.scaleDown, child: Text(_short(c.label), maxLines: 1, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Color(0xFF475467)))),
          ]),
        ),
      ),
    );
  }
}

/// A two-way pill switch: a thumb slides under the chosen chart, and the labels change colour as it passes.
class _ChartSwitch extends StatelessWidget {
  const _ChartSwitch({required this.selected, required this.onChanged});

  final int selected;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;

    Widget segment(int i, IconData icon, String text) {
      final on = selected == i;
      final color = on ? Colors.white : const Color(0xFF667085);
      return Expanded(
        child: GestureDetector(
          key: Key('trend-tab-$i'),
          behavior: HitTestBehavior.opaque,
          onTap: () => onChanged(i),
          child: Center(
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              Icon(icon, size: 17, color: color),
              const SizedBox(width: 6),
              AnimatedDefaultTextStyle(duration: const Duration(milliseconds: 200), style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: color), child: Text(text)),
            ]),
          ),
        ),
      );
    }

    return Container(
      key: const Key('trend-tabs'),
      height: 38,
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(color: const Color(0xFFF2F4F7), borderRadius: BorderRadius.circular(22)),
      child: Stack(children: [
        AnimatedAlign(
          duration: const Duration(milliseconds: 280),
          curve: Curves.easeOutCubic,
          alignment: selected == 0 ? Alignment.centerLeft : Alignment.centerRight,
          child: FractionallySizedBox(
            widthFactor: 0.5,
            heightFactor: 1,
            child: Container(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(19),
                gradient: LinearGradient(colors: [primary, primary.withValues(alpha: 0.82)]),
                boxShadow: [BoxShadow(color: primary.withValues(alpha: 0.3), blurRadius: 8, offset: const Offset(0, 3))],
              ),
            ),
          ),
        ),
        Row(children: [
          segment(0, Icons.show_chart_rounded, 'Progress'),
          segment(1, Icons.bar_chart_rounded, 'Issues'),
        ]),
      ]),
    );
  }
}

// ---------------------------------------------------------------------------------------------
// Designer Reviews carousel
// ---------------------------------------------------------------------------------------------

class _Reviews extends ConsumerStatefulWidget {
  const _Reviews(this.reviews);

  final List<ReviewCard> reviews;

  @override
  ConsumerState<_Reviews> createState() => _ReviewsState();
}

class _ReviewsState extends ConsumerState<_Reviews> {
  final _controller = PageController(viewportFraction: 0.92);
  Timer? _auto;
  DateTime _pausedUntil = DateTime.fromMillisecondsSinceEpoch(0);
  int _page = 0;

  static const _tones = [Color(0xFFEFF4FF), Color(0xFFFFF4E5), Color(0xFFF4EBFF), Color(0xFFE8F8EF), Color(0xFFFFEAF3)];

  @override
  void initState() {
    super.initState();
    // Drifts to the next review every few seconds, but never fights a thumb that is on it.
    _auto = Timer.periodic(const Duration(seconds: 5), (_) {
      if (!mounted || widget.reviews.length < 2 || DateTime.now().isBefore(_pausedUntil) || !_controller.hasClients) return;
      final next = (_page + 1) % widget.reviews.length;
      _controller.animateToPage(next, duration: const Duration(milliseconds: 450), curve: Curves.easeInOut);
    });
  }

  @override
  void dispose() {
    _auto?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final reviews = widget.reviews;

    return SectionCard(
      title: 'Designer Reviews', icon: Icons.reviews_rounded,
      subtitle: 'What BDs said about your work',
      child: reviews.isEmpty
          ? const Padding(padding: EdgeInsets.symmetric(vertical: 16), child: Center(child: Text('No BD comments yet on your completed tasks.', key: Key('reviews-empty'))))
          : Column(children: [
              SizedBox(
                height: 168,
                child: Listener(
                  onPointerDown: (_) => _pausedUntil = DateTime.now().add(const Duration(milliseconds: 2200)),
                  child: PageView.builder(
                    key: const Key('reviews-pager'),
                    controller: _controller,
                    itemCount: reviews.length,
                    onPageChanged: (i) => setState(() => _page = i),
                    itemBuilder: (_, i) {
                      final r = reviews[i];
                      return Padding(
                        padding: const EdgeInsets.only(right: 10),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(14),
                          onTap: r.taskDbId == null ? null : () => context.push('/task/${r.taskDbId}'),
                          child: Container(
                            key: Key('review-$i'),
                            padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                            decoration: BoxDecoration(
                              color: _tones[i % _tones.length],
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: Stack(children: [
                              // a faint quote mark as the only decoration
                              const Positioned(right: -2, top: -10, child: Text('”', style: TextStyle(fontSize: 76, height: 1, fontWeight: FontWeight.w900, color: Color(0x14000000)))),
                              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                Row(children: [
                                  HalfStars(r.rating, size: 17),
                                  const SizedBox(width: 6),
                                  Text(r.rating.toStringAsFixed(r.rating == r.rating.roundToDouble() ? 0 : 1), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: Color(0xFF344054))),
                                ]),
                                const SizedBox(height: 8),
                                Expanded(
                                  child: Text('“${r.comment}”', maxLines: 3, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 15, height: 1.3, fontWeight: FontWeight.w600, color: Color(0xFF101828))),
                                ),
                                Row(children: [
                                  CircleAvatar(
                                    radius: 13,
                                    backgroundColor: Colors.white,
                                    child: Text(((r.reviewer ?? 'BD').trim().isEmpty ? 'B' : (r.reviewer ?? 'BD').trim()[0]).toUpperCase(), style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: theme.colorScheme.primary)),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                      Text('${r.reviewer ?? 'BD'}  ·  ${r.taskId ?? ''}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFF344054))),
                                      Text('Reviewed: ${_dt(r.reviewedAt, 'd MMM yyyy')}  ·  Completed: ${_dt(r.completedAt, 'd MMM yyyy')}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 10.5, color: Color(0xFF667085))),
                                    ]),
                                  ),
                                ]),
                              ]),
                            ]),
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ),
              if (reviews.length > 1)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    for (var i = 0; i < reviews.length; i++)
                      Container(
                        width: i == _page ? 18 : 7,
                        height: 7,
                        margin: const EdgeInsets.symmetric(horizontal: 2),
                        decoration: BoxDecoration(color: i == _page ? theme.colorScheme.primary : const Color(0xFFD0D5DD), borderRadius: BorderRadius.circular(8)),
                      ),
                  ]),
                ),
            ]),
    );
  }
}

// ---------------------------------------------------------------------------------------------
// My Requests — Swap, Decline & Split
// ---------------------------------------------------------------------------------------------

class _MyRequests extends StatelessWidget {
  const _MyRequests(this.requests);

  final List<MyRequest> requests;

  static Color _tone(String type) => switch (type) {
        'swap' => const Color(0xFF2970FF),
        'split' => const Color(0xFF7C3AED),
        'decline' => const Color(0xFFD92D20),
        _ => const Color(0xFFE65100),
      };

  static IconData _icon(String type) => switch (type) {
        'swap' => Icons.swap_horiz,
        'split' => Icons.call_split,
        'decline' => Icons.close,
        _ => Icons.grid_view,
      };

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return SectionCard(
      title: 'My Requests — Swap, Decline & Split', icon: Icons.sync_alt_rounded,
      trailing: Text('${requests.length} requests', key: const Key('requests-count'), style: theme.textTheme.labelMedium),
      child: requests.isEmpty
          ? Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Column(children: [
                const Icon(Icons.inbox_outlined, size: 36, color: Colors.black38),
                const SizedBox(height: 6),
                Text('No swap, decline or split requests yet', key: const Key('requests-empty'), style: theme.textTheme.titleSmall),
                Text('Requests you raise from a task will show up here with their status.', textAlign: TextAlign.center, style: theme.textTheme.bodySmall?.copyWith(color: Colors.black54)),
              ]),
            )
          : Column(children: [
              for (final r in requests)
                Container(
                  key: Key('request-${r.id}'),
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(color: _tone(r.type).withValues(alpha: 0.07), borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.black12)),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      Icon(_icon(r.type), color: _tone(r.type)),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          InkWell(
                            onTap: r.taskDbId == null ? null : () => context.push('/task/${r.taskDbId}'),
                            child: Text('${r.taskId ?? 'Task'} — ${r.taskName ?? 'Task unavailable'}', style: theme.textTheme.titleSmall?.copyWith(color: r.taskDbId == null ? null : theme.colorScheme.primary), maxLines: 2, overflow: TextOverflow.ellipsis),
                          ),
                          Text('${r.typeLabel} Request', style: theme.textTheme.bodySmall?.copyWith(color: Colors.black54)),
                        ]),
                      ),
                      _Pill(
                        r.status == 'approved' ? 'Approved' : (r.status == 'rejected' ? 'Rejected' : 'Pending'),
                        r.status == 'approved' ? const Color(0xFF2E7D32) : (r.status == 'rejected' ? const Color(0xFFB00020) : const Color(0xFFE65100)),
                      ),
                    ]),
                    const SizedBox(height: 8),
                    _kv('Requested by', r.requestedBy),
                    _kv('Requested at', _dt(r.requestedAt, "d MMM yyyy 'at' h:mm a")),
                    _kv('Responded by', r.respondedBy),
                    _kv('Responded at', _dt(r.respondedAt, "d MMM yyyy 'at' h:mm a")),
                    _kv('Current handler', r.currentHandler),
                  ]),
                ),
            ]),
    );
  }

  Widget _kv(String k, String? v) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          SizedBox(width: 112, child: Text(k, style: const TextStyle(color: Colors.black54, fontSize: 12))),
          Expanded(child: Text(v == null || v.isEmpty ? '—' : v, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600))),
        ]),
      );
}
