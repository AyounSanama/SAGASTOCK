import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../data/stock_service.dart';

class StocksPage extends StatefulWidget {
  const StocksPage({super.key});

  @override
  State<StocksPage> createState() => _StocksPageState();
}

class _StocksPageState extends State<StocksPage> {
  final _service = StockService();
  List<Map<String, dynamic>> _organizations = [];
  List<Map<String, dynamic>> _balances = [];
  List<Map<String, dynamic>> _movements = [];
  String? _organizationId;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadOrganizations();
  }

  Future<void> _loadOrganizations() async {
    try {
      final organizations = await _service.organizations();
      if (!mounted) return;
      setState(() {
        _organizations = organizations;
        _organizationId = organizations.isEmpty
            ? null
            : organizations.first['id'] as String;
      });
      if (_organizationId != null) await _loadStocks();
    } on DioException catch (error) {
      if (mounted) {
        setState(
          () => _error = error.response?.statusCode == 403
              ? 'Vous n’avez pas la permission de consulter les stocks.'
              : 'Impossible de charger les stocks.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadStocks() async {
    final id = _organizationId;
    if (id == null) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        _service.balances(id),
        _service.movements(id),
      ]);
      if (mounted) {
        setState(() {
          _balances = results[0];
          _movements = results[1];
        });
      }
    } on DioException catch (error) {
      if (mounted) {
        setState(
          () => _error = error.response?.statusCode == 403
              ? 'Le module Stocks est désactivé ou votre rôle ne permet pas cet accès.'
              : 'Chargement impossible. Vérifiez la connexion au serveur.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final total = _balances.fold<double>(
      0,
      (sum, row) =>
          sum + (double.tryParse('${row['theoretical_quantity']}') ?? 0),
    );
    return Scaffold(
      appBar: AppBar(title: const Text('Stocks et mouvements')),
      body: RefreshIndicator(
        onRefresh: _loadStocks,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            DropdownButtonFormField<String>(
              initialValue: _organizationId,
              decoration: const InputDecoration(
                labelText: 'Organisation',
                prefixIcon: Icon(Icons.business_outlined),
              ),
              items: _organizations
                  .map(
                    (item) => DropdownMenuItem(
                      value: item['id'] as String,
                      child: Text(item['name'] as String),
                    ),
                  )
                  .toList(),
              onChanged: (value) {
                setState(() => _organizationId = value);
                _loadStocks();
              },
            ),
            const SizedBox(height: 16),
            if (_loading)
              const Center(child: CircularProgressIndicator())
            else if (_error != null)
              Card(
                color: Theme.of(context).colorScheme.errorContainer,
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text(_error!),
                ),
              )
            else ...[
              Row(
                children: [
                  Expanded(
                    child: _SummaryCard(
                      label: 'Lignes de stock',
                      value: '${_balances.length}',
                      icon: Icons.inventory_2_outlined,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _SummaryCard(
                      label: 'Quantité totale',
                      value: total.toStringAsFixed(2),
                      icon: Icons.stacked_bar_chart,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              Text(
                'Stocks par lot',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 8),
              if (_balances.isEmpty)
                const Card(
                  child: Padding(
                    padding: EdgeInsets.all(18),
                    child: Text(
                      'Aucun stock enregistré pour cette organisation.',
                    ),
                  ),
                )
              else
                for (final balance in _balances)
                  Card(
                    child: ListTile(
                      leading: const CircleAvatar(
                        child: Icon(Icons.medication_outlined),
                      ),
                      title: Text(
                        '${balance['product']?['name'] ?? 'Produit'}',
                      ),
                      subtitle: Text(
                        '${balance['site']?['name'] ?? 'Site'} · Lot ${balance['batch']?['batch_number'] ?? '—'}\n'
                        'Expiration : ${balance['batch']?['expires_on'] ?? '—'}',
                      ),
                      isThreeLine: true,
                      trailing: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(
                            '${balance['available_quantity']}',
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 17,
                            ),
                          ),
                          const Text('disponible'),
                        ],
                      ),
                    ),
                  ),
              const SizedBox(height: 20),
              Text(
                'Mouvements récents',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 8),
              for (final movement in _movements.take(20))
                Card(
                  child: ListTile(
                    leading: Icon(
                      '${movement['quantity']}'.startsWith('-')
                          ? Icons.arrow_upward
                          : Icons.arrow_downward,
                      color: '${movement['quantity']}'.startsWith('-')
                          ? Colors.red
                          : Colors.green,
                    ),
                    title: Text('${movement['product']?['name'] ?? 'Produit'}'),
                    subtitle: Text(
                      '${movement['movement_type']} · ${movement['site']?['name'] ?? 'Site'}',
                    ),
                    trailing: Text(
                      '${movement['quantity']}',
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            Icon(icon, color: Theme.of(context).colorScheme.primary),
            const SizedBox(height: 8),
            Text(value, style: Theme.of(context).textTheme.headlineSmall),
            Text(label, textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}
