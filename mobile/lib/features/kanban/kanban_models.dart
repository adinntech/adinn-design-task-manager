/// Models for the designer "My Tasks" kanban (`GET /designer/tasks`, see Api\Designer\TaskController::index).
library;

DateTime? _date(Object? v) => v == null ? null : DateTime.tryParse('$v')?.toLocal();
int _int(Object? v) => v is int ? v : (v is num ? v.toInt() : int.tryParse('$v') ?? 0);

class BoardTag {
  BoardTag(this.label, this.title, this.tone);

  final String label;
  final String title;

  /// pending | approved | declined
  final String tone;

  factory BoardTag.fromJson(Map<String, dynamic> j) =>
      BoardTag((j['label'] ?? '') as String, (j['title'] ?? j['label'] ?? '') as String, (j['tone'] ?? 'pending') as String);
}

class CardProgress {
  CardProgress(this.completed, this.remaining, this.percentage);

  final int completed;
  final int remaining;
  final int percentage;

  factory CardProgress.fromJson(Map<String, dynamic> j) => CardProgress(_int(j['completed']), _int(j['remaining']), _int(j['percentage']));
}

class KanbanCard {
  KanbanCard({
    required this.id,
    required this.taskId,
    required this.name,
    required this.status,
    required this.priority,
    this.partyType,
    this.partyName,
    this.vertical,
    this.totalCreatives = 0,
    this.dueAt,
    this.assigner,
    this.createdAt,
    this.completedAt,
    this.rating,
    this.nowWith,
    this.progress,
    this.waitingSince,
    this.continuationLabel,
    this.continuationEvent,
    this.previousMonthLabel,
    this.tag,
    this.forwardTo = const [],
    this.backwardTo = const [],
  });

  final int id;
  final String taskId;
  final String name;
  final String status;
  final String priority;
  final String? partyType;
  final String? partyName;
  final String? vertical;
  final int totalCreatives;
  final DateTime? dueAt;
  final String? assigner;
  final DateTime? createdAt;
  final DateTime? completedAt;
  final double? rating;
  final String? nowWith;
  final CardProgress? progress;
  final DateTime? waitingSince;
  final String? continuationLabel;
  final String? continuationEvent;
  final String? previousMonthLabel;
  final BoardTag? tag;

  /// Statuses the server's state machine lets this designer move the card to / request a move back to.
  final List<String> forwardTo;
  final List<String> backwardTo;

  bool get canMove => forwardTo.isNotEmpty || backwardTo.isNotEmpty;

  /// The due pill the web card shows: Completed / Overdue / Due Today / Due Soon (≤ 48 h) / On Track.
  DuePill duePill(DateTime now) {
    if (status == 'completed') return DuePill.completed;
    final due = dueAt;
    if (due == null) return DuePill.onTrack;
    if (due.isBefore(now)) return DuePill.overdue;
    if (due.year == now.year && due.month == now.month && due.day == now.day) return DuePill.dueToday;
    if (due.difference(now).inHours <= 48) return DuePill.dueSoon;
    return DuePill.onTrack;
  }

  factory KanbanCard.fromJson(Map<String, dynamic> j) => KanbanCard(
        id: _int(j['id']),
        taskId: (j['task_id'] ?? '') as String,
        name: ((j['display_task_name'] ?? j['task_name']) ?? '') as String,
        status: (j['status'] ?? '') as String,
        priority: (j['priority'] ?? 'medium') as String,
        partyType: j['party_type'] as String?,
        partyName: j['party_name'] as String?,
        vertical: j['vertical'] as String?,
        totalCreatives: _int(j['total_creatives']),
        dueAt: _date(j['due_at']),
        assigner: j['assigner'] as String?,
        createdAt: _date(j['created_at']),
        completedAt: _date(j['completed_at']),
        rating: j['rating'] == null ? null : (j['rating'] as num).toDouble(),
        nowWith: j['now_with'] as String?,
        progress: j['progress'] is Map ? CardProgress.fromJson((j['progress'] as Map).cast<String, dynamic>()) : null,
        waitingSince: _date(j['waiting_since']),
        continuationLabel: j['continuation_label'] as String?,
        continuationEvent: j['continuation_event_label'] as String?,
        previousMonthLabel: j['previous_month_label'] as String?,
        tag: j['tag'] is Map ? BoardTag.fromJson((j['tag'] as Map).cast<String, dynamic>()) : null,
        forwardTo: ((j['forward_to'] as List?) ?? []).map((e) => '$e').toList(),
        backwardTo: ((j['backward_to'] as List?) ?? []).map((e) => '$e').toList(),
      );
}

enum DuePill {
  completed('Completed'),
  overdue('Overdue'),
  dueToday('Due Today'),
  dueSoon('Due Soon'),
  onTrack('On Track');

  const DuePill(this.label);

  final String label;
}

class KanbanColumn {
  KanbanColumn(this.key, this.label, {this.isSplitLog = false, this.isAll = false});

  /// The first tab: every ticket in one list, each labelled with the stage it is in.
  factory KanbanColumn.all() => KanbanColumn(allKey, 'All Tickets', isAll: true);

  static const allKey = 'all';

  final String key;
  final String label;
  final bool isSplitLog;
  final bool isAll;

  factory KanbanColumn.fromJson(Map<String, dynamic> j) =>
      KanbanColumn(j['key'] as String, (j['label'] ?? j['key']) as String, isSplitLog: j['type'] == 'split_log');
}

/// One ticket in the Split Tasks column. An approved split has a child task; a pending, rejected or
/// "completed all before approval" one does not, and opens the original task instead.
class SplitEntry {
  SplitEntry({
    required this.id,
    required this.taskId,
    required this.name,
    this.requestId,
    this.hasChild = true,
    this.statusKey = 'approved',
    this.statusLabel = 'Split approved',
    this.splitFrom,
    this.designer,
    this.creatives = 0,
    this.requestedSplit,
    this.approvedAt,
    this.requestedAt,
  });

  /// The task the card opens (the child when there is one, otherwise the original).
  final int id;
  final int? requestId;
  final String taskId;
  final String name;
  final bool hasChild;

  /// approved | pending | rejected | completed_all
  final String statusKey;
  final String statusLabel;
  final String? splitFrom;
  final String? designer;
  final int creatives;
  final int? requestedSplit;
  final DateTime? approvedAt;
  final DateTime? requestedAt;

  factory SplitEntry.fromJson(Map<String, dynamic> j) {
    final status = (j['status'] as Map?)?.cast<String, dynamic>();
    return SplitEntry(
      id: _int(j['id']),
      requestId: j['request_id'] == null ? null : _int(j['request_id']),
      taskId: (j['task_id'] ?? '') as String,
      name: (j['name'] ?? '') as String,
      hasChild: j['has_child'] != false, // older payloads were approved splits only
      statusKey: (status?['key'] ?? 'approved') as String,
      statusLabel: (status?['label'] ?? 'Split approved') as String,
      splitFrom: j['split_from'] as String?,
      designer: j['designer'] as String?,
      creatives: _int(j['creatives']),
      requestedSplit: j['requested_split'] == null ? null : _int(j['requested_split']),
      approvedAt: _date(j['approved_at']),
      requestedAt: _date(j['requested_at']),
    );
  }
}

class BdOption {
  BdOption(this.id, this.name);

  final int id;
  final String name;
}

class KanbanBoard {
  KanbanBoard({
    required this.columns,
    required this.cards,
    required this.splitLog,
    required this.stats,
    required this.activeBreakdown,
    required this.periodLabel,
    required this.bds,
  });

  final List<KanbanColumn> columns;
  final List<KanbanCard> cards;
  final List<SplitEntry> splitLog;
  final Map<String, int> stats;
  final List<({String label, int count})> activeBreakdown;
  final String periodLabel;
  final List<BdOption> bds;

  List<KanbanCard> inColumn(String key) => cards.where((c) => c.status == key).toList();

  int countFor(KanbanColumn c) => c.isAll ? cards.length : (c.isSplitLog ? splitLog.length : inColumn(c.key).length);

  /// Tolerant of older/minimal payloads (only `statuses` + `tasks`), which is all the plain board needs.
  factory KanbanBoard.fromJson(Map<String, dynamic> j) {
    final rawColumns = (j['columns'] ?? j['statuses']) as List? ?? const [];
    final cards = ((j['tasks'] as List?) ?? []).map((t) => KanbanCard.fromJson((t as Map).cast<String, dynamic>())).toList();

    var columns = rawColumns.map((c) => KanbanColumn.fromJson((c as Map).cast<String, dynamic>())).toList();
    // Defensive: a response with cards but no column list still shows its cards, one column per status.
    if (columns.isEmpty && cards.isNotEmpty) {
      columns = [for (final s in {for (final c in cards) c.status}) KanbanColumn(s, s.replaceAll('_', ' '))];
    }

    return KanbanBoard(
      columns: columns,
      cards: cards,
      splitLog: ((j['split_log'] as List?) ?? []).map((s) => SplitEntry.fromJson((s as Map).cast<String, dynamic>())).toList(),
      stats: ((j['stats'] as Map?) ?? {}).map((k, v) => MapEntry('$k', _int(v))),
      activeBreakdown: ((j['active_breakdown'] as List?) ?? [])
          .map((r) => (label: ((r as Map)['label'] ?? '') as String, count: _int(r['count'])))
          .toList(),
      periodLabel: (j['period_label'] ?? '') as String,
      bds: ((j['bds'] as List?) ?? []).map((b) => BdOption(_int((b as Map)['id']), (b['name'] ?? '') as String)).toList(),
    );
  }
}

/// The board's filters, mirroring the web toolbar.
class BoardFilters {
  const BoardFilters({
    this.search = '',
    this.vertical = '',
    this.priority = '',
    this.bdId,
    this.projectNumber = '',
    this.period = 'current_month',
    this.dateFrom,
    this.dateTo,
  });

  final String search;
  final String vertical;
  final String priority;
  final int? bdId;
  final String projectNumber;
  final String period; // current_month | last_month | custom
  final DateTime? dateFrom;
  final DateTime? dateTo;

  bool get isDefault => this == const BoardFilters();

  /// Web hides empty columns while a text search is active.
  bool get isSearching => search.trim().isNotEmpty || projectNumber.trim().isNotEmpty;

  BoardFilters copyWith({
    String? search,
    String? vertical,
    String? priority,
    Object? bdId = _keep,
    String? projectNumber,
    String? period,
    Object? dateFrom = _keep,
    Object? dateTo = _keep,
  }) =>
      BoardFilters(
        search: search ?? this.search,
        vertical: vertical ?? this.vertical,
        priority: priority ?? this.priority,
        bdId: identical(bdId, _keep) ? this.bdId : bdId as int?,
        projectNumber: projectNumber ?? this.projectNumber,
        period: period ?? this.period,
        dateFrom: identical(dateFrom, _keep) ? this.dateFrom : dateFrom as DateTime?,
        dateTo: identical(dateTo, _keep) ? this.dateTo : dateTo as DateTime?,
      );

  static const Object _keep = Object();

  static String _ymd(DateTime d) => '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  Map<String, dynamic> toQuery() => {
        if (search.trim().isNotEmpty) 'search': search.trim(),
        if (vertical.isNotEmpty) 'vertical': vertical,
        if (priority.isNotEmpty) 'priority': priority,
        if (bdId != null) 'bd_id': bdId,
        if (projectNumber.trim().isNotEmpty) 'project_number': projectNumber.trim(),
        if (period != 'current_month') 'period': period,
        if (period == 'custom' && dateFrom != null) 'date_from': _ymd(dateFrom!),
        if (period == 'custom' && dateTo != null) 'date_to': _ymd(dateTo!),
      };

  @override
  bool operator ==(Object other) =>
      other is BoardFilters &&
      other.search == search &&
      other.vertical == vertical &&
      other.priority == priority &&
      other.bdId == bdId &&
      other.projectNumber == projectNumber &&
      other.period == period &&
      other.dateFrom == dateFrom &&
      other.dateTo == dateTo;

  @override
  int get hashCode => Object.hash(search, vertical, priority, bdId, projectNumber, period, dateFrom, dateTo);
}
