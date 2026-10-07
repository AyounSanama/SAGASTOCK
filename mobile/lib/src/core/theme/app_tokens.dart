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
///
/// Niveau 4 (validé le 06/10) : les couleurs qui changent en mode sombre sont
/// des accesseurs ; l'application se reconstruit après [AppColors.useDark].
/// L'orange de marque et le bouton orange restent identiques dans les deux modes.
abstract final class AppColors {
  static bool _dark = false;

  /// Vrai quand la palette sombre est active.
  static bool get isDark => _dark;

  /// Active la palette sombre ou claire (appelé par l'application avant de se reconstruire).
  static void useDark(bool dark) => _dark = dark;

  static Color _pick(int light, int dark) => Color(_dark ? dark : light);

  // Marque (identique dans les deux modes).
  static const primary = Color(0xFFF57C00);
  static const primaryStrong = Color(0xFFB85D00);
  static const primaryStrongPressed = Color(0xFF9C4F00);
  static Color get primarySoft => _pick(0xFFFFF3E8, 0xFF3A2A1B);

  /// Texte orange sur fond de sélection (5,45:1 en clair, 8,1:1 en sombre).
  static Color get primarySoftText => _pick(0xFF9C4F00, 0xFFFFB877);

  /// Alias historique : même valeur que [primaryStrong].
  static const primaryDark = primaryStrong;

  // Neutres.
  static Color get background => _pick(0xFFF5F5F3, 0xFF121417);
  static Color get surface => _pick(0xFFFFFFFF, 0xFF1B1F24);
  static Color get surfaceSubtle => _pick(0xFFFAFAF8, 0xFF22272D);
  static Color get border => _pick(0xFFE3E3E0, 0xFF353B43);
  static Color get controlBorder => _pick(0xFF8E9196, 0xFF7D848D);
  static Color get text => _pick(0xFF1C1F23, 0xFFECEEF0);
  static Color get textMuted => _pick(0xFF5F6368, 0xFFA9AFB6);
  static Color get disabledSurface => _pick(0xFFECEEEA, 0xFF2A2F33);
  static Color get disabledText => _pick(0xFF8E9196, 0xFF7D848D);
  static const backdrop = Color(0x661C1F23);

  // Menu latéral sombre.
  static Color get sidebar => _pick(0xFF1E2329, 0xFF0D0F12);
  static const sidebarText = Color(0xFFD5D8DC);
  static const sidebarActiveText = primary;

  // États (texte / fond).
  static Color get successText => _pick(0xFF1E6B3A, 0xFF8FD3A5);
  static Color get successSurface => _pick(0xFFE6F2E9, 0xFF173323);
  static Color get infoText => _pick(0xFF1F4E79, 0xFFA9C8EE);
  static Color get infoSurface => _pick(0xFFEEF3F8, 0xFF18293B);
  static Color get dangerText => _pick(0xFFA61B1B, 0xFFFFADA6);
  static Color get dangerSurface => _pick(0xFFFBE9E7, 0xFF3A2023);
  static Color get neutralText => _pick(0xFF4A544F, 0xFFC3C9C5);
  static Color get neutralSurface => _pick(0xFFECEEEA, 0xFF2A2F33);

  /// Violet de péremption (charte).
  static Color get expiryText => _pick(0xFF5B2C83, 0xFFD2B6F0);
  static Color get expirySurface => _pick(0xFFF1EAF7, 0xFF2E2240);

  // Alias historiques raccordés aux états (aucun orange).
  static Color get success => successText;
  static Color get danger => dangerText;
  static Color get info => infoText;
  static Color get warning => infoText;
  static Color get link => _pick(0xFFB85D00, 0xFFFFA24D);
  static Color get purple => expiryText;
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
