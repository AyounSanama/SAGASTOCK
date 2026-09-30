import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

enum AppBadgeVariant { neutral, success, warning, danger, info }

class AppBadge extends StatelessWidget {
  const AppBadge({
    super.key,
    required this.label,
    this.variant = AppBadgeVariant.neutral,
    this.icon,
  });
  final String label;
  final AppBadgeVariant variant;
  final IconData? icon;

  (Color, Color) get _colors => switch (variant) {
    AppBadgeVariant.success => (AppColors.successSurface, AppColors.successText),
    // AM-160 : un état n'est jamais orange ; « avertissement » = information.
    AppBadgeVariant.warning => (AppColors.infoSurface, AppColors.infoText),
    AppBadgeVariant.danger => (AppColors.dangerSurface, AppColors.dangerText),
    AppBadgeVariant.info => (AppColors.infoSurface, AppColors.infoText),
    AppBadgeVariant.neutral => (AppColors.neutralSurface, AppColors.neutralText),
  };

  @override
  Widget build(BuildContext context) {
    final (background, foreground) = _colors;
    return Semantics(
      label: label,
      child: Container(
        constraints: const BoxConstraints(minHeight: 24),
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm,
          vertical: AppSpacing.xs,
        ),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(AppRadius.pill),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (icon != null) ...[
              Icon(icon, size: 15, color: foreground),
              const SizedBox(width: AppSpacing.sm),
            ],
            Flexible(
              child: Text(
                label,
                overflow: TextOverflow.ellipsis,
                style: AppTypography.caption.copyWith(
                  color: foreground,
                  fontWeight: FontWeight.w600,
                  height: 1.15,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
