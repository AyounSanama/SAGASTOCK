import 'package:flutter/material.dart';
import '../../../core/theme/app_theme.dart';
import '../../auth/data/auth_service.dart';
import '../data/operational_report_service.dart';

class OperationalReportsPage extends StatefulWidget {
  const OperationalReportsPage({super.key});
  @override
  State<OperationalReportsPage> createState() => _OperationalReportsPageState();
}

class _OperationalReportsPageState extends State<OperationalReportsPage> {
  final _service = OperationalReportService();
  Map<String, dynamic> _report = {};
  bool _loading = true;
  String? _error;

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
      final user = await AuthService().cachedUser() ?? {};
      final organization = user['organization'] as Map?;
      final id = '${user['organization_id'] ?? organization?['id'] ?? ''}';
      if (id.isEmpty) throw StateError('missing organization');
      _report = await _service.load(id);
    } catch (_) {
      _error = 'Impossible de charger le rapport opérationnel.';
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Map<String, dynamic> _section(String key) =>
      (_report[key] as Map?)?.cast<String, dynamic>() ?? {};

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Rapports opérationnels')),
    body: RefreshIndicator(
      onRefresh: _load,
      child: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (_error != null)
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Text(_error!),
                    ),
                  )
                else ...[
                  _ReportCard(
                    title: 'Stock',
                    icon: Icons.inventory_2_outlined,
                    color: AppTheme.blue,
                    values: {
                      'Quantité disponible': _section(
                        'stock',
                      )['available_quantity'],
                      'Quantité réservée': _section(
                        'stock',
                      )['reserved_quantity'],
                      'Lots disponibles': _section('stock')['lots'],
                    },
                  ),
                  _ReportCard(
                    title: 'Réceptions',
                    icon: Icons.move_to_inbox_outlined,
                    color: AppTheme.orange,
                    values: {
                      'Total': _section('receipts')['total'],
                      'Validées': _section('receipts')['validated'],
                    },
                  ),
                  _ReportCard(
                    title: 'Inventaires',
                    icon: Icons.fact_check_outlined,
                    color: AppTheme.purple,
                    values: {
                      'Total': _section('inventories')['total'],
                      'Clôturés': _section('inventories')['validated'],
                      'Ouverts': _section('inventories')['open'],
                    },
                  ),
                  _ReportCard(
                    title: 'Propositions de commande',
                    icon: Icons.shopping_cart_outlined,
                    color: AppTheme.green,
                    values: {
                      'Total': _section('orders')['total'],
                      'En cours': _section('orders')['pending'],
                    },
                  ),
                  _ReportCard(
                    title: 'Dispensations',
                    icon: Icons.medication_outlined,
                    color: AppTheme.orange,
                    values: {
                      'Total': _section('dispensations')['total'],
                      'Ce mois': _section('dispensations')['this_month'],
                    },
                  ),
                ],
              ],
            ),
    ),
  );
}

class _ReportCard extends StatelessWidget {
  const _ReportCard({
    required this.title,
    required this.icon,
    required this.color,
    required this.values,
  });
  final String title;
  final IconData icon;
  final Color color;
  final Map<String, dynamic> values;
  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(bottom: 12),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                backgroundColor: color.withValues(alpha: .1),
                foregroundColor: color,
                child: Icon(icon),
              ),
              const SizedBox(width: 12),
              Text(
                title,
                style: const TextStyle(
                  fontWeight: FontWeight.w900,
                  fontSize: 17,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          for (final entry in values.entries)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 5),
              child: Row(
                children: [
                  Expanded(child: Text(entry.key)),
                  Text(
                    '${entry.value ?? 0}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ],
              ),
            ),
        ],
      ),
    ),
  );
}
