import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/auth.dart';
import 'core/theme.dart';
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
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'Adinn Tasks',
      debugShowCheckedModeBanner: false,
      theme: buildTheme(),
      routerConfig: ref.watch(routerProvider),
    );
  }
}
