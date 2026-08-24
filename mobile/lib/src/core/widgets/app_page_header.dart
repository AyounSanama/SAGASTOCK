import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

class AppPageHeader extends StatelessWidget {
  const AppPageHeader({super.key, required this.title, this.description, this.action});

  final String title;
  final String? description;
  final Widget? action;

  @override
  Widget build(BuildContext context) => LayoutBuilder(builder: (context, constraints) {
    final compact = constraints.maxWidth < AppBreakpoints.mobile;
    final copy = Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(title, style: AppTypography.pageTitle.copyWith(color: AppColors.text)),
      if (description != null) ...[
        const SizedBox(height: AppSpacing.sm),
        Text(description!, style: AppTypography.body.copyWith(color: AppColors.textMuted)),
      ],
    ]);
    if (action == null) return copy;
    if (compact) return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [copy, const SizedBox(height: AppSpacing.lg), action!]);
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Expanded(child: copy), const SizedBox(width: AppSpacing.xl), action!]);
  });
}
