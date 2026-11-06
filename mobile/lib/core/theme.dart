import 'package:flutter/material.dart';

const adinnRed = Color(0xFFE30613);

ThemeData buildTheme() {
  final scheme = ColorScheme.fromSeed(seedColor: adinnRed, primary: adinnRed);
  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    scaffoldBackgroundColor: const Color(0xFFF6F6F8),
    appBarTheme: const AppBarTheme(centerTitle: false, backgroundColor: Colors.white, foregroundColor: Colors.black87, elevation: 0, scrolledUnderElevation: 1),
    cardTheme: CardThemeData(
      color: Colors.white,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14), side: BorderSide(color: Colors.black.withValues(alpha: 0.06))),
    ),
    inputDecorationTheme: InputDecorationTheme(
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      filled: true,
      fillColor: Colors.white,
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
    ),
  );
}

Color priorityColor(String priority) => switch (priority) {
      'urgent' => const Color(0xFFB00020),
      'high' => const Color(0xFFE65100),
      'medium' => const Color(0xFF1565C0),
      _ => const Color(0xFF546E7A),
    };

Color statusColor(String status) => switch (status) {
      'completed' => const Color(0xFF2E7D32),
      'rework' => const Color(0xFFE65100),
      'waiting_confirmation' => const Color(0xFF6A1B9A),
      'in_progress' => const Color(0xFF1565C0),
      'bd_rejected' => const Color(0xFFB00020),
      _ => const Color(0xFF546E7A),
    };
