import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/auth.dart';
import '../../core/models.dart';
import '../../core/role_api.dart';
import '../kanban/kanban_models.dart';

/// API facade for the signed-in user's role.
final roleApiProvider = Provider<RoleApi>((ref) {
  final role = ref.watch(authProvider.select((s) => s.user?.role)) ?? '';
  return RoleApi(ref.watch(apiClientProvider), role);
});

final boardProvider = FutureProvider.autoDispose.family<Board, String>((ref, search) => ref.watch(roleApiProvider).board(search: search));

final taskDetailProvider =
    FutureProvider.autoDispose.family<Map<String, dynamic>, int>((ref, id) => ref.watch(roleApiProvider).detail(id));

final commentsProvider =
    FutureProvider.autoDispose.family<List<ChatMessage>, int>((ref, id) => ref.watch(roleApiProvider).comments(id));

final notificationsProvider = FutureProvider.autoDispose<({List<AppNotification> items, int unread})>(
  (ref) => ref.watch(roleApiProvider).notifications(),
);


/// Filters of the designer "My Tasks" kanban (search, vertical, priority, BD, project number, period).
final kanbanFiltersProvider = NotifierProvider<KanbanFilters, BoardFilters>(KanbanFilters.new);

class KanbanFilters extends Notifier<BoardFilters> {
  @override
  BoardFilters build() => const BoardFilters();

  void update(BoardFilters next) => state = next;

  void clear() => state = const BoardFilters();
}

final kanbanBoardProvider = FutureProvider.autoDispose<KanbanBoard>((ref) {
  return ref.watch(roleApiProvider).designerBoard(ref.watch(kanbanFiltersProvider));
});
