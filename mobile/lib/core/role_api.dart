import 'api_client.dart';
import '../features/dashboard/dashboard_models.dart';
import '../features/kanban/kanban_models.dart';
import 'models.dart';
import 'uploads.dart';

/// The API groups tasks per role (`/designer/…`, `/designer-head/…`, `/bd/…`, `/admin/…`) with the same
/// shapes, so one class covers the board, detail and chat for all four roles.
class RoleApi {
  RoleApi(this._api, this.role);

  final ApiClient _api;
  final String role;

  String get _prefix => switch (role) {
        'designer' => '/designer',
        'designer_head' => '/designer-head',
        'bd' => '/bd',
        'admin' => '/admin',
        _ => throw ApiException('This role is not supported in the app.'),
      };

  /// Designer, Designer Head and BD get a kanban (`statuses` + `tasks`); Admin gets a paginated list.
  Future<Board> board({String? search}) async {
    final json = await _api.get('$_prefix/tasks', query: {if (search != null && search.isNotEmpty) 'search': search});

    if (role == 'admin') {
      final tasks = ((json['data'] as List?) ?? []).map((t) => TaskSummary.fromJson(t as Map<String, dynamic>)).toList();
      final statuses = ((json['filters']?['statuses'] as List?) ?? []).map((s) => StatusColumn.fromJson(s as Map<String, dynamic>)).toList();
      return Board(statuses: statuses, tasks: tasks);
    }

    return Board(
      statuses: ((json['statuses'] as List?) ?? []).map((s) => StatusColumn.fromJson(s as Map<String, dynamic>)).toList(),
      tasks: ((json['tasks'] as List?) ?? []).map((t) => TaskSummary.fromJson(t as Map<String, dynamic>)).toList(),
    );
  }

  Future<Map<String, dynamic>> detail(int id) => _api.get('$_prefix/tasks/$id');

  Future<List<ChatMessage>> comments(int id, {int afterId = 0}) async {
    final json = await _api.get('$_prefix/tasks/$id/comments', query: {if (afterId > 0) 'after_id': afterId});
    return ((json['data'] as List?) ?? []).map((c) => ChatMessage.fromJson(c as Map<String, dynamic>)).toList();
  }

  Future<ChatMessage> sendComment(int id, String text, {String? uploadIds}) async {
    final json = await _api.post('$_prefix/tasks/$id/comments', data: {
      'comment': text,
      if (uploadIds != null && uploadIds.isNotEmpty) 'upload_ids': uploadIds,
    });
    return ChatMessage.fromJson(json['comment'] as Map<String, dynamic>);
  }

  /// Designer-only: moves a task along the allowed state machine.
  Future<void> moveStatus(int id, String status) => _api.post('/designer/tasks/$id/move', data: {'status': status});

  /// Task Updation (EOD): creatives added to progress + finished `progress_update` upload ids.
  Future<void> submitEod(int taskId, {required int count, required String uploadIds}) =>
      _api.post('/designer/tasks/$taskId/eod', data: {'completed_count': count, 'upload_ids': uploadIds});

  Future<void> submitRework(int taskId, {required int count, required String uploadIds}) =>
      _api.post('/designer/tasks/$taskId/rework', data: {'completed_count': count, 'upload_ids': uploadIds});

  // ---- Head / Admin (and delegate-approver designers): request approvals ----
  String get _requestsPath => role == 'designer' ? '/designer/approvals' : '$_prefix/requests';

  Future<({List<RequestItem> items, List<Designer> designers})> pendingRequests() async {
    final json = await _api.get(_requestsPath);
    return (
      items: ((json['data'] as List?) ?? []).map((r) => RequestItem.fromJson(r as Map<String, dynamic>)).toList(),
      designers: ((json['designers'] as List?) ?? []).map((d) => Designer.fromJson(d as Map<String, dynamic>)).toList(),
    );
  }

  Future<void> approveRequest(int id, {int? designerId, int? creativeCount, String? comment}) => _api.post('$_requestsPath/$id/approve', data: {
        'approved_designer_id': ?designerId,
        'approved_creative_count': ?creativeCount,
        if (comment != null && comment.isNotEmpty) 'decision_comment': comment,
      });

  Future<void> rejectRequest(int id, String reason) => _api.post('$_requestsPath/$id/reject', data: {'decision_reason': reason});

  // ---- BD ----
  Future<void> requestRework(int taskId, {required int count, required String comment}) =>
      _api.post('/bd/tasks/$taskId/rework', data: {'number_of_creatives': count, 'comment': comment});

  Future<void> completeWithRating(int taskId, {required Map<String, double> ratings, String? comment, required bool printingFile}) =>
      _api.post('/bd/tasks/$taskId/complete', data: {
        ...ratings,
        if (comment != null && comment.isNotEmpty) 'rating_comment': comment,
        'completion_type': printingFile ? 'prepare_printing_file' : 'completed',
      });

  Future<void> approveNewTask(int taskId, {String? comment}) =>
      _api.post('/bd/tasks/$taskId/approve', data: {if (comment != null && comment.isNotEmpty) 'comment': comment});

  Future<void> rejectNewTask(int taskId, String comment) => _api.post('/bd/tasks/$taskId/reject', data: {'comment': comment});

  // ---- Designer "My Tasks" kanban ----
  Future<KanbanBoard> designerBoard(BoardFilters filters) async => KanbanBoard.fromJson(await _api.get('/designer/tasks', query: filters.toQuery()));

  /// The kanban for the signed-in role: the designer's own board, or the BD's tasks (read-only cards).
  Future<KanbanBoard> kanbanBoard(BoardFilters filters) async {
    if (role == 'bd') {
      // The BD board has no "BD" filter (it is always the BD's own tasks).
      return KanbanBoard.fromJson(await _api.get('/bd/tasks', query: filters.copyWith(bdId: null).toQuery()));
    }
    return designerBoard(filters);
  }

  /// A designer can't move a task backward directly; this raises the request the Designer Head approves.
  Future<void> requestBackward(int taskId, {required String toStatus, required String reason}) =>
      _api.post('/designer/tasks/$taskId/backward-request', data: {'to_status': toStatus, 'reason': reason});

  // ---- Designer dashboard ----
  Future<DesignerDashboard> designerDashboard() async => DesignerDashboard.fromJson(await _api.get('/designer/dashboard'));

  Future<List<DashRow>> dashboardTasks(String search) async {
    final json = await _api.get('/designer/dashboard/tasks', query: {'search': search});
    return ((json['data'] as List?) ?? []).map((r) => DashRow.fromJson(r as Map<String, dynamic>)).toList();
  }

  // ---- BD: create ----
  Future<TaskFormDef> taskForm() async => TaskFormDef.fromJson(await _api.get('/bd/task-form'));

  Future<List<ClientSuggestion>> searchClients(String q) async {
    final json = await _api.getList('/bd/clients/search', query: {'q': q});
    return json.map((c) => ClientSuggestion.fromJson(c as Map<String, dynamic>)).toList();
  }

  Future<Availability> availability(int designerId) async => Availability.fromJson(await _api.get('${role == 'admin' ? '/bd' : _prefix}/designers/$designerId/availability'));

  /// Creates a task (`inhouse` for the In-house tab) or saves a draft. Sends JSON, or multipart when files are attached.
  /// Returns the new task/draft id. Field-level problems arrive as [ApiException.fieldErrors].
  Future<int> submitTask({
    required Map<String, dynamic> values,
    Map<String, List<UploadSource>> files = const {},
    bool inhouse = false,
    bool draft = false,
  }) async {
    final path = switch ((inhouse, draft)) {
      (false, false) => '/bd/tasks',
      (true, false) => '/bd/tasks/in-house',
      (false, true) => '/bd/drafts',
      (true, true) => '/bd/drafts/in-house',
    };

    final hasFiles = files.values.any((l) => l.isNotEmpty);
    final json = await _api.post(path, data: hasFiles ? await buildMultipart(values, files) : values);
    return json['id'] as int;
  }

  // ---- Designer requests ----
  Future<({List<String> types, List<Designer> designers})> requestOptions(int taskId) async {
    final json = await _api.get('/designer/tasks/$taskId/request-options');
    return (
      types: ((json['allowed_types'] as List?) ?? []).cast<String>(),
      designers: ((json['designers'] as List?) ?? []).map((d) => Designer.fromJson(d as Map<String, dynamic>)).toList(),
    );
  }

  Future<void> submitRequest(int taskId, {required String type, required String reason, int? targetDesignerId, int? creativeCount, String? details, String? notes}) =>
      _api.post('/designer/tasks/$taskId/requests', data: {
        'type': type,
        'reason': reason,
        'target_designer_id': ?targetDesignerId,
        'creative_count': ?creativeCount,
        if (details != null && details.isNotEmpty) 'details': details,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
      });

  // ---- Notifications (same for every role) ----
  Future<({List<AppNotification> items, int unread})> notifications() async {
    final json = await _api.get('/notifications');
    return (
      items: ((json['data'] as List?) ?? []).map((n) => AppNotification.fromJson(n as Map<String, dynamic>)).toList(),
      unread: (json['unread_count'] ?? 0) as int,
    );
  }

  Future<void> markRead(String id) => _api.post('/notifications/$id/read');

  Future<void> markAllRead() => _api.post('/notifications/read-all');
}
