import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/auth.dart';

enum HomeTab { dashboard, tasks, requests, alerts, profile }

/// Which tabs a role sees, in order — mirrors the web sidebars (a designer lands on the Dashboard).
List<HomeTab> tabsFor(String? role) => switch (role) {
      'designer' => const [HomeTab.dashboard, HomeTab.tasks, HomeTab.alerts, HomeTab.profile],
      'designer_head' || 'admin' => const [HomeTab.tasks, HomeTab.requests, HomeTab.alerts, HomeTab.profile],
      _ => const [HomeTab.tasks, HomeTab.alerts, HomeTab.profile],
    };

/// The selected bottom-navigation tab. Held in a provider so a dashboard card can jump to the Tasks tab.
final homeTabProvider = NotifierProvider<HomeTabController, HomeTab>(HomeTabController.new);

class HomeTabController extends Notifier<HomeTab> {
  @override
  HomeTab build() => tabsFor(ref.watch(authProvider.select((s) => s.user?.role))).first;

  void goTo(HomeTab tab) {
    if (tabsFor(ref.read(authProvider).user?.role).contains(tab)) state = tab;
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
