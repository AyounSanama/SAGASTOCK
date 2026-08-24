import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';
import 'app_button.dart';

class AppPagination extends StatelessWidget {
  const AppPagination({super.key, required this.currentPage, required this.totalPages, required this.onPageChanged});

  final int currentPage;
  final int totalPages;
  final ValueChanged<int> onPageChanged;

  @override
  Widget build(BuildContext context) => Semantics(
    label: 'Pagination, page $currentPage sur $totalPages',
    child: Row(
      mainAxisAlignment: MainAxisAlignment.end,
      children: [
        AppIconButton(icon: Icons.chevron_left, tooltip: 'Page précédente', onPressed: currentPage > 1 ? () => onPageChanged(currentPage - 1) : null),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
          child: Text('Page $currentPage sur $totalPages', style: AppTypography.secondary.copyWith(color: AppColors.textMuted)),
        ),
        AppIconButton(icon: Icons.chevron_right, tooltip: 'Page suivante', onPressed: currentPage < totalPages ? () => onPageChanged(currentPage + 1) : null),
      ],
    ),
  );
}
