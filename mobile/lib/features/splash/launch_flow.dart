import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'onboarding_screen.dart';
import 'splash_screen.dart';

/// What the app shows when it opens: the animated Adinn logo reveal, then, on the first launch after installing, the
/// welcome tour ending in "Get started", then whatever the app has ready: the sign-in screen or the PIN lock.
///
/// Animation only, no video. The app keeps starting up underneath (session check, routing), so it is ready when this steps
/// aside. The reveal plays once per app run; the tour once per install.
class LaunchFlow extends StatefulWidget {
  const LaunchFlow({super.key, required this.child});

  final Widget child;

  /// Tests switch it off so screens start in their final state.
  static bool enabled = true;

  /// Set once it has started, so a rebuild never replays it.
  static bool played = false;

  /// Where "the welcome tour has been seen" is remembered (replaceable in tests).
  static OnboardingStore store = const SecureOnboardingStore();

  @override
  State<LaunchFlow> createState() => _LaunchFlowState();
}

enum _Stage { reveal, tour, done }

class _LaunchFlowState extends State<LaunchFlow> {
  _Stage _stage = _Stage.done;
  bool _tourSeen = false;
  // Keeps the app's state when it moves from behind the launch screens to the front.
  final _appKey = GlobalKey();
  Timer? _watchdog;

  @override
  void initState() {
    super.initState();
    if (!LaunchFlow.enabled || LaunchFlow.played) return;
    LaunchFlow.played = true;
    _stage = _Stage.reveal;
    // Never leave the user on a launch screen: if the reveal has not finished by now, show the app.
    _watchdog = Timer(const Duration(seconds: 9), () {
      if (mounted && _stage == _Stage.reveal) setState(() => _stage = _Stage.done);
    });
    // If the answer cannot be read, show the tour rather than hide it.
    LaunchFlow.store.seen().then((seen) => _tourSeen = seen, onError: (_) => _tourSeen = false);
  }

  @override
  void dispose() {
    _watchdog?.cancel();
    super.dispose();
  }

  void _afterReveal() {
    if (!mounted) return;
    setState(() => _stage = _tourSeen ? _Stage.done : _Stage.tour);
  }

  void _afterTour() {
    LaunchFlow.store.markSeen().catchError((_) {});
    if (mounted) setState(() => _stage = _Stage.done);
  }

  @override
  Widget build(BuildContext context) {
    final app = KeyedSubtree(key: _appKey, child: widget.child);
    if (_stage == _Stage.done) return app;
    return Stack(
      fit: StackFit.expand,
      children: [
        // Held back meanwhile: not painted and its animations paused, so the launch screens get every frame.
        TickerMode(enabled: false, child: Offstage(child: app)),
        AnnotatedRegion<SystemUiOverlayStyle>(
          value: SystemUiOverlayStyle.dark,
          child: AnimatedSwitcher(
            duration: const Duration(milliseconds: 350),
            child: _stage == _Stage.reveal
                ? SplashScreen(key: const ValueKey('reveal'), onDone: _afterReveal)
                : OnboardingScreen(key: const ValueKey('tour'), onDone: _afterTour),
          ),
        ),
      ],
    );
  }
}

abstract class OnboardingStore {
  Future<bool> seen();
  Future<void> markSeen();
}

class SecureOnboardingStore implements OnboardingStore {
  const SecureOnboardingStore([this._storage = const FlutterSecureStorage()]);

  final FlutterSecureStorage _storage;
  static const _key = 'onboarding_seen';

  @override
  Future<bool> seen() async => (await _storage.read(key: _key)) == '1';

  @override
  Future<void> markSeen() => _storage.write(key: _key, value: '1');
}
