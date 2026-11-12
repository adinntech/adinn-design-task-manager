import 'package:adinn_tasks/features/splash/launch_flow.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class _Store implements OnboardingStore {
  _Store(this.isSeen);

  bool isSeen;

  @override
  Future<bool> seen() async => isSeen;

  @override
  Future<void> markSeen() async => isSeen = true;
}

Widget _app() => const MaterialApp(home: LaunchFlow(child: Scaffold(body: Center(child: Text('the app')))));

void main() {
  late _Store store;
  setUp(() {
    store = _Store(false);
    LaunchFlow.store = store;
    LaunchFlow.enabled = true;
    LaunchFlow.played = false;
  });
  tearDown(() => LaunchFlow.enabled = false);

  void phone(WidgetTester tester, [Size size = const Size(1080, 2340), double dpr = 2.7]) {
    tester.view.physicalSize = size;
    tester.view.devicePixelRatio = dpr;
    addTearDown(tester.view.reset);
  }

  Future<void> start(WidgetTester tester) async {
    await tester.pumpWidget(_app());
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 500)));
    await tester.pump();
    await tester.pump();
  }

  Future<void> throughReveal(WidgetTester tester) async {
    await tester.pump(const Duration(milliseconds: 4600));
    await tester.pump(const Duration(milliseconds: 500));
  }

  testWidgets('first install: the reveal, then the tour ending in Get started, then the app', (tester) async {
    phone(tester);
    await start(tester);
    await tester.pump(const Duration(milliseconds: 3400));
    expect(tester.takeException(), isNull);
    expect(find.text('DESIGNER TASK TOOL'), findsWidgets);
    await throughReveal(tester);
    expect(find.byKey(const Key('onboarding-continue')), findsOneWidget);
    for (var i = 0; i < 2; i++) {
      await tester.tap(find.byKey(const Key('onboarding-continue')));
      await tester.pump(const Duration(milliseconds: 600));
      await tester.pump(const Duration(milliseconds: 1500));
    }
    expect(find.text('Get started'), findsOneWidget);
    await tester.tap(find.byKey(const Key('onboarding-continue')));
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(milliseconds: 600));
    expect(find.text('the app'), findsOneWidget);
    expect(find.byKey(const Key('onboarding-continue')), findsNothing);
    expect(store.isSeen, isTrue);
  });

  testWidgets('Skip leaves the tour; a returning user goes straight from the reveal to the app', (tester) async {
    phone(tester);
    await start(tester);
    await throughReveal(tester);
    await tester.tap(find.byKey(const Key('onboarding-skip')));
    await tester.pump(const Duration(milliseconds: 700));
    expect(find.text('the app'), findsOneWidget);
    expect(store.isSeen, isTrue);

    await tester.pumpWidget(const SizedBox());
    LaunchFlow.played = false;
    await start(tester);
    await throughReveal(tester);
    expect(find.byKey(const Key('onboarding-continue')), findsNothing);
    expect(find.text('the app'), findsOneWidget);
  });

  testWidgets('the tour cannot be pulled past its first or last page', (tester) async {
    phone(tester);
    await start(tester);
    await throughReveal(tester);

    double offset() => tester.state<ScrollableState>(find.byType(Scrollable).first).position.pixels;
    final title = find.textContaining('Welcome to Adinn');

    // Dragging right on page one (towards nothing) goes nowhere.
    await tester.drag(title, const Offset(300, 0));
    await tester.pump();
    expect(offset(), 0);
    await tester.pump(const Duration(milliseconds: 700)); // the illustrations loop, so nothing ever "settles"
    expect(offset(), 0);

    // Through to the last page, then dragging left (towards nothing) goes nowhere either.
    for (var i = 0; i < 2; i++) {
      await tester.tap(find.byKey(const Key('onboarding-continue')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pump(const Duration(milliseconds: 500));
    }
    expect(find.text('Get started'), findsOneWidget);
    final end = offset();
    await tester.drag(find.textContaining('Always in the loop'), const Offset(-300, 0));
    await tester.pump();
    expect(offset(), end);
    await tester.pump(const Duration(milliseconds: 700)); // the illustrations loop, so nothing ever "settles"
    expect(offset(), end);
  });

  testWidgets('a tap skips the reveal and it never replays', (tester) async {
    phone(tester);
    await start(tester);
    await tester.pump(const Duration(milliseconds: 400));
    await tester.tapAt(const Offset(200, 300));
    await tester.pump(const Duration(milliseconds: 600));
    expect(find.byKey(const Key('onboarding-continue')), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    await tester.pumpWidget(_app());
    await tester.pump();
    expect(find.text('DESIGNER TASK TOOL'), findsNothing);
  });

  testWidgets('fits a small screen', (tester) async {
    phone(tester, const Size(720, 1280), 2);
    await start(tester);
    await throughReveal(tester);
    expect(tester.takeException(), isNull);
    for (var i = 0; i < 2; i++) {
      await tester.tap(find.byKey(const Key('onboarding-continue')));
      await tester.pump(const Duration(milliseconds: 1500));
      expect(tester.takeException(), isNull);
    }
  });
}
