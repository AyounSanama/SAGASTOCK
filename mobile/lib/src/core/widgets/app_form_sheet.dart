import 'package:flutter/material.dart';
import '../theme/app_tokens.dart';

/// Create/Edit window, centered on desktop, tablet and mobile.
Future<T?> showAppFormSheet<T>({
  required BuildContext context,
  required String title,
  String? description,
  required WidgetBuilder builder,
  WidgetBuilder? footerBuilder,
  double maxWidth = AppSizes.formSheetMaxWidth,
}) => Navigator.of(context, rootNavigator: true).push<T>(
  _FormSheetRoute<T>(
    context: context,
    builder: (context) => PharmaCareCenteredFormSheet(
      title: Text(title),
      description: description,
      maxWidth: maxWidth,
      footer: footerBuilder?.call(context),
      child: builder(context),
    ),
  ),
);

/// Preserves legacy builders and their state during progressive migration.
Future<T?> showAppDialogAsFormSheet<T>({
  required BuildContext context,
  required WidgetBuilder builder,
}) => Navigator.of(context, rootNavigator: true).push<T>(
  _FormSheetRoute<T>(
    context: context,
    builder: (context) {
      final dialog = builder(context);
      if (dialog is! AlertDialog) return dialog;
      return PharmaCareCenteredFormSheet(
        title: dialog.title ?? const SizedBox.shrink(),
        footer: dialog.actions?.isNotEmpty == true
            ? OverflowBar(
                alignment: MainAxisAlignment.end,
                spacing: AppSpacing.sm,
                overflowSpacing: AppSpacing.sm,
                children: dialog.actions!,
              )
            : null,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: dialog.content ?? const SizedBox.shrink(),
        ),
      );
    },
  ),
);

class PharmaCareCenteredFormSheet extends StatelessWidget {
  const PharmaCareCenteredFormSheet({
    required this.title,
    required this.child,
    this.description,
    this.footer,
    this.maxWidth = AppSizes.formSheetMaxWidth,
    super.key,
  });
  final Widget title, child;
  final String? description;
  final Widget? footer;
  final double maxWidth;

  @override
  Widget build(BuildContext context) => Dialog(
    insetPadding: const EdgeInsets.all(AppSpacing.lg),
    clipBehavior: Clip.antiAlias,
    backgroundColor: AppColors.surface,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadius.sheet),
    ),
    child: ConstrainedBox(
      constraints: BoxConstraints(
        maxWidth: maxWidth,
        maxHeight: MediaQuery.sizeOf(context).height * .88,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(24, 16, 12, 16),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      DefaultTextStyle(
                        style: Theme.of(context).textTheme.titleLarge!.copyWith(
                          fontWeight: FontWeight.w700,
                          color: AppColors.text,
                        ),
                        child: title,
                      ),
                      if (description != null) ...[
                        const SizedBox(height: AppSpacing.xs),
                        Text(description!, style: AppTypography.secondary),
                      ],
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'Fermer',
                  onPressed: () => Navigator.of(context).pop(),
                  icon: const Icon(Icons.close),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Flexible(child: child),
          if (footer != null) ...[
            const Divider(height: 1),
            Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: footer!,
            ),
          ],
        ],
      ),
    ),
  );
}

/// Live draft/busy checks shared by X, Escape, back, backdrop and cancel.
class AppFormSheetGuard extends StatefulWidget {
  const AppFormSheetGuard({
    required this.isDirty,
    required this.child,
    this.isBusy = false,
    super.key,
  });
  final bool Function() isDirty;
  final bool isBusy;
  final Widget child;
  @override
  State<AppFormSheetGuard> createState() => _AppFormSheetGuardState();
}

class _AppFormSheetGuardState extends State<AppFormSheetGuard> {
  _FormSheetRoute<dynamic>? _route;
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final route = ModalRoute.of(context);
    if (route is _FormSheetRoute) {
      _route = route;
      route.guards.add(this);
    }
  }

  @override
  void dispose() {
    _route?.guards.remove(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.child;
}

class _FormSheetRoute<T> extends DialogRoute<T> {
  _FormSheetRoute({required super.context, required super.builder})
    : super(barrierColor: AppColors.backdrop, barrierDismissible: true);
  final guards = <_AppFormSheetGuardState>{};
  bool _confirming = false;
  bool _discard = false;
  @override
  bool didPop(T? result) {
    if (result == null && !_discard) {
      if (guards.any((guard) => guard.widget.isBusy)) return false;
      if (guards.any((guard) => guard.widget.isDirty())) {
        if (!_confirming) _confirmDiscard();
        return false;
      }
    }
    return super.didPop(result);
  }

  Future<void> _confirmDiscard() async {
    _confirming = true;
    await Future<void>.delayed(Duration.zero);
    if (!isActive) {
      _confirming = false;
      return;
    }
    final navigatorState = navigator;
    if (navigatorState == null || !navigatorState.mounted) {
      _confirming = false;
      return;
    }
    final discard = await showDialog<bool>(
      context: navigatorState.context,
      builder: (context) => AlertDialog(
        title: const Text('Abandonner les modifications ?'),
        content: const Text(
          'Les informations saisies ne seront pas enregistrées.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Continuer la saisie'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Abandonner'),
          ),
        ],
      ),
    );
    _confirming = false;
    if (discard == true && isActive) {
      _discard = true;
      navigator!.pop();
    }
  }
}
