import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

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
        StatTile(key: const Key('tile-swap'), label: 'Swapped Tasks', value: '${d.requestCounts['swap']}', hint: 'Swap requests raised by you', onTap: () => focus('swap_tasks')),
      if ((d.requestCounts['decline'] ?? 0) > 0)
        StatTile(key: const Key('tile-decline'), label: 'Declined Tasks', value: '${d.requestCounts['decline']}', hint: 'Decline requests raised by you', onTap: () => focus('self_declined')),
      if ((d.requestCounts['split'] ?? 0) > 0)
        StatTile(key: const Key('tile-split'), label: 'Split Tasks', value: '${d.requestCounts['split']}', hint: 'Split requests raised by you', onTap: () => focus('split_log')),
      if ((d.requestCounts['approved'] ?? 0) > 0)
        StatTile(key: const Key('tile-approved'), label: 'Approved Requests', value: '${d.requestCounts['approved']}', hint: 'Across swap/split/decline'),
      if (d.reworkCycles + d.reworkCreatives > 0)
        StatTile(key: const Key('tile-overall-rework'), label: 'Overall Rework', value: '${d.reworkCycles} tickets | ${d.reworkCreatives} creatives', accent: const Color(0xFFE65100)),
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
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        gradient: const LinearGradient(colors: [Color(0xFFE30613), Color(0xFFB5040F)], begin: Alignment.topLeft, end: Alignment.bottomRight),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('DESIGNER DASHBOARD', style: theme.textTheme.labelSmall?.copyWith(color: Colors.white70, letterSpacing: 1.2, fontWeight: FontWeight.w700)),
        const SizedBox(height: 6),
        Text('Welcome back, ${d.name}', style: theme.textTheme.headlineSmall?.copyWith(color: Colors.white, fontWeight: FontWeight.w800)),
        const SizedBox(height: 4),
        const Text('All important task and request information in one place.', style: TextStyle(color: Colors.white70)),
        const SizedBox(height: 10),
        Text(
          d.lastLogin == null ? 'This is your first login' : 'Last Login: ${_dt(d.lastLogin, 'd MMM yyyy • h:mm a')}',
          key: const Key('last-login'),
          style: const TextStyle(color: Colors.white, fontSize: 12),
        ),
        const SizedBox(height: 14),
        Consumer(
          builder: (context, ref, _) => FilledButton(
            key: const Key('hero-view-tasks'),
            style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: const Color(0xFFB5040F), minimumSize: const Size(0, 44)),
            onPressed: () {
              ref.read(boardFocusProvider.notifier).focus('');
              ref.read(homeTabProvider.notifier).goTo(HomeTab.tasks);
            },
            child: const Text('View All Tasks'),
          ),
        ),
      ]),
    );
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
    StatTile? tile(String id, String label, int value, String hint, String? status, {Color? accent}) => value > 0
        ? StatTile(key: Key('tile-$id'), label: label, value: '$value', hint: hint, accent: accent, onTap: status == null ? null : () => focus(status))
        : null;

    final tiles = <Widget>[
      StatTile(key: const Key('tile-total'), label: 'Total Tasks', value: '${d.stat('total')}', hint: 'Tasks assigned to you'),
      ?tile('assigned', 'Assigned', d.stat('assigned'), 'Awaiting your action', 'assigned_tasks'),
      ?tile('ready', 'Ready to Start', d.stat('ready_to_start'), 'Ready to begin work', 'yet_to_start'),
      ?tile('in-progress', 'In Progress', d.stat('in_progress'), 'Currently being worked on', 'in_progress', accent: const Color(0xFF1565C0)),
      ?tile('rework', 'Rework Creatives', d.stat('rework_creatives'), 'Creatives pending resubmission', 'rework', accent: const Color(0xFFE65100)),
      ?tile('completed', 'Completed', d.stat('completed'), 'Finished tasks', 'completed', accent: const Color(0xFF2E7D32)),
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
        StatTile(key: const Key('tile-waiting'), label: 'Waiting for BD Review', value: '${d.stat('waiting_bd_review')}', hint: 'Completed by you, awaiting BD', accent: const Color(0xFF6A1B9A), onTap: () => focus('waiting_confirmation')),
      if (d.stat('waiting_bd_confirmation') > 0)
        StatTile(key: const Key('tile-bd-approval'), label: 'Awaiting BD Approval', value: '${d.stat('waiting_bd_confirmation')}', hint: 'Tasks you created that are waiting for BD to approve or reject'),
      if (d.stat('prepare_printing_file') > 0)
        StatTile(key: const Key('tile-printing'), label: 'Prepare Printing File Tasks', value: '${d.stat('prepare_printing_file')}', hint: 'Assigned back to you for printing files', onTap: () => focus('prepare_printing_file')),
      StatTile(
        key: const Key('tile-rating'),
        label: 'Overall Rating',
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
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            const Text('Completion Rate', style: TextStyle(fontWeight: FontWeight.w700)),
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

    return SectionCard(
      title: 'Tasks Overview',
      subtitle: 'Track and manage all your assigned design tasks in one place.',
      trailing: TextButton(
        key: const Key('overview-view-all'),
        onPressed: () {
          ref.read(boardFocusProvider.notifier).focus('');
          ref.read(homeTabProvider.notifier).goTo(HomeTab.tasks);
        },
        child: const Text('View All'),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Total Tasks ${widget.d.stat('total')}', style: Theme.of(context).textTheme.labelLarge),
        const SizedBox(height: 10),
        TextField(
          key: const Key('overview-search'),
          controller: _search,
          onChanged: _onSearch,
          decoration: const InputDecoration(hintText: 'Search by Task ID, Project Number, Task, Client / Agency', prefixIcon: Icon(Icons.search), isDense: true),
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
              Wrap(spacing: 8, runSpacing: 4, children: [
                _FilterMenu(keyName: 'status', label: 'Status', value: _status, options: {for (final s in statuses) s: pillLabel(s)}, onChanged: (v) => setState(() => _status = v)),
                _FilterMenu(keyName: 'priority', label: 'Priority', value: _priority, options: {for (final p in priorities) p: _label(p)}, onChanged: (v) => setState(() => _priority = v)),
                _FilterMenu(keyName: 'vertical', label: 'Vertical', value: _vertical, options: {for (final v in verticals) v: _label(v)}, onChanged: (v) => setState(() => _vertical = v)),
                if (_status != null || _priority != null || _vertical != null)
                  ActionChip(
                    key: const Key('clear-filters'),
                    avatar: const Icon(Icons.filter_alt_off, size: 16),
                    label: const Text('Clear'),
                    onPressed: () => setState(() => _status = _priority = _vertical = null),
                  ),
              ]),
              const SizedBox(height: 8),
              if (shown.isEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 20),
                  child: Center(child: Text(rows.isEmpty && !searching ? 'No tasks assigned yet.' : 'No matching tasks found.', key: const Key('overview-empty'))),
                )
              else
                for (final r in shown) _OverviewRow(r),
            ]);
          },
        ),
      ]),
    );
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
        child: Chip(
          label: Text(value == null ? '$label: All' : '$label: ${options[value] ?? value}'),
          backgroundColor: value == null ? null : Theme.of(context).colorScheme.primary.withValues(alpha: 0.10),
          visualDensity: VisualDensity.compact,
        ),
      );
}

class _OverviewRow extends StatelessWidget {
  const _OverviewRow(this.r);

  final DashRow r;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      key: Key('row-${r.id}'),
      onTap: () => showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        showDragHandle: true,
        builder: (_) => TaskDrawer(row: r),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Text(r.taskId, style: theme.textTheme.labelMedium?.copyWith(color: theme.colorScheme.primary, fontWeight: FontWeight.w700)),
            if (r.projectNumber != null && r.projectNumber!.isNotEmpty) Text('  ·  ${r.projectNumber}', style: theme.textTheme.labelMedium?.copyWith(color: Colors.black54)),
            const Spacer(),
            _Pill(pillLabel(r.pillKey), pillColor(r.pillKey)),
          ]),
          const SizedBox(height: 2),
          Text(r.name, style: theme.textTheme.titleSmall, maxLines: 2, overflow: TextOverflow.ellipsis),
          Text([r.party, _label(r.vertical), _label(r.nature)].where((s) => s != null && s.isNotEmpty && s != '—').join(' · '), style: theme.textTheme.bodySmall?.copyWith(color: Colors.black54), maxLines: 2),
          const SizedBox(height: 4),
          Row(children: [
            _Pill(_label(r.priority), priorityColor(r.priority)),
            const SizedBox(width: 8),
            Icon(Icons.schedule, size: 14, color: r.overdue ? theme.colorScheme.error : Colors.black45),
            const SizedBox(width: 4),
            Expanded(child: Text(_dt(r.dueAt), style: theme.textTheme.bodySmall?.copyWith(color: r.overdue ? theme.colorScheme.error : null))),
            if (r.assignedBy != null) Text(r.assignedBy!, style: theme.textTheme.bodySmall?.copyWith(color: Colors.black45)),
            const Icon(Icons.chevron_right, size: 18, color: Colors.black38),
          ]),
          const Divider(height: 18),
        ]),
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

class _PerformanceTrend extends StatefulWidget {
  const _PerformanceTrend(this.d);

  final DesignerDashboard d;

  @override
  State<_PerformanceTrend> createState() => _PerformanceTrendState();
}

class _PerformanceTrendState extends State<_PerformanceTrend> {
  int _tab = 0;

  static const _blue = Color(0xFF2563EB);
  static const _purple = Color(0xFF9333EA);
  static const _green = Color(0xFF16A34A);
  static const _amber = Color(0xFFF59E0B);
  static const _rose = Color(0xFFE11D48);

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
    final active = _tab == 0 ? progress : issues;

    return SectionCard(
      title: 'Performance Trend',
      subtitle: 'Track the status-wise performance of your tasks over time.',
      trailing: Text(d.trendContext, key: const Key('trend-context'), style: Theme.of(context).textTheme.labelSmall),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        StatGrid(tiles: [
          for (final c in d.trendCards)
            StatTile(key: Key('trend-${c.label}'), label: c.label, value: '${c.value}', accent: hexColor(c.colorHex)),
        ]),
        const SizedBox(height: 14),
        SegmentedButton<int>(
          key: const Key('trend-tabs'),
          showSelectedIcon: false,
          segments: const [
            ButtonSegment(value: 0, label: Text('Assigned · In Progress · Completed', maxLines: 2, textAlign: TextAlign.center, style: TextStyle(fontSize: 11))),
            ButtonSegment(value: 1, label: Text('Overdue & Completed · Declined', maxLines: 2, textAlign: TextAlign.center, style: TextStyle(fontSize: 11))),
          ],
          selected: {_tab},
          onSelectionChanged: (s) => setState(() => _tab = s.first),
        ),
        const SizedBox(height: 12),
        Legend(active),
        const SizedBox(height: 8),
        if (months.isEmpty)
          const Padding(padding: EdgeInsets.all(20), child: Text('No data yet.'))
        else
          _tab == 0 ? TrendLineChart(key: const Key('chart-line'), months: months, series: progress) : TrendBarChart(key: const Key('chart-bars'), months: months, series: issues),
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
      title: 'Designer Reviews',
      subtitle: 'Recent client reviews and ratings for completed tasks.',
      child: reviews.isEmpty
          ? const Padding(padding: EdgeInsets.symmetric(vertical: 16), child: Center(child: Text('No BD comments yet on your completed tasks.', key: Key('reviews-empty'))))
          : Column(children: [
              SizedBox(
                height: 190,
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
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(color: _tones[i % _tones.length], borderRadius: BorderRadius.circular(14)),
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              HalfStars(r.rating),
                              const SizedBox(height: 6),
                              Expanded(child: Text('“${r.comment}”', maxLines: 4, overflow: TextOverflow.ellipsis, style: theme.textTheme.bodyMedium?.copyWith(fontStyle: FontStyle.italic))),
                              Text('${r.taskId ?? ''} · ${r.taskName ?? ''}', maxLines: 1, overflow: TextOverflow.ellipsis, style: theme.textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w700)),
                              Text('${r.reviewer ?? 'BD'}  ·  Reviewed: ${_dt(r.reviewedAt, 'd MMM yyyy, h:mm a')}', style: theme.textTheme.bodySmall?.copyWith(color: Colors.black54, fontSize: 11)),
                              Text('Completed: ${_dt(r.completedAt, 'd MMM yyyy, h:mm a')}', style: theme.textTheme.bodySmall?.copyWith(color: Colors.black54, fontSize: 11)),
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
      title: 'My Requests — Swap, Decline & Split',
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
