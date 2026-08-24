import 'package:flutter/material.dart';

/// Source unique des constantes visuelles PharmaCare.
///
/// Les composants et écrans seront raccordés progressivement à ces valeurs
/// pendant les phases de migration, sans modifier la logique métier.
abstract final class AppColors {
  static const primary = Color(0xFFF57C00);
  static const primaryDark = Color(0xFFC86500);
  static const primarySoft = Color(0xFFFFF3E8);
  static const success = Color(0xFF22A447);
  static const danger = Color(0xFFE53935);
  static const text = Color(0xFF24324A);
  static const textMuted = Color(0xFF6B778C);
  static const border = Color(0xFFE8EDF3);
  static const background = Color(0xFFF7F9FC);
  static const surface = Color(0xFFFFFFFF);
  static const link = Color(0xFF2563EB);
  static const purple = Color(0xFF6B778C);
}

abstract final class AppSpacing {
  static const xs = 4.0;
  static const sm = 8.0;
  static const md = 12.0;
  static const lg = 16.0;
  static const xl = 24.0;
  static const xxl = 32.0;
  static const xxxl = 48.0;
}

abstract final class AppRadius {
  static const sm = 8.0;
  static const md = 10.0;
  static const lg = 14.0;
  static const pill = 999.0;
}

abstract final class AppSizes {
  static const buttonHeight = 42.0;
  static const inputHeightDesktop = 46.0;
  static const inputHeightMobile = 50.0;
  static const icon = 20.0;
  static const iconButton = 40.0;
  static const sidebarWidth = 260.0;
  static const sidebarCollapsedWidth = 78.0;
  static const topBarHeight = 64.0;
  static const pagePaddingDesktop = 28.0;
  static const pagePaddingTablet = 22.0;
  static const pagePaddingMobile = 16.0;
  static const formSheetMinWidth = 480.0;
  static const formSheetMaxWidth = 620.0;
}

abstract final class AppTypography {
  static const pageTitle = TextStyle(
    fontSize: 26,
    fontWeight: FontWeight.w700,
    height: 1.2,
  );
  static const sectionTitle = TextStyle(
    fontSize: 21,
    fontWeight: FontWeight.w700,
    height: 1.25,
  );
  static const title = TextStyle(
    fontSize: 18,
    fontWeight: FontWeight.w600,
    height: 1.3,
  );
  static const body = TextStyle(fontSize: 14, height: 1.5);
  static const secondary = TextStyle(fontSize: 13, height: 1.4);
  static const caption = TextStyle(fontSize: 12, height: 1.35);
  static const button = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w600,
  );
  static const input = TextStyle(fontSize: 14);
  static const label = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w600,
  );
}

abstract final class AppDurations {
  static const fast = Duration(milliseconds: 150);
  static const standard = Duration(milliseconds: 220);
}

abstract final class AppBreakpoints {
  static const mobile = 600.0;
  static const tablet = 900.0;
  static const desktop = 1200.0;
}
