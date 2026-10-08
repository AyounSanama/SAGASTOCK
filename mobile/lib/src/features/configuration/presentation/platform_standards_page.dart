import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/connectivity/connectivity_service.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/app_empty_state.dart';
import '../../../core/widgets/app_kpi_card.dart';
import '../../../core/widgets/app_search_field.dart';
import '../data/platform_configuration_service.dart';

enum PlatformStandardsSection { home, assistance, history }

class PlatformStandardsPage extends StatefulWidget {
  const PlatformStandardsPage({
    super.key,
    this.section = PlatformStandardsSection.home,
  });
  final PlatformStandardsSection section;
  @override
  State<PlatformStandardsPage> createState() => _PlatformStandardsPageState();
}

class _PlatformStandardsPageState extends State<PlatformStandardsPage> {
  final _service = PlatformConfigurationService();
  Map<String, dynamic> _home = {};
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  String? _error;
  bool _offline = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      _offline = !await ConnectivityService().hasNetwork;
      if (widget.section == PlatformStandardsSection.home) {
        _home = await _service.home();
      } else if (widget.section == PlatformStandardsSection.assistance) {
        _items = await _service.organizations();
      } else {
        _items = await _service.history();
      }
    } catch (_) {
      _error = 'Impossible de charger les données. Vérifiez la connexion.';
    }
    if (mounted) setState(() => _loading = false);
  }

  String get _title => switch (widget.section) {
    PlatformStandardsSection.home => 'Standards & Référentiels',
    PlatformStandardsSection.assistance => 'Assistance aux organisations',
    PlatformStandardsSection.history => 'Historique',
  };

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(_title)),
    body: RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_offline && !_loading)
            Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppTheme.orange.withValues(alpha: .1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Row(
                children: [
                  Icon(Icons.cloud_off_outlined, color: AppTheme.orange),
                  SizedBox(width: 9),
                  Expanded(
                    child: Text(
                      'Hors connexion — données de la dernière synchronisation.',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                  ),
                ],
              ),
            ),
          if (_loading)
            const Padding(
              padding: EdgeInsets.all(48),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_error != null)
            _Error(message: _error!, retry: _load)
          else if (widget.section == PlatformStandardsSection.home)
            _Home(data: _home)
          else if (widget.section == PlatformStandardsSection.assistance)
            _Assistance(
              items: _items,
              onSearch: (value) async {
                _items = await _service.organizations(search: value);
                if (mounted) setState(() {});
              },
            )
          else
            _History(items: _items),
        ],
      ),
    ),
  );
}

class _Home extends StatelessWidget {
  const _Home({required this.data});
  final Map<String, dynamic> data;
  @override
  Widget build(BuildContext context) {
    final stats = Map<String, dynamic>.from(data['stats'] as Map? ?? {});
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Standards & Référentiels',
          style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 4),
        Text(
          'Assistance et traçabilité des configurations.',
          style: TextStyle(color: AppTheme.muted),
        ),
        const SizedBox(height: 18),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          childAspectRatio: 1.35,
          children: [
            AppKpiCard(
              label: 'Organisations',
              value: '${stats['organizations'] ?? 0}',
              icon: Icons.domain_outlined,
              tone: AppKpiTone.blue,
            ),
            AppKpiCard(
              label: 'Actives',
              value: '${stats['active'] ?? 0}',
              icon: Icons.verified_user_outlined,
              tone: AppKpiTone.green,
            ),
            AppKpiCard(
              label: 'Interventions',
              value: '${stats['interventions'] ?? 0}',
              icon: Icons.history,
              tone: AppKpiTone.blue,
            ),
            AppKpiCard(
              label: 'En attente',
              value: '${stats['pending'] ?? 0}',
              icon: Icons.sync,
              tone: AppKpiTone.orange,
            ),
          ],
        ),
        const SizedBox(height: 14),
        _ActionCard(
          icon: Icons.domain_outlined,
          title: 'Assistance aux organisations',
          text: 'Configurez uniquement les paramètres de plateforme autorisés.',
          color: AppTheme.orange,
          onTap: () => context.go('/standards/assistance'),
        ),
        _ActionCard(
          icon: Icons.history,
          title: 'Historique des interventions',
          text: 'Consultez les versions, auteurs et états de synchronisation.',
          color: AppTheme.blue,
          onTap: () => context.go('/standards/history'),
        ),
      ],
    );
  }
}

class _ActionCard extends StatelessWidget {
  const _ActionCard({
    required this.icon,
    required this.title,
    required this.text,
    required this.color,
    required this.onTap,
  });
  final IconData icon;
  final String title;
  final String text;
  final Color color;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: onTap,
      child: AppCard(
        child: Row(
          children: [
            CircleAvatar(
              backgroundColor: color.withValues(alpha: .1),
              foregroundColor: color,
              child: Icon(icon),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    text,
                    style: TextStyle(color: AppTheme.muted, fontSize: 12),
                  ),
                ],
              ),
            ),
            Icon(Icons.arrow_forward, color: color),
          ],
        ),
      ),
    ),
  );
}

class _Assistance extends StatelessWidget {
  const _Assistance({required this.items, required this.onSearch});
  final List<Map<String, dynamic>> items;
  final ValueChanged<String> onSearch;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        'Périmètre protégé',
        style: TextStyle(fontWeight: FontWeight.w800, color: AppTheme.purple),
      ),
      Text(
        'Aucune donnée métier n’est accessible.',
        style: TextStyle(color: AppTheme.muted, fontSize: 12),
      ),
      const SizedBox(height: 14),
      AppSearchField(
        hint: 'Rechercher une organisation…',
        onSubmitted: onSearch,
      ),
      const SizedBox(height: 14),
      if (items.isEmpty)
        const AppEmptyState(
          icon: Icons.domain_disabled_outlined,
          title: 'Aucune organisation',
          description: 'Aucune organisation ne correspond à votre recherche.',
        )
      else
        ...items.map(
          (org) => Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: AppCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          '${org['name']}',
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                      ),
                      _Status(active: org['is_active'] == true),
                    ],
                  ),
                  const SizedBox(height: 5),
                  Text(
                    '${org['access_type'] == 'multi_country' ? 'Multipays' : 'Unipays'} · ${org['code']}',
                    style: TextStyle(color: AppTheme.muted, fontSize: 12),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          'Configuration ${org['configuration_version'] == null ? 'Aucune' : 'v${org['configuration_version']}'}',
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 12,
                          ),
                        ),
                      ),
                      FilledButton(
                        onPressed: () =>
                            context.push('/standards/assistance/${org['id']}'),
                        child: const Text('Accompagner'),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
    ],
  );
}

class _History extends StatelessWidget {
  const _History({required this.items});
  final List<Map<String, dynamic>> items;
  @override
  Widget build(BuildContext context) => Column(
    children: [
      if (items.isEmpty)
        const AppEmptyState(
          icon: Icons.history,
          title: 'Aucune intervention',
          description: 'Les interventions apparaîtront ici.',
        )
      else
        ...items.map(
          (item) => Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: InkWell(
              borderRadius: BorderRadius.circular(AppRadius.lg),
              onTap: () => _showDetail(context, item),
              child: AppCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          '${item['organization'] ?? 'Organisation'}',
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                      ),
                      _SyncStatus(status: '${item['status']}'),
                    ],
                  ),
                  const SizedBox(height: 5),
                  Text(
                    '${item['category_label']} · ${item['version']}',
                    style: TextStyle(color: AppTheme.muted, fontSize: 12),
                  ),
                  const SizedBox(height: 7),
                  Text(
                    '${item['author']} · ${_date(item['created_at'])}',
                    style: const TextStyle(fontSize: 11),
                  ),
                ],
              ),
            ),
            ),
          ),
        ),
    ],
  );

  /// Date lisible (heure locale du téléphone) : « 08/10/2026 à 09:48 ».
  static String _date(Object? value) {
    final date = DateTime.tryParse('${value ?? ''}')?.toLocal();
    if (date == null) return '';
    String two(int number) => number.toString().padLeft(2, '0');
    return '${two(date.day)}/${two(date.month)}/${date.year} à ${two(date.hour)}:${two(date.minute)}';
  }

  /// Détail d'une intervention : paramètres modifiés, avant / après.
  static void _showDetail(BuildContext context, Map<String, dynamic> item) {
    final changes = (item['changes'] as List? ?? const [])
        .whereType<Map>()
        .toList(growable: false);
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${item['organization'] ?? 'Organisation'}',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 4),
              Text(
                '${item['category_label']} · ${item['version']} · ${item['author']} · ${_date(item['created_at'])}',
                style: TextStyle(color: AppTheme.muted),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  _SyncStatus(status: '${item['status']}'),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      item['status'] == 'synced'
                          ? 'Reçue par les appareils de l’organisation.'
                          : 'Les appareils de l’organisation la recevront à leur prochaine synchronisation.',
                      style: const TextStyle(fontSize: 13),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              if (changes.isEmpty)
                const Text('Aucun paramètre modifié.')
              else
                for (final change in changes) ...[
                  Text(
                    '${change['label'] ?? change['key']}',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 2),
                  Text('Avant : ${change['old'] ?? 'Non défini'}'),
                  Text('Après : ${change['new'] ?? 'Non défini'}'),
                  const SizedBox(height: 12),
                ],
            ],
          ),
        ),
      ),
    );
  }
}

class _Status extends StatelessWidget {
  const _Status({required this.active});
  final bool active;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: (active ? AppTheme.green : AppTheme.gray).withValues(alpha: .1),
      borderRadius: BorderRadius.circular(8),
    ),
    child: Text(
      active ? 'Active' : 'Inactive',
      style: TextStyle(
        color: active ? AppTheme.green : AppTheme.gray,
        fontWeight: FontWeight.w700,
        fontSize: 11,
      ),
    ),
  );
}

class _SyncStatus extends StatelessWidget {
  const _SyncStatus({required this.status});
  final String status;
  @override
  Widget build(BuildContext context) {
    final color = status == 'synced'
        ? AppTheme.green
        : status == 'error'
        ? AppColors.dangerText
        : AppColors.infoText;
    final label = status == 'synced'
        ? 'Synchronisée'
        : status == 'error'
        ? 'Échec'
        : 'En attente';
    return Container(
      padding: const EdgeInsets.all(6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .1),
        borderRadius: BorderRadius.circular(7),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 10,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _Error extends StatelessWidget {
  const _Error({required this.message, required this.retry});
  final String message;
  final VoidCallback retry;
  @override
  Widget build(BuildContext context) => AppCard(
    child: Column(
      children: [
        Icon(Icons.cloud_off, color: AppTheme.red),
        const SizedBox(height: 8),
        Text(message),
        TextButton(onPressed: retry, child: const Text('Réessayer')),
      ],
    ),
  );
}
