import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/app_update.dart';
import 'core/auth.dart';
import 'core/push.dart';
import 'core/realtime.dart';
import 'core/theme.dart';
import 'features/requests/requests_screen.dart';
import 'features/tasks/providers.dart';
import 'features/update/update_gate.dart';
import 'router.dart';

void main() {
  // Riverpod 3 retries failed providers silently with backoff; the screens have an explicit "Try again" instead.
  runApp(ProviderScope(retry: (_, _) => null, child: const AdinnApp()));
}

final _theme = buildTheme();

class AdinnApp extends ConsumerStatefulWidget {
  const AdinnApp({super.key});

  @override
  ConsumerState<AdinnApp> createState() => _AdinnAppState();
}

class _AdinnAppState extends ConsumerState<AdinnApp> with WidgetsBindingObserver {
  StreamSubscription<RealtimeEvent>? _live;

  /// The Admin changed what this designer may do (Approvals): re-read the login, and the approvals list with it, so the tab
  /// appears (and fills) without reopening the app.
  Future<void> _refreshAccess() async {
    await ref.read(authProvider.notifier).refreshUser();
    ref.invalidate(pendingRequestsProvider);
  }

  /// Everything a server-side change can make stale: alerts, boards, request lists, open tasks and chats.
  void _refreshLiveData() {
    ref.invalidate(notificationsProvider);
    ref.invalidate(boardProvider);
    ref.invalidate(kanbanBoardProvider);
    ref.invalidate(pendingRequestsProvider);
    ref.invalidate(taskDetailProvider);
    ref.invalidate(commentsProvider);
    ref.invalidate(appUpdateProvider); // a new release may have been published
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _live = ref.read(realtimeProvider).events.listen((e) {
      _refreshLiveData();
      if (e.category == 'approval_access') _refreshAccess();
    });
    // Restore a saved session; the router shows a splash until this resolves.
    Future.microtask(() => ref.read(authProvider.notifier).restore());

    // Push: tapping a notification opens its task; a push in the foreground refreshes the badges/lists.
    final push = ref.read(pushRegistrarProvider);
    push.onOpened = (event) {
      // "Update available": opening the app re-checks the release, which raises the Update screen if it is required.
      if (event.isAppUpdate) {
        ref.invalidate(appUpdateProvider);
        return;
      }
      final id = event.taskId;
      if (id != null) ref.read(routerProvider).push('/task/$id');
    };
    push.onForeground = () {
      _refreshLiveData();
      _refreshAccess(); // a push carries no category, and re-reading /me is cheap and does nothing if nothing changed
    };
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final realtime = ref.read(realtimeProvider);
    if (state == AppLifecycleState.resumed) {
      // Anything that happened while the socket was closed is picked up by a refresh.
      if (ref.read(authProvider).status == AuthStatus.signedIn) {
        realtime.start();
        _refreshLiveData();
        ref.read(authProvider.notifier).refreshUser();
      }
    } else if (state == AppLifecycleState.paused) {
      // Android kills idle sockets anyway; push covers the background.
      realtime.stop();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _live?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Register this device for push whenever someone is signed in (a no-op without Firebase config).
    ref.listen(authProvider.select((s) => s.status), (_, status) {
      if (status == AuthStatus.signedIn) {
        ref.read(pushRegistrarProvider).start();
        ref.read(realtimeProvider).start();
      } else {
        ref.read(realtimeProvider).stop();
      }
    });
    if (ref.read(authProvider).status == AuthStatus.signedIn) {
      Future.microtask(() {
        ref.read(pushRegistrarProvider).start();
        ref.read(realtimeProvider).start();
      });
    }

    return MaterialApp.router(
      title: 'Adinn Tasks',
      debugShowCheckedModeBanner: false,
      theme: _theme,
      scrollBehavior: const SmoothScrollBehavior(),
      routerConfig: ref.watch(routerProvider),
      // Freezes the app behind an Update screen when the server says this build is too old.
      builder: (context, child) => UpdateGate(child: child ?? const SizedBox.shrink()),
    );
  }
}
