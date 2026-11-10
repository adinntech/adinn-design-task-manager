import 'dart:math' as math;
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/app_lock.dart';
import '../../core/auth.dart';

/// Sits above the whole app. Covers it with the lock screen (MPIN or fingerprint/face) when the lock is on and the app is
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
        overlay = const _Backdrop(child: Center(child: CircularProgressIndicator()));
      } else if (lock.enabled && lock.locked) {
        overlay = const _WithOverlay(key: ValueKey('lock'), child: LockScreen());
      } else if (lock.setupActive || lock.shouldOffer) {
        overlay = const _WithOverlay(key: ValueKey('setup'), child: LockSetupScreen());
      }
    }

    // While something covers the app it must not take taps, focus or screen-reader attention underneath.
    return Stack(children: [
      ExcludeFocus(excluding: overlay != null, child: ExcludeSemantics(excluding: overlay != null, child: child)),
      if (overlay != null) Positioned.fill(child: overlay),
    ]);
  }
}

/// The gate sits above the app's Navigator, so it has no Overlay of its own, and a text field (the MPIN input) needs one.
/// This gives the lock screens a small private Overlay.
class _WithOverlay extends StatefulWidget {
  const _WithOverlay({super.key, required this.child});

  final Widget child;

  @override
  State<_WithOverlay> createState() => _WithOverlayState();
}

class _WithOverlayState extends State<_WithOverlay> {
  late final OverlayEntry _entry = OverlayEntry(builder: (_) => widget.child);

  @override
  void dispose() {
    _entry.remove();
    _entry.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Overlay(initialEntries: [_entry]);
}

class _Backdrop extends StatelessWidget {
  const _Backdrop({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    // The gate sits above the app, where nothing resizes for the keyboard, so make room for it here: the content then
    // re-centres in the space that is left, above the keyboard, and the dots stay in view while typing.
    return Material(
      color: _neoBase,
      child: Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
        child: SafeArea(child: child),
      ),
    );
  }
}

// ---------------------------------------------------------------------------------------------
// Soft, pressed-in keypad: raised is empty, sunk is filled.
// ---------------------------------------------------------------------------------------------

/// Minimal red and white: a warm white page, blush-grey shadows, red as the only colour.
const _neoBase = Color(0xFFFCF9F9);
const _neoLight = Color(0xFFFFFFFF);
const _neoDark = Color(0xFFE2CFD1);

/// A round surface that is either raised off the page (soft light from the top-left, soft shadow to the bottom-right) or
/// sunk into it (the same two shadows, on the inside). [depth] 0 → fully raised, 1 → fully sunk; in between it moves.
class _NeoPainter extends CustomPainter {
  _NeoPainter({required this.depth, required this.fill});

  final double depth;
  final Color fill;

  @override
  void paint(Canvas canvas, Size size) {
    final r = size.shortestSide / 2;
    final c = size.center(Offset.zero);
    final circle = Rect.fromCircle(center: c, radius: r);
    final raised = 1 - depth;

    // raised: light and shadow outside the edge
    if (raised > 0.01) {
      final blur = MaskFilter.blur(BlurStyle.normal, r * 0.28);
      canvas.drawCircle(c + Offset(-r * 0.16, -r * 0.16), r, Paint()..color = _neoLight.withValues(alpha: raised)..maskFilter = blur);
      canvas.drawCircle(c + Offset(r * 0.16, r * 0.16), r, Paint()..color = _neoDark.withValues(alpha: raised)..maskFilter = blur);
    }

    canvas.drawCircle(c, r, Paint()..color = fill);

    // sunk: the same light and shadow, inside the edge
    if (depth > 0.01) {
      canvas.save();
      canvas.clipPath(Path()..addOval(circle));
      final ring = Path()
        ..fillType = PathFillType.evenOdd
        ..addRect(circle.inflate(r))
        ..addOval(circle);
      final blur = MaskFilter.blur(BlurStyle.normal, r * 0.26);
      canvas.drawPath(ring.shift(Offset(r * 0.16, r * 0.16)), Paint()..color = const Color(0xFFD4B5B8).withValues(alpha: 0.9 * depth)..maskFilter = blur);
      canvas.drawPath(ring.shift(Offset(-r * 0.16, -r * 0.16)), Paint()..color = _neoLight.withValues(alpha: 0.9 * depth)..maskFilter = blur);
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(_NeoPainter old) => old.depth != depth || old.fill != fill;
}

/// One box of the MPIN: raised and empty until a digit is typed, then sunk in, with the digit itself showing in red.
class _NeoDot extends StatefulWidget {
  const _NeoDot({super.key, required this.digit, this.size = 34});

  final String? digit;
  final double size;

  @override
  State<_NeoDot> createState() => _NeoDotState();
}

class _NeoDotState extends State<_NeoDot> {
  String _last = ''; // kept while the box rises again, so the digit fades out instead of vanishing

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    final filled = widget.digit != null;
    if (filled) _last = widget.digit!;

    return TweenAnimationBuilder<double>(
      tween: Tween(end: filled ? 1 : 0),
      duration: const Duration(milliseconds: 160),
      curve: Curves.easeOut,
      builder: (context, depth, _) => SizedBox(
        width: widget.size,
        height: widget.size,
        child: CustomPaint(
          painter: _NeoPainter(depth: depth, fill: _neoBase),
          child: Center(
            child: Opacity(
              opacity: depth,
              child: Text(_last, style: TextStyle(fontSize: widget.size * 0.52, fontWeight: FontWeight.w800, color: primary, height: 1)),
            ),
          ),
        ),
      ),
    );
  }
}

/// Four boxes and the phone's own number keyboard. The boxes show what has been typed: raised and empty until a digit
/// comes, then sunk in with the digit showing. Calls [onComplete] with the 4 digits, then clears itself for the next try.
class PinEntry extends StatefulWidget {
  const PinEntry({super.key, required this.title, this.subtitle = 'Enter the 4 digits on your keyboard', this.message, this.messageIsError = false, required this.onComplete, this.bottomLeft});

  final String title;
  final String subtitle;
  final String? message;
  final bool messageIsError;
  final ValueChanged<String> onComplete;

  /// An extra round button under the dots (the fingerprint button on the lock screen).
  final Widget? bottomLeft;

  @override
  State<PinEntry> createState() => _PinEntryState();
}

class _PinEntryState extends State<PinEntry> with SingleTickerProviderStateMixin {
  late final AnimationController _shake = AnimationController(vsync: this, duration: const Duration(milliseconds: 420));
  final _controller = TextEditingController();
  final _focus = FocusNode();
  String _pin = '';

  @override
  void didUpdateWidget(PinEntry old) {
    super.didUpdateWidget(old);
    // A wrong MPIN: the dots shake, and the phone buzzes once.
    if (widget.messageIsError && (!old.messageIsError || old.message != widget.message)) {
      HapticFeedback.heavyImpact();
      _shake.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _shake.dispose();
    _controller.dispose();
    _focus.dispose();
    super.dispose();
  }

  void _changed(String v) {
    final digits = v.replaceAll(RegExp(r'[^0-9]'), '');
    if (digits.length > _pin.length) HapticFeedback.selectionClick();
    setState(() => _pin = digits);

    if (digits.length == 4) {
      // A beat so the fourth dot is seen sinking before the screen reacts.
      Future<void>.delayed(const Duration(milliseconds: 160), () {
        if (!mounted) return;
        _controller.clear();
        setState(() => _pin = '');
        widget.onComplete(digits);
        if (mounted) _focus.requestFocus(); // keep the keyboard up for the next try
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(mainAxisSize: MainAxisSize.min, children: [
      Text(widget.title, textAlign: TextAlign.center, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: -0.4, color: Color(0xFF1F0A0C))),
      const SizedBox(height: 6),
      Text(widget.subtitle, textAlign: TextAlign.center, style: const TextStyle(fontSize: 13.5, color: Color(0xFF8C6F72))),
      const SizedBox(height: 28),
      // Tapping the dots brings the keyboard back if it was dismissed.
      GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () => _focus.requestFocus(),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 12),
          child: AnimatedBuilder(
            animation: _shake,
            builder: (context, child) => Transform.translate(offset: Offset(math.sin(_shake.value * math.pi * 5) * 12 * (1 - _shake.value), 0), child: child),
            child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            for (var i = 0; i < 4; i++) Padding(padding: const EdgeInsets.symmetric(horizontal: 11), child: _NeoDot(key: Key('pin-dot-$i'), digit: i < _pin.length ? _pin[i] : null, size: 54)),
            ]),
          ),
        ),
      ),
      // The real input: invisible, but it is what the phone's keyboard types into.
      SizedBox(
        width: 1,
        height: 1,
        child: Opacity(
          opacity: 0,
          child: TextField(
            key: const Key('pin-input'),
            controller: _controller,
            focusNode: _focus,
            autofocus: true,
            keyboardType: TextInputType.number,
            textInputAction: TextInputAction.done,
            obscureText: true,
            enableSuggestions: false,
            autocorrect: false,
            enableIMEPersonalizedLearning: false,
            maxLength: 4,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(4)],
            decoration: const InputDecoration(counterText: '', border: InputBorder.none, contentPadding: EdgeInsets.zero),
            onChanged: _changed,
          ),
        ),
      ),
      SizedBox(
        height: 52,
        child: Center(
          child: widget.message == null
              ? null
              : Text(widget.message!, key: const Key('pin-message'), textAlign: TextAlign.center, style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700, color: widget.messageIsError ? const Color(0xFFD92D20) : const Color(0xFF8C6F72))),
        ),
      ),
      if (widget.bottomLeft != null) Padding(padding: const EdgeInsets.only(top: 2), child: SizedBox(width: 86, height: 86, child: widget.bottomLeft)),
    ]);
  }
}

/// "Enter your MPIN", with the fingerprint / face prompt opening by itself when it is allowed and enrolled.
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
      if (usable) await lock.unlockWithBiometric(); // a cancelled or failed scan just leaves the MPIN pad
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
        setState(() => _error = 'Wrong MPIN. $left ${left == 1 ? 'try' : 'tries'} left');
      case PinResult.lockedOut:
        setState(() {
          _signingOut = true;
          _error = 'Too many wrong MPINs. Signing you out…';
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
    final primary = Theme.of(context).colorScheme.primary;
    final initial = name.isNotEmpty ? name[0].toUpperCase() : '?';
    final typing = MediaQuery.viewInsetsOf(context).bottom > 0; // keyboard up: the mark shrinks so the dots fit above it

    return _Backdrop(
      child: Column(children: [
        Expanded(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(24, 16, 24, 8),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                // the mark, over a soft ring
                AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  width: typing ? 56 : 84,
                  height: typing ? 56 : 84,
                  padding: EdgeInsets.all(typing ? 9 : 14),
                  decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white, boxShadow: [BoxShadow(color: primary.withValues(alpha: 0.14), blurRadius: 26, offset: const Offset(0, 10))]),
                  child: Image.asset('assets/branding/logo.png', errorBuilder: (_, _, _) => Icon(Icons.lock_rounded, color: primary, size: 36)),
                ),
                SizedBox(height: typing ? 10 : 18),
                if (name.isNotEmpty)
                  Row(mainAxisSize: MainAxisSize.min, children: [
                    CircleAvatar(radius: 13, backgroundColor: primary.withValues(alpha: 0.1), child: Text(initial, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: primary))),
                    const SizedBox(width: 8),
                    Flexible(child: Text('Welcome back, $name', key: const Key('lock-welcome'), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 14, color: Color(0xFF8C6F72), fontWeight: FontWeight.w600))),
                  ]),
                SizedBox(height: typing ? 12 : 22),
                PinEntry(
                  title: 'Enter your MPIN',
                  subtitle: 'Your 4-digit code to open Adinn Tasks',
                  message: _error,
                  messageIsError: _error != null,
                  onComplete: _onPin,
                  bottomLeft: null,
                ),
                if (_bioUsable)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: OutlinedButton.icon(
                      key: const Key('lock-biometric'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: primary,
                        side: BorderSide(color: primary.withValues(alpha: 0.35)),
                        minimumSize: const Size(0, 46),
                        padding: const EdgeInsets.symmetric(horizontal: 22),
                        shape: const StadiumBorder(),
                      ),
                      onPressed: _biometric,
                      icon: const Icon(Icons.fingerprint_rounded, size: 24),
                      label: const Text('Use fingerprint', style: TextStyle(fontWeight: FontWeight.w800)),
                    ),
                  ),
                const SizedBox(height: 6),
                TextButton(
                  key: const Key('lock-forgot'),
                  onPressed: _signingOut ? null : _forgot,
                  style: TextButton.styleFrom(foregroundColor: primary),
                  child: Text(_confirmForgot ? 'Tap again to sign out and use your password' : 'Forgot MPIN?', style: const TextStyle(fontWeight: FontWeight.w700)),
                ),
              ]),
            ),
          ),
        ),
        // a quiet reassurance at the foot
        if (!typing)
        Padding(
          padding: const EdgeInsets.only(bottom: 14),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Icon(Icons.verified_user_outlined, size: 15, color: primary.withValues(alpha: 0.7)),
            const SizedBox(width: 6),
            const Text('Secured on this device', style: TextStyle(fontSize: 12, color: Color(0xFF8C6F72), fontWeight: FontWeight.w600)),
          ]),
        ),
      ]),
    );
  }
}

enum _Step { offer, create, confirm, done }

/// The one-time "turn on fingerprint / face unlock?" flow: ask → new MPIN → confirm MPIN → (verify the fingerprint) → done.
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
    // Gate: a fresh offer starts at the question; one already accepted (rebuilt) carries on at the MPIN.
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
        _error = 'The MPINs did not match. Try again.';
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
          text: 'Skip typing every time: open Adinn Tasks with your fingerprint or face. You will also create a 4-digit MPIN as a backup.',
          primaryKey: 'lock-offer-yes',
          primary: 'Yes, turn it on',
          onPrimary: _accept,
          secondaryKey: 'lock-offer-later',
          secondary: 'Do it later',
          onSecondary: _later,
        ),
      _Step.create => PinEntry(key: const ValueKey('create'), title: 'Create a 4-digit MPIN', subtitle: 'Pick digits only you will remember', message: _error ?? 'You will use it when the fingerprint is not available.', messageIsError: _error != null, onComplete: _onCreate),
      _Step.confirm => _working
          ? const Padding(padding: EdgeInsets.all(40), child: CircularProgressIndicator())
          : PinEntry(key: const ValueKey('confirm'), title: 'Confirm your MPIN', subtitle: 'One more time, to be sure', message: 'Enter the same MPIN again.', onComplete: _onConfirm),
      _Step.done => _Message(
          icon: Icons.verified_user_rounded,
          title: 'App lock is on',
          text: _biometricOn
              ? 'Adinn Tasks now asks for your fingerprint or face when you open it, or your MPIN if you prefer.'
              : _needsEnrolling
                  ? 'Adinn Tasks now asks for your MPIN. Add a fingerprint or face in your phone Settings and it will be offered here too.'
                  : 'Adinn Tasks now asks for your MPIN when you open it.',
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
            child: IconButton(key: const Key('lock-setup-close'), color: const Color(0xFF1F0A0C), icon: const Icon(Icons.close_rounded), onPressed: _close),
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
        Icon(icon, size: 72, color: Theme.of(context).colorScheme.primary),
        const SizedBox(height: 18),
        Text(title, textAlign: TextAlign.center, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: Color(0xFF1F0A0C))),
        const SizedBox(height: 10),
        Text(text, textAlign: TextAlign.center, style: const TextStyle(fontSize: 14.5, height: 1.4, color: Color(0xFF8C6F72))),
        const SizedBox(height: 28),
        SizedBox(
          width: double.infinity,
          child: FilledButton(
            key: Key(primaryKey),
            style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(52)),
            onPressed: onPrimary,
            child: Text(primary, style: const TextStyle(fontWeight: FontWeight.w800)),
          ),
        ),
        if (secondary != null) ...[
          const SizedBox(height: 6),
          TextButton(key: Key(secondaryKey ?? 'secondary'), style: TextButton.styleFrom(foregroundColor: const Color(0xFF8C6F72)), onPressed: onSecondary, child: Text(secondary!)),
        ],
      ]);
}
