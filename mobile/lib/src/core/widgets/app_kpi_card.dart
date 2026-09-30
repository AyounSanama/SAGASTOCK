import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

enum AppKpiTone { orange, green, red, blue }

class AppKpiCard extends StatelessWidget {
  const AppKpiCard({super.key, required this.label, required this.value, required this.icon, this.caption, this.onTap, this.tone = AppKpiTone.orange});

  final String label;
  final String value;
  final IconData icon;
  final String? caption;
  final VoidCallback? onTap;
  final AppKpiTone tone;

  Color get _color => switch (tone) {
    AppKpiTone.orange => AppColors.primaryStrong,
    AppKpiTone.green => AppColors.success,
    AppKpiTone.red => AppColors.danger,
    AppKpiTone.blue => AppColors.infoText,
  };

  @override
  Widget build(BuildContext context) {
    final color = _color;
    return Semantics(
      button: onTap != null,
      label: '$label : $value',
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        child: Ink(
          height: 128,
          padding: const EdgeInsets.all(AppSpacing.lg),
          decoration: BoxDecoration(
            color: AppColors.surface,
            border: Border.all(color: AppColors.border),
            borderRadius: BorderRadius.circular(AppRadius.lg),
            boxShadow: const [BoxShadow(color: Color(0x0D1C1F23), blurRadius: 18, offset: Offset(0, 6))],
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(color: color.withValues(alpha: .10), borderRadius: BorderRadius.circular(AppRadius.md)),
                child: Icon(icon, color: color, size: AppSizes.icon),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(label, maxLines: 2, overflow: TextOverflow.ellipsis, style: AppTypography.label.copyWith(color: AppColors.textMuted)),
                    const Spacer(),
                    Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTypography.pageTitle.copyWith(fontWeight: FontWeight.w800)),
                    if (caption?.isNotEmpty == true) Text(caption!, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTypography.caption.copyWith(color: AppColors.textMuted)),
                  ],
                ),
              ),
              if (onTap != null) const Icon(Icons.arrow_outward, size: 18, color: AppColors.textMuted),
            ],
          ),
        ),
      ),
    );
  }
}
