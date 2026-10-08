import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';

enum AppButtonVariant {
  add,
  save,
  validate,
  edit,
  duplicate,
  view,
  delete,
  archive,
  cancel,
  text,
}

class AppButton extends StatelessWidget {
  const AppButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.variant = AppButtonVariant.add,
    this.loading = false,
    this.expanded = false,
    this.compact = false,
    this.trailingIcon = false,
  });

  const AppButton.primary({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
    this.expanded = false,
    this.compact = false,
    this.trailingIcon = false,
  }) : variant = AppButtonVariant.add;

  const AppButton.secondary({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
    this.expanded = false,
    this.compact = false,
    this.trailingIcon = false,
  }) : variant = AppButtonVariant.edit;

  const AppButton.danger({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
    this.expanded = false,
    this.compact = false,
    this.trailingIcon = false,
  }) : variant = AppButtonVariant.delete;

  const AppButton.ghost({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
    this.expanded = false,
    this.compact = false,
    this.trailingIcon = false,
  }) : variant = AppButtonVariant.text;

  const AppButton.add({
    super.key,
    this.label = 'Ajouter',
    required this.onPressed,
    this.icon = Icons.add_rounded,
    this.loading = false,
    this.expanded = false,
    this.compact = false,
    this.trailingIcon = false,
  }) : variant = AppButtonVariant.add;

  const AppButton.save({
    super.key,
    this.label = 'Enregistrer',
    required this.onPressed,
    this.icon = Icons.save_outlined,
    this.loading = false,
    this.expanded = false,
    this.compact = false,
    this.trailingIcon = false,
  }) : variant = AppButtonVariant.save;

  const AppButton.validate({
    super.key,
    this.label = 'Valider',
    required this.onPressed,
    this.icon = Icons.check_rounded,
    this.loading = false,
    this.expanded = false,
    this.compact = false,
    this.trailingIcon = false,
  }) : variant = AppButtonVariant.validate;

  const AppButton.edit({
    super.key,
    this.label = 'Modifier',
    required this.onPressed,
    this.icon = Icons.edit_outlined,
    this.loading = false,
    this.expanded = false,
    this.compact = false,
    this.trailingIcon = false,
  }) : variant = AppButtonVariant.edit;

  const AppButton.duplicate({
    super.key,
    this.label = 'Dupliquer',
    required this.onPressed,
    this.icon = Icons.copy_all_outlined,
    this.loading = false,
    this.expanded = false,
    this.compact = false,
    this.trailingIcon = false,
  }) : variant = AppButtonVariant.duplicate;

  const AppButton.view({
    super.key,
    this.label = 'Voir',
    required this.onPressed,
    this.icon = Icons.visibility_outlined,
    this.loading = false,
    this.expanded = false,
    this.compact = false,
    this.trailingIcon = false,
  }) : variant = AppButtonVariant.view;

  const AppButton.delete({
    super.key,
    this.label = 'Supprimer',
    required this.onPressed,
    this.icon = Icons.delete_outline_rounded,
    this.loading = false,
    this.expanded = false,
    this.compact = false,
    this.trailingIcon = false,
  }) : variant = AppButtonVariant.delete;

  const AppButton.archive({
    super.key,
    this.label = 'Archiver',
    required this.onPressed,
    this.icon = Icons.archive_outlined,
    this.loading = false,
    this.expanded = false,
    this.compact = false,
    this.trailingIcon = false,
  }) : variant = AppButtonVariant.archive;

  const AppButton.cancel({
    super.key,
    this.label = 'Annuler',
    required this.onPressed,
    this.icon = Icons.cancel_outlined,
    this.loading = false,
    this.expanded = false,
    this.compact = false,
    this.trailingIcon = false,
  }) : variant = AppButtonVariant.cancel;

  const AppButton.text({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
    this.expanded = false,
    this.compact = false,
    this.trailingIcon = false,
  }) : variant = AppButtonVariant.text;

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final AppButtonVariant variant;
  final bool loading;
  final bool expanded;
  final bool compact;
  final bool trailingIcon;

  Color get _color => switch (variant) {
    AppButtonVariant.add || AppButtonVariant.edit => AppTheme.orange,
    AppButtonVariant.save ||
    AppButtonVariant.duplicate ||
    AppButtonVariant.text => AppTheme.blue,
    AppButtonVariant.validate || AppButtonVariant.view => AppTheme.green,
    AppButtonVariant.delete ||
    AppButtonVariant.archive ||
    AppButtonVariant.cancel => AppTheme.red,
  };

  bool get _filled => switch (variant) {
    AppButtonVariant.add ||
    AppButtonVariant.save ||
    AppButtonVariant.validate ||
    AppButtonVariant.delete => true,
    _ => false,
  };

  bool get _textOnly => variant == AppButtonVariant.text;

  @override
  Widget build(BuildContext context) {
    final callback = loading ? null : onPressed;
    final color = _color;
    final height = AppSizes.buttonHeight;
    // Bouton plein : texte lisible sur son fond (mode sombre : fonds clairs,
    // texte foncé ; contraste minimum 4,5:1 de la charte).
    final onFilled =
        ThemeData.estimateBrightnessForColor(color) == Brightness.light
        ? const Color(0xFF1E2329)
        : Colors.white;
    final foreground = _filled ? onFilled : color;
    final disabledForeground = AppColors.disabledText;
    final content = loading
        ? SizedBox.square(
            dimension: 20,
            child: CircularProgressIndicator(
              strokeWidth: 2.2,
              color: _filled ? onFilled : color,
            ),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (icon != null && !trailingIcon) ...[
                Icon(icon, size: 18),
                const SizedBox(width: AppSpacing.sm),
              ],
              Flexible(child: Text(label, textAlign: TextAlign.center)),
              if (icon != null && trailingIcon) ...[
                const SizedBox(width: AppSpacing.sm),
                Icon(icon, size: 18),
              ],
            ],
          );

    final style = ButtonStyle(
      minimumSize: WidgetStatePropertyAll(Size(compact ? 44 : 96, height)),
      padding: WidgetStatePropertyAll(
        EdgeInsets.symmetric(horizontal: compact ? 12 : 18, vertical: 9),
      ),
      elevation: const WidgetStatePropertyAll(0),
      shape: WidgetStatePropertyAll(
        RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.md)),
      ),
      textStyle: const WidgetStatePropertyAll(
        AppTypography.button,
      ),
      foregroundColor: WidgetStateProperty.resolveWith(
        (states) =>
            states.contains(WidgetState.disabled) ? disabledForeground : foreground,
      ),
      backgroundColor: WidgetStateProperty.resolveWith((states) {
        if (!_filled) return Colors.transparent;
        if (states.contains(WidgetState.disabled)) {
          return AppColors.disabledSurface;
        }
        if (states.contains(WidgetState.pressed)) {
          return Color.alphaBlend(const Color(0x33000000), color);
        }
        if (states.contains(WidgetState.hovered)) {
          return Color.alphaBlend(const Color(0x14000000), color);
        }
        return color;
      }),
      side: WidgetStateProperty.resolveWith((states) {
        if (_filled || _textOnly) return BorderSide.none;
        return BorderSide(
          color: states.contains(WidgetState.disabled)
              ? AppColors.border
              : color,
          width: 2,
        );
      }),
      overlayColor: WidgetStatePropertyAll(color.withValues(alpha: .10)),
    );

    Widget button;
    if (_textOnly) {
      button = TextButton(onPressed: callback, style: style, child: content);
    } else if (_filled) {
      button = FilledButton(onPressed: callback, style: style, child: content);
    } else {
      button = OutlinedButton(onPressed: callback, style: style, child: content);
    }

    if (expanded) button = SizedBox(width: double.infinity, child: button);
    return Semantics(
      button: true,
      enabled: callback != null,
      label: loading ? '$label, chargement' : label,
      child: button,
    );
  }
}

enum AppActionColor { orange, blue, green, red, purple, gray }

extension on AppActionColor {
  Color get value => switch (this) {
    AppActionColor.orange => AppTheme.orange,
    AppActionColor.blue => AppTheme.blue,
    AppActionColor.green => AppTheme.green,
    AppActionColor.red => AppTheme.red,
    AppActionColor.purple => AppTheme.purple,
    AppActionColor.gray => AppTheme.gray,
  };
}

class AppIconButton extends StatelessWidget {
  const AppIconButton({
    super.key,
    required this.icon,
    required this.tooltip,
    required this.onPressed,
    this.color = AppActionColor.gray,
    this.loading = false,
  });

  final IconData icon;
  final String tooltip;
  final VoidCallback? onPressed;
  final AppActionColor color;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    final actionColor = color.value;
    return IconButton(
      tooltip: tooltip,
      onPressed: loading ? null : onPressed,
      style: ButtonStyle(
        minimumSize: const WidgetStatePropertyAll(Size.square(AppSizes.iconButton)),
        fixedSize: const WidgetStatePropertyAll(Size.square(AppSizes.iconButton)),
        shape: WidgetStatePropertyAll(
          RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.md)),
        ),
        backgroundColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.disabled)
              ? AppColors.disabledSurface
              : actionColor.withValues(alpha: .10),
        ),
        foregroundColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.disabled)
              ? AppColors.disabledText
              : actionColor,
        ),
        overlayColor: WidgetStatePropertyAll(actionColor.withValues(alpha: .14)),
      ),
      icon: loading
          ? SizedBox.square(
              dimension: 20,
              child: CircularProgressIndicator(
                strokeWidth: 2.2,
                color: actionColor,
              ),
            )
          : Icon(icon, size: AppSizes.icon),
    );
  }
}

@Deprecated('Utiliser AppIconButton. Cet alias sera supprimé après la migration.')
typedef AppIconAction = AppIconButton;

class AppFab extends StatelessWidget {
  const AppFab({
    super.key,
    required this.onPressed,
    this.icon = Icons.add_rounded,
    this.tooltip = 'Ajouter',
    this.color = AppActionColor.orange,
  });

  final VoidCallback? onPressed;
  final IconData icon;
  final String tooltip;
  final AppActionColor color;

  @override
  Widget build(BuildContext context) {
    return FloatingActionButton(
      onPressed: onPressed,
      tooltip: tooltip,
      backgroundColor: color.value,
      foregroundColor: Colors.white,
      shape: const CircleBorder(),
      child: Icon(icon),
    );
  }
}
