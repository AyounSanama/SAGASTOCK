import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

class AppDataTable extends StatelessWidget {
  const AppDataTable({super.key, required this.columns, required this.rows, this.empty});

  final List<DataColumn> columns;
  final List<DataRow> rows;
  final Widget? empty;

  @override
  Widget build(BuildContext context) {
    if (rows.isEmpty && empty != null) return empty!;
    return Container(
      decoration: BoxDecoration(
        color: AppColors.surface,
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      clipBehavior: Clip.antiAlias,
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: DataTable(
          headingRowHeight: 44,
          dataRowMinHeight: 48,
          dataRowMaxHeight: 56,
          headingTextStyle: AppTypography.caption.copyWith(fontWeight: FontWeight.w700, color: AppColors.textMuted),
          dataTextStyle: AppTypography.body,
          dividerThickness: 1,
          columns: columns,
          rows: rows,
        ),
      ),
    );
  }
}
