import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../../core/widgets/app_text_field.dart';
import '../data/platform_configuration_service.dart';

Future<bool?> showOrganizationConfigurationSheet({
  required BuildContext context,
  required String organizationId,
  required Map<String, dynamic> category,
}) => showAppFormSheet<bool>(
  context: context,
  title:
      '${category['configuration'] == null ? 'Configurer' : 'Modifier'} · ${category['label']}',
  description: 'Les changements seront prévisualisés avant leur application.',
  builder: (_) =>
      _ConfigurationForm(organizationId: organizationId, category: category),
);

class _ConfigurationForm extends StatefulWidget {
  const _ConfigurationForm({
    required this.organizationId,
    required this.category,
  });
  final String organizationId;
  final Map<String, dynamic> category;
  @override
  State<_ConfigurationForm> createState() => _ConfigurationFormState();
}

class _ConfigurationFormState extends State<_ConfigurationForm> {
  final _service = PlatformConfigurationService();
  final _formKey = GlobalKey<FormState>();
  late Map<String, dynamic> values;
  bool loading = false;
  String? error;
  String get key => '${widget.category['key']}';

  @override
  void initState() {
    super.initState();
    final configuration = widget.category['configuration'] as Map?;
    final envelope = configuration?['configuration'] as Map?;
    final current = envelope?['definition'] as Map?;
    values = {..._defaults(key), ...?current?.cast<String, dynamic>()};
  }

  Map<String, dynamic> _defaults(String category) => switch (category) {
    'general' => {
      'default_language': 'fr',
      'additional_languages': <String>[],
      'timezone': 'Africa/Douala',
      'locale': 'fr_FR',
      'date_format': 'd/m/Y',
      'time_format': 'H:i',
    },
    'access' => {
      'allowed_profiles': <String>[
        'ADMIN_COORDINATION',
        'ADMIN_PROJECT',
        'ADMIN_SITE',
      ],
    },
    'security' => {
      'session_duration_minutes': 60,
      'logout_after_inactivity': true,
      'maximum_login_attempts': 5,
      'temporary_lock_minutes': 15,
      'authentication_policy': 'standard',
    },
    'synchronization' => {
      'automatic_sync': true,
      'sync_on_reconnect': true,
      'offline_enabled': true,
      'configuration_download': 'automatic',
      'sync_interval_minutes': 15,
    },
    _ => {
      'notifications_enabled': true,
      'maintenance_messages': true,
      'default_page_size': 25,
      'support_contact_visible': true,
    },
  };

  Future<void> _continue() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = await _service.preview(
        widget.organizationId,
        key,
        values,
      );
      if (!mounted) return;
      final preview = Map<String, dynamic>.from(
        response['preview'] as Map? ?? {},
      );
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (_) => _PreviewDialog(preview: preview),
      );
      if (confirmed == true) {
        final synchronized = await _service.apply(
          widget.organizationId,
          key,
          values,
        );
        if (mounted) Navigator.pop(context, synchronized);
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => error =
              'La configuration n’a pas pu être appliquée. Vérifiez les valeurs saisies.',
        );
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
    child: Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          ..._fields(),
          if (error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(error!, style: const TextStyle(color: AppTheme.red)),
            ),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: loading ? null : () => Navigator.pop(context),
                  child: const Text('Annuler'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: FilledButton.icon(
                  onPressed: loading ? null : _continue,
                  icon: loading
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.arrow_forward),
                  label: const Text('Continuer'),
                ),
              ),
            ],
          ),
        ],
      ),
    ),
  );

  List<Widget> _fields() => switch (key) {
    'general' => [
      _select('Langue principale', 'default_language', const {
        'fr': 'Français',
        'en': 'English',
        'es': 'Español',
        'pt': 'Português',
      }),
      _select('Fuseau horaire', 'timezone', const {
        'Africa/Douala': 'Africa/Douala',
        'Africa/Dakar': 'Africa/Dakar',
        'Africa/Kinshasa': 'Africa/Kinshasa',
        'UTC': 'UTC',
      }),
      _select('Paramètres régionaux', 'locale', const {
        'fr_FR': 'Français',
        'en_US': 'English',
        'es_ES': 'Español',
        'pt_PT': 'Português',
      }),
      _select('Format de date', 'date_format', const {
        'd/m/Y': '31/12/2026',
        'Y-m-d': '2026-12-31',
        'm/d/Y': '12/31/2026',
      }),
      _select('Format de l’heure', 'time_format', const {
        'H:i': '24 heures',
        'h:i A': '12 heures',
      }),
    ],
    'access' => [
      const Text(
        'Profils standards autorisés',
        style: TextStyle(fontWeight: FontWeight.w800),
      ),
      ...['ADMIN_COORDINATION', 'ADMIN_PROJECT', 'ADMIN_SITE'].map(
        (profile) => CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(profile),
          value: (values['allowed_profiles'] as List).contains(profile),
          onChanged: (checked) => setState(() {
            final list = List<String>.from(values['allowed_profiles'] as List);
            checked == true ? list.add(profile) : list.remove(profile);
            values['allowed_profiles'] = list.toSet().toList();
          }),
        ),
      ),
    ],
    'security' => [
      _number(
        'Durée de session (minutes)',
        'session_duration_minutes',
        5,
        1440,
      ),
      _toggle('Déconnexion après inactivité', 'logout_after_inactivity'),
      _number('Nombre maximal de tentatives', 'maximum_login_attempts', 1, 20),
      _number(
        'Verrouillage temporaire (minutes)',
        'temporary_lock_minutes',
        1,
        1440,
      ),
      _select('Politique d’authentification', 'authentication_policy', const {
        'standard': 'Standard',
        'strict': 'Renforcée',
      }),
    ],
    'synchronization' => [
      _toggle('Synchronisation automatique', 'automatic_sync'),
      _toggle('Synchronisation au retour du réseau', 'sync_on_reconnect'),
      _toggle('Fonctionnement hors connexion', 'offline_enabled'),
      _select(
        'Téléchargement des configurations',
        'configuration_download',
        const {'automatic': 'Automatique', 'manual': 'Manuel'},
      ),
      _number(
        'Intervalle de synchronisation (minutes)',
        'sync_interval_minutes',
        5,
        1440,
      ),
    ],
    _ => [
      _toggle('Notifications de plateforme', 'notifications_enabled'),
      _toggle('Messages de maintenance', 'maintenance_messages'),
      _toggle('Contact support visible', 'support_contact_visible'),
      _select('Taille des listes par défaut', 'default_page_size', const {
        10: '10 éléments',
        25: '25 éléments',
        50: '50 éléments',
      }),
    ],
  };

  Widget _select<T>(String label, String name, Map<T, String> options) =>
      Padding(
        padding: const EdgeInsets.only(bottom: 14),
        child: DropdownButtonFormField<T>(
          initialValue: options.keys.contains(values[name])
              ? values[name] as T
              : options.keys.first,
          decoration: InputDecoration(labelText: label),
          items: options.entries
              .map(
                (entry) => DropdownMenuItem(
                  value: entry.key,
                  child: Text(entry.value),
                ),
              )
              .toList(),
          onChanged: (value) => values[name] = value,
        ),
      );

  Widget _toggle(String label, String name) => SwitchListTile(
    contentPadding: EdgeInsets.zero,
    title: Text(label),
    value: values[name] == true,
    onChanged: (value) => setState(() => values[name] = value),
  );

  Widget _number(String label, String name, int min, int max) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: AppTextField(
      label: label,
      initialValue: '${values[name]}',
      keyboardType: TextInputType.number,
      validator: (raw) {
        final value = int.tryParse(raw ?? '');
        return value == null || value < min || value > max
            ? 'Valeur comprise entre $min et $max requise.'
            : null;
      },
      onChanged: (raw) {
        final parsed = int.tryParse(raw);
        if (parsed != null) values[name] = parsed;
      },
    ),
  );
}

class _PreviewDialog extends StatelessWidget {
  const _PreviewDialog({required this.preview});
  final Map<String, dynamic> preview;
  @override
  Widget build(BuildContext context) {
    final changes = (preview['changes'] as List? ?? const []).cast<Map>();
    return AlertDialog(
      title: const Text('Aperçu avant / après'),
      content: SizedBox(
        width: 520,
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${preview['current_configuration_version']} → ${preview['next_configuration_version']}',
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  color: AppTheme.orange,
                ),
              ),
              const SizedBox(height: 12),
              if (changes.isEmpty)
                const Text('Aucune modification détectée.')
              else
                ...changes.map(
                  (change) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${change['label']}',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        Text(
                          'Avant : ${change['old'] ?? 'Non défini'}',
                          style: const TextStyle(
                            color: AppTheme.muted,
                            fontSize: 12,
                          ),
                        ),
                        Text(
                          'Après : ${change['new'] ?? 'Non défini'}',
                          style: const TextStyle(fontSize: 12),
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context, false),
          child: const Text('Retour'),
        ),
        FilledButton(
          onPressed: changes.isEmpty
              ? null
              : () => Navigator.pop(context, true),
          child: const Text('Confirmer et appliquer'),
        ),
      ],
    );
  }
}
