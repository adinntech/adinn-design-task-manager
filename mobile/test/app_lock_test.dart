import 'package:adinn_tasks/core/app_lock.dart';
import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:adinn_tasks/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

class MemoryLockStore implements AppLockStore {
  MemoryLockStore([this.config]);

  LockConfig? config;

  @override
  Future<LockConfig?> read() async => config;

  @override
  Future<void> write(LockConfig c) async => config = c;

  @override
  Future<void> clear() async => config = null;
}

class FakeBiometrics implements BiometricService {
  FakeBiometrics({this.supported = true, this.enrolled = true, this.matches = true});

  bool supported;
  bool enrolled;
  bool matches;
  int prompts = 0;

  @override
  Future<bool> isSupported() async => supported;

  @override
  Future<bool> hasEnrolled() async => enrolled;

  @override
  Future<bool> authenticate(String reason) async {
    prompts++;
    return matches;
  }
}

LockConfig savedLock({String pin = '1234', bool biometric = true, int failed = 0}) =>
    LockConfig(salt: 'salt', hash: LockConfig.hashPin(pin, 'salt'), biometric: biometric, failed: failed);

void main() {
  group('AppLockController', () {
    late MemoryLockStore store;
    late FakeBiometrics bio;
    late ProviderContainer c;

    setUp(() {
      store = MemoryLockStore();
      bio = FakeBiometrics();
      c = ProviderContainer(overrides: [appLockStoreProvider.overrideWithValue(store), biometricServiceProvider.overrideWithValue(bio)]);
      addTearDown(c.dispose);
    });

    AppLockController lock() => c.read(appLockProvider.notifier);
    AppLockState state() => c.read(appLockProvider);

    test('a phone with biometrics and no lock yet is offered the lock', () async {
      await lock().load();

      expect(state().ready, isTrue);
      expect(state().enabled, isFalse);
      expect(state().shouldOffer, isTrue);
    });

    test('a phone without biometric hardware is not asked', () async {
      bio.supported = false;
      await lock().load();

      expect(state().shouldOffer, isFalse);
    });

    test('"do it later" stops the offer until the next sign-in', () async {
      await lock().load();
      lock().dismissOffer();
      expect(state().shouldOffer, isFalse);

      await lock().onSignedIn();
      expect(state().shouldOffer, isTrue);
    });

    test('the MPIN is stored as a salted hash, never in the clear', () async {
      await lock().load();
      await lock().enable('4321', biometric: true);

      final saved = store.config!;
      expect(saved.hash, isNot(contains('4321')));
      expect(saved.hash, LockConfig.hashPin('4321', saved.salt));
      expect(saved.biometric, isTrue);
      expect(state().enabled, isTrue);
      expect(state().locked, isFalse);
    });

    test('a saved lock starts a cold launch locked', () async {
      store.config = savedLock();
      await lock().load();

      expect(state().enabled, isTrue);
      expect(state().locked, isTrue);
      expect(state().shouldOffer, isFalse);
    });

    test('the right MPIN unlocks and clears earlier mistakes; wrong ones count down to a lockout', () async {
      store.config = savedLock(failed: 2);
      await lock().load();

      expect(await lock().unlockWithPin('1234'), PinResult.ok);
      expect(state().locked, isFalse);
      expect(store.config!.failed, 0);

      for (var i = 1; i < maxPinAttempts; i++) {
        expect(await lock().unlockWithPin('0000'), PinResult.wrong);
        expect(state().failed, i);
      }
      expect(await lock().unlockWithPin('0000'), PinResult.lockedOut);
      // The count survives a restart, so killing the app does not hand out fresh tries.
      expect(store.config!.failed, maxPinAttempts);
    });

    test('fingerprint unlocks only when allowed and enrolled', () async {
      store.config = savedLock(biometric: true);
      await lock().load();
      bio.enrolled = false;
      expect(await lock().unlockWithBiometric(), isFalse);
      expect(state().locked, isTrue);

      bio.enrolled = true;
      bio.matches = false;
      expect(await lock().unlockWithBiometric(), isFalse);
      expect(state().locked, isTrue);

      bio.matches = true;
      expect(await lock().unlockWithBiometric(), isTrue);
      expect(state().locked, isFalse);
    });

    test('back within 30 s stays open; away longer locks again', () async {
      store.config = savedLock();
      await lock().load();
      await lock().unlockWithPin('1234');

      final t = DateTime(2026, 11, 9, 10);
      lock().appPaused(at: t);
      lock().appResumed(at: t.add(const Duration(seconds: 10)));
      expect(state().locked, isFalse);

      lock().appPaused(at: t);
      lock().appResumed(at: t.add(lockGracePeriod));
      expect(state().locked, isTrue);
    });

    test('reset forgets the MPIN', () async {
      store.config = savedLock();
      await lock().load();
      await lock().reset();

      expect(store.config, isNull);
      expect(state().enabled, isFalse);
      expect(state().locked, isFalse);
    });
  });

  group('in the app', () {
    late MemoryLockStore store;
    late FakeBiometrics bio;

    Future<void> boot(WidgetTester tester, {LockConfig? saved, FakeBiometrics? biometrics}) async {
      tester.view.physicalSize = const Size(800, 1800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);

      store = MemoryLockStore(saved);
      bio = biometrics ?? FakeBiometrics();
      final api = FakeAdapter({
        'GET /me': (_) => (status: 200, body: {'user': userJson()}),
        'GET /notifications': (_) => (status: 200, body: {'unread_count': 0, 'data': []}),
        'GET /designer/tasks': (_) => (status: 200, body: {'statuses': [], 'tasks': []}),
      });

      await tester.pumpWidget(ProviderScope(
        retry: (_, _) => null,
        overrides: [
          sessionStoreProvider.overrideWithValue(MemoryStore(token: 'tok', user: AppUser.fromJson(userJson()))),
          apiClientProvider.overrideWith((ref) => fakeClient(api, token: () => ref.read(authProvider).token)),
          appLockStoreProvider.overrideWithValue(store),
          biometricServiceProvider.overrideWithValue(bio),
        ],
        child: const AdinnApp(),
      ));
      await tester.pumpAndSettle();
    }

    Future<void> typePin(WidgetTester tester, String digits) async {
      // The MPIN is typed on the phone's own keyboard, into the field behind the dots.
      await tester.enterText(find.byKey(const Key('pin-input')), digits);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pumpAndSettle();
    }

    testWidgets('the first sign-in asks; "Do it later" goes straight to the app and sets nothing up', (tester) async {
      await boot(tester);

      expect(find.text('Unlock with your fingerprint or face?'), findsOneWidget);

      await tester.tap(find.byKey(const Key('lock-offer-later')));
      await tester.pumpAndSettle();

      expect(find.text('Unlock with your fingerprint or face?'), findsNothing);
      expect(store.config, isNull);
    });

    testWidgets('yes → new MPIN → confirm MPIN → fingerprint check → the lock is on', (tester) async {
      await boot(tester);

      await tester.tap(find.byKey(const Key('lock-offer-yes')));
      await tester.pumpAndSettle();
      expect(find.text('Create a 4-digit MPIN'), findsOneWidget);

      await typePin(tester, '2580');
      expect(find.text('Confirm your MPIN'), findsOneWidget);

      await typePin(tester, '2580');
      expect(bio.prompts, 1); // the fingerprint was proved before being relied on
      expect(find.text('App lock is on'), findsOneWidget);
      expect(find.textContaining('fingerprint or face when you open it'), findsOneWidget);

      await tester.tap(find.byKey(const Key('lock-done')));
      await tester.pumpAndSettle();

      expect(find.text('App lock is on'), findsNothing);
      expect(store.config, isNotNull);
      expect(store.config!.biometric, isTrue);
      expect(store.config!.matches('2580'), isTrue);
    });

    testWidgets('a confirm that does not match starts the MPIN over', (tester) async {
      await boot(tester);
      await tester.tap(find.byKey(const Key('lock-offer-yes')));
      await tester.pumpAndSettle();

      await typePin(tester, '1111');
      await typePin(tester, '2222');

      expect(find.text('The MPINs did not match. Try again.'), findsOneWidget);
      expect(find.text('Create a 4-digit MPIN'), findsOneWidget);
      expect(store.config, isNull);
    });

    testWidgets('a cancelled fingerprint check still turns the MPIN lock on, without fingerprint', (tester) async {
      await boot(tester, biometrics: FakeBiometrics(matches: false));
      await tester.tap(find.byKey(const Key('lock-offer-yes')));
      await tester.pumpAndSettle();
      await typePin(tester, '1357');
      await typePin(tester, '1357');

      expect(store.config!.biometric, isFalse);
      expect(find.textContaining('now asks for your MPIN'), findsOneWidget);
    });

    testWidgets('no fingerprint enrolled yet: MPIN lock now, and the user is told how to add one', (tester) async {
      await boot(tester, biometrics: FakeBiometrics(enrolled: false));
      await tester.tap(find.byKey(const Key('lock-offer-yes')));
      await tester.pumpAndSettle();
      await typePin(tester, '1357');
      await typePin(tester, '1357');

      expect(bio.prompts, 0);
      expect(find.textContaining('Add a fingerprint or face in your phone Settings'), findsOneWidget);
    });

    testWidgets('a phone with no biometric hardware is not asked at all', (tester) async {
      await boot(tester, biometrics: FakeBiometrics(supported: false, enrolled: false));

      expect(find.text('Unlock with your fingerprint or face?'), findsNothing);
      expect(find.byKey(const Key('lock-offer-yes')), findsNothing);
    });

    testWidgets('opening the app locked: the fingerprint prompt appears by itself and unlocks', (tester) async {
      await boot(tester, saved: savedLock());

      expect(bio.prompts, 1);
      expect(find.text('Enter your MPIN'), findsNothing); // unlocked by the fingerprint
    });

    testWidgets('fingerprint refused: the MPIN pad stays, the right MPIN opens the app', (tester) async {
      await boot(tester, saved: savedLock(), biometrics: FakeBiometrics(matches: false));

      expect(find.text('Enter your MPIN'), findsOneWidget);
      expect(find.byKey(const Key('lock-biometric')), findsOneWidget);

      await typePin(tester, '1234');
      expect(find.text('Enter your MPIN'), findsNothing);
    });

    testWidgets('with the keyboard up the dots and the message stay above it, on the lock screen and when creating an MPIN', (tester) async {
      await boot(tester, saved: savedLock(), biometrics: FakeBiometrics(matches: false));

      const keyboard = 700.0; // a tall phone keyboard, in logical pixels (the test view is 1800 tall)
      tester.view.viewInsets = const FakeViewPadding(bottom: keyboard);
      addTearDown(tester.view.resetViewInsets);
      await tester.pumpAndSettle();

      for (var i = 0; i < 4; i++) {
        expect(tester.getBottomLeft(find.byKey(Key('pin-dot-$i'))).dy, lessThan(1800 - keyboard));
      }
      expect(tester.getTopLeft(find.byKey(const Key('pin-dot-0'))).dy, greaterThan(0));
    });

    testWidgets('the digits typed are shown in the boxes, not hidden', (tester) async {
      await boot(tester, saved: savedLock(), biometrics: FakeBiometrics(matches: false));

      await tester.enterText(find.byKey(const Key('pin-input')), '47');
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 200));

      expect(find.descendant(of: find.byKey(const Key('pin-dot-0')), matching: find.text('4')), findsOneWidget);
      expect(find.descendant(of: find.byKey(const Key('pin-dot-1')), matching: find.text('7')), findsOneWidget);
      expect(tester.widget<Opacity>(find.descendant(of: find.byKey(const Key('pin-dot-2')), matching: find.byType(Opacity))).opacity, 0); // nothing in the third yet
    });

    testWidgets('a wrong MPIN says how many tries are left', (tester) async {
      await boot(tester, saved: savedLock(biometric: false));

      await typePin(tester, '9999');
      expect(find.text('Wrong MPIN. 4 tries left'), findsOneWidget);
      expect(find.text('Enter your MPIN'), findsOneWidget);
    });

    testWidgets('five wrong MPINs sign the user out and forget the lock', (tester) async {
      await boot(tester, saved: savedLock(biometric: false));

      for (var i = 0; i < maxPinAttempts; i++) {
        await typePin(tester, '0000');
      }
      expect(find.text('Too many wrong MPINs. Signing you out…'), findsOneWidget);

      await tester.pump(const Duration(seconds: 2));
      await tester.pumpAndSettle();

      expect(find.text('Enter your MPIN'), findsNothing);
      expect(store.config, isNull);
      expect(find.byType(TextField), findsWidgets); // the login form
    });

    testWidgets('Forgot MPIN asks twice, then signs out to the password login', (tester) async {
      await boot(tester, saved: savedLock(biometric: false));

      await tester.tap(find.byKey(const Key('lock-forgot')));
      await tester.pump();
      expect(find.text('Tap again to sign out and use your password'), findsOneWidget);

      await tester.tap(find.byKey(const Key('lock-forgot')));
      await tester.pumpAndSettle();

      expect(find.text('Enter your MPIN'), findsNothing);
      expect(store.config, isNull);
    });
  });
}
