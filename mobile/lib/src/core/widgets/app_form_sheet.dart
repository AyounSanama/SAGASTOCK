import 'package:flutter/material.dart';

/// Form Sheet Material 3 commun à tous les formulaires courts et moyens.
Future<T?> showAppFormSheet<T>({
  required BuildContext context,
  required String title,
  String? description,
  required WidgetBuilder builder,
}) {
  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: false,
    builder: (sheetContext) => ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 760),
      child: Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.viewInsetsOf(sheetContext).bottom,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 44,
              height: 4,
              margin: const EdgeInsets.only(top: 10),
              decoration: BoxDecoration(
                color: Theme.of(sheetContext).colorScheme.outlineVariant,
                borderRadius: BorderRadius.circular(99),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 14, 10, 12),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          style: Theme.of(sheetContext).textTheme.titleLarge
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                        if (description != null) ...[
                          const SizedBox(height: 4),
                          Text(description),
                        ],
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: 'Fermer',
                    onPressed: () => Navigator.pop(sheetContext),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Flexible(child: builder(sheetContext)),
          ],
        ),
      ),
    ),
  );
}

/// Pont de migration pour les anciens formulaires construits avec AlertDialog.
/// Le contenu et les actions sont rendus dans le même Form Sheet que le reste
/// de l'application, le temps que chaque formulaire adopte l'API déclarative.
Future<T?> showAppDialogAsFormSheet<T>({
  required BuildContext context,
  required WidgetBuilder builder,
}) {
  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Colors.white,
    builder: (sheetContext) {
      final dialog = builder(sheetContext);
      if (dialog is! AlertDialog) return dialog;
      return Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.viewInsetsOf(sheetContext).bottom,
        ),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 760),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 44,
                height: 4,
                margin: const EdgeInsets.only(top: 10),
                decoration: BoxDecoration(
                  color: Theme.of(sheetContext).colorScheme.outlineVariant,
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 14, 10, 12),
                child: Row(
                  children: [
                    Expanded(
                      child: DefaultTextStyle(
                        style: Theme.of(sheetContext).textTheme.titleLarge!
                            .copyWith(fontWeight: FontWeight.w800),
                        child: dialog.title ?? const SizedBox.shrink(),
                      ),
                    ),
                    IconButton(
                      tooltip: 'Fermer',
                      onPressed: () => Navigator.pop(sheetContext),
                      icon: const Icon(Icons.close_rounded),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1),
              Flexible(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(20),
                  child: dialog.content ?? const SizedBox.shrink(),
                ),
              ),
              if (dialog.actions?.isNotEmpty == true)
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      for (
                        var index = 0;
                        index < dialog.actions!.length;
                        index++
                      ) ...[
                        if (index > 0) const SizedBox(width: 10),
                        Flexible(child: dialog.actions![index]),
                      ],
                    ],
                  ),
                ),
            ],
          ),
        ),
      );
    },
  );
}
