import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:adinn_tasks/features/tasks/task_actions.dart';

/// Opens the rating sheet exactly like the task screen does and reports how it ended.
Future<({bool? Function() dismissed, RatingResult? Function() result})> _open(WidgetTester tester) async {
  var done = false;
  RatingResult? result;
  await tester.pumpWidget(MaterialApp(
    home: Builder(
      builder: (context) => Scaffold(
        body: Center(
          child: ElevatedButton(
            key: const Key('open'),
            onPressed: () async {
              result = await showModalBottomSheet<RatingResult>(
                context: context,
                isScrollControlled: true,
                useSafeArea: true,
                showDragHandle: true,
                builder: (_) => const RatingSheet(),
              );
              done = true;
            },
            child: const Text('open'),
          ),
        ),
      ),
    ),
  ));
  await tester.tap(find.byKey(const Key('open')));
  await tester.pumpAndSettle();
  return (dismissed: () => done, result: () => result);
}

/// A phone-sized window, with a 28 dp status bar and a 24 dp gesture/navigation bar like a real device.
void _phone(WidgetTester tester, {double width = 360, double height = 780}) {
  tester.view.devicePixelRatio = 3;
  tester.view.physicalSize = Size(width * 3, height * 3);
  const insets = FakeViewPadding(top: 28 * 3, bottom: 24 * 3);
  tester.view.padding = insets;
  tester.view.viewPadding = insets;
  addTearDown(tester.view.reset);
}

double _maxScroll(WidgetTester tester) {
  final scrollable = find.descendant(of: find.byType(RatingSheet), matching: find.byType(Scrollable)).first;
  return tester.state<ScrollableState>(scrollable).position.maxScrollExtent;
}

void main() {
  testWidgets('on a normal phone the whole sheet fits one screen, so nothing scrolls', (tester) async {
    _phone(tester);
    await _open(tester);

    expect(find.byType(RatingSheet), findsOneWidget);
    expect(_maxScroll(tester), 0);
    // Every control is on screen at once, above the navigation bar.
    for (final k in ['rate-designer_attitude', 'rate-client_satisfaction', 'rating-comment', 'rating-submit', 'rating-submit-printing']) {
      final bottom = tester.getBottomLeft(find.byKey(Key(k))).dy;
      expect(bottom, lessThanOrEqualTo(780 - 24), reason: '$k should be fully visible');
    }
  });

  testWidgets('a swipe down anywhere on the sheet drops it', (tester) async {
    _phone(tester);
    final outcome = await _open(tester);

    await tester.fling(find.byKey(const Key('overall')), const Offset(0, 700), 2000);
    await tester.pumpAndSettle();

    expect(find.byType(RatingSheet), findsNothing);
    expect(outcome.dismissed(), isTrue);
    expect(outcome.result(), isNull);
  });

  testWidgets('a swipe down that starts on a star row drops the sheet too, and does not change the rating', (tester) async {
    _phone(tester);
    final outcome = await _open(tester);

    await tester.fling(find.byKey(const Key('rate-design_satisfaction')), const Offset(0, 700), 2000);
    await tester.pumpAndSettle();

    expect(find.byType(RatingSheet), findsNothing);
    expect(outcome.dismissed(), isTrue);
  });

  testWidgets('a smaller 700 dp phone still fits on one screen', (tester) async {
    _phone(tester, height: 700);
    await _open(tester);

    expect(find.byType(RatingSheet), findsOneWidget);
    expect(_maxScroll(tester), 0);
  });

  testWidgets('a short 640 dp screen still shows the sheet (smaller stars, scrolling only if it must)', (tester) async {
    _phone(tester, height: 640);
    await _open(tester);

    expect(find.byType(RatingSheet), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
