import 'package:flutter/material.dart';

import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_badge.dart';

/// Niveau 5 — Éléments communs aux écrans mobiles de l'Admin Projet.

/// Ton d'un statut (« success », « danger »…) → variante de badge.
AppBadgeVariant badgeVariant(Object? tone) => switch ('$tone') {
  'success' || 'ok' => AppBadgeVariant.success,
  'danger' || 'failed' || 'suspended' => AppBadgeVariant.danger,
  'info' || 'late' => AppBadgeVariant.info,
  _ => AppBadgeVariant.neutral,
};

Map<String, dynamic> asMap(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

List<Map<String, dynamic>> asMaps(Object? value) => value is List
    ? [for (final item in value) if (item is Map) Map<String, dynamic>.from(item)]
    : const [];

/// « 0,5 mois », « — ».
String monthsLabel(Object? value) {
  final number = num.tryParse('${value ?? ''}');
  if (number == null) return '—';
  final text = number == number.roundToDouble() ? '${number.round()}' : '$number'.replaceAll('.', ',');
  return '$text mois';
}

/// « il y a 5 min », « il y a 3 j », « Jamais ».
String agoLabel(Object? value) {
  final date = DateTime.tryParse('${value ?? ''}');
  if (date == null) return 'Jamais';
  final elapsed = DateTime.now().difference(date);
  if (elapsed.inMinutes < 1) return 'à l’instant';
  if (elapsed.inHours < 1) return 'il y a ${elapsed.inMinutes} min';
  if (elapsed.inDays < 1) return 'il y a ${elapsed.inHours} h';
  return 'il y a ${elapsed.inDays} j';
}

/// Bandeau sombre en haut des écrans (charte : menu #1E2329).
class ProjectAdminHeader extends StatelessWidget {
  const ProjectAdminHeader({
    required this.title,
    this.overline,
    this.subtitle,
    this.trailing,
    this.bottom,
    super.key,
  });

  final String title;
  final String? overline;
  final String? subtitle;
  final Widget? trailing;
  final Widget? bottom;

  @override
  Widget build(BuildContext context) => Material(
    color: AppColors.sidebar,
    child: SafeArea(
      bottom: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (overline != null)
                        Text(overline!, style: const TextStyle(color: AppColors.sidebarText, fontSize: 13)),
                      const SizedBox(height: 2),
                      Text(
                        title,
                        style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w700),
                      ),
                      if (subtitle != null) ...[
                        const SizedBox(height: 4),
                        Text(subtitle!, style: const TextStyle(color: Color(0xFF8FD3A5), fontSize: 13)),
                      ],
                    ],
                  ),
                ),
                ?trailing,
              ],
            ),
            if (bottom != null) ...[const SizedBox(height: 12), bottom!],
          ],
        ),
      ),
    ),
  );
}

class ProjectAdminCard extends StatelessWidget {
  const ProjectAdminCard({required this.child, this.padding = const EdgeInsets.all(AppSpacing.lg), super.key});

  final Widget child;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    margin: const EdgeInsets.only(bottom: AppSpacing.md),
    padding: padding,
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      border: Border.all(color: AppColors.border),
    ),
    child: child,
  );
}

class ProjectAdminKpi extends StatelessWidget {
  const ProjectAdminKpi({required this.label, required this.value, this.color, super.key});

  final String label;
  final String value;
  final Color? color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(AppSpacing.md),
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      border: Border.all(color: AppColors.border),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Text(label, maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(color: AppColors.textMuted, fontSize: 13)),
        const SizedBox(height: 6),
        Text(value, style: TextStyle(fontSize: 24, fontWeight: FontWeight.w700, color: color ?? AppColors.text)),
      ],
    ),
  );
}

class ProjectAdminMessage extends StatelessWidget {
  const ProjectAdminMessage(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(AppSpacing.xl),
    child: Text(text, textAlign: TextAlign.center, style: TextStyle(color: AppColors.textMuted)),
  );
}
