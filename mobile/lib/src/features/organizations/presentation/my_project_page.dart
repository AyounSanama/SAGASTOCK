import 'package:flutter/material.dart';

import '../../../core/access/session_scope.dart';
import '../../../core/theme/app_theme.dart';
import '../../auth/data/auth_service.dart';
import '../data/organization_service.dart';
import 'project_medical_configuration.dart';

class MyProjectPage extends StatefulWidget {
  const MyProjectPage({super.key});

  @override
  State<MyProjectPage> createState() => _MyProjectPageState();
}

class _MyProjectPageState extends State<MyProjectPage> {
  final _service = OrganizationService();
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _project = const {};
  Map<String, dynamic>? _medical;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final user = await AuthService().cachedUser();
      final projectId = SessionScope.projectId(user);
      final organizationId = SessionScope.organizationId(user);
      if (projectId.isEmpty || organizationId.isEmpty) {
        throw StateError('missing project scope');
      }
      final response = await _service.project(
        projectId: projectId,
        organizationId: organizationId,
      );
      final value = response['project'];
      if (value is! Map) throw StateError('missing project');
      Map<String, dynamic>? medical;
      try {
        final result = await _service.medicalConfiguration(
          projectId: projectId,
          organizationId: organizationId,
        );
        if (result['configuration'] is Map) {
          medical = Map<String, dynamic>.from(result['configuration'] as Map);
        }
      } catch (_) {
        // La fiche projet reste affichée même si la configuration médicale
        // n'a jamais été synchronisée sur cet appareil.
      }
      if (mounted) {
        setState(() {
          _project = Map<String, dynamic>.from(value);
          _medical = medical;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _error =
              'Impossible de charger votre projet. Vérifiez la connexion puis réessayez.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Mon projet')),
    body: RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_loading)
            const Padding(
              padding: EdgeInsets.all(48),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_error != null)
            _ErrorCard(message: _error!, retry: _load)
          else ...[
            _ProjectHeader(project: _project),
            const SizedBox(height: 16),
            _InfoSection(project: _project),
            const SizedBox(height: 16),
            _RelationsSection(
              title: 'Bailleurs associés',
              values: _maps(_project['donors']),
            ),
            const SizedBox(height: 16),
            _RelationsSection(
              title: 'Programmes associés',
              values: _maps(_project['programs']),
            ),
            const SizedBox(height: 16),
            if (_medical != null) ...[
              MedicalConfigurationSection(configuration: _medical!),
              const SizedBox(height: 16),
            ],
            _SupplySection(project: _project),
          ],
        ],
      ),
    ),
  );

  static List<Map<String, dynamic>> _maps(dynamic value) => value is List
      ? value.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
      : const [];
}

class _ProjectHeader extends StatelessWidget {
  const _ProjectHeader({required this.project});
  final Map<String, dynamic> project;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Row(
        children: [
          CircleAvatar(
            backgroundColor: AppTheme.orangeSoft,
            child: Icon(Icons.business_center_outlined, color: AppTheme.orange),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${project['name'] ?? 'Projet'}',
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                Text(
                  'Code : ${project['code'] ?? '—'}',
                  style: TextStyle(color: AppTheme.muted),
                ),
              ],
            ),
          ),
          Chip(
            label: Text(
              '${project['status_label'] ?? (project['is_active'] == true ? 'Actif' : 'Inactif')}',
            ),
          ),
        ],
      ),
    ),
  );
}

class _InfoSection extends StatelessWidget {
  const _InfoSection({required this.project});
  final Map<String, dynamic> project;
  @override
  Widget build(BuildContext context) {
    final mission = project['mission'] as Map?;
    final country = mission?['country'] as Map?;
    final organization = project['organization'] as Map?;
    return _Section(
      title: 'Informations générales',
      children: [
        _Line('Organisation', '${organization?['name'] ?? '—'}'),
        _Line('Mise en œuvre', _orDash(project['implementing_partner'])),
        _Line('Coordination / Mission', '${mission?['name'] ?? '—'}'),
        _Line('Pays', '${country?['name'] ?? '—'}'),
        _Line('Date de début', '${project['starts_on'] ?? '—'}'),
        _Line('Date de fin', '${project['ends_on'] ?? '—'}'),
        _Line('Responsable', _orDash(project['responsible_name'])),
        _Line('Contact', _orDash(project['responsible_contact'])),
        _Line('Code bailleur', _orDash(project['donor_reference_code'])),
        _Line('Code programme MoH', _orDash(project['moh_program_code'])),
        if ('${project['description'] ?? ''}'.trim().isNotEmpty)
          _Line('Description', '${project['description']}'),
      ],
    );
  }
}

class _RelationsSection extends StatelessWidget {
  const _RelationsSection({required this.title, required this.values});
  final String title;
  final List<Map<String, dynamic>> values;
  @override
  Widget build(BuildContext context) => _Section(
    title: title,
    children: values.isEmpty
        ? [
            Text(
              'Aucun élément associé.',
              style: TextStyle(color: AppTheme.muted),
            ),
          ]
        : values
              .map(
                (item) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  dense: true,
                  leading: Icon(
                    Icons.check_circle_outline,
                    color: AppTheme.green,
                  ),
                  title: Text('${item['name'] ?? '—'}'),
                ),
              )
              .toList(),
  );
}

class _SupplySection extends StatelessWidget {
  const _SupplySection({required this.project});
  final Map<String, dynamic> project;
  @override
  Widget build(BuildContext context) => _Section(
    title: 'Paramètres d’approvisionnement',
    children: [
      _Line(
        'Périodicité de commande',
        _months(project['order_period_months']),
      ),
      _Line(
        'Délai de livraison',
        _months(project['delivery_lead_time_months']),
      ),
      _Line('Stock de sécurité', _months(project['safety_stock_months'])),
    ],
  );
  static String _months(dynamic value) =>
      value == null ? 'Non renseigné' : '$value mois';
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.children});
  final String title;
  final List<Widget> children;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 12),
          ...children,
        ],
      ),
    ),
  );
}

class _Line extends StatelessWidget {
  const _Line(this.label, this.value);
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 7),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Text(label, style: TextStyle(color: AppTheme.muted)),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );
}

class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.message, required this.retry});
  final String message;
  final Future<void> Function() retry;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          const Icon(Icons.error_outline, size: 38),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          TextButton(onPressed: retry, child: const Text('Réessayer')),
        ],
      ),
    ),
  );
}

String _orDash(Object? value) {
  final text = '${value ?? ''}'.trim();
  return text.isEmpty ? '—' : text;
}
