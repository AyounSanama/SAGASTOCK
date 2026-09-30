import 'package:flutter/material.dart';

import 'app_tokens.dart';

abstract final class AppTheme {
  /// Texte et icônes orange sur fond clair (#B85D00, 4,56:1).
  static const orange = AppColors.primaryStrong;
  static const orangeSoft = AppColors.primarySoft;
  static const blue = AppColors.text;
  static const green = AppColors.success;
  static const red = AppColors.danger;
  static const purple = AppColors.purple;
  static const gray = AppColors.textMuted;

  /// Orange de marque : indicateurs, soulignements, bordures (jamais du texte sur fond clair).
  static const primary = AppColors.primary;
  static const primaryDark = AppColors.primaryDark;

  /// Fond des boutons à texte blanc et texte orange sur fond clair.
  static const strong = AppColors.primaryStrong;
  static const strongPressed = AppColors.primaryStrongPressed;
  static const danger = red;
  static const ink = AppColors.text;
  static const muted = AppColors.textMuted;
  static const border = AppColors.border;
  static const surface = AppColors.background;

  static final _shape = RoundedRectangleBorder(
    borderRadius: BorderRadius.circular(AppRadius.md),
  );

  static ThemeData get light {
    // `primary` du schéma Material = teinte forte : Material l'utilise pour
    // du texte et des fonds sous texte blanc. L'orange de marque est posé
    // explicitement sur les indicateurs, soulignements et bordures de focus.
    final scheme = ColorScheme.fromSeed(
      seedColor: primary,
      primary: strong,
      onPrimary: Colors.white,
      secondary: strong,
      // Pastilles sélectionnées (Material 3) : fond clair, texte fort.
      secondaryContainer: orangeSoft,
      onSecondaryContainer: strong,
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
      appBarTheme: const AppBarTheme(
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 1,
        backgroundColor: Colors.white,
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
        color: Colors.white,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
          side: const BorderSide(color: border),
        ),
      ),
      dataTableTheme: DataTableThemeData(
        headingRowColor: const WidgetStatePropertyAll(AppColors.surfaceSubtle),
        dataRowColor: WidgetStateProperty.resolveWith(
          (states) =>
              states.contains(WidgetState.selected) ? orangeSoft : Colors.white,
        ),
        headingTextStyle: const TextStyle(
          fontFamily: AppTypography.family,
          color: ink,
          fontSize: 12,
          fontWeight: FontWeight.w800,
        ),
        dataTextStyle: const TextStyle(
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
          color: Colors.white,
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
        backgroundColor: Colors.white,
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
      navigationDrawerTheme: const NavigationDrawerThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        indicatorColor: orangeSoft,
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        modalBackgroundColor: Colors.white,
        modalBarrierColor: Color(0x991C1F23),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: Colors.white,
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
        side: const BorderSide(color: AppColors.controlBorder, width: 1.5),
      ),
      popupMenuTheme: PopupMenuThemeData(
        color: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 6,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: const BorderSide(color: border),
        ),
        textStyle: const TextStyle(
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
        backgroundColor: const WidgetStatePropertyAll(Colors.white),
        surfaceTintColor: const WidgetStatePropertyAll(Colors.transparent),
        elevation: const WidgetStatePropertyAll(0),
        side: const WidgetStatePropertyAll(BorderSide(color: border)),
        shape: WidgetStatePropertyAll(
          RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
        hintStyle: const WidgetStatePropertyAll(
          TextStyle(fontFamily: AppTypography.family, color: muted),
        ),
      ),
      dividerTheme: const DividerThemeData(color: border, thickness: 1),
      listTileTheme: const ListTileThemeData(
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
          overlayColor: const WidgetStatePropertyAll(AppColors.primarySoft),
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
          overlayColor: const WidgetStatePropertyAll(AppColors.primarySoft),
        ),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: ButtonStyle(
          minimumSize: const WidgetStatePropertyAll(Size.square(44)),
          iconSize: const WidgetStatePropertyAll(22),
          shape: WidgetStatePropertyAll(_shape),
          foregroundColor: const WidgetStatePropertyAll(ink),
          backgroundColor: const WidgetStatePropertyAll(AppColors.background),
          overlayColor: const WidgetStatePropertyAll(AppColors.primarySoft),
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
        fillColor: Colors.white,
        hintStyle: const TextStyle(
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
          borderSide: const BorderSide(color: border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: primary, width: 1.6),
        ),
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: primary,
        linearTrackColor: AppColors.primarySoft,
      ),
    );
  }
}
