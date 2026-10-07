import 'package:flutter/material.dart';

import 'app_tokens.dart';

abstract final class AppTheme {
  /// Texte et icônes orange sur fond clair (#B85D00, 4,56:1).
  static const orange = AppColors.primaryStrong;
  static Color get orangeSoft => AppColors.primarySoft;
  static Color get blue => AppColors.text;
  static Color get green => AppColors.success;
  static Color get red => AppColors.danger;
  static Color get purple => AppColors.purple;
  static Color get gray => AppColors.textMuted;

  /// Orange de marque : indicateurs, soulignements, bordures (jamais du texte sur fond clair).
  static const primary = AppColors.primary;
  static const primaryDark = AppColors.primaryDark;

  /// Fond des boutons à texte blanc et texte orange sur fond clair.
  static const strong = AppColors.primaryStrong;
  static const strongPressed = AppColors.primaryStrongPressed;
  static Color get danger => red;
  static Color get ink => AppColors.text;
  static Color get muted => AppColors.textMuted;
  static Color get border => AppColors.border;
  static Color get surface => AppColors.background;

  static final _shape = RoundedRectangleBorder(
    borderRadius: BorderRadius.circular(AppRadius.md),
  );

  /// Thème de la palette active (claire ou sombre, voir [AppColors.useDark]).
  static ThemeData get current => light;

  static ThemeData get light {
    // `primary` du schéma Material = teinte forte : Material l'utilise pour
    // du texte et des fonds sous texte blanc. L'orange de marque est posé
    // explicitement sur les indicateurs, soulignements et bordures de focus.
    final scheme = ColorScheme.fromSeed(
      seedColor: primary,
      brightness: AppColors.isDark ? Brightness.dark : Brightness.light,
      primary: strong,
      onPrimary: Colors.white,
      secondary: strong,
      // Pastilles sélectionnées (Material 3) : fond de sélection, texte orange lisible.
      secondaryContainer: orangeSoft,
      onSecondaryContainer: AppColors.primarySoftText,
      surface: AppColors.surface,
      onSurface: ink,
      onSurfaceVariant: muted,
      outline: AppColors.controlBorder,
      outlineVariant: border,
      error: danger,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: surface,
      fontFamily: AppTypography.family,
      textTheme: const TextTheme(
        headlineSmall: AppTypography.pageTitle,
        titleLarge: AppTypography.sectionTitle,
        titleMedium: AppTypography.title,
        bodyMedium: AppTypography.body,
        bodySmall: AppTypography.secondary,
        labelSmall: AppTypography.caption,
        labelLarge: TextStyle(
          fontFamily: AppTypography.family,
          fontSize: 15,
          fontWeight: FontWeight.w700,
          letterSpacing: .1,
        ),
      ),
      appBarTheme: AppBarTheme(
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 1,
        backgroundColor: AppColors.surface,
        foregroundColor: ink,
        surfaceTintColor: Colors.transparent,
        titleTextStyle: TextStyle(
          fontFamily: AppTypography.family,
          color: ink,
          fontSize: 19,
          fontWeight: FontWeight.w700,
        ),
        iconTheme: IconThemeData(color: ink),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        margin: EdgeInsets.zero,
        color: AppColors.surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
          side: BorderSide(color: border),
        ),
      ),
      dataTableTheme: DataTableThemeData(
        headingRowColor: WidgetStatePropertyAll(AppColors.surfaceSubtle),
        dataRowColor: WidgetStateProperty.resolveWith(
          (states) =>
              states.contains(WidgetState.selected) ? orangeSoft : AppColors.surface,
        ),
        headingTextStyle: TextStyle(
          fontFamily: AppTypography.family,
          color: ink,
          fontSize: 12,
          fontWeight: FontWeight.w800,
        ),
        dataTextStyle: TextStyle(
          fontFamily: AppTypography.family,
          color: ink,
          fontSize: 13,
          fontWeight: FontWeight.w500,
        ),
        dividerThickness: 1,
        headingRowHeight: 48,
        dataRowMinHeight: 48,
        dataRowMaxHeight: 60,
        horizontalMargin: 16,
        columnSpacing: 22,
        decoration: BoxDecoration(
          color: AppColors.surface,
          border: Border.all(color: border),
          borderRadius: BorderRadius.circular(14),
        ),
      ),
      tabBarTheme: TabBarThemeData(
        labelColor: strong,
        unselectedLabelColor: muted,
        indicatorColor: primary,
        indicatorSize: TabBarIndicatorSize.label,
        dividerColor: border,
        labelStyle: const TextStyle(
          fontFamily: AppTypography.family,
          fontSize: 13,
          fontWeight: FontWeight.w800,
        ),
        unselectedLabelStyle: const TextStyle(
          fontFamily: AppTypography.family,
          fontSize: 13,
          fontWeight: FontWeight.w600,
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 72,
        backgroundColor: AppColors.surface,
        indicatorColor: orangeSoft,
        elevation: 0,
        iconTheme: WidgetStateProperty.resolveWith(
          (states) => IconThemeData(
            size: 24,
            color: states.contains(WidgetState.selected) ? strong : muted,
          ),
        ),
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => TextStyle(
            fontFamily: AppTypography.family,
            fontSize: 11,
            fontWeight: states.contains(WidgetState.selected)
                ? FontWeight.w800
                : FontWeight.w600,
            color: states.contains(WidgetState.selected) ? strong : muted,
          ),
        ),
      ),
      navigationDrawerTheme: NavigationDrawerThemeData(
        backgroundColor: AppColors.surface,
        surfaceTintColor: Colors.transparent,
        indicatorColor: orangeSoft,
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: AppColors.surface,
        surfaceTintColor: Colors.transparent,
        modalBackgroundColor: AppColors.surface,
        modalBarrierColor: Color(0x991C1F23),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: ink,
        contentTextStyle: const TextStyle(
          fontFamily: AppTypography.family,
          color: Colors.white,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      // Pastille sélectionnée : fond clair, bordure marque, texte fort.
      chipTheme: ChipThemeData(
        backgroundColor: AppColors.surface,
        selectedColor: orangeSoft,
        checkmarkColor: strong,
        side: WidgetStateBorderSide.resolveWith(
          (states) => BorderSide(
            color: states.contains(WidgetState.selected) ? primary : border,
          ),
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
        // RawChip ne résout que `labelStyle.color` selon l'état : couleur
        // d'état explicite (texte courant / teinte forte si sélectionnée).
        labelStyle: TextStyle(
          fontFamily: AppTypography.family,
          fontWeight: FontWeight.w600,
          fontSize: 12,
          color: WidgetStateColor.resolveWith(
            (states) => states.contains(WidgetState.selected) ? strong : ink,
          ),
        ),
      ),
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? Colors.white
              : AppColors.controlBorder,
        ),
        trackColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? strong
              : AppColors.disabledSurface,
        ),
        trackOutlineColor: const WidgetStatePropertyAll(Colors.transparent),
      ),
      checkboxTheme: CheckboxThemeData(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
        fillColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? strong
              : Colors.transparent,
        ),
        side: BorderSide(color: AppColors.controlBorder, width: 1.5),
      ),
      popupMenuTheme: PopupMenuThemeData(
        color: AppColors.surface,
        surfaceTintColor: Colors.transparent,
        elevation: 6,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: BorderSide(color: border),
        ),
        textStyle: TextStyle(
          fontFamily: AppTypography.family,
          color: ink,
          fontSize: 13,
          fontWeight: FontWeight.w600,
        ),
      ),
      tooltipTheme: TooltipThemeData(
        decoration: BoxDecoration(
          color: ink,
          borderRadius: BorderRadius.circular(8),
        ),
        textStyle: const TextStyle(
          fontFamily: AppTypography.family,
          color: Colors.white,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
      searchBarTheme: SearchBarThemeData(
        backgroundColor: WidgetStatePropertyAll(AppColors.surface),
        surfaceTintColor: const WidgetStatePropertyAll(Colors.transparent),
        elevation: const WidgetStatePropertyAll(0),
        side: WidgetStatePropertyAll(BorderSide(color: border)),
        shape: WidgetStatePropertyAll(
          RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
        hintStyle: WidgetStatePropertyAll(
          TextStyle(fontFamily: AppTypography.family, color: muted),
        ),
      ),
      dividerTheme: DividerThemeData(color: border, thickness: 1),
      listTileTheme: ListTileThemeData(
        iconColor: ink,
        textColor: ink,
        contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 3),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: ButtonStyle(
          minimumSize: const WidgetStatePropertyAll(Size(88, 50)),
          padding: const WidgetStatePropertyAll(
            EdgeInsets.symmetric(horizontal: 20, vertical: 13),
          ),
          shape: WidgetStatePropertyAll(_shape),
          elevation: const WidgetStatePropertyAll(0),
          textStyle: const WidgetStatePropertyAll(
            TextStyle(
              fontFamily: AppTypography.family,
              fontSize: 15,
              fontWeight: FontWeight.w700,
            ),
          ),
          backgroundColor: WidgetStateProperty.resolveWith(
            (states) => states.contains(WidgetState.disabled)
                ? AppColors.disabledSurface
                : states.contains(WidgetState.pressed) ||
                      states.contains(WidgetState.hovered)
                ? strongPressed
                : strong,
          ),
          foregroundColor: WidgetStateProperty.resolveWith(
            (states) => states.contains(WidgetState.disabled)
                ? AppColors.disabledText
                : Colors.white,
          ),
          // Jamais d'éclaircissement : l'appui fonce le fond (voir ci-dessus).
          overlayColor: const WidgetStatePropertyAll(Colors.transparent),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: ButtonStyle(
          minimumSize: const WidgetStatePropertyAll(Size(88, 50)),
          padding: const WidgetStatePropertyAll(
            EdgeInsets.symmetric(horizontal: 19, vertical: 12),
          ),
          shape: WidgetStatePropertyAll(_shape),
          side: WidgetStateProperty.resolveWith(
            (states) => BorderSide(
              color: states.contains(WidgetState.disabled)
                  ? AppColors.disabledSurface
                  : AppColors.controlBorder,
            ),
          ),
          foregroundColor: WidgetStateProperty.resolveWith(
            (states) => states.contains(WidgetState.disabled)
                ? AppColors.disabledText
                : ink,
          ),
          textStyle: const WidgetStatePropertyAll(
            TextStyle(
              fontFamily: AppTypography.family,
              fontSize: 15,
              fontWeight: FontWeight.w700,
            ),
          ),
          overlayColor: WidgetStatePropertyAll(AppColors.primarySoft),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: ButtonStyle(
          minimumSize: const WidgetStatePropertyAll(Size(48, 44)),
          padding: const WidgetStatePropertyAll(
            EdgeInsets.symmetric(horizontal: 13, vertical: 10),
          ),
          shape: WidgetStatePropertyAll(_shape),
          foregroundColor: WidgetStateProperty.resolveWith(
            (states) => states.contains(WidgetState.disabled)
                ? AppColors.disabledText
                : strong,
          ),
          textStyle: const WidgetStatePropertyAll(
            TextStyle(
              fontFamily: AppTypography.family,
              fontSize: 14,
              fontWeight: FontWeight.w700,
            ),
          ),
          overlayColor: WidgetStatePropertyAll(AppColors.primarySoft),
        ),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: ButtonStyle(
          minimumSize: const WidgetStatePropertyAll(Size.square(44)),
          iconSize: const WidgetStatePropertyAll(22),
          shape: WidgetStatePropertyAll(_shape),
          foregroundColor: WidgetStatePropertyAll(ink),
          backgroundColor: WidgetStatePropertyAll(AppColors.background),
          overlayColor: WidgetStatePropertyAll(AppColors.primarySoft),
        ),
      ),
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        elevation: 2,
        focusElevation: 3,
        hoverElevation: 3,
        highlightElevation: 1,
        backgroundColor: strong,
        foregroundColor: Colors.white,
        shape: const CircleBorder(),
        extendedTextStyle: const TextStyle(
          fontFamily: AppTypography.family,
          fontSize: 14,
          fontWeight: FontWeight.w700,
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surface,
        hintStyle: TextStyle(
          fontFamily: AppTypography.family,
          color: muted,
          fontSize: 15,
        ),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 15,
          vertical: 15,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: primary, width: 1.6),
        ),
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(
        color: primary,
        linearTrackColor: AppColors.primarySoft,
      ),
    );
  }
}
