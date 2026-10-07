import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

class AppBreadcrumbItem {
  const AppBreadcrumbItem(this.label, {this.onTap});
  final String label;
  final VoidCallback? onTap;
}

class AppBreadcrumb extends StatelessWidget {
  const AppBreadcrumb({super.key, required this.items});
  final List<AppBreadcrumbItem> items;

  @override
  Widget build(BuildContext context) => Semantics(label: 'Fil d’Ariane', child: Wrap(crossAxisAlignment: WrapCrossAlignment.center, spacing: AppSpacing.xs, children: [for (var index = 0; index < items.length; index++) ...[if (index > 0) Icon(Icons.chevron_right, size: 16, color: AppColors.disabledText), if (items[index].onTap != null && index < items.length - 1) TextButton(onPressed: items[index].onTap, child: Text(items[index].label)) else Text(items[index].label, style: AppTypography.caption.copyWith(color: index == items.length - 1 ? AppColors.text : AppColors.textMuted, fontWeight: index == items.length - 1 ? FontWeight.w600 : FontWeight.w400))]]));
}
