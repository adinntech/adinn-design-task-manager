import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/auth.dart';

enum HomeTab { dashboard, tasks, requests, alerts, profile }

/// Which tabs a role sees, in order — mirrors the web sidebars (a designer lands on the Dashboard).
///
/// A designer the Admin allowed to approve requests ([approver]) also gets the Approvals tab (shown as [HomeTab.requests]).
List<HomeTab> tabsFor(String? role, {bool approver = false}) => switch (role) {
      'designer' when approver => const [HomeTab.dashboard, HomeTab.tasks, HomeTab.requests, HomeTab.alerts, HomeTab.profile],
      'designer' => const [HomeTab.dashboard, HomeTab.tasks, HomeTab.alerts, HomeTab.profile],
      'designer_head' || 'admin' => const [HomeTab.tasks, HomeTab.requests, HomeTab.alerts, HomeTab.profile],
      'bd' => const [HomeTab.dashboard, HomeTab.tasks, HomeTab.alerts, HomeTab.profile],
      _ => const [HomeTab.tasks, HomeTab.alerts, HomeTab.profile],
    };

/// The selected bottom-navigation tab. Held in a provider so a dashboard card can jump to the Tasks tab.
final homeTabProvider = NotifierProvider<HomeTabController, HomeTab>(HomeTabController.new);

class HomeTabController extends Notifier<HomeTab> {
  @override
  HomeTab build() => tabsFor(ref.watch(authProvider.select((s) => s.user?.role))).first;

  void goTo(HomeTab tab) {
    final user = ref.read(authProvider).user;
    if (tabsFor(user?.role, approver: user?.isRequestApprover ?? false).contains(tab)) state = tab;
  }
}

/// A request for the Tasks tab, set from the dashboard and consumed (reset to null) by the board:
/// null = nothing requested, '' = show every task, otherwise the status chip to select.
final boardFocusProvider = NotifierProvider<BoardFocus, String?>(BoardFocus.new);

class BoardFocus extends Notifier<String?> {
  @override
  String? build() => null;

  void focus(String? status) => state = status;
}

/// With a board focus: the task (database id) to flash once its column is open — "Open on board" from the dashboard.
final boardTaskFocusProvider = NotifierProvider<BoardTaskFocus, int?>(BoardTaskFocus.new);

class BoardTaskFocus extends Notifier<int?> {
  @override
  int? build() => null;

  void focus(int? taskId) => state = taskId;
}
