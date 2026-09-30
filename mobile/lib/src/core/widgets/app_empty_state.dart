import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

class AppEmptyState extends StatelessWidget {
  const AppEmptyState({super.key, required this.title, this.description, this.icon = Icons.inbox_outlined, this.action});

  final String title;
  final String? description;
  final IconData icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(AppSpacing.xxl),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 36, color: AppColors.primaryStrong),
        const SizedBox(height: AppSpacing.md),
        Text(title, style: AppTypography.title, textAlign: TextAlign.center),
        if (description != null) ...[
          const SizedBox(height: AppSpacing.xs),
          Text(description!, style: AppTypography.secondary.copyWith(color: AppColors.textMuted), textAlign: TextAlign.center),
        ],
        if (action != null) ...[const SizedBox(height: AppSpacing.lg), action!],
      ],
    ),
  );
}
