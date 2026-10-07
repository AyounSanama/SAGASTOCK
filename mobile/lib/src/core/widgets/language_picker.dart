import 'package:flutter/material.dart';

import '../localization/language_catalog.dart';

/// Choix de plusieurs langues parmi toutes les langues ISO 639-1, avec
/// recherche. Retourne null si l'utilisateur annule.
Future<List<String>?> showLanguagePicker(
  BuildContext context, {
  required List<String> initial,
  String title = 'Langues supplémentaires',
  Set<String> excluded = const {},
}) {
  final chosen = {...initial};
  var search = '';
  return showDialog<List<String>>(
    context: context,
    builder: (dialogContext) => StatefulBuilder(
      builder: (dialogContext, setDialogState) {
        final term = search.trim().toLowerCase();
        final entries = languageCatalog.entries
            .where((entry) => !excluded.contains(entry.key))
            .where((entry) => term.isEmpty || entry.value.toLowerCase().contains(term) || entry.key.contains(term))
            .toList();
        return AlertDialog(
          title: Text(title),
          content: SizedBox(
            width: double.maxFinite,
            height: 420,
            child: Column(
              children: [
                TextField(
                  decoration: const InputDecoration(prefixIcon: Icon(Icons.search), hintText: 'Rechercher une langue'),
                  onChanged: (value) => setDialogState(() => search = value),
                ),
                Expanded(
                  child: ListView(
                    children: [
                      for (final entry in entries)
                        CheckboxListTile(
                          dense: true,
                          value: chosen.contains(entry.key),
                          title: Text(entry.value),
                          onChanged: (checked) => setDialogState(
                            () => checked == true ? chosen.add(entry.key) : chosen.remove(entry.key),
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Annuler')),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, [
                for (final code in languageCatalog.keys)
                  if (chosen.contains(code)) code,
              ]),
              child: Text('Valider (${chosen.length})'),
            ),
          ],
        );
      },
    ),
  );
}
