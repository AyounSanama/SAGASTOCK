import 'package:flutter/material.dart';

abstract final class AppTheme {
  static const _primary = Color(0xFFF47A20);
  static const _secondary = Color(0xFF8A3D00);

  static ThemeData get light => ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.fromSeed(
      seedColor: _primary,
      primary: _primary,
      secondary: _secondary,
      surface: const Color(0xFFF6F8F7),
    ),
    scaffoldBackgroundColor: const Color(0xFFF6F8F7),
    appBarTheme: const AppBarTheme(
      centerTitle: false,
      backgroundColor: _primary,
      foregroundColor: Colors.white,
    ),
    cardTheme: const CardThemeData(elevation: 0, margin: EdgeInsets.zero),
  );
}
