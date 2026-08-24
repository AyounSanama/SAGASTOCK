import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

class AppCard extends StatelessWidget {
  const AppCard({super.key, required this.child, this.title, this.description, this.actions, this.padding = const EdgeInsets.all(AppSpacing.lg)});
  final Widget child;
  final String? title;
  final String? description;
  final Widget? actions;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: padding,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (title != null || description != null || actions != null) ...[
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [if (title != null) Text(title!, style: AppTypography.title), if (description != null) ...[const SizedBox(height: AppSpacing.xs), Text(description!, style: AppTypography.secondary)]])), ?actions]),
          const SizedBox(height: AppSpacing.lg),
        ],
        child,
      ]),
    ),
  );
}
