import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../../core/widgets/app_navigation_drawer.dart';
import '../data/inventory_service.dart';

class InventoriesPage extends StatefulWidget {
  const InventoriesPage({super.key});
  @override
  State<InventoriesPage> createState() => _InventoriesPageState();
}

class _InventoriesPageState extends State<InventoriesPage> {
  final service = InventoryService();
  List<Map<String, dynamic>> organizations = [], inventories = [], sites = [];
  String? organizationId;
  bool loading = true;
  int pending = 0;
  String? error;
  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    try {
      organizations = await service.organizations();
      organizationId = organizations.isEmpty
          ? null
          : '${organizations.first['id']}';
      await _load();
    } catch (_) {
      error = 'Chargement impossible.';
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _load() async {
    if (organizationId == null) return;
    if (mounted) setState(() => loading = true);
    try {
      final r = await Future.wait([
        service.inventories(organizationId!),
        service.sites(organizationId!),
        service.pendingCount(),
      ]);
      inventories = r[0] as List<Map<String, dynamic>>;
      sites = r[1] as List<Map<String, dynamic>>;
      pending = r[2] as int;
      error = null;
    } on DioException catch (e) {
      error = e.response == null
          ? 'Mode hors connexion : données locales affichées.'
          : 'Accès impossible.';
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  void msg(String text) =>
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  Future<void> action(Future<bool> Function() fn, String success) async {
    try {
      final online = await fn();
      msg(online ? success : 'Action enregistrée hors connexion.');
      await _load();
    } on DioException catch (e) {
      msg(
        e.response?.data is Map
            ? '${e.response?.data['message'] ?? 'Opération impossible.'}'
            : 'Opération impossible.',
      );
    }
  }

  Future<void> create() async {
    final data = await showAppFormSheet<Map<String, dynamic>>(
      context: context,
      title: 'Nouvel inventaire',
      description: 'Choisissez le site et la période de comptage.',
      builder: (_) => InventoryCreateForm(sites: sites),
    );
    if (data != null && organizationId != null)
      await action(
        () => service.create(organizationId!, data),
        'Inventaire créé.',
      );
  }

  Future<void> count(
    Map<String, dynamic> inv,
    Map<String, dynamic> line,
  ) async {
    final data = await showAppFormSheet<Map<String, dynamic>>(
      context: context,
      title: 'Comptage physique',
      description:
          '${line['product']?['name']} · lot ${line['batch']?['batch_number']}',
      builder: (_) => InventoryCountForm(line: line),
    );
    if (data != null)
      await action(
        () => service.count(organizationId!, '${inv['id']}', [data]),
        'Comptage enregistré.',
      );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    drawer: const AppNavigationDrawer(),
    appBar: AppBar(title: const Text('Inventaires physiques')),
    floatingActionButton: AppFab(
      tooltip: 'Nouvel inventaire',
      onPressed: organizationId == null ? null : create,
    ),
    body: Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: DropdownButtonFormField<String>(
            initialValue: organizationId,
            decoration: const InputDecoration(
              labelText: 'Organisation',
              prefixIcon: Icon(Icons.apartment_outlined),
            ),
            items: organizations
                .map(
                  (o) => DropdownMenuItem(
                    value: '${o['id']}',
                    child: Text('${o['name']}'),
                  ),
                )
                .toList(),
            onChanged: (v) {
              organizationId = v;
              _load();
            },
          ),
        ),
        if (pending > 0)
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 16),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: AppTheme.orangeSoft,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text('$pending action(s) en attente de synchronisation'),
          ),
        if (error != null)
          Padding(
            padding: const EdgeInsets.all(8),
            child: Text(error!, style: const TextStyle(color: AppTheme.red)),
          ),
        Expanded(
          child: loading
              ? const Center(child: CircularProgressIndicator())
              : inventories.isEmpty
              ? const Center(child: Text('Aucun inventaire'))
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: inventories.length,
                    itemBuilder: (_, i) => card(inventories[i]),
                  ),
                ),
        ),
      ],
    ),
  );
  Widget card(Map<String, dynamic> inv) {
    final lines = (inv['lines'] as List? ?? []).cast<Map<String, dynamic>>(),
        status = '${inv['status']}';
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${inv['reference']}',
                    style: const TextStyle(
                      fontWeight: FontWeight.w900,
                      fontSize: 17,
                    ),
                  ),
                ),
                Chip(label: Text(status)),
              ],
            ),
            Text(
              '${inv['site']?['name'] ?? ''} · ${inv['period_date']}',
              style: const TextStyle(color: AppTheme.muted),
            ),
            if (status == 'counting')
              for (final line in lines)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(
                    '${line['product']?['name']} · ${line['batch']?['batch_number']}',
                  ),
                  subtitle: Text(
                    'Théorique ${line['theoretical_quantity']} · Physique ${line['physical_quantity'] ?? 'à compter'}',
                  ),
                  trailing: AppIconButton.edit(
                    tooltip: 'Compter',
                    onPressed: () => count(inv, line),
                  ),
                ),
            const SizedBox(height: 8),
            if (status == 'draft')
              AppButton.primary(
                label: 'Démarrer et geler le stock',
                icon: Icons.lock_outline,
                expanded: true,
                onPressed: () => action(
                  () => service.start(organizationId!, '${inv['id']}'),
                  'Comptage démarré.',
                ),
              ),
            if (status == 'counting')
              AppButton.validate(
                label: 'Soumettre pour validation',
                expanded: true,
                onPressed: () => action(
                  () => service.submit(organizationId!, '${inv['id']}'),
                  'Inventaire soumis.',
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class InventoryCreateForm extends StatefulWidget {
  const InventoryCreateForm({super.key, required this.sites});
  final List<Map<String, dynamic>> sites;
  @override
  State<InventoryCreateForm> createState() => _InventoryCreateFormState();
}

class _InventoryCreateFormState extends State<InventoryCreateForm> {
  final key = GlobalKey<FormState>(),
      reference = TextEditingController(
        text: 'INV-${DateTime.now().millisecondsSinceEpoch}',
      ),
      period = TextEditingController(
        text: DateTime.now().toIso8601String().substring(0, 10),
      );
  String? site;
  String type = 'monthly';
  @override
  Widget build(BuildContext c) => _Body(
    keyForm: key,
    onSave: () {
      if (key.currentState!.validate())
        Navigator.pop(c, {
          'site_id': site,
          'reference': reference.text,
          'inventory_type': type,
          'period_date': period.text,
        });
    },
    children: [
      DropdownButtonFormField<String>(
        decoration: const InputDecoration(labelText: 'Site'),
        items: widget.sites
            .map(
              (s) => DropdownMenuItem(
                value: '${s['id']}',
                child: Text('${s['name']}'),
              ),
            )
            .toList(),
        onChanged: (v) => site = v,
        validator: (v) => v == null ? 'Sélection obligatoire.' : null,
      ),
      TextFormField(
        controller: reference,
        decoration: const InputDecoration(labelText: 'Référence'),
        validator: _required,
      ),
      DropdownButtonFormField<String>(
        initialValue: type,
        decoration: const InputDecoration(labelText: 'Type'),
        items: const [
          DropdownMenuItem(value: 'monthly', child: Text('Mensuel')),
          DropdownMenuItem(value: 'exceptional', child: Text('Exceptionnel')),
        ],
        onChanged: (v) => type = v ?? 'monthly',
      ),
      TextFormField(
        controller: period,
        decoration: const InputDecoration(labelText: 'Période (AAAA-MM-JJ)'),
        validator: _required,
      ),
    ],
  );
}

class InventoryCountForm extends StatefulWidget {
  const InventoryCountForm({super.key, required this.line});
  final Map<String, dynamic> line;
  @override
  State<InventoryCountForm> createState() => _InventoryCountFormState();
}

class _InventoryCountFormState extends State<InventoryCountForm> {
  final key = GlobalKey<FormState>(),
      quantity = TextEditingController(),
      justification = TextEditingController();
  @override
  Widget build(BuildContext c) => _Body(
    keyForm: key,
    onSave: () {
      if (!key.currentState!.validate()) return;
      final physical = double.parse(quantity.text.replaceAll(',', '.')),
          theoretical = double.parse('${widget.line['theoretical_quantity']}');
      if ((physical - theoretical).abs() > .0001 &&
          justification.text.trim().length < 5) {
        ScaffoldMessenger.of(c).showSnackBar(
          const SnackBar(content: Text('Justifiez précisément l’écart.')),
        );
        return;
      }
      Navigator.pop(c, {
        'id': widget.line['id'],
        'physical_quantity': physical,
        if (justification.text.isNotEmpty) 'justification': justification.text,
      });
    },
    children: [
      TextFormField(
        controller: quantity,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        decoration: const InputDecoration(labelText: 'Quantité physique'),
        validator: _required,
      ),
      TextFormField(
        controller: justification,
        minLines: 2,
        maxLines: 4,
        decoration: const InputDecoration(
          labelText: 'Justification en cas d’écart',
        ),
      ),
    ],
  );
}

class _Body extends StatelessWidget {
  const _Body({
    required this.keyForm,
    required this.children,
    required this.onSave,
  });
  final GlobalKey<FormState> keyForm;
  final List<Widget> children;
  final VoidCallback onSave;
  @override
  Widget build(BuildContext c) => SingleChildScrollView(
    padding: const EdgeInsets.all(20),
    child: Form(
      key: keyForm,
      child: Column(
        children: [
          for (final w in children) ...[w, const SizedBox(height: 12)],
          Row(
            children: [
              Expanded(
                child: AppButton.cancel(onPressed: () => Navigator.pop(c)),
              ),
              const SizedBox(width: 10),
              Expanded(child: AppButton.save(onPressed: onSave)),
            ],
          ),
        ],
      ),
    ),
  );
}

String? _required(String? v) =>
    v == null || v.trim().isEmpty ? 'Champ obligatoire.' : null;

abstract final class AppIconButton {
  static Widget edit({
    required String tooltip,
    required VoidCallback onPressed,
  }) => AppIconAction(
    icon: Icons.edit_outlined,
    tooltip: tooltip,
    color: AppActionColor.orange,
    onPressed: onPressed,
  );
}
