import 'api_client.dart';
import 'models.dart';

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

  Future<ChatMessage> sendComment(int id, String text) async {
    final json = await _api.post('$_prefix/tasks/$id/comments', data: {'comment': text});
    return ChatMessage.fromJson(json['comment'] as Map<String, dynamic>);
  }

  /// Designer-only: moves a task along the allowed state machine.
  Future<void> moveStatus(int id, String status) => _api.post('/designer/tasks/$id/move', data: {'status': status});

  // ---- Head / Admin: request approvals ----
  Future<({List<RequestItem> items, List<Designer> designers})> pendingRequests() async {
    final json = await _api.get('$_prefix/requests');
    return (
      items: ((json['data'] as List?) ?? []).map((r) => RequestItem.fromJson(r as Map<String, dynamic>)).toList(),
      designers: ((json['designers'] as List?) ?? []).map((d) => Designer.fromJson(d as Map<String, dynamic>)).toList(),
    );
  }

  Future<void> approveRequest(int id, {int? designerId, int? creativeCount, String? comment}) => _api.post('$_prefix/requests/$id/approve', data: {
        'approved_designer_id': ?designerId,
        'approved_creative_count': ?creativeCount,
        if (comment != null && comment.isNotEmpty) 'decision_comment': comment,
      });

  Future<void> rejectRequest(int id, String reason) => _api.post('$_prefix/requests/$id/reject', data: {'decision_reason': reason});

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
