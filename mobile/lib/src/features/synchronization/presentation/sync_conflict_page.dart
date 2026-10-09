import 'package:flutter/material.dart';

import '../../../core/sync/sync_status_service.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_button.dart';

/// Détail d'un conflit : la saisie du téléphone est conservée (jamais
/// effacée) ; l'utilisateur peut la signaler à son Admin ou en prendre acte.
class SyncConflictPage extends StatefulWidget {
  const SyncConflictPage({
    super.key,
    required this.issue,
    required this.service,
  });

  final SyncIssue issue;
  final SyncStatusService service;

  @override
  State<SyncConflictPage> createState() => _SyncConflictPageState();
}

class _SyncConflictPageState extends State<SyncConflictPage> {
  bool _busy = false;

  Future<void> _act(Future<void> Function() action, String message) async {
    setState(() => _busy = true);
    await action();
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
    Navigator.pop(context, true);
  }

  @override
  Widget build(BuildContext context) {
    final issue = widget.issue;
    final at = issue.occurredAt;
    String two(int value) => value.toString().padLeft(2, '0');
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Conflit'),
            Text(
              issue.title,
              style: TextStyle(fontSize: 13, color: AppColors.textMuted),
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.primarySoft,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.primary.withValues(alpha: .35)),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.warning_amber_rounded, color: AppColors.primarySoftText),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Le serveur n’a pas accepté cette saisie : elle ne correspond plus aux données enregistrées. Votre saisie est conservée sur ce téléphone.',
                    style: TextStyle(color: AppColors.primarySoftText),
                  ),
                ),
              ],
            ),
          ),
          _block(
            'Saisie concernée',
            issue.title,
            'Saisie hors connexion sur ce téléphone · ${two(at.day)}/${two(at.month)} à ${two(at.hour)}:${two(at.minute)}',
          ),
          _block(
            'Pourquoi ?',
            issue.reason ?? 'Le serveur n’a pas précisé le motif.',
            null,
          ),
          _block(
            'Et maintenant ?',
            issue.reported
                ? 'Ce conflit a été signalé à votre Admin.'
                : 'Votre Admin Projet voit ce conflit dans sa supervision.',
            issue.reported
                ? null
                : 'Si votre saisie est la bonne, signalez-le-lui.',
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
          child: Row(
            children: [
              Expanded(
                child: AppButton.secondary(
                  label: issue.reported ? 'Déjà signalé' : 'Signaler à l’Admin',
                  onPressed: _busy || issue.reported
                      ? null
                      : () => _act(
                          () => widget.service.reportToAdmin(issue.operationId),
                          'Conflit signalé à votre Admin.',
                        ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: AppButton.primary(
                  label: 'J’ai compris',
                  onPressed: _busy
                      ? null
                      : () => _act(
                          () => widget.service.acknowledge(issue.operationId),
                          'Conflit classé. Votre saisie reste archivée sur ce téléphone.',
                        ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _block(String title, String body, String? caption) => Container(
    margin: const EdgeInsets.only(top: 12),
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: AppColors.border),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: TextStyle(color: AppColors.textMuted)),
        const SizedBox(height: 4),
        Text(
          body,
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
        ),
        if (caption != null) ...[
          const SizedBox(height: 4),
          Text(caption, style: TextStyle(color: AppColors.textMuted)),
        ],
      ],
    ),
  );
}
