import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/auth.dart';
import '../dashboard/designer_dashboard_screen.dart';
import '../kanban/kanban_screen.dart';
import '../notifications/notifications_screen.dart';
import '../requests/requests_screen.dart';
import '../tasks/board_screen.dart';
import '../tasks/providers.dart';
import '../widgets/liquid_nav_bar.dart';
import 'navigation.dart';

class HomeShell extends ConsumerWidget {
  const HomeShell({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final unread = ref.watch(notificationsProvider).maybeWhen(data: (d) => d.unread, orElse: () => 0);
    final role = ref.watch(authProvider.select((s) => s.user?.role));
    final approver = ref.watch(authProvider.select((s) => s.user?.isRequestApprover ?? false));
    final tabs = tabsFor(role, approver: approver);
    final current = ref.watch(homeTabProvider);
    final index = tabs.indexOf(current).clamp(0, tabs.length - 1);
    final pending = tabs.contains(HomeTab.requests) ? ref.watch(pendingRequestsProvider).maybeWhen(data: (d) => d.items.length, orElse: () => 0) : 0;

    Widget page(HomeTab t) => switch (t) {
          HomeTab.dashboard => const DesignerDashboardScreen(),
          // Designers get the web's kanban; the other roles keep the status-chip board for now.
          HomeTab.tasks => role == 'designer' ? const KanbanScreen() : const BoardScreen(),
          HomeTab.requests => const RequestsScreen(),
          HomeTab.alerts => const NotificationsScreen(),
          HomeTab.profile => const ProfileScreen(),
        };

    LiquidNavItem destination(HomeTab t) => switch (t) {
          HomeTab.dashboard => const LiquidNavItem(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard), label: 'Dashboard'),
          HomeTab.tasks => LiquidNavItem(
              icon: const Icon(Icons.view_kanban_outlined),
              selectedIcon: const Icon(Icons.view_kanban),
              label: role == 'designer' ? 'My Tasks' : 'Tasks',
            ),
          HomeTab.requests => LiquidNavItem(
              icon: Badge(isLabelVisible: pending > 0, label: Text('$pending'), child: const Icon(Icons.fact_check_outlined)),
              selectedIcon: Badge(isLabelVisible: pending > 0, label: Text('$pending'), child: const Icon(Icons.fact_check)),
              label: role == 'designer' ? 'Approvals' : 'Requests',
            ),
          HomeTab.alerts => LiquidNavItem(
              icon: Badge(isLabelVisible: unread > 0, label: Text('$unread'), child: const Icon(Icons.notifications_outlined)),
              selectedIcon: Badge(isLabelVisible: unread > 0, label: Text('$unread'), child: const Icon(Icons.notifications)),
              label: 'Alerts',
            ),
          HomeTab.profile => const LiquidNavItem(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'Profile'),
        };

    return Scaffold(
      body: IndexedStack(index: index, children: [for (final t in tabs) page(t)]),
      bottomNavigationBar: LiquidNavBar(
        selectedIndex: index,
        onSelected: (i) => ref.read(homeTabProvider.notifier).goTo(tabs[i]),
        items: [for (final t in tabs) destination(t)],
      ),
    );
  }
}

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authProvider).user;

    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: ListTile(
              leading: CircleAvatar(child: Text((user?.name.isNotEmpty ?? false) ? user!.name[0].toUpperCase() : '?')),
              title: Text(user?.name ?? ''),
              subtitle: Text('${user?.roleLabel ?? ''}\n${user?.email ?? ''}'),
              isThreeLine: true,
            ),
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            key: const Key('sign-out'),
            onPressed: () => ref.read(authProvider.notifier).logout(),
            icon: const Icon(Icons.logout),
            label: const Text('Sign out'),
          ),
        ],
      ),
    );
  }
}
