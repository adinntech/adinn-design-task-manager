/// Models for `GET /designer/dashboard` (see Api\Designer\DashboardController).
library;

DateTime? _date(Object? v) => v == null ? null : DateTime.tryParse('$v')?.toLocal();
/// A "yyyy-MM-dd" day as a local date (no time zone shift).
DateTime? _day(Object? v) {
  final m = RegExp(r'^(\d{4})-(\d{2})-(\d{2})').firstMatch('${v ?? ''}');
  return m == null ? null : DateTime(int.parse(m.group(1)!), int.parse(m.group(2)!), int.parse(m.group(3)!));
}

int _int(Object? v) => v is int ? v : (v is num ? v.toInt() : int.tryParse('$v') ?? 0);
double? _dbl(Object? v) => v == null ? null : (v is num ? v.toDouble() : double.tryParse('$v'));

/// One row of "Your Task Details": the task plus progress, timeliness and rating.
class DashRow {
  DashRow({
    required this.id,
    required this.taskId,
    required this.name,
    required this.status,
    required this.priority,
    this.projectNumber,
    this.party,
    this.vertical,
    this.nature,
    this.assignedBy,
    this.designer,
    this.assignedAt,
    this.dueAt,
    this.total = 0,
    this.done = 0,
    this.remaining = 0,
    this.percentage = 0,
    this.reworkCount = 0,
    this.reworkCreatives = 0,
    this.completedAt,
    this.overdue = false,
    this.completionStatus = 'in_progress',
    this.completionDays = 0,
    this.rating,
  });

  final int id;
  final String taskId;
  final String name;
  final String status;
  final String priority;
  final String? projectNumber;
  final String? party;
  final String? vertical;
  final String? nature;
  final String? assignedBy;
  final String? designer;
  final DateTime? assignedAt;
  final DateTime? dueAt;
  final int total;
  final int done;
  final int remaining;
  final int percentage;
  final int reworkCount;
  final int reworkCreatives;
  final DateTime? completedAt;
  final bool overdue;

  /// on_time | late | overdue | in_progress — judged against the original due date.
  final String completionStatus;
  final int completionDays;
  final double? rating;

  /// What the web table calls the pill: an incomplete task past its due date reads "Overdue" whatever its status.
  String get pillKey => overdue ? 'overdue' : status;

  factory DashRow.fromJson(Map<String, dynamic> j) {
    final c = (j['completion'] as Map?)?.cast<String, dynamic>() ?? const {};
    return DashRow(
      id: _int(j['id']),
      taskId: (j['task_id'] ?? '') as String,
      name: (j['name'] ?? '') as String,
      status: (j['status'] ?? '') as String,
      priority: (j['priority'] ?? 'medium') as String,
      projectNumber: j['project_number'] as String?,
      party: j['party'] as String?,
      vertical: j['vertical'] as String?,
      nature: j['task_nature'] as String?,
      assignedBy: j['assigned_by'] as String?,
      designer: j['designer'] as String?,
      assignedAt: _date(j['assigned_at']),
      dueAt: _date(j['due_at']),
      total: _int(j['total_creatives']),
      done: _int(j['done']),
      remaining: _int(j['remaining']),
      percentage: _int(j['percentage']),
      reworkCount: _int(j['rework_count']),
      reworkCreatives: _int(j['rework_creatives']),
      completedAt: _date(j['completed_at']),
      overdue: j['overdue'] == true,
      completionStatus: (c['status'] ?? 'in_progress') as String,
      completionDays: _int(c['days']),
      rating: _dbl(j['rating']),
    );
  }
}

class TrendMonth {
  TrendMonth(this.label, this.assigned, this.inProgress, this.completed, this.overdueCompleted, this.declined);

  final String label;
  final int assigned;
  final int inProgress;
  final int completed;
  final int overdueCompleted;
  final int declined;

  factory TrendMonth.fromJson(Map<String, dynamic> j) => TrendMonth(
        (j['label'] ?? '') as String,
        _int(j['assigned']),
        _int(j['in_progress']),
        _int(j['completed']),
        _int(j['overdue_completed']),
        _int(j['declined']),
      );
}

class TrendCard {
  TrendCard(this.label, this.value, this.colorHex);

  final String label;
  final int value;
  final String colorHex;

  factory TrendCard.fromJson(Map<String, dynamic> j) => TrendCard((j['label'] ?? '') as String, _int(j['value']), (j['color'] ?? '#2970ff') as String);
}

class ReviewCard {
  ReviewCard({required this.rating, required this.comment, this.taskDbId, this.taskId, this.taskName, this.reviewer, this.reviewedAt, this.completedAt});

  final double rating;
  final String comment;
  final int? taskDbId;
  final String? taskId;
  final String? taskName;
  final String? reviewer;
  final DateTime? reviewedAt;
  final DateTime? completedAt;

  factory ReviewCard.fromJson(Map<String, dynamic> j) => ReviewCard(
        rating: _dbl(j['rating']) ?? 0,
        comment: (j['comment'] ?? '') as String,
        taskDbId: j['task_db_id'] as int?,
        taskId: j['task_id'] as String?,
        taskName: j['task_name'] as String?,
        reviewer: j['reviewer'] as String?,
        reviewedAt: _date(j['reviewed_at']),
        completedAt: _date(j['completed_at']),
      );
}

class MyRequest {
  MyRequest({
    required this.id,
    required this.type,
    required this.status,
    this.taskDbId,
    this.taskId,
    this.taskName,
    this.requestedBy,
    this.requestedAt,
    this.respondedBy,
    this.respondedAt,
    this.currentHandler,
  });

  final int id;
  final String type; // swap | split | decline | status_change
  final String status; // pending | approved | rejected
  final int? taskDbId;
  final String? taskId;
  final String? taskName;
  final String? requestedBy;
  final DateTime? requestedAt;
  final String? respondedBy;
  final DateTime? respondedAt;
  final String? currentHandler;

  String get typeLabel => type == 'status_change' ? 'Status change' : '${type[0].toUpperCase()}${type.substring(1)}';

  factory MyRequest.fromJson(Map<String, dynamic> j) => MyRequest(
        id: _int(j['id']),
        type: (j['type'] ?? '') as String,
        status: (j['status'] ?? 'pending') as String,
        taskDbId: j['task_db_id'] as int?,
        taskId: j['task_id'] as String?,
        taskName: j['task_name'] as String?,
        requestedBy: j['requested_by'] as String?,
        requestedAt: _date(j['requested_at']),
        respondedBy: j['responded_by'] as String?,
        respondedAt: _date(j['responded_at']),
        currentHandler: j['current_handler'] as String?,
      );
}

/// The month the dashboard is scoped to (`period` in `GET /designer/dashboard`).
class DashPeriod {
  DashPeriod({
    required this.key,
    required this.label,
    required this.isCurrent,
    required this.year,
    required this.month,
    required this.years,
    this.mode = 'month',
    this.from,
    this.to,
    this.firstDate,
  });

  final String key; // yyyy-MM, "overall" or "range"
  final String label; // November 2026 / Overall / 01 Oct 2026 – 15 Nov 2026
  final bool isCurrent;
  final int year;
  final int month;
  final List<int> years; // newest first

  /// month | overall | range
  final String mode;
  final DateTime? from;
  final DateTime? to;

  /// The day of the first ticket (the earliest a custom range can start).
  final DateTime? firstDate;

  factory DashPeriod.current() {
    final n = DateTime.now();
    const names = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    return DashPeriod(key: '${n.year}-${n.month.toString().padLeft(2, '0')}', label: '${names[n.month - 1]} ${n.year}', isCurrent: true, year: n.year, month: n.month, years: [n.year]);
  }

  factory DashPeriod.fromJson(Map<String, dynamic> j) {
    final fallback = DashPeriod.current();
    return DashPeriod(
      key: (j['key'] ?? fallback.key) as String,
      label: (j['label'] ?? fallback.label) as String,
      isCurrent: j['is_current'] != false,
      year: j['year'] == null ? fallback.year : _int(j['year']),
      month: j['month'] == null ? fallback.month : _int(j['month']),
      years: ((j['years'] as List?) ?? const []).map(_int).toList()..sort((a, b) => b.compareTo(a)),
      mode: (j['mode'] ?? 'month') as String,
      from: _day(j['from']),
      to: _day(j['to']),
      firstDate: _day(j['first_date']),
    );
  }
}

class DesignerDashboard {
  DesignerDashboard({
    required this.name,
    required this.stats,
    required this.completionRate,
    required this.ratingAverage,
    required this.ratedCount,
    required this.completedTotal,
    required this.reworkCycles,
    required this.reworkCreatives,
    required this.requestCounts,
    required this.recent,
    required this.trendContext,
    required this.trendCards,
    required this.trendLine,
    required this.reviews,
    required this.myRequests,
    required this.period,
    this.lastLogin,
  });

  final String name;
  final DateTime? lastLogin;
  final Map<String, int> stats;
  final int completionRate;
  final double? ratingAverage;
  final int ratedCount;
  final int completedTotal;
  final int reworkCycles;
  final int reworkCreatives;
  final Map<String, int> requestCounts;
  final List<DashRow> recent;
  final String trendContext;
  final List<TrendCard> trendCards;
  final List<TrendMonth> trendLine;
  final List<ReviewCard> reviews;
  final List<MyRequest> myRequests;
  final DashPeriod period;

  int stat(String key) => stats[key] ?? 0;

  factory DesignerDashboard.fromJson(Map<String, dynamic> j) {
    Map<String, int> ints(Object? raw) => ((raw as Map?) ?? {}).map((k, v) => MapEntry('$k', _int(v)));
    List<T> list<T>(Object? raw, T Function(Map<String, dynamic>) f) => ((raw as List?) ?? []).map((e) => f(e as Map<String, dynamic>)).toList();

    final user = (j['user'] as Map?)?.cast<String, dynamic>() ?? const {};
    final rating = (j['overall_rating'] as Map?)?.cast<String, dynamic>() ?? const {};
    final rework = (j['overall_rework'] as Map?)?.cast<String, dynamic>() ?? const {};
    final trend = (j['trend'] as Map?)?.cast<String, dynamic>() ?? const {};

    return DesignerDashboard(
      name: (user['name'] ?? '') as String,
      lastLogin: _date(user['last_login_at']),
      stats: ints(j['stats']),
      completionRate: _int(j['completion_rate']),
      ratingAverage: _dbl(rating['average']),
      ratedCount: _int(rating['rated']),
      completedTotal: _int(rating['total']),
      reworkCycles: _int(rework['cycles']),
      reworkCreatives: _int(rework['creatives']),
      requestCounts: ints(j['request_counts']),
      recent: list(j['recent_tasks'], DashRow.fromJson),
      trendContext: (trend['context'] ?? '') as String,
      trendCards: list(trend['cards'], TrendCard.fromJson),
      trendLine: list(trend['line'], TrendMonth.fromJson),
      reviews: list(j['reviews'], ReviewCard.fromJson),
      myRequests: list(j['my_requests'], MyRequest.fromJson),
      period: j['period'] is Map ? DashPeriod.fromJson((j['period'] as Map).cast<String, dynamic>()) : DashPeriod.current(),
    );
  }
}
