import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'core/auth.dart';
import 'features/auth/forgot_password_screen.dart';
import 'features/auth/login_screen.dart';
import 'features/create/create_task_screen.dart';
import 'features/home/home_shell.dart';
import 'features/tasks/task_detail_screen.dart';

/// Redirects purely from auth state: unknown → splash, signed out → login, signed in → app.
final routerProvider = Provider<GoRouter>((ref) {
  final refresh = ValueNotifier<int>(0);
  ref.listen(authProvider.select((s) => s.status), (_, _) => refresh.value++);
  ref.onDispose(refresh.dispose);

  return GoRouter(
    refreshListenable: refresh,
    redirect: (context, state) {
      final status = ref.read(authProvider).status;
      final here = state.matchedLocation;

      if (status == AuthStatus.unknown) return here == '/splash' ? null : '/splash';
      if (status == AuthStatus.signedOut) return (here == '/login' || here == '/forgot-password') ? null : '/login';
      return (here == '/login' || here == '/forgot-password' || here == '/splash') ? '/' : null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, _) => const Scaffold(body: Center(child: CircularProgressIndicator()))),
      GoRoute(path: '/login', builder: (_, _) => const LoginScreen()),
      GoRoute(path: '/forgot-password', builder: (_, _) => const ForgotPasswordScreen()),
      GoRoute(path: '/', builder: (_, _) => const HomeShell()),
      GoRoute(path: '/create', builder: (_, _) => const CreateTaskScreen()),
      GoRoute(
        path: '/task/:id',
        builder: (_, state) => TaskDetailScreen(taskId: int.parse(state.pathParameters['id']!)),
      ),
    ],
  );
});
