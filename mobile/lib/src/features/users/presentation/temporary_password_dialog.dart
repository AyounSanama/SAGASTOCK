import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/theme/app_tokens.dart';

/// Mot de passe temporaire d'un compte créé ou réinitialisé : montré une
/// seule fois à l'administrateur, qui le transmet à l'utilisateur. Ce dernier
/// le change à sa première connexion. Rien n'est conservé sur le téléphone.
Future<void> showTemporaryPassword(
  BuildContext context, {
  required String password,
  required String userName,
}) => showDialog<void>(
  context: context,
  barrierDismissible: false,
  builder: (dialogContext) => AlertDialog(
    title: const Text('Mot de passe temporaire'),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          'Transmettez-le à $userName. Il ne sera plus affiché ; '
          'l’utilisateur devra le changer à sa première connexion.',
        ),
        const SizedBox(height: 14),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            border: Border.all(color: AppColors.border),
            borderRadius: BorderRadius.circular(10),
          ),
          child: SelectableText(
            password,
            style: const TextStyle(fontSize: 18, letterSpacing: .5, fontFamily: 'monospace'),
          ),
        ),
        const SizedBox(height: 8),
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            onPressed: () async {
              await Clipboard.setData(ClipboardData(text: password));
              if (dialogContext.mounted) {
                ScaffoldMessenger.maybeOf(dialogContext)?.showSnackBar(
                  const SnackBar(content: Text('Mot de passe copié.')),
                );
              }
            },
            icon: const Icon(Icons.copy_rounded, size: 18),
            label: const Text('Copier'),
          ),
        ),
      ],
    ),
    actions: [
      FilledButton(
        style: FilledButton.styleFrom(backgroundColor: AppColors.primaryStrong),
        onPressed: () => Navigator.pop(dialogContext),
        child: const Text('J’ai noté le mot de passe'),
      ),
    ],
  ),
);
