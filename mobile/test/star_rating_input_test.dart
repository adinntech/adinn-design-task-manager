import 'package:flutter/material.dart';
import 'package:flutter/semantics.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:adinn_tasks/features/tasks/star_rating_input.dart';

/// Hosts the widget at a known width so star positions are predictable:
/// 360 wide -> star size 50 (capped), gap 6, row 274 wide, inset 43.
Widget _host(ValueNotifier<double> value) => MaterialApp(
      home: Scaffold(
        body: Center(
          child: SizedBox(
            width: 360,
            child: ValueListenableBuilder<double>(
              valueListenable: value,
              builder: (_, v, _) => StarRatingInput(key: const Key('rating'), value: v, onChanged: (n) => value.value = n),
            ),
          ),
        ),
      ),
    );

/// Screen x for a point `stars` star-cells from the left edge of the first star.
Offset _at(WidgetTester tester, double stars) {
  final box = tester.getTopLeft(find.byKey(const Key('rating')));
  final height = tester.getSize(find.byKey(const Key('rating'))).height;
  return Offset(box.dx + 43 + stars * 56, box.dy + height / 2);
}

double _scaleOf(WidgetTester tester, int star) => tester.widget<AnimatedScale>(find.descendant(of: find.byKey(Key('star-$star')), matching: find.byType(AnimatedScale)).first).scale;

void main() {
  testWidgets('tapping the left half of a star gives x.5, the right half gives the full star', (tester) async {
    final value = ValueNotifier<double>(5);
    await tester.pumpWidget(_host(value));

    await tester.tapAt(_at(tester, 2.2)); // left half of star 3
    await tester.pump();
    expect(value.value, 2.5);

    await tester.tapAt(_at(tester, 2.8)); // right half of star 3
    await tester.pump();
    expect(value.value, 3.0);

    await tester.tapAt(_at(tester, 0.1)); // left half of star 1 -> the minimum
    await tester.pump();
    expect(value.value, 0.5);
  });

  testWidgets('dragging across the row follows the finger in half steps and clamps at both ends', (tester) async {
    final value = ValueNotifier<double>(5);
    await tester.pumpWidget(_host(value));

    final gesture = await tester.startGesture(_at(tester, 4.8));
    await tester.pump();
    final seen = <double>[];
    for (final s in [3.9, 3.1, 2.4, 1.6, 0.6]) {
      await gesture.moveTo(_at(tester, s));
      await tester.pump();
      seen.add(value.value);
    }
    // 3.9 -> 4.0, 3.1 -> 3.5, 2.4 -> 2.5, 1.6 -> 2.0, 0.6 -> 1.0 (always the next half step at or above the finger)
    expect(seen, [4.0, 3.5, 2.5, 2.0, 1.0]);

    await gesture.moveTo(_at(tester, -3)); // far past the left edge
    await tester.pump();
    expect(value.value, 0.5);

    await gesture.moveTo(_at(tester, 9)); // far past the right edge
    await tester.pump();
    expect(value.value, 5.0);
    await gesture.up();
  });

  testWidgets('the star under the finger lifts while held and settles when released', (tester) async {
    final value = ValueNotifier<double>(5);
    await tester.pumpWidget(_host(value));
    await tester.pumpAndSettle();
    expect(_scaleOf(tester, 3), 1);

    final gesture = await tester.startGesture(_at(tester, 2.8));
    await tester.pumpAndSettle();
    expect(_scaleOf(tester, 3), greaterThan(1));
    expect(_scaleOf(tester, 1), 1);

    await gesture.moveTo(_at(tester, 0.8)); // the lift follows to star 1
    await tester.pumpAndSettle();
    expect(_scaleOf(tester, 1), greaterThan(1));
    expect(_scaleOf(tester, 3), 1);

    await gesture.up();
    await tester.pumpAndSettle();
    expect(_scaleOf(tester, 1), 1);
  });

  testWidgets('the semantics actions step by half a star', (tester) async {
    final handle = tester.ensureSemantics();
    final value = ValueNotifier<double>(3);
    await tester.pumpWidget(_host(value));

    final rating = find.semantics.byLabel('Rating');

    tester.semantics.performAction(rating, SemanticsAction.increase);
    await tester.pump();
    expect(value.value, 3.5);

    tester.semantics.performAction(rating, SemanticsAction.decrease);
    await tester.pump();
    tester.semantics.performAction(rating, SemanticsAction.decrease);
    await tester.pump();
    expect(value.value, 2.5);
    handle.dispose();
  });
}
