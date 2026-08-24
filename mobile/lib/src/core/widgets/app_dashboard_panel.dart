import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

class AppDashboardPanel extends StatelessWidget {
  const AppDashboardPanel({super.key, required this.title, required this.child, this.description, this.icon = Icons.analytics_outlined, this.color = AppColors.primary, this.action});

  final String title;
  final String? description;
  final IconData icon;
  final Color color;
  final Widget? action;
  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      color: AppColors.surface,
      border: Border.all(color: AppColors.border),
      borderRadius: BorderRadius.circular(AppRadius.lg),
      boxShadow: const [BoxShadow(color: Color(0x0D24324A), blurRadius: 18, offset: Offset(0, 6))],
    ),
    clipBehavior: Clip.antiAlias,
    child: Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(color: color.withValues(alpha: .10), borderRadius: BorderRadius.circular(AppRadius.md)),
                child: Icon(icon, color: color, size: AppSizes.icon),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: AppTypography.title),
                    if (description != null) Text(description!, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTypography.caption.copyWith(color: AppColors.textMuted)),
                  ],
                ),
              ),
              ?action,
            ],
          ),
        ),
        const Divider(height: 1),
        Padding(padding: const EdgeInsets.all(AppSpacing.lg), child: child),
      ],
    ),
  );
}
