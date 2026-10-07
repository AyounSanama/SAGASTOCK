import 'package:flutter/material.dart';

import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../data/order_service.dart';

class OrdersPage extends StatefulWidget {
  const OrdersPage({super.key});
  @override
  State<OrdersPage> createState() => _OrdersPageState();
}

class _OrdersPageState extends State<OrdersPage> {
  final service = OrderService();
  bool loading = true;
  String? organizationId;
  List<Map<String, dynamic>> organizations = [], orders = [];
  Map<String, dynamic> options = {};
  int pending = 0;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    try {
      organizations = await service.organizations();
      organizationId ??= organizations.firstOrNull?['id']?.toString();
      if (organizationId != null) {
        final values = await Future.wait([
          service.orders(organizationId!),
          service.options(organizationId!),
          service.pendingCount(),
        ]);
        orders = values[0] as List<Map<String, dynamic>>;
        options = values[1] as Map<String, dynamic>;
        pending = values[2] as int;
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _create() async {
    final allSites = (options['sites'] as List? ?? [])
            .cast<Map<String, dynamic>>(),
        sites = (options['sites'] as List? ?? [])
            .cast<Map<String, dynamic>>()
            .where((site) => site['order_proposal_ready'] == true)
            .toList(),
        products = (options['products'] as List? ?? [])
            .cast<Map<String, dynamic>>();
    if (allSites.isEmpty || products.isEmpty) {
      _message('Ajoutez d’abord un point de dispensation et un produit actif.');
      return;
    }
    if (sites.isEmpty) {
      _message(
        'Clôturez et validez d’abord un inventaire pour ce point de dispensation.',
      );
      return;
    }
    String? site = sites.first['id']?.toString(),
        product = products.first['id']?.toString();
    final reference = TextEditingController(
          text: 'CMD-${DateTime.now().millisecondsSinceEpoch}',
        ),
        quantity = TextEditingController();
    final ok = await showAppFormSheet<bool>(
      context: context,
      title: 'Nouvelle commande',
      description: 'Saisissez les besoins puis enregistrez le brouillon.',
      builder: (context) => Padding(
        padding: EdgeInsets.fromLTRB(
          20,
          8,
          20,
          MediaQuery.viewInsetsOf(context).bottom + 24,
        ),
        child: StatefulBuilder(
          builder: (context, setSheet) => Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TextField(
                controller: reference,
                decoration: const InputDecoration(labelText: 'Référence'),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: site,
                decoration: const InputDecoration(labelText: 'Site demandeur'),
                items: sites
                    .map(
                      (s) => DropdownMenuItem(
                        value: s['id'].toString(),
                        child: Text('${s['name']}'),
                      ),
                    )
                    .toList(),
                onChanged: (v) => setSheet(() => site = v),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: product,
                decoration: const InputDecoration(labelText: 'Produit'),
                items: products
                    .map(
                      (p) => DropdownMenuItem(
                        value: p['id'].toString(),
                        child: Text('${p['name']}'),
                      ),
                    )
                    .toList(),
                onChanged: (v) => setSheet(() => product = v),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: quantity,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Quantité demandée',
                ),
              ),
              const SizedBox(height: 18),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: () => Navigator.pop(context, true),
                  icon: const Icon(Icons.save_outlined),
                  label: const Text('Enregistrer le brouillon'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
    if (ok == true && organizationId != null) {
      final online = await service.create(organizationId!, {
        'reference': reference.text,
        'requesting_site_id': site,
        'priority': 'normal',
        'required_approval_levels': 1,
        'lines': [
          {
            'product_id': product,
            'requested_quantity': double.tryParse(quantity.text) ?? 0,
          },
        ],
      });
      _message(
        online ? 'Commande créée.' : 'Commande enregistrée hors connexion.',
      );
      await _load();
    }
  }

  void _message(String text) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Commandes'),
      actions: [IconButton(onPressed: _load, icon: const Icon(Icons.sync))],
    ),
    floatingActionButton: FloatingActionButton(
      onPressed: _create,
      child: const Icon(Icons.add),
    ),
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (pending > 0)
                  Card(
                    color: AppColors.infoSurface,
                    child: ListTile(
                      leading: Icon(
                        Icons.cloud_off,
                        color: AppColors.infoText,
                      ),
                      title: Text('$pending opération(s) en attente'),
                      subtitle: const Text(
                        'Synchronisation automatique au retour du réseau.',
                      ),
                    ),
                  ),
                DropdownButtonFormField<String>(
                  initialValue: organizationId,
                  decoration: const InputDecoration(labelText: 'Organisation'),
                  items: organizations
                      .map(
                        (o) => DropdownMenuItem(
                          value: o['id'].toString(),
                          child: Text('${o['name']}'),
                        ),
                      )
                      .toList(),
                  onChanged: (v) {
                    organizationId = v;
                    _load();
                  },
                ),
                const SizedBox(height: 18),
                ...orders.map((order) {
                  final status = '${order['status']}',
                      site = order['requesting_site'] as Map?;
                  return Card(
                    child: ListTile(
                      leading: const CircleAvatar(
                        child: Icon(Icons.shopping_cart_outlined),
                      ),
                      title: Text('${order['reference']}'),
                      subtitle: Text(
                        '${site?['name'] ?? 'Site'} · ${_label(status)}',
                      ),
                      trailing: status == 'draft'
                          ? IconButton(
                              icon: const Icon(Icons.send_outlined),
                              onPressed: () async {
                                await service.submit(
                                  organizationId!,
                                  order['id'].toString(),
                                );
                                await _load();
                              },
                            )
                          : null,
                    ),
                  );
                }),
                if (orders.isEmpty)
                  const Padding(
                    padding: EdgeInsets.all(40),
                    child: Center(child: Text('Aucune commande enregistrée.')),
                  ),
              ],
            ),
          ),
  );
  String _label(String s) =>
      const {
        'draft': 'Brouillon',
        'submitted': 'En approbation',
        'approved': 'Approuvée',
        'rejected': 'Rejetée',
        'preparing': 'En préparation',
        'completed': 'Clôturée',
      }[s] ??
      s;
}
