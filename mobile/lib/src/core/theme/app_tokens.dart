import 'package:flutter/material.dart';

/// Source unique des constantes visuelles PharmaCare.
///
/// Les composants et écrans seront raccordés progressivement à ces valeurs
/// pendant les phases de migration, sans modifier la logique métier.
///
/// Charte AM-160 (identique au Web, `resources/css/design-system.css`) :
/// - [primary] #F57C00 : marque. Indicateurs, soulignements, bordures de
///   focus et de sélection, texte actif sur le menu sombre. Jamais pour du
///   texte sur fond clair (2,7:1).
/// - [primaryStrong] #B85D00 : fond des boutons à texte blanc (4,56:1) et
///   tout texte orange sur fond clair. Ne jamais l'éclaircir.
/// - [primaryStrongPressed] : survol / appui, toujours plus foncé.
/// - Les états n'utilisent jamais l'orange et sont toujours libellés.
abstract final class AppColors {
  // Marque.
  static const primary = Color(0xFFF57C00);
  static const primaryStrong = Color(0xFFB85D00);
  static const primaryStrongPressed = Color(0xFF9C4F00);
  static const primarySoft = Color(0xFFFFF3E8);

  /// Alias historique : même valeur que [primaryStrong].
  static const primaryDark = primaryStrong;

  // Neutres.
  static const background = Color(0xFFF5F5F3);
  static const surface = Color(0xFFFFFFFF);
  static const surfaceSubtle = Color(0xFFFAFAF8);
  static const border = Color(0xFFE3E3E0);
  static const controlBorder = Color(0xFF8E9196);
  static const text = Color(0xFF1C1F23);
  static const textMuted = Color(0xFF5F6368);
  static const disabledSurface = Color(0xFFECEEEA);
  static const disabledText = Color(0xFF8E9196);
  static const backdrop = Color(0x661C1F23);

  // Menu latéral sombre.
  static const sidebar = Color(0xFF1E2329);
  static const sidebarText = Color(0xFFD5D8DC);
  static const sidebarActiveText = primary;

  // États (texte / fond).
  static const successText = Color(0xFF1E6B3A);
  static const successSurface = Color(0xFFE6F2E9);
  static const infoText = Color(0xFF1F4E79);
  static const infoSurface = Color(0xFFEEF3F8);
  static const dangerText = Color(0xFFA61B1B);
  static const dangerSurface = Color(0xFFFBE9E7);
  static const neutralText = Color(0xFF4A544F);
  static const neutralSurface = Color(0xFFECEEEA);

  // Alias historiques raccordés aux états (aucun orange).
  static const success = successText;
  static const danger = dangerText;
  static const info = infoText;
  static const warning = infoText;
  static const link = primaryStrong;
  static const purple = neutralText;
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
  static const md = 12.0;
  static const lg = 14.0;
  static const sheet = 20.0;
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
  static const pagePaddingDesktop = 32.0;
  static const pagePaddingTablet = 24.0;
  static const pagePaddingMobile = 16.0;
  static const formSheetMinWidth = 480.0;
  static const formSheetMaxWidth = 720.0;
  static const formSheetSmallWidth = 520.0;
  static const formSheetLargeWidth = 880.0;
}

abstract final class AppTypography {
  /// Police de la charte, embarquée (assets/fonts).
  static const family = 'Inter';

  static const pageTitle = TextStyle(
    fontFamily: family,
    fontSize: 26,
    fontWeight: FontWeight.w700,
    height: 1.2,
  );
  static const sectionTitle = TextStyle(
    fontFamily: family,
    fontSize: 21,
    fontWeight: FontWeight.w700,
    height: 1.25,
  );
  static const title = TextStyle(
    fontFamily: family,
    fontSize: 18,
    fontWeight: FontWeight.w600,
    height: 1.3,
  );
  static const body = TextStyle(fontFamily: family, fontSize: 14, height: 1.5);
  static const secondary = TextStyle(
    fontFamily: family,
    fontSize: 13,
    height: 1.4,
  );
  static const caption = TextStyle(
    fontFamily: family,
    fontSize: 12,
    height: 1.35,
  );
  static const button = TextStyle(
    fontFamily: family,
    fontSize: 14,
    fontWeight: FontWeight.w600,
  );

  /// 15 px : évite le zoom automatique des champs (iOS).
  static const input = TextStyle(fontFamily: family, fontSize: 15);
  static const label = TextStyle(
    fontFamily: family,
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
