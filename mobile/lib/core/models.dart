/// Plain models matching the Laravel API (no codegen — the shapes are small and stable).
library;

class AppUser {
  AppUser({required this.id, required this.name, required this.email, required this.role, this.roleName, this.isRequestApprover = false});

  final int id;
  final String name;
  final String email;
  final String role; // admin | bd | designer | designer_head
  final String? roleName;

  /// A designer the Admin allowed to approve teammates' Swap / Split requests.
  final bool isRequestApprover;

  factory AppUser.fromJson(Map<String, dynamic> j) => AppUser(
        id: j['id'] as int,
        name: (j['name'] ?? '') as String,
        email: (j['email'] ?? '') as String,
        role: (j['role'] ?? '') as String,
        roleName: j['role_name'] as String?,
        isRequestApprover: j['is_request_approver'] == true,
      );

  Map<String, dynamic> toJson() => {'id': id, 'name': name, 'email': email, 'role': role, 'role_name': roleName, 'is_request_approver': isRequestApprover};

  String get roleLabel => switch (role) {
        'admin' => 'Admin',
        'bd' => 'Business Development',
        'designer' => 'Designer',
        'designer_head' => 'Designer Head',
        _ => role,
      };
}

class TaskSummary {
  TaskSummary({
    required this.id,
    required this.taskId,
    required this.name,
    required this.status,
    required this.priority,
    this.vertical,
    this.party,
    this.dueAt,
    this.totalCreatives = 0,
    this.designerName,
  });

  final int id;
  final String taskId;
  final String name;
  final String status;
  final String priority;
  final String? vertical;
  final String? party;
  final DateTime? dueAt;
  final int totalCreatives;
  final String? designerName;

  bool get isOverdue => dueAt != null && status != 'completed' && dueAt!.isBefore(DateTime.now());

  factory TaskSummary.fromJson(Map<String, dynamic> j) => TaskSummary(
        id: j['id'] as int,
        taskId: (j['task_id'] ?? '') as String,
        name: ((j['display_task_name'] ?? j['task_name']) ?? '') as String,
        status: (j['status'] ?? '') as String,
        priority: (j['priority'] ?? 'medium') as String,
        vertical: j['vertical'] as String?,
        party: j['party_name'] as String?,
        dueAt: j['due_at'] == null ? null : DateTime.tryParse(j['due_at'] as String)?.toLocal(),
        totalCreatives: (j['total_creatives'] ?? 0) as int,
        designerName: (j['designer'] as Map<String, dynamic>?)?['name'] as String?,
      );
}

class StatusColumn {
  StatusColumn(this.key, this.label);

  final String key;
  final String label;

  factory StatusColumn.fromJson(Map<String, dynamic> j) => StatusColumn(j['key'] as String, j['label'] as String);
}

class Board {
  Board({required this.statuses, required this.tasks});

  final List<StatusColumn> statuses;
  final List<TaskSummary> tasks;

  List<TaskSummary> inStatus(String key) => tasks.where((t) => t.status == key).toList();
}

/// A designer request (decline / split / swap / status_change) awaiting a decision.
class RequestItem {
  RequestItem({
    required this.id,
    required this.type,
    required this.status,
    required this.reason,
    this.taskDbId,
    this.taskRef,
    this.taskName,
    this.requester,
    this.targetDesigner,
    this.splitCount,
    this.toStatus,
  });

  final int id;
  final String type;
  final String status;
  final String reason;
  final int? taskDbId;
  final String? taskRef;
  final String? taskName;
  final String? requester;
  final String? targetDesigner;
  final int? splitCount;
  final String? toStatus;

  bool get isPending => status.startsWith('pending');

  String get typeLabel => switch (type) {
        'decline' => 'Decline',
        'split' => 'Split',
        'swap' => 'Swap',
        'status_change' => 'Backward move',
        _ => type,
      };

  factory RequestItem.fromJson(Map<String, dynamic> j) {
    final task = j['task'] as Map<String, dynamic>?;
    final split = j['split_details'] as Map<String, dynamic>?;
    return RequestItem(
      id: j['id'] as int,
      type: (j['request_type'] ?? '') as String,
      status: (j['overall_status'] ?? '') as String,
      reason: (j['reason'] ?? '') as String,
      taskDbId: task?['id'] as int?,
      taskRef: task?['task_id'] as String?,
      taskName: (task?['display_task_name'] ?? task?['task_name']) as String?,
      requester: (j['requester'] as Map<String, dynamic>?)?['name'] as String?,
      targetDesigner: (j['target_designer'] as Map<String, dynamic>?)?['name'] as String?,
      splitCount: split?['creative_count'] is int ? split!['creative_count'] as int : int.tryParse('${split?['creative_count'] ?? ''}'),
      toStatus: j['to_status'] as String?,
    );
  }
}

class Designer {
  Designer(this.id, this.name);

  final int id;
  final String name;

  factory Designer.fromJson(Map<String, dynamic> j) => Designer(j['id'] as int, (j['name'] ?? '') as String);
}

class ChatAttachment {
  ChatAttachment({required this.name, required this.url, this.mimeType});

  final String name;
  final String url;
  final String? mimeType;
}

class ChatMessage {
  ChatMessage({
    required this.id,
    required this.text,
    required this.userId,
    required this.userName,
    required this.role,
    required this.createdAt,
    this.attachments = const [],
  });

  final int id;
  final String text;
  final int? userId;
  final String userName;
  final String role;
  final DateTime? createdAt;
  final List<ChatAttachment> attachments;

  factory ChatMessage.fromJson(Map<String, dynamic> j) {
    final user = j['user'] as Map<String, dynamic>?;
    return ChatMessage(
      id: j['id'] as int,
      text: (j['comment'] ?? '') as String,
      userId: user?['id'] as int?,
      userName: (user?['name'] ?? 'Unknown') as String,
      role: (user?['role'] ?? '') as String,
      createdAt: j['created_at'] == null ? null : DateTime.tryParse(j['created_at'] as String)?.toLocal(),
      attachments: ((j['attachments'] as List?) ?? [])
          .map((a) => ChatAttachment(name: (a['name'] ?? 'file') as String, url: (a['url'] ?? '') as String, mimeType: a['mime_type'] as String?))
          .toList(),
    );
  }
}

class AppNotification {
  AppNotification({required this.id, required this.read, required this.title, required this.message, this.taskId, this.createdAt});

  final String id;
  final bool read;
  final String title;
  final String message;
  final int? taskId;
  final DateTime? createdAt;

  factory AppNotification.fromJson(Map<String, dynamic> j) {
    final data = (j['data'] as Map?)?.cast<String, dynamic>() ?? {};
    return AppNotification(
      id: j['id'] as String,
      read: j['read'] == true,
      title: (data['title'] ?? 'Notification') as String,
      message: (data['message'] ?? '') as String,
      taskId: data['task_id'] is int ? data['task_id'] as int : int.tryParse('${data['task_id'] ?? ''}'),
      createdAt: j['created_at'] == null ? null : DateTime.tryParse(j['created_at'] as String)?.toLocal(),
    );
  }
}

String statusLabel(String key) {
  const labels = {
    'pending_bd_approval': 'Awaiting BD approval',
    'bd_rejected': 'Rejected by BD',
    'draft': 'Draft',
    'assigned_tasks': 'Assigned',
    'review_analysis': 'Review & analysis',
    'need_clarification': 'Need clarification',
    'yet_to_start': 'Yet to start',
    'in_progress': 'In progress',
    'waiting_confirmation': 'Waiting for BD review',
    'rework': 'Rework',
    'prepare_printing_file': 'Prepare printing file',
    'completed': 'Completed',
    'swap_tasks': 'Swapped',
  };
  return labels[key] ?? key.replaceAll('_', ' ');
}


/// One input on the BD task-create form, as described by `GET /bd/task-form` (derived server-side from the
/// same rules that validate the submission).
class FormFieldDef {
  FormFieldDef({
    required this.key,
    required this.label,
    required this.type,
    required this.required,
    this.options = const [],
    this.columns = const [],
    this.orUpload,
    this.either,
  });

  final String key;
  final String label;

  /// text | multiline | integer | number | email | url | phone | choice | file | audio | rows
  final String type;
  final bool required;
  final List<String> options;
  final List<String> columns;
  final String? orUpload;
  final String? either;

  bool get isFile => type == 'file' || type == 'audio';

  factory FormFieldDef.fromJson(Map<String, dynamic> j) => FormFieldDef(
        key: j['key'] as String,
        label: (j['label'] ?? j['key']) as String,
        type: (j['type'] ?? 'text') as String,
        required: j['required'] == true,
        options: ((j['options'] as List?) ?? []).map((o) => '$o').toList(),
        columns: ((j['columns'] as List?) ?? []).map((o) => '$o').toList(),
        orUpload: j['or_upload'] as String?,
        either: j['either'] as String?,
      );
}

class Option {
  Option(this.key, this.label);

  final String key;
  final String label;

  factory Option.fromJson(Map<String, dynamic> j) => Option(j['key'] as String, (j['label'] ?? j['key']) as String);
}

class TaskFormDef {
  TaskFormDef({
    required this.verticals,
    required this.natures,
    required this.inhouseNatures,
    required this.fields,
    required this.priorities,
    required this.designers,
    this.maxDueAt,
  });

  final List<Option> verticals;
  final Map<String, List<Option>> natures;
  final Map<String, List<Option>> inhouseNatures;
  final Map<String, Map<String, List<FormFieldDef>>> fields;
  final List<String> priorities;
  final List<Designer> designers;
  final DateTime? maxDueAt;

  List<FormFieldDef> fieldsFor(String vertical, String nature) => fields[vertical]?[nature] ?? const [];

  static List<Option> _options(Object? raw) => ((raw as List?) ?? []).map((o) => Option.fromJson(o as Map<String, dynamic>)).toList();

  factory TaskFormDef.fromJson(Map<String, dynamic> j) {
    Map<String, List<Option>> optionMap(Object? raw) => ((raw as Map?) ?? {}).map((k, v) => MapEntry('$k', _options(v)));

    return TaskFormDef(
      verticals: _options(j['verticals']),
      natures: optionMap(j['natures']),
      inhouseNatures: optionMap(j['inhouse_natures']),
      fields: ((j['fields'] as Map?) ?? {}).map((vertical, natures) => MapEntry(
            '$vertical',
            (natures as Map).map((nature, list) => MapEntry(
                  '$nature',
                  (list as List).map((f) => FormFieldDef.fromJson(f as Map<String, dynamic>)).toList(),
                )),
          )),
      priorities: ((j['priorities'] as List?) ?? []).map((p) => '$p').toList(),
      designers: ((j['designers'] as List?) ?? []).map((d) => Designer.fromJson(d as Map<String, dynamic>)).toList(),
      maxDueAt: j['max_due_at'] == null ? null : DateTime.tryParse(j['max_due_at'] as String)?.toLocal(),
    );
  }
}

class ClientSuggestion {
  ClientSuggestion(this.name, this.contact, this.mobile);

  final String name;
  final String? contact;
  final String? mobile;

  factory ClientSuggestion.fromJson(Map<String, dynamic> j) =>
      ClientSuggestion((j['client_name'] ?? '') as String, j['contact_person_name'] as String?, j['mobile_number'] as String?);
}

class Availability {
  Availability(this.label, this.level, this.percent, {this.activeTasks, this.activeCreatives, this.checkedIn});

  final String label;
  final String level;
  final int percent;
  final int? activeTasks;
  final int? activeCreatives;
  final bool? checkedIn;

  factory Availability.fromJson(Map<String, dynamic> j) => Availability(
        (j['label'] ?? '') as String,
        (j['level'] ?? '') as String,
        (j['availability_percent'] ?? 0) as int,
        activeTasks: j['active_tasks'] as int?,
        activeCreatives: j['active_creatives'] as int?,
        checkedIn: j['checked_in'] as bool?,
      );
}
