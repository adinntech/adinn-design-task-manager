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
  Designer(this.id, this.name, {this.verticals = const [], this.skills = const []});

  final int id;
  final String name;

  /// Verticals the designer has experience in (keys) and their skills: shown when choosing who to assign.
  final List<String> verticals;
  final List<String> skills;

  factory Designer.fromJson(Map<String, dynamic> j) => Designer(
        j['id'] as int,
        (j['name'] ?? '') as String,
        verticals: ((j['experienced_verticals'] as List?) ?? []).map((v) => '$v').toList(),
        skills: ((j['skills'] as List?) ?? []).map((v) => '$v').toList(),
      );
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

  /// section | text | textarea | select | vehicle_select | number | url | email | location |
  /// file | files | mediafiles | zipfiles | audio | dimensions | sizes | media_sizes
  final String type;
  final bool required;
  final List<String> options;
  final List<String> columns;
  final String? orUpload;
  final String? either;

  bool get isFile => const {'file', 'files', 'mediafiles', 'zipfiles', 'audio'}.contains(type);
  bool get isRows => const {'dimensions', 'sizes', 'media_sizes'}.contains(type);
  bool get isSection => type == 'section';

  /// One file only (the others take several).
  bool get singleFile => type == 'file' || type == 'audio';

  /// The name the rows are posted under (the web form's `dimension_rows[0][name]`...).
  String get rowsKey => switch (type) {
        'dimensions' => 'dimension_rows',
        'sizes' => 'size_rows',
        'media_sizes' => 'media_size_rows',
        _ => key,
      };

  /// The web form's own definition: `[name, label, type, required, options]`.
  factory FormFieldDef.fromWeb(List<dynamic> t) {
    final type = '${t[2]}';
    return FormFieldDef(
      key: '${t[0]}',
      label: '${t[1]}',
      type: type,
      required: t.length > 3 && t[3] == true,
      options: t.length > 4 ? ((t[4] as List?) ?? []).map((o) => '$o').toList() : const [],
      columns: switch (type) {
        'dimensions' || 'sizes' => const ['name', 'width', 'height'],
        'media_sizes' => const ['name', 'width', 'height', 'ratio'],
        _ => const [],
      },
      orUpload: switch (type) {
        'dimensions' => 'dimension_upload',
        'sizes' => 'size_upload',
        _ => null,
      },
    );
  }

  /// The server's derived definition (required fields only), as an older server still sends it.
  factory FormFieldDef.fromJson(Map<String, dynamic> j) {
    final key = j['key'] as String;
    var type = (j['type'] ?? 'text') as String;
    type = switch (type) {
      'multiline' => 'textarea',
      'choice' => 'select',
      'integer' || 'number' => 'number',
      'phone' => 'text',
      'rows' => switch (key) {
          'size_rows' => 'sizes',
          'media_size_rows' => 'media_sizes',
          _ => 'dimensions',
        },
      _ => type,
    };
    return FormFieldDef(
      key: key,
      label: (j['label'] ?? key) as String,
      type: type,
      required: j['required'] == true,
      options: ((j['options'] as List?) ?? []).map((o) => '$o').toList(),
      columns: ((j['columns'] as List?) ?? []).map((o) => '$o').toList(),
      orUpload: j['or_upload'] as String?,
      either: j['either'] as String?,
    );
  }
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
    this.webForms = const {},
    this.vehicleImages = const {},
    this.locations = const [],
    this.creativeTypes = const [],
    this.inhouseClientName = 'Adinn Advertising Services Ltd',
  });

  final List<Option> verticals;
  final Map<String, List<Option>> natures;
  final Map<String, List<Option>> inhouseNatures;
  final Map<String, Map<String, List<FormFieldDef>>> fields;
  final List<String> priorities;
  final List<Designer> designers;
  final DateTime? maxDueAt;

  /// The web form's own field lists, by vertical then nature (with sections and every optional field). Empty on an older
  /// server, which then only has the required fields in [fields].
  final Map<String, Map<String, List<FormFieldDef>>> webForms;

  /// Road Show vehicle name -> picture path on the server (`/images/roadshow-vehicles/...`).
  final Map<String, String> vehicleImages;
  final List<String> locations;
  final List<String> creativeTypes;
  final String inhouseClientName;

  List<FormFieldDef> fieldsFor(String vertical, String nature) => webForms[vertical]?[nature] ?? fields[vertical]?[nature] ?? const [];

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
      webForms: ((j['web_forms'] as Map?) ?? {}).map((vertical, cfg) => MapEntry(
            '$vertical',
            (((cfg as Map)['natures'] as Map?) ?? {}).map((nature, form) => MapEntry(
                  '$nature',
                  ((((form as Map)['fields']) as List?) ?? []).map((f) => FormFieldDef.fromWeb(f as List)).toList(),
                )),
          )),
      vehicleImages: ((j['vehicle_images'] as Map?) ?? {}).map((k, v) => MapEntry('$k', '$v')),
      locations: ((j['locations'] as List?) ?? []).map((l) => '$l').toList(),
      creativeTypes: ((j['creative_types'] as List?) ?? []).map((l) => '$l').toList(),
      inhouseClientName: (j['inhouse_client_name'] ?? 'Adinn Advertising Services Ltd') as String,
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

/// A file already stored on the server (kept from a draft or a cloned ticket).
class StoredFile {
  StoredFile({required this.path, required this.name, this.url});

  final String path;
  final String name;
  final String? url;

  factory StoredFile.fromJson(Map<String, dynamic> j) => StoredFile(path: '${j['path']}', name: '${j['name'] ?? j['path']}', url: j['url'] as String?);
}

/// A previous ticket offered by "Clone existing ticket".
class CloneHit {
  CloneHit({required this.id, required this.taskId, required this.name, this.party, this.zoho});

  final int id;
  final String taskId;
  final String name;
  final String? party;
  final String? zoho;

  factory CloneHit.fromJson(Map<String, dynamic> j) =>
      CloneHit(id: j['id'] as int, taskId: '${j['task_id'] ?? ''}', name: '${j['task_name'] ?? ''}', party: j['party_name'] as String?, zoho: j['zoho_project_number'] as String?);
}

/// A saved draft in the list.
class DraftSummary {
  DraftSummary({required this.id, required this.name, required this.vertical, required this.inHouse, this.party, this.updatedAt});

  final int id;
  final String name;
  final String? vertical;
  final bool inHouse;
  final String? party;
  final DateTime? updatedAt;

  factory DraftSummary.fromJson(Map<String, dynamic> j) => DraftSummary(
        id: j['id'] as int,
        name: '${j['task_name'] ?? ''}',
        vertical: j['vertical'] as String?,
        inHouse: j['in_house'] == true,
        party: j['party_name'] as String?,
        updatedAt: j['updated_at'] == null ? null : DateTime.tryParse('${j['updated_at']}')?.toLocal(),
      );
}

/// Everything needed to prefill the form from a draft (`draftId` set) or a cloned ticket (`sourceId` set).
class FormPrefill {
  FormPrefill({required this.task, required this.requirements, required this.attachments, this.draftId, this.sourceId, this.inHouse = false});

  final Map<String, dynamic> task;
  final Map<String, dynamic> requirements;
  final Map<String, List<StoredFile>> attachments;
  final int? draftId;
  final int? sourceId;
  final bool inHouse;

  factory FormPrefill.fromJson(Map<String, dynamic> j) => FormPrefill(
        task: ((j['task'] as Map?) ?? {}).cast<String, dynamic>(),
        requirements: ((j['requirements'] as Map?) ?? {}).cast<String, dynamic>(),
        attachments: ((j['attachments'] as Map?) ?? {}).map((k, v) => MapEntry('$k', ((v as List?) ?? []).map((f) => StoredFile.fromJson(f as Map<String, dynamic>)).toList())),
        draftId: j['id'] as int?,
        sourceId: j['source_task_id'] as int?,
        inHouse: j['in_house'] == true,
      );
}
