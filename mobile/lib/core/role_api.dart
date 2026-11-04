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
