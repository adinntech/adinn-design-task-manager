import 'package:adinn_tasks/features/auth/intro/adinn_intro.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

Widget _app({bool reduceMotion = false}) => MaterialApp(
      builder: (context, child) => MediaQuery(data: MediaQuery.of(context).copyWith(disableAnimations: reduceMotion), child: child!),
      home: const Scaffold(
        backgroundColor: Colors.black,
        resizeToAvoidBottomInset: false,
        body: AdinnIntro(panel: TextField(key: Key('field'))),
      ),
    );

void main() {
  setUp(() {
    AdinnIntro.enabled = true;
    AdinnIntro.played = false;
  });
  tearDown(() => AdinnIntro.enabled = false);

  testWidgets('plays for 6.5 s, takes no taps until the module has arrived, then is usable', (tester) async {
    tester.view.physicalSize = const Size(780, 1688);
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(_app());
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(milliseconds: 2900)); // 3 s: the pencil is writing
    await tester.tap(find.byKey(const Key('field')), warnIfMissed: false);
    await tester.pump();
    expect(tester.testTextInput.isVisible, isFalse); // the tap went nowhere

    await tester.pump(const Duration(milliseconds: 3600)); // past 6.5 s
    await tester.pump(const Duration(milliseconds: 300));
    final field = find.byKey(const Key('field'));
    expect(tester.getTopLeft(field).dy, lessThan(1688 / 2 + 400)); // on screen, not still below the fold
    await tester.tap(field);
    await tester.pump();
    expect(tester.testTextInput.isVisible, isTrue);
    await tester.enterText(field, 'bd@adinn.com');
    expect(find.text('bd@adinn.com'), findsOneWidget);
  });

  testWidgets('does not replay when the screen is shown again', (tester) async {
    await tester.pumpWidget(_app());
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(seconds: 7));
    await tester.pump(const Duration(milliseconds: 300));

    await tester.pumpWidget(const SizedBox());
    await tester.pumpWidget(_app());
    // Final state straight away: the module is already usable with no time passing.
    await tester.enterText(find.byKey(const Key('field')), 'again');
    expect(find.text('again'), findsOneWidget);
  });

  testWidgets('reduced motion skips straight to the finished layout', (tester) async {
    await tester.pumpWidget(_app(reduceMotion: true));
    await tester.enterText(find.byKey(const Key('field')), 'x');
    expect(find.text('x'), findsOneWidget);
    expect(AdinnIntro.played, isFalse);
  });

  testWidgets('a tap during the opening skips to the end', (tester) async {
    await tester.pumpWidget(_app());
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(seconds: 1));
    await tester.tapAt(const Offset(100, 100));
    await tester.pump(const Duration(milliseconds: 300));
    await tester.enterText(find.byKey(const Key('field')), 'skip');
    expect(find.text('skip'), findsOneWidget);
  });
}
