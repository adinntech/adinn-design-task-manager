import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/app_lock.dart';
import '../../core/auth.dart';

/// Sits above the whole app. Covers it with the lock screen (PIN or fingerprint/face) when the lock is on and the app is
/// locked, and with the "enable fingerprint unlock?" flow for someone who has not set it up yet.
class LockGate extends ConsumerWidget {
  const LockGate({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final signedIn = ref.watch(authProvider.select((s) => s.status == AuthStatus.signedIn));
    final lock = ref.watch(appLockProvider);

    Widget? overlay;
    if (signedIn) {
      if (!lock.ready) {
        overlay = const _Backdrop(child: Center(child: CircularProgressIndicator(color: Colors.white)));
      } else if (lock.enabled && lock.locked) {
        overlay = const LockScreen();
      } else if (lock.setupActive || lock.shouldOffer) {
        overlay = const LockSetupScreen();
      }
    }

    // While something covers the app it must not take taps, focus or screen-reader attention underneath.
    return Stack(children: [
      ExcludeFocus(excluding: overlay != null, child: ExcludeSemantics(excluding: overlay != null, child: child)),
      if (overlay != null) Positioned.fill(child: overlay),
    ]);
  }
}

class _Backdrop extends StatelessWidget {
  const _Backdrop({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;

    return Material(
      child: Container(
        decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [primary, const Color(0xFF8E030C)])),
        child: SafeArea(child: child),
      ),
    );
  }
}

/// Four dots and a number pad. Calls [onComplete] with the 4 digits, then clears itself for the next try.
class PinEntry extends StatefulWidget {
  const PinEntry({super.key, required this.title, this.message, this.messageIsError = false, required this.onComplete, this.bottomLeft});

  final String title;
  final String? message;
  final bool messageIsError;
  final ValueChanged<String> onComplete;

  /// The pad's bottom-left key (the fingerprint button on the lock screen).
  final Widget? bottomLeft;

  @override
  State<PinEntry> createState() => _PinEntryState();
}

class _PinEntryState extends State<PinEntry> {
  String _pin = '';

  void _digit(String d) {
    if (_pin.length >= 4) return;
    HapticFeedback.selectionClick();
    setState(() => _pin += d);

    if (_pin.length == 4) {
      final done = _pin;
      // A beat so the fourth dot is seen before the screen reacts.
      Future<void>.delayed(const Duration(milliseconds: 120), () {
        if (!mounted) return;
        setState(() => _pin = '');
        widget.onComplete(done);
      });
    }
  }

  void _back() {
    if (_pin.isEmpty) return;
    setState(() => _pin = _pin.substring(0, _pin.length - 1));
  }

  Widget _key(String label, {String? keyName, VoidCallback? onTap, Widget? icon}) => SizedBox(
        width: 78,
        height: 78,
        child: onTap == null
            ? const SizedBox.shrink()
            : Material(
                color: Colors.white.withValues(alpha: 0.14),
                shape: const CircleBorder(),
                child: InkWell(
                  key: Key(keyName ?? 'pin-$label'),
                  customBorder: const CircleBorder(),
                  onTap: onTap,
                  child: Center(child: icon ?? Text(label, style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w600, color: Colors.white))),
                ),
              ),
      );

  @override
  Widget build(BuildContext context) {
    final rows = [
      ['1', '2', '3'],
      ['4', '5', '6'],
      ['7', '8', '9'],
    ];

    return Column(mainAxisSize: MainAxisSize.min, children: [
      Text(widget.title, textAlign: TextAlign.center, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: Colors.white)),
      const SizedBox(height: 18),
      Row(mainAxisAlignment: MainAxisAlignment.center, children: [
        for (var i = 0; i < 4; i++)
          Container(
            key: Key('pin-dot-$i'),
            width: 16,
            height: 16,
            margin: const EdgeInsets.symmetric(horizontal: 9),
            decoration: BoxDecoration(shape: BoxShape.circle, color: i < _pin.length ? Colors.white : Colors.transparent, border: Border.all(color: Colors.white, width: 2)),
          ),
      ]),
      SizedBox(
        height: 44,
        child: Center(
          child: widget.message == null
              ? null
              : Text(widget.message!, key: const Key('pin-message'), textAlign: TextAlign.center, style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700, color: widget.messageIsError ? const Color(0xFFFFD6D2) : Colors.white70)),
        ),
      ),
      for (final row in rows)
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 7),
          child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            for (final d in row) Padding(padding: const EdgeInsets.symmetric(horizontal: 11), child: _key(d, onTap: () => _digit(d))),
          ]),
        ),
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 7),
        child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
          Padding(padding: const EdgeInsets.symmetric(horizontal: 11), child: SizedBox(width: 78, height: 78, child: widget.bottomLeft)),
          Padding(padding: const EdgeInsets.symmetric(horizontal: 11), child: _key('0', onTap: () => _digit('0'))),
          Padding(padding: const EdgeInsets.symmetric(horizontal: 11), child: _key('', keyName: 'pin-back', onTap: _back, icon: const Icon(Icons.backspace_outlined, color: Colors.white, size: 26))),
        ]),
      ),
    ]);
  }
}

/// "Enter your PIN", with the fingerprint / face prompt opening by itself when it is allowed and enrolled.
class LockScreen extends ConsumerStatefulWidget {
  const LockScreen({super.key});

  @override
  ConsumerState<LockScreen> createState() => _LockScreenState();
}

class _LockScreenState extends ConsumerState<LockScreen> {
  bool _bioUsable = false;
  bool _bioBusy = false;
  bool _signingOut = false;
  bool _confirmForgot = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _biometric());
  }

  Future<void> _biometric() async {
    if (_bioBusy || _signingOut) return;
    _bioBusy = true;
    final lock = ref.read(appLockProvider.notifier);

    try {
      final usable = await lock.biometricUsable();
      if (mounted) setState(() => _bioUsable = usable);
      if (usable) await lock.unlockWithBiometric(); // a cancelled or failed scan just leaves the PIN pad
    } finally {
      _bioBusy = false;
    }
  }

  Future<void> _onPin(String pin) async {
    if (_signingOut) return;
    final result = await ref.read(appLockProvider.notifier).unlockWithPin(pin);
    if (!mounted) return;

    switch (result) {
      case PinResult.ok:
        break; // the gate removes this screen
      case PinResult.wrong:
        final left = ref.read(appLockProvider).attemptsLeft;
        setState(() => _error = 'Wrong PIN. $left ${left == 1 ? 'try' : 'tries'} left');
      case PinResult.lockedOut:
        setState(() {
          _signingOut = true;
          _error = 'Too many wrong PINs. Signing you out…';
        });
        await Future<void>.delayed(const Duration(milliseconds: 1200));
        await ref.read(authProvider.notifier).logout();
    }
  }

  Future<void> _forgot() async {
    if (!_confirmForgot) {
      setState(() => _confirmForgot = true);
      return;
    }
    setState(() => _signingOut = true);
    await ref.read(authProvider.notifier).logout();
  }

  @override
  Widget build(BuildContext context) {
    final name = ref.watch(authProvider.select((s) => s.user?.name)) ?? '';

    return _Backdrop(
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(vertical: 16),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.lock_rounded, size: 40, color: Colors.white),
            const SizedBox(height: 10),
            if (name.isNotEmpty) Text('Welcome back, $name', key: const Key('lock-welcome'), style: const TextStyle(fontSize: 14, color: Colors.white70, fontWeight: FontWeight.w600)),
            const SizedBox(height: 6),
            PinEntry(
              title: 'Enter your PIN',
              message: _error,
              messageIsError: _error != null,
              onComplete: _onPin,
              bottomLeft: _bioUsable
                  ? Material(
                      color: Colors.white.withValues(alpha: 0.14),
                      shape: const CircleBorder(),
                      child: InkWell(
                        key: const Key('lock-biometric'),
                        customBorder: const CircleBorder(),
                        onTap: _biometric,
                        child: const Icon(Icons.fingerprint_rounded, color: Colors.white, size: 38),
                      ),
                    )
                  : null,
            ),
            const SizedBox(height: 6),
            TextButton(
              key: const Key('lock-forgot'),
              onPressed: _signingOut ? null : _forgot,
              style: TextButton.styleFrom(foregroundColor: Colors.white),
              child: Text(_confirmForgot ? 'Tap again to sign out and use your password' : 'Forgot PIN?'),
            ),
          ]),
        ),
      ),
    );
  }
}

enum _Step { offer, create, confirm, done }

/// The one-time "turn on fingerprint / face unlock?" flow: ask → new PIN → confirm PIN → (verify the fingerprint) → done.
/// Shown by the [LockGate] after sign-in, and pushed from Profile (where there is no question first and it can be closed).
class LockSetupScreen extends ConsumerStatefulWidget {
  const LockSetupScreen({super.key, this.fromProfile = false});

  final bool fromProfile;

  @override
  ConsumerState<LockSetupScreen> createState() => _LockSetupScreenState();
}

class _LockSetupScreenState extends ConsumerState<LockSetupScreen> {
  late _Step _step;
  String _first = '';
  String? _error;
  bool _working = false;
  bool _biometricOn = false;
  bool _needsEnrolling = false;

  @override
  void initState() {
    super.initState();
    // Gate: a fresh offer starts at the question; one already accepted (rebuilt) carries on at the PIN.
    _step = widget.fromProfile || ref.read(appLockProvider).setupActive ? _Step.create : _Step.offer;
  }

  void _accept() {
    ref.read(appLockProvider.notifier).beginSetup();
    setState(() => _step = _Step.create);
  }

  void _later() => ref.read(appLockProvider.notifier).dismissOffer();

  void _close() {
    if (widget.fromProfile) {
      Navigator.of(context).pop();
    } else {
      ref.read(appLockProvider.notifier).endSetup();
    }
  }

  void _onCreate(String pin) => setState(() {
        _first = pin;
        _error = null;
        _step = _Step.confirm;
      });

  Future<void> _onConfirm(String pin) async {
    if (pin != _first) {
      setState(() {
        _first = '';
        _error = 'The PINs did not match. Try again.';
        _step = _Step.create;
      });
      return;
    }

    setState(() => _working = true);
    final lock = ref.read(appLockProvider.notifier);
    final supported = ref.read(appLockProvider).supported;
    final enrolled = await lock.hasEnrolledBiometric();

    // If a fingerprint / face is enrolled, make the user prove it works before it is relied on to open the app.
    var biometric = supported;
    if (enrolled) biometric = await lock.confirmBiometric();

    await lock.enable(pin, biometric: biometric);
    if (!mounted) return;
    setState(() {
      _working = false;
      _biometricOn = biometric && enrolled;
      _needsEnrolling = supported && !enrolled;
      _step = _Step.done;
    });
  }

  @override
  Widget build(BuildContext context) {
    final body = switch (_step) {
      _Step.offer => _Message(
          icon: Icons.fingerprint_rounded,
          title: 'Unlock with your fingerprint or face?',
          text: 'Skip typing every time: open Adinn Tasks with your fingerprint or face. You will also create a 4-digit PIN as a backup.',
          primaryKey: 'lock-offer-yes',
          primary: 'Yes, turn it on',
          onPrimary: _accept,
          secondaryKey: 'lock-offer-later',
          secondary: 'Do it later',
          onSecondary: _later,
        ),
      _Step.create => PinEntry(key: const ValueKey('create'), title: 'Create a 4-digit PIN', message: _error ?? 'You will use it when the fingerprint is not available.', messageIsError: _error != null, onComplete: _onCreate),
      _Step.confirm => _working
          ? const Padding(padding: EdgeInsets.all(40), child: CircularProgressIndicator(color: Colors.white))
          : PinEntry(key: const ValueKey('confirm'), title: 'Confirm your PIN', message: 'Enter the same PIN again.', onComplete: _onConfirm),
      _Step.done => _Message(
          icon: Icons.verified_user_rounded,
          title: 'App lock is on',
          text: _biometricOn
              ? 'Adinn Tasks now asks for your fingerprint or face when you open it, or your PIN if you prefer.'
              : _needsEnrolling
                  ? 'Adinn Tasks now asks for your PIN. Add a fingerprint or face in your phone Settings and it will be offered here too.'
                  : 'Adinn Tasks now asks for your PIN when you open it.',
          primaryKey: 'lock-done',
          primary: 'Done',
          onPrimary: _close,
        ),
    };

    final canClose = widget.fromProfile && _step != _Step.done && !_working;

    final page = _Backdrop(
      child: Stack(children: [
        Center(child: SingleChildScrollView(padding: const EdgeInsets.all(24), child: body)),
        if (canClose)
          Positioned(
            top: 4,
            left: 4,
            child: IconButton(key: const Key('lock-setup-close'), color: Colors.white, icon: const Icon(Icons.close_rounded), onPressed: _close),
          ),
      ]),
    );

    // From Profile this is an ordinary page; in the gate it simply is the screen.
    return page;
  }
}

class _Message extends StatelessWidget {
  const _Message({required this.icon, required this.title, required this.text, required this.primaryKey, required this.primary, required this.onPrimary, this.secondaryKey, this.secondary, this.onSecondary});

  final IconData icon;
  final String title;
  final String text;
  final String primaryKey;
  final String primary;
  final VoidCallback onPrimary;
  final String? secondaryKey;
  final String? secondary;
  final VoidCallback? onSecondary;

  @override
  Widget build(BuildContext context) => Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, size: 72, color: Colors.white),
        const SizedBox(height: 18),
        Text(title, textAlign: TextAlign.center, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: Colors.white)),
        const SizedBox(height: 10),
        Text(text, textAlign: TextAlign.center, style: const TextStyle(fontSize: 14.5, height: 1.4, color: Colors.white70)),
        const SizedBox(height: 28),
        SizedBox(
          width: double.infinity,
          child: FilledButton(
            key: Key(primaryKey),
            style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: const Color(0xFFB5040F), minimumSize: const Size.fromHeight(50)),
            onPressed: onPrimary,
            child: Text(primary, style: const TextStyle(fontWeight: FontWeight.w800)),
          ),
        ),
        if (secondary != null) ...[
          const SizedBox(height: 6),
          TextButton(key: Key(secondaryKey ?? 'secondary'), style: TextButton.styleFrom(foregroundColor: Colors.white), onPressed: onSecondary, child: Text(secondary!)),
        ],
      ]);
}
