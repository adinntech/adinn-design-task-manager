import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/auth.dart';
import 'core/push.dart';
import 'core/theme.dart';
import 'features/tasks/providers.dart';
import 'router.dart';

void main() {
  runApp(const ProviderScope(child: AdinnApp()));
}

class AdinnApp extends ConsumerStatefulWidget {
  const AdinnApp({super.key});

  @override
  ConsumerState<AdinnApp> createState() => _AdinnAppState();
}

class _AdinnAppState extends ConsumerState<AdinnApp> {
  @override
  void initState() {
    super.initState();
    // Restore a saved session; the router shows a splash until this resolves.
    Future.microtask(() => ref.read(authProvider.notifier).restore());

    // Push: tapping a notification opens its task; a push in the foreground refreshes the badges/lists.
    final push = ref.read(pushRegistrarProvider);
    push.onOpened = (event) {
      final id = event.taskId;
      if (id != null) ref.read(routerProvider).push('/task/$id');
    };
    push.onForeground = () {
      ref.invalidate(notificationsProvider);
      ref.invalidate(boardProvider);
    };
  }

  @override
  Widget build(BuildContext context) {
    // Register this device for push whenever someone is signed in (a no-op without Firebase config).
    ref.listen(authProvider.select((s) => s.status), (_, status) {
      if (status == AuthStatus.signedIn) ref.read(pushRegistrarProvider).start();
    });
    if (ref.read(authProvider).status == AuthStatus.signedIn) {
      Future.microtask(() => ref.read(pushRegistrarProvider).start());
    }

    return MaterialApp.router(
      title: 'Adinn Tasks',
      debugShowCheckedModeBanner: false,
      theme: buildTheme(),
      routerConfig: ref.watch(routerProvider),
    );
  }
}
