import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

class AppFilterBar extends StatelessWidget {
  const AppFilterBar({super.key, required this.children, this.actions = const []});

  final List<Widget> children;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final compact = constraints.maxWidth < AppBreakpoints.tablet;
      final fields = compact
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: _separated(children),
            )
          : Wrap(
              spacing: AppSpacing.md,
              runSpacing: AppSpacing.md,
              crossAxisAlignment: WrapCrossAlignment.end,
              children: children,
            );
      return Container(
        padding: const EdgeInsets.all(AppSpacing.lg),
        decoration: BoxDecoration(
          color: AppColors.surface,
          border: Border.all(color: AppColors.border),
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
        child: compact
            ? Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [fields, if (actions.isNotEmpty) ...[const SizedBox(height: AppSpacing.md), Wrap(spacing: AppSpacing.sm, runSpacing: AppSpacing.sm, children: actions)]],
              )
            : Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [Expanded(child: fields), if (actions.isNotEmpty) ...[const SizedBox(width: AppSpacing.md), Wrap(spacing: AppSpacing.sm, children: actions)]],
              ),
      );
    },
  );

  static List<Widget> _separated(List<Widget> values) => [
    for (var index = 0; index < values.length; index++) ...[
      if (index > 0) const SizedBox(height: AppSpacing.md),
      values[index],
    ],
  ];
}
