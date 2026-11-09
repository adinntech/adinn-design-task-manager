/// Models for `GET /bd/dashboard` (see Api\Bd\DashboardController).
library;

import 'dashboard_models.dart';

DateTime? _date(Object? v) => v == null ? null : DateTime.tryParse('$v')?.toLocal();
int _int(Object? v) => v is int ? v : (v is num ? v.toInt() : int.tryParse('$v') ?? 0);
double? _dbl(Object? v) => v == null ? null : (v is num ? v.toDouble() : double.tryParse('$v'));

class BdOption {
  BdOption(this.value, this.label);

  final String value;
  final String label;
}

class BarStat {
  BarStat(this.key, this.label, this.value, this.colorHex);

  final String key;
  final String label;
  final int value;
  final String colorHex;
}

class WorkloadRow {
  WorkloadRow(this.id, this.name, this.n, this.rating);

  final int id;
  final String name;
  final Map<String, int> n;
  final double? rating;

  int operator [](String k) => n[k] ?? 0;
}

class OverdueRow {
  OverdueRow({required this.id, required this.taskId, required this.name, required this.status, this.designer, this.dueAt, this.days = 0, this.done = 0, this.total = 0, this.percentage = 0});

  final int id;
  final String taskId;
  final String name;
  final String status;
  final String? designer;
  final DateTime? dueAt;
  final int days;
  final int done;
  final int total;
  final int percentage;
}

class ReworkRow {
  ReworkRow({required this.id, required this.taskId, required this.name, required this.status, this.designer, this.number = 0, this.assignedAt, this.creatives, this.spent});

  final int id;
  final String taskId;
  final String name;
  final String status;
  final String? designer;
  final int number;
  final DateTime? assignedAt;
  final int? creatives;
  final String? spent;
}

class RatingRow {
  RatingRow({this.taskDbId, this.taskId, this.taskName, this.designer, this.reviewer, this.rating, this.comment, this.reviewedAt});

  final int? taskDbId;
  final String? taskId;
  final String? taskName;
  final String? designer;
  final String? reviewer;
  final double? rating;
  final String? comment;
  final DateTime? reviewedAt;
}

class CompletionRow {
  CompletionRow({required this.id, required this.taskId, required this.name, this.designer, this.completedAt, this.duration, this.rating, this.reworkCount = 0});

  final int id;
  final String taskId;
  final String name;
  final String? designer;
  final DateTime? completedAt;
  final String? duration;
  final double? rating;
  final int reworkCount;
}

class BdDashboard {
  BdDashboard({
    required this.name,
    required this.stats,
    required this.designers,
    required this.months,
    required this.selectedDesigner,
    required this.selectedMonth,
    required this.designerName,
    required this.monthLabel,
    required this.bar,
    required this.trendContext,
    required this.trendCards,
    required this.trendLine,
    required this.workload,
    required this.recent,
    required this.overdue,
    required this.rework,
    required this.ratings,
    required this.completions,
    this.lastLogin,
  });

  final String name;
  final DateTime? lastLogin;
  final Map<String, int> stats;
  final List<BdOption> designers;
  final List<BdOption> months;
  final String selectedDesigner; // 'all' or a user id
  final String selectedMonth; // yyyy-MM
  final String? designerName;
  final String monthLabel;
  final List<BarStat> bar;
  final String trendContext;
  final List<TrendCard> trendCards;
  final List<TrendMonth> trendLine;
  final List<WorkloadRow> workload;
  final List<DashRow> recent;
  final List<OverdueRow> overdue;
  final List<ReworkRow> rework;
  final List<RatingRow> ratings;
  final List<CompletionRow> completions;

  int stat(String key) => stats[key] ?? 0;

  int get completionRate => stat('total_tasks') > 0 ? (stat('completed') * 100 / stat('total_tasks')).round() : 0;

  String get scope => '$monthLabel · ${designerName ?? 'All Designers'}';

  factory BdDashboard.fromJson(Map<String, dynamic> j) {
    List<T> list<T>(Object? raw, T Function(Map<String, dynamic>) f) => ((raw as List?) ?? []).map((e) => f(e as Map<String, dynamic>)).toList();
    Map<String, int> ints(Object? raw) => ((raw as Map?) ?? {}).map((k, v) => MapEntry('$k', _int(v)));

    final user = (j['user'] as Map?)?.cast<String, dynamic>() ?? const {};
    final f = (j['filters'] as Map?)?.cast<String, dynamic>() ?? const {};
    final trend = (j['trend'] as Map?)?.cast<String, dynamic>() ?? const {};

    return BdDashboard(
      name: (user['name'] ?? '') as String,
      lastLogin: _date(user['last_login_at']),
      stats: ints(j['stats']),
      designers: list(f['designers'], (d) => BdOption('${d['id']}', (d['name'] ?? '') as String)),
      months: list(f['months'], (m) => BdOption('${m['value']}', (m['label'] ?? '') as String)),
      selectedDesigner: f['designer'] == null ? 'all' : '${f['designer']}',
      selectedMonth: (f['month'] ?? '') as String,
      designerName: f['designer_name'] as String?,
      monthLabel: (f['month_label'] ?? '') as String,
      bar: list(j['bar'], (b) => BarStat('${b['key']}', (b['label'] ?? '') as String, _int(b['value']), (b['color'] ?? '#2970ff') as String)),
      trendContext: (trend['context'] ?? '') as String,
      trendCards: list(trend['cards'], TrendCard.fromJson),
      trendLine: list(trend['line'], TrendMonth.fromJson),
      workload: list(j['workload'], (w) => WorkloadRow(_int(w['id']), (w['name'] ?? '') as String, ints(w), _dbl(w['rating']))),
      recent: list(j['recent_tasks'], DashRow.fromJson),
      overdue: list(
        j['overdue'],
        (o) => OverdueRow(
          id: _int(o['id']),
          taskId: (o['task_id'] ?? '') as String,
          name: (o['name'] ?? '') as String,
          status: (o['status'] ?? '') as String,
          designer: o['designer'] as String?,
          dueAt: _date(o['due_at']),
          days: _int(o['days']),
          done: _int(o['done']),
          total: _int(o['total']),
          percentage: _int(o['percentage']),
        ),
      ),
      rework: list(
        j['rework'],
        (r) => ReworkRow(
          id: _int(r['id']),
          taskId: (r['task_id'] ?? '') as String,
          name: (r['name'] ?? '') as String,
          status: (r['status'] ?? '') as String,
          designer: r['designer'] as String?,
          number: _int(r['rework_number']),
          assignedAt: _date(r['rework_assigned_at']),
          creatives: r['rework_creatives'] == null ? null : _int(r['rework_creatives']),
          spent: r['rework_spent'] as String?,
        ),
      ),
      ratings: list(
        j['ratings'],
        (r) => RatingRow(
          taskDbId: r['task_db_id'] as int?,
          taskId: r['task_id'] as String?,
          taskName: r['task_name'] as String?,
          designer: r['designer'] as String?,
          reviewer: r['reviewer'] as String?,
          rating: _dbl(r['rating']),
          comment: r['comment'] as String?,
          reviewedAt: _date(r['reviewed_at']),
        ),
      ),
      completions: list(
        j['completions'],
        (c) => CompletionRow(
          id: _int(c['id']),
          taskId: (c['task_id'] ?? '') as String,
          name: (c['name'] ?? '') as String,
          designer: c['designer'] as String?,
          completedAt: _date(c['completed_at']),
          duration: c['duration'] as String?,
          rating: _dbl(c['rating']),
          reworkCount: _int(c['rework_count']),
        ),
      ),
    );
  }
}
