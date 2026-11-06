import 'package:adinn_tasks/features/widgets/liquid_nav_bar.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const items = [
    LiquidNavItem(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard), label: 'Dashboard'),
    LiquidNavItem(icon: Icon(Icons.view_kanban_outlined), selectedIcon: Icon(Icons.view_kanban), label: 'My Tasks'),
    LiquidNavItem(icon: Icon(Icons.notifications_outlined), selectedIcon: Icon(Icons.notifications), label: 'Alerts'),
  ];

  Future<ValueNotifier<int>> boot(WidgetTester tester) async {
    final selected = ValueNotifier(0);
    await tester.pumpWidget(MaterialApp(
      home: ValueListenableBuilder<int>(
        valueListenable: selected,
        builder: (_, i, _) => Scaffold(
          bottomNavigationBar: LiquidNavBar(items: items, selectedIndex: i, onSelected: (v) => selected.value = v),
        ),
      ),
    ));
    await tester.pumpAndSettle();
    return selected;
  }

  testWidgets('shows every label, with only the selected icon lifted into the bubble', (tester) async {
    await boot(tester);

    for (final l in ['Dashboard', 'My Tasks', 'Alerts']) {
      expect(find.text(l), findsOneWidget);
    }
    expect(find.byIcon(Icons.dashboard), findsOneWidget); // selected: filled icon in the bubble
    expect(find.byIcon(Icons.dashboard_outlined), findsNothing);
    expect(find.byIcon(Icons.view_kanban_outlined), findsOneWidget); // the others stay in the bar
    expect(find.byIcon(Icons.view_kanban), findsNothing);
  });

  testWidgets('tapping a tab selects it, the bubble melts across, and the icons swap', (tester) async {
    final selected = await boot(tester);

    await tester.tap(find.text('Alerts'));
    await tester.pump(const Duration(milliseconds: 200));
    // Mid-travel the bar is still animating (liquid in motion).
    expect(tester.hasRunningAnimations, isTrue);

    await tester.pumpAndSettle();
    expect(selected.value, 2);
    expect(find.byIcon(Icons.notifications), findsOneWidget);
    expect(find.byIcon(Icons.dashboard_outlined), findsOneWidget);
    expect(tester.hasRunningAnimations, isFalse);
  });

  testWidgets('tapping the icon of an unselected tab also selects it', (tester) async {
    final selected = await boot(tester);

    await tester.tap(find.byIcon(Icons.view_kanban_outlined));
    await tester.pumpAndSettle();

    expect(selected.value, 1);
  });

  testWidgets('fits narrow screens and many tabs without overflow', (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(const MaterialApp(
      home: Scaffold(
        bottomNavigationBar: LiquidNavBar(
          selectedIndex: 4,
          onSelected: _noop,
          items: [
            LiquidNavItem(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard), label: 'Dashboard'),
            LiquidNavItem(icon: Icon(Icons.view_kanban_outlined), selectedIcon: Icon(Icons.view_kanban), label: 'My Tasks'),
            LiquidNavItem(icon: Icon(Icons.fact_check_outlined), selectedIcon: Icon(Icons.fact_check), label: 'Approvals'),
            LiquidNavItem(icon: Icon(Icons.notifications_outlined), selectedIcon: Icon(Icons.notifications), label: 'Alerts'),
            LiquidNavItem(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'Profile'),
          ],
        ),
      ),
    ));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.text('Profile'), findsOneWidget);
  });
}

void _noop(int _) {}
