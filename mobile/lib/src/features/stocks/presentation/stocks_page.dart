import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/widgets/app_navigation_drawer.dart';
import '../../../core/localization/app_terms.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../../core/access/application_access.dart';
import '../../auth/data/auth_service.dart';
import '../data/stock_service.dart';

class StocksPage extends StatefulWidget {
  const StocksPage({super.key});

  @override
  State<StocksPage> createState() => _StocksPageState();
}

class _StocksPageState extends State<StocksPage> {
  final _service = StockService();
  final _search = TextEditingController();
  List<Map<String, dynamic>> _organizations = [];
  List<Map<String, dynamic>> _balances = [];
  List<Map<String, dynamic>> _movements = [];
  Map<String, dynamic>? _user;
  String? _organizationId;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadOrganizations();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _loadOrganizations() async {
    try {
      _user = await AuthService().cachedUser();
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

  bool get _canAdjust => ApplicationAccess.allows(_user, 'stocks.adjust');

  Future<void> _compensate(Map<String, dynamic> movement) async {
    final reason = TextEditingController();
    final key = GlobalKey<FormState>();
    var saving = false;
    final saved = await showAppFormSheet<bool>(
      context: context,
      title: 'Compenser le mouvement',
      description:
          'Le mouvement original restera visible. Une écriture inverse traçable sera créée.',
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: key,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextFormField(
                  controller: reason,
                  minLines: 3,
                  maxLines: 5,
                  decoration: const InputDecoration(
                    labelText: 'Motif de la correction *',
                    prefixIcon: Icon(Icons.history_outlined),
                  ),
                  validator: (value) => value == null || value.trim().length < 5
                      ? 'Saisissez un motif d’au moins 5 caractères.'
                      : null,
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Expanded(
                      child: AppButton.cancel(
                        onPressed: saving
                            ? null
                            : () => Navigator.pop(sheetContext, false),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: AppButton.validate(
                        label: 'Compenser',
                        loading: saving,
                        onPressed: () async {
                          if (!(key.currentState?.validate() ?? false)) return;
                          setSheetState(() => saving = true);
                          try {
                            await _service.compensate(
                              _organizationId!,
                              '${movement['id']}',
                              reason.text,
                            );
                            if (sheetContext.mounted) {
                              Navigator.pop(sheetContext, true);
                            }
                          } catch (_) {
                            setSheetState(() => saving = false);
                            if (sheetContext.mounted) {
                              ScaffoldMessenger.of(sheetContext).showSnackBar(
                                const SnackBar(
                                  content: Text(
                                    'Compensation impossible ou déjà effectuée.',
                                  ),
                                ),
                              );
                            }
                          }
                        },
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
    reason.dispose();
    if (saved == true) await _loadStocks();
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

  Future<void> _openMovementForm() async {
    final organizationId = _organizationId;
    if (organizationId == null) return;
    try {
      final data = await _service.movementOptions(organizationId);
      if (!mounted) return;
      final saved = await showAppFormSheet<bool>(
        context: context,
        title: 'Nouveau mouvement de stock',
        description:
            'Le registre est immuable. Toute erreur est corrigée par compensation.',
        builder: (context) => _MovementForm(
          sites: data['sites']!,
          batches: data['batches']!,
          onSubmit:
              ({
                required siteId,
                required batchId,
                required movementType,
                required quantity,
                reason,
              }) => _service.createMovement(
                organizationId,
                siteId: siteId,
                batchId: batchId,
                movementType: movementType,
                quantity: quantity,
                reason: reason,
              ),
        ),
      );
      if (saved == true && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Mouvement enregistré et stock recalculé.'),
          ),
        );
        await _loadStocks();
      }
    } on DioException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            error.response?.statusCode == 403
                ? 'Votre rôle ne permet pas d’enregistrer un mouvement.'
                : 'Impossible de préparer le formulaire de mouvement.',
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final query = _search.text.trim().toLowerCase();
    final visibleBalances = query.isEmpty
        ? _balances
        : _balances
              .where(
                (row) =>
                    '${row['product']?['name']}'.toLowerCase().contains(query) ||
                    '${row['product']?['code']}'.toLowerCase().contains(query) ||
                    '${row['batch']?['batch_number']}'.toLowerCase().contains(query) ||
                    '${row['site']?['name']}'.toLowerCase().contains(query),
              )
              .toList();
    final fefo = visibleBalances.where((row) {
      final available = double.tryParse('${row['available_quantity']}') ?? 0;
      final expiry = DateTime.tryParse('${row['batch']?['expires_on']}');
      return available > 0 && expiry != null && !expiry.isBefore(DateTime.now());
    }).toList()..sort((a, b) => '${a['batch']?['expires_on']}'.compareTo('${b['batch']?['expires_on']}'));
    final total = visibleBalances.fold<double>(
      0,
      (sum, row) =>
          sum + (double.tryParse('${row['theoretical_quantity']}') ?? 0),
    );
    return Scaffold(
      drawer: const AppNavigationDrawer(),
      appBar: AppBar(
        title: const Text(AppTerms.medicationStock),
        actions: [
          IconButton(
            tooltip: 'Gérer les lots',
            onPressed: () => context.go('/stocks/lots'),
            icon: const Icon(Icons.inventory_2_outlined),
          ),
          IconButton(
            tooltip: 'Nouveau mouvement',
            onPressed: _organizationId == null ? null : _openMovementForm,
            icon: const Icon(Icons.add_circle_outline),
          ),
        ],
      ),
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
            TextField(
              controller: _search,
              decoration: InputDecoration(
                labelText: 'Rechercher un produit, lot ou site',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _search.text.isEmpty
                    ? null
                    : IconButton(
                        tooltip: 'Effacer',
                        onPressed: () {
                          _search.clear();
                          setState(() {});
                        },
                        icon: const Icon(Icons.close),
                      ),
              ),
              onChanged: (_) => setState(() {}),
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
                      label: 'Lignes de stock de médicaments',
                      value: '${visibleBalances.length}',
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
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'Priorités FEFO',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                  ),
                  const Chip(label: Text('Premier expiré, premier sorti')),
                ],
              ),
              const SizedBox(height: 8),
              if (fefo.isEmpty)
                const Card(
                  child: Padding(
                    padding: EdgeInsets.all(18),
                    child: Text('Aucun lot disponible à prioriser.'),
                  ),
                )
              else
                for (final indexed in fefo.take(5).indexed)
                  Card(
                    child: ListTile(
                      leading: CircleAvatar(child: Text('#${indexed.$1 + 1}')),
                      title: Text('${indexed.$2['product']?['name'] ?? 'Produit'}'),
                      subtitle: Text(
                        'Lot ${indexed.$2['batch']?['batch_number']} • expiration ${indexed.$2['batch']?['expires_on']}\n${indexed.$2['site']?['name']}',
                      ),
                      isThreeLine: true,
                      trailing: Text(
                        '${indexed.$2['available_quantity']}',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ),
                  ),
              const SizedBox(height: 20),
              Text(
                'Stock de médicaments par lot',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 8),
              if (visibleBalances.isEmpty)
                const Card(
                  child: Padding(
                    padding: EdgeInsets.all(18),
                    child: Text(
                      'Aucun stock enregistré pour cette organisation.',
                    ),
                  ),
                )
              else
                for (final balance in visibleBalances)
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
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          '${movement['quantity']}',
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        if (_canAdjust &&
                            movement['compensates_movement_id'] == null)
                          IconButton(
                            tooltip: 'Compenser',
                            onPressed: () => _compensate(movement),
                            icon: const Icon(Icons.history_outlined),
                          ),
                      ],
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

typedef _MovementSubmit =
    Future<void> Function({
      required String siteId,
      required String batchId,
      required String movementType,
      required double quantity,
      String? reason,
    });

class _MovementForm extends StatefulWidget {
  const _MovementForm({
    required this.sites,
    required this.batches,
    required this.onSubmit,
  });

  final List<Map<String, dynamic>> sites;
  final List<Map<String, dynamic>> batches;
  final _MovementSubmit onSubmit;

  @override
  State<_MovementForm> createState() => _MovementFormState();
}

class _MovementFormState extends State<_MovementForm> {
  static const _types = {
    'opening': 'Stock initial',
    'receipt': 'Réception pharmaceutique',
    'entry': 'Autre entrée',
    'issue': 'Sortie de stock',
    'return_in': 'Retour entrant',
    'return_out': 'Retour sortant',
    'adjustment_in': 'Ajustement positif',
    'adjustment_out': 'Ajustement négatif',
    'loss': 'Perte',
    'damage': 'Détérioration',
    'expiry': 'Péremption',
    'quarantine': 'Mise en quarantaine',
    'quarantine_release': 'Sortie de quarantaine',
    'destruction': 'Destruction',
  };
  static const _reasonRequired = {
    'adjustment_in',
    'adjustment_out',
    'loss',
    'damage',
    'expiry',
    'quarantine',
    'destruction',
  };

  final _formKey = GlobalKey<FormState>();
  final _quantity = TextEditingController();
  final _reason = TextEditingController();
  String? _siteId;
  String? _batchId;
  String? _type;
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _quantity.dispose();
    _reason.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.onSubmit(
        siteId: _siteId!,
        batchId: _batchId!,
        movementType: _type!,
        quantity: double.parse(_quantity.text.replaceAll(',', '.')),
        reason: _reason.text,
      );
      if (mounted) Navigator.pop(context, true);
    } on DioException catch (error) {
      final errors = error.response?.data is Map
          ? (error.response?.data as Map)['errors']
          : null;
      setState(() {
        _error = errors is Map && errors.isNotEmpty
            ? '${(errors.values.first as List).first}'
            : 'Le mouvement n’a pas pu être enregistré. Vérifiez le stock disponible.';
        _saving = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 8),
              DropdownButtonFormField<String>(
                decoration: const InputDecoration(
                  labelText: 'Site de stockage / dispensation',
                  prefixIcon: Icon(Icons.location_on_outlined),
                ),
                items: widget.sites
                    .map(
                      (site) => DropdownMenuItem(
                        value: '${site['id']}',
                        child: Text(
                          '${site['name']} · ${site['health_facility']?['name']}',
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    )
                    .toList(),
                onChanged: (value) => _siteId = value,
                validator: (value) =>
                    value == null ? 'Sélectionnez un site.' : null,
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<String>(
                decoration: const InputDecoration(
                  labelText: 'Médicament / produit médical et lot',
                  prefixIcon: Icon(Icons.medication_outlined),
                ),
                items: widget.batches
                    .map(
                      (batch) => DropdownMenuItem(
                        value: '${batch['id']}',
                        child: Text(
                          '${batch['product']?['name']} · ${batch['batch_number']}',
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    )
                    .toList(),
                onChanged: (value) => _batchId = value,
                validator: (value) =>
                    value == null ? 'Sélectionnez un lot.' : null,
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<String>(
                decoration: const InputDecoration(
                  labelText: 'Nature du mouvement',
                  prefixIcon: Icon(Icons.swap_vert),
                ),
                items: _types.entries
                    .map(
                      (entry) => DropdownMenuItem(
                        value: entry.key,
                        child: Text(entry.value),
                      ),
                    )
                    .toList(),
                onChanged: (value) => setState(() => _type = value),
                validator: (value) =>
                    value == null ? 'Sélectionnez une opération.' : null,
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _quantity,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(
                  labelText: 'Quantité',
                  prefixIcon: Icon(Icons.numbers),
                ),
                validator: (value) {
                  final number = double.tryParse(
                    (value ?? '').replaceAll(',', '.'),
                  );
                  return number == null || number <= 0
                      ? 'Saisissez une quantité supérieure à zéro.'
                      : null;
                },
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _reason,
                minLines: 2,
                maxLines: 4,
                decoration: InputDecoration(
                  labelText: _reasonRequired.contains(_type)
                      ? 'Justification obligatoire'
                      : 'Justification',
                  prefixIcon: const Icon(Icons.notes),
                ),
                validator: (value) =>
                    _reasonRequired.contains(_type) &&
                        (value == null || value.trim().length < 5)
                    ? 'Indiquez une justification d’au moins 5 caractères.'
                    : null,
              ),
              if (_error != null) ...[
                const SizedBox(height: 14),
                Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ],
              const SizedBox(height: 22),
              Row(
                children: [
                  Expanded(
                    child: AppButton.cancel(
                      label: 'Annuler',
                      onPressed: _saving ? null : () => Navigator.pop(context),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: AppButton.validate(
                      label: 'Valider',
                      icon: Icons.check,
                      loading: _saving,
                      onPressed: _save,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
