import 'dart:async';

import 'package:adinn_tasks/features/kanban/kanban_screen.dart';

/// Runs before every test file: the endlessly flowing swipe-up hint would stop pumpAndSettle from settling.
Future<void> testExecutable(FutureOr<void> Function() testMain) async {
  swipeHintAnimates = false;
  await testMain();
}
