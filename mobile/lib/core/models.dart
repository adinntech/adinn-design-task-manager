/// Plain models matching the Laravel API (no codegen — the shapes are small and stable).
library;

class AppUser {
  AppUser({required this.id, required this.name, required this.email, required this.role, this.roleName});

  final int id;
  final String name;
  final String email;
  final String role; // admin | bd | designer | designer_head
  final String? roleName;

  factory AppUser.fromJson(Map<String, dynamic> j) => AppUser(
        id: j['id'] as int,
        name: (j['name'] ?? '') as String,
        email: (j['email'] ?? '') as String,
        role: (j['role'] ?? '') as String,
        roleName: j['role_name'] as String?,
      );

  Map<String, dynamic> toJson() => {'id': id, 'name': name, 'email': email, 'role': role, 'role_name': roleName};

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

class ChatAttachment {
  ChatAttachment({required this.name, required this.url});

  final String name;
  final String url;
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
          .map((a) => ChatAttachment(name: (a['name'] ?? 'file') as String, url: (a['url'] ?? '') as String))
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
