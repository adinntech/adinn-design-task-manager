import 'dart:async';

import 'package:adinn_tasks/core/app_lock.dart';
import 'package:adinn_tasks/features/auth/intro/adinn_intro.dart';
import 'package:adinn_tasks/features/auth/login_screen.dart';
import 'package:adinn_tasks/features/kanban/kanban_screen.dart';
import 'package:adinn_tasks/features/splash/launch_flow.dart';

/// Runs before every test file.
///
/// - The endlessly flowing swipe-up hint would stop pumpAndSettle from settling, so it is switched off.
/// - The app lock reads secure storage and asks the phone about fingerprint hardware. Those platform calls never answer
///   under the widget-test clock, which would leave every test that boots the app waiting on the lock. So by default a test
///   sees a phone with no lock set and no biometric hardware; tests of the lock itself override the providers.
Future<void> testExecutable(FutureOr<void> Function() testMain) async {
  swipeHintAnimates = false;
  LoginScreen.animateBackground = false; // an endless background drift would keep pumpAndSettle from settling
  LaunchFlow.enabled = false; // no launch splash or tour under test
  AdinnIntro.enabled = false; // the sign-in screen starts in its final state; the sequence has its own test
  defaultAppLockStore = _NoLockStore.new;
  defaultBiometricService = _NoBiometrics.new;

  await testMain();
}

class _NoLockStore implements AppLockStore {
  @override
  Future<LockConfig?> read() => Future.value();

  @override
  Future<void> write(LockConfig config) => Future.value();

  @override
  Future<void> clear() => Future.value();
}

class _NoBiometrics implements BiometricService {
  @override
  Future<bool> isSupported() => Future.value(false);

  @override
  Future<bool> hasEnrolled() => Future.value(false);

  @override
  Future<bool> authenticate(String reason) => Future.value(false);
}
