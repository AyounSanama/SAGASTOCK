import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../../core/access/application_access.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../../core/widgets/app_navigation_drawer.dart';
import '../../auth/data/auth_service.dart';
import '../data/stock_service.dart';

class BatchesPage extends StatefulWidget {
  const BatchesPage({super.key});

  @override
  State<BatchesPage> createState() => _BatchesPageState();
}

class _BatchesPageState extends State<BatchesPage> {
  final _service = StockService();
  final _search = TextEditingController();
  List<Map<String, dynamic>> _organizations = [];
  List<Map<String, dynamic>> _batches = [];
  Map<String, dynamic>? _user;
  String? _organizationId;
  String _status = '';
  bool _loading = true;
  String? _error;

  bool get _canManage => ApplicationAccess.allows(_user, 'batches.manage');

  @override
  void initState() {
    super.initState();
    _initialize();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _initialize() async {
    try {
      _user = await AuthService().cachedUser();
      _organizations = await _service.organizations();
      _organizationId = _organizations.isEmpty
          ? null
          : '${_organizations.first['id']}';
      await _load();
    } catch (_) {
      if (mounted) setState(() => _error = 'Impossible de charger les lots.');
    }
  }

  Future<void> _load() async {
    if (_organizationId == null) {
      if (mounted) setState(() => _loading = false);
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      _batches = await _service.batches(
        _organizationId!,
        search: _search.text,
        status: _status,
      );
    } on DioException catch (error) {
      _error = error.response?.statusCode == 403
          ? 'Vous n’avez pas la permission de consulter les lots.'
          : 'Chargement des lots impossible.';
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openForm([Map<String, dynamic>? batch]) async {
    final options = await _service.batchOptions(_organizationId!);
    if (!mounted) return;
    final saved = await showAppFormSheet<bool>(
      context: context,
      title: batch == null ? 'Ajouter un lot' : 'Modifier le lot',
      description:
          'Renseignez les informations de traçabilité et la date d’expiration.',
      builder: (context) => _BatchForm(
        batch: batch,
        products: options['products']!,
        suppliers: options['suppliers']!,
        onSubmit:
            ({
              required productId,
              supplierId,
              required batchNumber,
              manufacturedOn,
              required expiresOn,
              unitCost,
              currency,
              origin,
              required status,
            }) => _service.saveBatch(
              _organizationId!,
              id: batch?['id']?.toString(),
              productId: productId,
              supplierId: supplierId,
              batchNumber: batchNumber,
              manufacturedOn: manufacturedOn,
              expiresOn: expiresOn,
              unitCost: unitCost,
              currency: currency,
              origin: origin,
              status: status,
            ),
      ),
    );
    if (saved == true) {
      await _load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              batch == null ? 'Lot ajouté avec succès.' : 'Lot mis à jour.',
            ),
          ),
        );
      }
    }
  }

  Future<void> _archiveOrRestore(Map<String, dynamic> batch) async {
    final archived = _status == 'archived';
    final confirmed = await showAppDialogAsFormSheet<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(archived ? 'Restaurer le lot ?' : 'Archiver le lot ?'),
        content: const Text(
          'Les mouvements et l’historique resteront conservés. Aucune donnée ne sera supprimée définitivement.',
        ),
        actions: [
          AppButton.cancel(
            compact: true,
            onPressed: () => Navigator.pop(context, false),
          ),
          AppButton.archive(
            compact: true,
            label: archived ? 'Restaurer' : 'Archiver',
            onPressed: () => Navigator.pop(context, true),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    archived
        ? await _service.restoreBatch(_organizationId!, '${batch['id']}')
        : await _service.archiveBatch(_organizationId!, '${batch['id']}');
    await _load();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    drawer: const AppNavigationDrawer(),
    appBar: AppBar(title: const Text('Lots pharmaceutiques')),
    floatingActionButton: _canManage && _status != 'archived'
        ? AppFab(onPressed: _openForm, tooltip: 'Ajouter un lot')
        : null,
    body: RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 18, 16, 100),
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
                    value: '${item['id']}',
                    child: Text('${item['name']}'),
                  ),
                )
                .toList(),
            onChanged: (value) {
              setState(() => _organizationId = value);
              _load();
            },
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _search,
            decoration: InputDecoration(
              labelText: 'Rechercher un lot ou un produit',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: IconButton(
                onPressed: _load,
                icon: const Icon(Icons.arrow_forward),
              ),
            ),
            onSubmitted: (_) => _load(),
          ),
          const SizedBox(height: 14),
          DropdownButtonFormField<String>(
            initialValue: _status,
            decoration: const InputDecoration(labelText: 'Statut'),
            items: const [
              DropdownMenuItem(value: '', child: Text('Tous les lots actifs')),
              DropdownMenuItem(value: 'available', child: Text('Disponibles')),
              DropdownMenuItem(
                value: 'quarantine',
                child: Text('En quarantaine'),
              ),
              DropdownMenuItem(value: 'expired', child: Text('Périmés')),
              DropdownMenuItem(value: 'destroyed', child: Text('Détruits')),
              DropdownMenuItem(value: 'archived', child: Text('Archivés')),
            ],
            onChanged: (value) {
              _status = value ?? '';
              _load();
            },
          ),
          const SizedBox(height: 18),
          if (_loading)
            const Center(
              child: Padding(
                padding: EdgeInsets.all(32),
                child: CircularProgressIndicator(),
              ),
            )
          else if (_error != null)
            _EmptyState(icon: Icons.error_outline, message: _error!)
          else if (_batches.isEmpty)
            const _EmptyState(
              icon: Icons.inventory_2_outlined,
              message: 'Aucun lot ne correspond aux critères.',
            )
          else
            ..._batches.map(_batchCard),
        ],
      ),
    ),
  );

  Widget _batchCard(Map<String, dynamic> batch) {
    final expiry = DateTime.tryParse('${batch['expires_on']}');
    final days = expiry?.difference(DateTime.now()).inDays;
    final urgent = days != null && days <= 90;
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: urgent
                      ? Theme.of(context).colorScheme.errorContainer
                      : null,
                  child: Icon(
                    Icons.inventory_2_outlined,
                    color: urgent ? Theme.of(context).colorScheme.error : null,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${batch['product']?['name'] ?? 'Produit'}',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      Text('Lot ${batch['batch_number']}'),
                    ],
                  ),
                ),
                Chip(label: Text('${batch['status']}')),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              'Expiration : ${batch['expires_on']} • Fournisseur : ${batch['supplier']?['name'] ?? 'Non renseigné'}',
              style: TextStyle(
                color: urgent ? Theme.of(context).colorScheme.error : null,
                fontWeight: urgent ? FontWeight.w700 : null,
              ),
            ),
            if (_canManage) ...[
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  if (_status != 'archived')
                    AppButton.edit(
                      compact: true,
                      onPressed: () => _openForm(batch),
                    ),
                  AppButton.archive(
                    compact: true,
                    label: _status == 'archived' ? 'Restaurer' : 'Archiver',
                    onPressed: () => _archiveOrRestore(batch),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

typedef _BatchSubmit =
    Future<void> Function({
      required String productId,
      String? supplierId,
      required String batchNumber,
      DateTime? manufacturedOn,
      required DateTime expiresOn,
      double? unitCost,
      String? currency,
      String? origin,
      required String status,
    });

class _BatchForm extends StatefulWidget {
  const _BatchForm({
    required this.batch,
    required this.products,
    required this.suppliers,
    required this.onSubmit,
  });
  final Map<String, dynamic>? batch;
  final List<Map<String, dynamic>> products;
  final List<Map<String, dynamic>> suppliers;
  final _BatchSubmit onSubmit;
  @override
  State<_BatchForm> createState() => _BatchFormState();
}

class _BatchFormState extends State<_BatchForm> {
  final _key = GlobalKey<FormState>();
  late final TextEditingController _number;
  late final TextEditingController _cost;
  late final TextEditingController _currency;
  late final TextEditingController _origin;
  String? _productId;
  String? _supplierId;
  DateTime? _manufacturedOn;
  DateTime? _expiresOn;
  String _status = 'available';
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final batch = widget.batch;
    _number = TextEditingController(text: batch?['batch_number']?.toString());
    _cost = TextEditingController(text: batch?['unit_cost']?.toString());
    _currency = TextEditingController(text: batch?['currency']?.toString());
    _origin = TextEditingController(text: batch?['origin']?.toString());
    _productId = batch?['product_id']?.toString();
    _supplierId = batch?['supplier_id']?.toString();
    _manufacturedOn = DateTime.tryParse('${batch?['manufactured_on'] ?? ''}');
    _expiresOn = DateTime.tryParse('${batch?['expires_on'] ?? ''}');
    _status = batch?['status']?.toString() ?? 'available';
  }

  @override
  void dispose() {
    _number.dispose();
    _cost.dispose();
    _currency.dispose();
    _origin.dispose();
    super.dispose();
  }

  Future<void> _pickDate(bool expiry) async {
    final value = await showDatePicker(
      context: context,
      initialDate: expiry
          ? (_expiresOn ?? DateTime.now().add(const Duration(days: 365)))
          : (_manufacturedOn ?? DateTime.now()),
      firstDate: DateTime(2000),
      lastDate: DateTime.now().add(const Duration(days: 3650)),
    );
    if (value != null) {
      setState(() => expiry ? _expiresOn = value : _manufacturedOn = value);
    }
  }

  Future<void> _save() async {
    if (!(_key.currentState?.validate() ?? false)) return;
    if (_expiresOn == null) {
      setState(() => _error = 'La date d’expiration est obligatoire.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.onSubmit(
        productId: _productId!,
        supplierId: _supplierId,
        batchNumber: _number.text.trim(),
        manufacturedOn: _manufacturedOn,
        expiresOn: _expiresOn!,
        unitCost: double.tryParse(_cost.text.replaceAll(',', '.')),
        currency: _currency.text,
        origin: _origin.text,
        status: _status,
      );
      if (mounted) Navigator.pop(context, true);
    } on DioException catch (error) {
      setState(() {
        _saving = false;
        _error = error.response?.statusCode == 422
            ? 'Vérifiez l’unicité du lot et la cohérence des dates.'
            : 'Enregistrement impossible.';
      });
    }
  }

  String _date(DateTime? value) => value == null
      ? 'Sélectionner une date'
      : value.toIso8601String().split('T').first;

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    padding: const EdgeInsets.all(20),
    child: Form(
      key: _key,
      child: Column(
        children: [
          DropdownButtonFormField<String>(
            initialValue: _productId,
            decoration: const InputDecoration(labelText: 'Produit médical *'),
            items: widget.products
                .map(
                  (item) => DropdownMenuItem(
                    value: '${item['id']}',
                    child: Text('${item['name']}'),
                  ),
                )
                .toList(),
            onChanged: (value) => _productId = value,
            validator: (value) =>
                value == null ? 'Sélectionnez un produit.' : null,
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _number,
            decoration: const InputDecoration(labelText: 'Numéro de lot *'),
            validator: (value) => value == null || value.trim().isEmpty
                ? 'Champ obligatoire.'
                : null,
          ),
          const SizedBox(height: 14),
          DropdownButtonFormField<String>(
            initialValue: _supplierId,
            decoration: const InputDecoration(labelText: 'Fournisseur'),
            items: [
              const DropdownMenuItem<String>(
                value: null,
                child: Text('Non renseigné'),
              ),
              ...widget.suppliers.map(
                (item) => DropdownMenuItem(
                  value: '${item['id']}',
                  child: Text('${item['name']}'),
                ),
              ),
            ],
            onChanged: (value) => _supplierId = value,
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _pickDate(false),
                  icon: const Icon(Icons.calendar_month_outlined),
                  label: Text('Fabrication : ${_date(_manufacturedOn)}'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _pickDate(true),
                  icon: const Icon(Icons.event_busy_outlined),
                  label: Text('Expiration : ${_date(_expiresOn)}'),
                ),
              ),
            ],
          ),
          if (_expiresOn == null)
            const Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'La date d’expiration est obligatoire.',
                style: TextStyle(color: Colors.red),
              ),
            ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: TextFormField(
                  controller: _cost,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: const InputDecoration(labelText: 'Coût unitaire'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: TextFormField(
                  controller: _currency,
                  maxLength: 3,
                  textCapitalization: TextCapitalization.characters,
                  decoration: const InputDecoration(labelText: 'Devise'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _origin,
            decoration: const InputDecoration(labelText: 'Origine'),
          ),
          const SizedBox(height: 14),
          DropdownButtonFormField<String>(
            initialValue: _status,
            decoration: const InputDecoration(labelText: 'Statut'),
            items: const [
              DropdownMenuItem(value: 'available', child: Text('Disponible')),
              DropdownMenuItem(value: 'quarantine', child: Text('Quarantaine')),
              DropdownMenuItem(value: 'expired', child: Text('Périmé')),
              DropdownMenuItem(value: 'destroyed', child: Text('Détruit')),
            ],
            onChanged: (value) => _status = value ?? _status,
          ),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(
              _error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ],
          const SizedBox(height: 20),
          Row(
            children: [
              Expanded(
                child: AppButton.cancel(
                  onPressed: _saving ? null : () => Navigator.pop(context),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: AppButton.save(loading: _saving, onPressed: _save),
              ),
            ],
          ),
        ],
      ),
    ),
  );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.icon, required this.message});
  final IconData icon;
  final String message;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        children: [
          Icon(icon, size: 40),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
        ],
      ),
    ),
  );
}
