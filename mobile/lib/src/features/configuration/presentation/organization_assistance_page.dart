import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/connectivity/connectivity_service.dart';
import '../../../core/widgets/app_card.dart';
import '../data/platform_configuration_service.dart';
import 'organization_configuration_sheet.dart';

class OrganizationAssistancePage extends StatefulWidget {
  const OrganizationAssistancePage({super.key, required this.organizationId});
  final String organizationId;

  @override
  State<OrganizationAssistancePage> createState() =>
      _OrganizationAssistancePageState();
}

class _OrganizationAssistancePageState
    extends State<OrganizationAssistancePage> {
  final _service = PlatformConfigurationService();
  Map<String, dynamic>? _data;
  String? _error;
  bool _offline = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _error = null);
    try {
      _offline = !await ConnectivityService().hasNetwork;
      _data = await _service.organization(widget.organizationId);
    } catch (_) {
      _error = 'Impossible de charger cette organisation.';
    }
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final organization = Map<String, dynamic>.from(
      _data?['organization'] as Map? ?? {},
    );
    final categories = (_data?['categories'] as List? ?? const []).cast<Map>();
    return Scaffold(
      appBar: AppBar(title: const Text('Assistance aux organisations')),
      body: _error != null
          ? Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (_offline)
                    Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppTheme.orange.withValues(alpha: .1),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Text(
                        'Hors connexion — données de la dernière synchronisation.',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ),
                  Text(_error!),
                  TextButton(onPressed: _load, child: const Text('Réessayer')),
                ],
              ),
            )
          : _data == null
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          '${organization['name']}',
                          style: const TextStyle(
                            fontSize: 23,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      _Badge(active: organization['is_active'] == true),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    '${organization['access_type'] == 'multi_country' ? 'Multipays' : 'Unipays'} · ${organization['code']}',
                    style: TextStyle(color: AppTheme.muted),
                  ),
                  const SizedBox(height: 14),
                  AppCard(
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.shield_outlined, color: AppTheme.blue),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Périmètre protégé',
                                style: TextStyle(fontWeight: FontWeight.w800),
                              ),
                              SizedBox(height: 4),
                              Text(
                                'Seuls les paramètres de plateforme autorisés sont accessibles. Aucune donnée métier ne peut être consultée.',
                                style: TextStyle(
                                  color: AppTheme.muted,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),
                  const Text(
                    'Paramètres de l’organisation',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 10),
                  ...categories.map((raw) {
                    final category = Map<String, dynamic>.from(raw);
                    final configuration = category['configuration'] as Map?;
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(12),
                        onTap: () async {
                          final changed =
                              await showOrganizationConfigurationSheet(
                                context: context,
                                organizationId: widget.organizationId,
                                category: category,
                              );
                          if (changed != null) {
                            if (context.mounted) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(
                                  content: Text(
                                    changed
                                        ? 'Configuration appliquée avec succès.'
                                        : 'Configuration enregistrée hors connexion. Synchronisation en attente.',
                                  ),
                                ),
                              );
                            }
                            await _load();
                          }
                        },
                        child: AppCard(
                          child: Row(
                            children: [
                              CircleAvatar(
                                backgroundColor: AppTheme.orangeSoft,
                                foregroundColor: AppTheme.orange,
                                child: Icon(_icon('${category['key']}')),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      '${category['label']}',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                    const SizedBox(height: 3),
                                    Text(
                                      configuration == null
                                          ? 'Configurer'
                                          : 'Modifier · Version ${configuration['configuration_version']}',
                                      style: TextStyle(
                                        color: AppTheme.muted,
                                        fontSize: 12,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const Icon(
                                Icons.chevron_right,
                                color: AppTheme.orange,
                              ),
                            ],
                          ),
                        ),
                      ),
                    );
                  }),
                ],
              ),
            ),
    );
  }

  IconData _icon(String key) => switch (key) {
    'access' => Icons.manage_accounts_outlined,
    'security' => Icons.shield_outlined,
    'synchronization' => Icons.sync,
    'platform' => Icons.tune,
    _ => Icons.language,
  };
}

class _Badge extends StatelessWidget {
  const _Badge({required this.active});
  final bool active;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(
      color: (active ? AppTheme.green : AppTheme.gray).withValues(alpha: .1),
      borderRadius: BorderRadius.circular(10),
    ),
    child: Text(
      active ? 'Actif' : 'Inactif',
      style: TextStyle(
        color: active ? AppTheme.green : AppTheme.gray,
        fontWeight: FontWeight.w800,
        fontSize: 11,
      ),
    ),
  );
}
