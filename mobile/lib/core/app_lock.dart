import 'dart:convert';
import 'dart:math';

import 'package:crypto/crypto.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:local_auth/local_auth.dart';

/// Wrong PINs allowed before the user is signed out and must use their password.
const maxPinAttempts = 5;

/// Leaving the app for less than this keeps it unlocked (a permission dialog, a file picker, a quick app switch).
/// A cold start, and anything longer, asks for the PIN or fingerprint again.
const lockGracePeriod = Duration(seconds: 30);

/// What is kept on the phone for the app lock: a salted hash of the PIN (never the PIN), whether fingerprint/face
/// may be used, and how many wrong PINs have been typed since the last good one.
class LockConfig {
  const LockConfig({required this.salt, required this.hash, required this.biometric, this.failed = 0});

  final String salt;
  final String hash;
  final bool biometric;
  final int failed;

  LockConfig copyWith({int? failed}) => LockConfig(salt: salt, hash: hash, biometric: biometric, failed: failed ?? this.failed);

  Map<String, dynamic> toJson() => {'salt': salt, 'hash': hash, 'biometric': biometric, 'failed': failed};

  factory LockConfig.fromJson(Map<String, dynamic> j) => LockConfig(
        salt: j['salt'] as String,
        hash: j['hash'] as String,
        biometric: j['biometric'] == true,
        failed: (j['failed'] as num?)?.toInt() ?? 0,
      );

  /// A salted, stretched SHA-256: cheap enough for a 4-digit PIN, but never stored in the clear.
  static String hashPin(String pin, String salt) {
    var digest = sha256.convert(utf8.encode('$salt:$pin')).bytes;
    for (var i = 0; i < 5000; i++) {
      digest = sha256.convert([...digest, ...utf8.encode(salt)]).bytes;
    }
    return base64Encode(digest);
  }

  static String newSalt() => base64Encode(List<int>.generate(16, (_) => Random.secure().nextInt(256)));

  bool matches(String pin) => hashPin(pin, salt) == hash;
}

/// Where the lock config lives. Abstract so tests can swap in memory.
abstract class AppLockStore {
  Future<LockConfig?> read();
  Future<void> write(LockConfig config);
  Future<void> clear();
}

class SecureAppLockStore implements AppLockStore {
  const SecureAppLockStore([this._storage = const FlutterSecureStorage()]);

  final FlutterSecureStorage _storage;

  static const _key = 'app_lock';

  @override
  Future<LockConfig?> read() async {
    try {
      final raw = await _storage.read(key: _key);
      return raw == null ? null : LockConfig.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  @override
  Future<void> write(LockConfig config) => _storage.write(key: _key, value: jsonEncode(config.toJson()));

  @override
  Future<void> clear() => _storage.delete(key: _key);
}

/// Fingerprint / face check. Abstract so tests need no sensor.
abstract class BiometricService {
  /// The phone has biometric hardware (whether or not anything is enrolled yet).
  Future<bool> isSupported();

  /// At least one fingerprint / face is enrolled in the phone's settings.
  Future<bool> hasEnrolled();

  /// Shows the system prompt; false when the user cancels or it does not match.
  Future<bool> authenticate(String reason);
}

class DeviceBiometricService implements BiometricService {
  DeviceBiometricService([LocalAuthentication? auth]) : _auth = auth ?? LocalAuthentication();

  final LocalAuthentication _auth;

  @override
  Future<bool> isSupported() async {
    try {
      return await _auth.isDeviceSupported() && await _auth.canCheckBiometrics;
    } catch (_) {
      return false;
    }
  }

  @override
  Future<bool> hasEnrolled() async {
    try {
      return (await _auth.getAvailableBiometrics()).isNotEmpty;
    } catch (_) {
      return false;
    }
  }

  @override
  Future<bool> authenticate(String reason) async {
    try {
      // biometricOnly: the app has its own PIN, so the phone's screen-lock PIN is not offered as a second fallback.
      // sensitiveTransaction: false: this is an app unlock, not a payment. With the default (true) Android makes a
      // recognised FACE wait for an extra "Confirm" tap, and on phones with both sensors the prompt then looks
      // fingerprint-only. Off, a matching face unlocks straight away while the fingerprint sensor is also listening.
      return await _auth.authenticate(localizedReason: reason, biometricOnly: true, sensitiveTransaction: false);
    } catch (_) {
      return false;
    }
  }
}

enum PinResult { ok, wrong, lockedOut }

class AppLockState {
  const AppLockState({
    this.ready = false,
    this.enabled = false,
    this.biometric = false,
    this.locked = false,
    this.supported = false,
    this.offerDismissed = false,
    this.setupActive = false,
    this.failed = 0,
  });

  /// The saved config has been read (until then a signed-in user sees a cover, never the app).
  final bool ready;

  /// A PIN is set: the app asks for it.
  final bool enabled;

  /// Fingerprint / face may be used in place of typing the PIN.
  final bool biometric;

  /// The app is currently behind the lock screen.
  final bool locked;

  /// The phone has biometric hardware, so the "enable fingerprint?" question makes sense.
  final bool supported;

  /// "Do it later" was chosen: do not ask again until the next launch or sign-in.
  final bool offerDismissed;

  /// The user said yes and is part-way through creating the PIN (the setup screen stays up until they press Done).
  final bool setupActive;
  final int failed;

  int get attemptsLeft => maxPinAttempts - failed;

  /// Whether to ask a signed-in user "enable fingerprint / face unlock?".
  bool get shouldOffer => ready && supported && !enabled && !offerDismissed;

  AppLockState copyWith({bool? ready, bool? enabled, bool? biometric, bool? locked, bool? supported, bool? offerDismissed, bool? setupActive, int? failed}) => AppLockState(
        ready: ready ?? this.ready,
        enabled: enabled ?? this.enabled,
        biometric: biometric ?? this.biometric,
        locked: locked ?? this.locked,
        supported: supported ?? this.supported,
        offerDismissed: offerDismissed ?? this.offerDismissed,
        setupActive: setupActive ?? this.setupActive,
        failed: failed ?? this.failed,
      );
}

/// What the providers build by default. A seam for `test/flutter_test_config.dart`: platform-plugin calls never answer under
/// the widget-test clock, so tests swap these for instant fakes instead of hanging every screen that boots the app.
AppLockStore Function() defaultAppLockStore = () => const SecureAppLockStore();
BiometricService Function() defaultBiometricService = DeviceBiometricService.new;

final appLockStoreProvider = Provider<AppLockStore>((ref) => defaultAppLockStore());
final biometricServiceProvider = Provider<BiometricService>((ref) => defaultBiometricService());
final appLockProvider = NotifierProvider<AppLockController, AppLockState>(AppLockController.new);

class AppLockController extends Notifier<AppLockState> {
  LockConfig? _config;
  DateTime? _leftAt;

  @override
  AppLockState build() => const AppLockState();

  AppLockStore get _store => ref.read(appLockStoreProvider);
  BiometricService get _bio => ref.read(biometricServiceProvider);

  /// Reads the saved lock at app start. A PIN already set means a cold start begins locked.
  Future<void> load() async {
    _config = await _store.read();
    final supported = _config == null ? await _bio.isSupported() : false;
    final c = _config;

    state = AppLockState(
      ready: true,
      enabled: c != null,
      biometric: c?.biometric ?? false,
      locked: c != null,
      supported: supported,
      failed: c?.failed ?? 0,
    );
  }

  /// A fresh password sign-in just proved who this is: nothing to unlock, and the lock question is asked again.
  Future<void> onSignedIn() async {
    final supported = _config == null && await _bio.isSupported();
    state = state.copyWith(ready: true, locked: false, offerDismissed: false, setupActive: false, supported: supported);
  }

  void dismissOffer() => state = state.copyWith(offerDismissed: true);

  void beginSetup() => state = state.copyWith(setupActive: true);

  void endSetup() => state = state.copyWith(setupActive: false);

  /// Turns the lock on with a new [pin]. [biometric] allows fingerprint / face as well.
  Future<void> enable(String pin, {required bool biometric}) async {
    final salt = LockConfig.newSalt();
    final config = LockConfig(salt: salt, hash: LockConfig.hashPin(pin, salt), biometric: biometric);
    await _store.write(config);
    _config = config;
    state = state.copyWith(ready: true, enabled: true, biometric: biometric, locked: false, failed: 0);
  }

  /// Turns the lock off and forgets the PIN (also used on sign-out).
  Future<void> reset() async {
    _config = null;
    _leftAt = null;
    await _store.clear();
    state = AppLockState(ready: true, supported: await _bio.isSupported(), offerDismissed: state.offerDismissed);
  }

  /// Checks a PIN without unlocking (used to confirm turning the lock off).
  bool verifyPin(String pin) => _config?.matches(pin) ?? false;

  Future<PinResult> unlockWithPin(String pin) async {
    final config = _config;
    if (config == null) return PinResult.ok;

    if (config.matches(pin)) {
      _config = config.copyWith(failed: 0);
      await _store.write(_config!);
      state = state.copyWith(locked: false, failed: 0);
      return PinResult.ok;
    }

    final updated = config.copyWith(failed: config.failed + 1);
    _config = updated;
    await _store.write(updated);
    state = state.copyWith(failed: updated.failed);

    return updated.failed >= maxPinAttempts ? PinResult.lockedOut : PinResult.wrong;
  }

  /// Whether the lock screen should offer the fingerprint button (allowed, and something is enrolled).
  Future<bool> biometricUsable() async => state.biometric && await _bio.hasEnrolled();

  Future<bool> unlockWithBiometric() async {
    if (!await biometricUsable()) return false;
    if (!await _bio.authenticate('Unlock Adinn Design Tasks')) return false;

    _config = _config?.copyWith(failed: 0);
    if (_config != null) await _store.write(_config!);
    state = state.copyWith(locked: false, failed: 0);
    return true;
  }

  /// The setup step that proves the user's fingerprint / face works before it is relied on.
  Future<bool> confirmBiometric() async => await _bio.hasEnrolled() && await _bio.authenticate('Confirm to turn on fingerprint unlock');

  Future<bool> hasEnrolledBiometric() => _bio.hasEnrolled();

  /// [at] is only for tests.
  void appPaused({DateTime? at}) => _leftAt = at ?? DateTime.now();

  /// Back in the foreground: locks again when it has been away longer than the grace period.
  void appResumed({DateTime? at}) {
    final left = _leftAt;
    _leftAt = null;
    if (state.enabled && !state.locked && left != null && (at ?? DateTime.now()).difference(left) >= lockGracePeriod) {
      state = state.copyWith(locked: true);
    }
  }
}
