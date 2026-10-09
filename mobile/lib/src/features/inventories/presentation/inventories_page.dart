import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../core/access/application_access.dart';
import '../../../core/format/display_format.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../../core/widgets/app_navigation_drawer.dart';
import '../../auth/data/auth_service.dart';
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

  Map<String, dynamic>? _user;

  Future<void> _init() async {
    try {
      _user = await AuthService().cachedUser();
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
      ]);
      inventories = r[0];
      sites = r[1];
      // Lu après la liste, qui synchronise d'abord la file hors connexion.
      pending = await service.pendingCount();
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
    if (data != null && organizationId != null) {
      await action(
        () => service.create(organizationId!, data),
        'Inventaire créé.',
      );
    }
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
    if (data != null) {
      await action(
        () => service.count(organizationId!, '${inv['id']}', [data]),
        'Comptage enregistré.',
      );
    }
  }

  Future<void> openWorkflow(Map<String, dynamic> inventory) async {
    final result = await Navigator.of(context).push<_InventoryWorkflowResult>(
      MaterialPageRoute(
        builder: (_) => _InventoryWorkflow(inventory: inventory),
      ),
    );
    if (result == null || organizationId == null) return;
    await action(
      () async {
        await service.count(
          organizationId!,
          '${inventory['id']}',
          result.lines,
        );
        if (result.submit) {
          await service.submit(organizationId!, '${inventory['id']}');
        }
        return true;
      },
      result.submit
          ? 'Inventaire soumis.'
          : 'Brouillon de comptage enregistré.',
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    drawer: const AppNavigationDrawer(),
    appBar: AppBar(
      title: const Text('Inventaires physiques'),
      // Le menu « Inventaires & Commandes » arrive ici : accès aux commandes.
      actions: [
        if (ApplicationAccess.allows(_user, 'orders.view'))
          TextButton.icon(
            onPressed: () => context.go('/orders'),
            icon: const Icon(Icons.shopping_cart_outlined),
            label: const Text('Commandes'),
          ),
      ],
    ),
    floatingActionButton: AppFab(
      tooltip: 'Nouvel inventaire',
      // Inactif pendant le chargement : la liste des sites serait vide.
      onPressed: organizationId == null || loading ? null : create,
    ),
    body: Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: DropdownButtonFormField<String>(
            isExpanded: true,
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
            child: Text(error!, style: TextStyle(color: AppTheme.red)),
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
  static const _statusLabels = {
    'draft': 'Brouillon',
    'counting': 'Comptage en cours',
    'submitted': 'Soumis',
    'validated': 'Validé',
    'rejected': 'Rejeté',
  };

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
                Chip(label: Text(_statusLabels[status] ?? status)),
              ],
            ),
            Text(
              '${inv['site']?['name'] ?? ''} · ${formatDay(inv['period_date'])}',
              style: TextStyle(color: AppTheme.muted),
            ),
            if (status == 'counting')
              Text(
                '${lines.length} lot(s) à compter',
                style: TextStyle(color: AppTheme.muted),
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
              AppButton.primary(
                label: 'Continuer l’inventaire',
                expanded: true,
                onPressed: () => openWorkflow(inv),
              ),
          ],
        ),
      ),
    );
  }
}

class _InventoryWorkflowResult {
  const _InventoryWorkflowResult(this.lines, {required this.submit});
  final List<Map<String, dynamic>> lines;
  final bool submit;
}

class _InventoryWorkflow extends StatefulWidget {
  const _InventoryWorkflow({required this.inventory});
  final Map<String, dynamic> inventory;
  @override
  State<_InventoryWorkflow> createState() => _InventoryWorkflowState();
}

class _InventoryWorkflowState extends State<_InventoryWorkflow> {
  final _pages = PageController();
  late final List<Map<String, dynamic>> _lines;
  late final Map<String, TextEditingController> _quantities;
  late final Map<String, TextEditingController> _reasons;
  int _step = 0;

  @override
  void initState() {
    super.initState();
    _lines = (widget.inventory['lines'] as List? ?? [])
        .cast<Map<String, dynamic>>();
    _quantities = {
      for (final line in _lines)
        '${line['id']}': TextEditingController(
          text: line['physical_quantity'] == null
              ? ''
              : formatQuantity(line['physical_quantity']),
        ),
    };
    _reasons = {
      for (final line in _lines)
        '${line['id']}': TextEditingController(
          text: '${line['justification'] ?? ''}',
        ),
    };
  }

  @override
  void dispose() {
    _pages.dispose();
    for (final c in _quantities.values) {
      c.dispose();
    }
    for (final c in _reasons.values) {
      c.dispose();
    }
    super.dispose();
  }

  double? _physical(Map<String, dynamic> line) =>
      double.tryParse(_quantities['${line['id']}']!.text.replaceAll(',', '.'));
  double _theoretical(Map<String, dynamic> line) =>
      double.tryParse('${line['theoretical_quantity']}') ?? 0;
  bool _countComplete() =>
      _lines.isNotEmpty &&
      _lines.every((line) => _physical(line) != null && _physical(line)! >= 0);
  List<Map<String, dynamic>> _payload() => _lines
      .map(
        (line) => {
          'id': line['id'],
          'physical_quantity': _physical(line),
          if (_reasons['${line['id']}']!.text.trim().isNotEmpty)
            'justification': _reasons['${line['id']}']!.text.trim(),
        },
      )
      .toList();
  bool _valid(int step) {
    if (step < 1) return true;
    if (!_countComplete()) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Terminez le comptage de tous les lots avant de continuer.',
          ),
        ),
      );
      return false;
    }
    for (final line in _lines) {
      if ((_physical(line)! - _theoretical(line)).abs() > .0001 &&
          _reasons['${line['id']}']!.text.trim().length < 5) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Chaque écart doit être justifié.')),
        );
        return false;
      }
    }
    return true;
  }

  Future<void> _go(int target) async {
    if (target > _step && !_valid(_step)) return;
    await _pages.animateToPage(
      target,
      duration: const Duration(milliseconds: 240),
      curve: Curves.easeOut,
    );
  }

  /*  @override Widget build(BuildContext context) {
    const labels = ['Informations', 'Comptage', 'Écarts', 'Résumé'];
    return Scaffold(
      appBar: AppBar(title: const Text('Inventaire physique')),
      body: SafeArea(child: Column(children: [
        SingleChildScrollView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.all(10), child: Row(children: List.generate(4, (index) => Padding(padding: const EdgeInsets.only(right: 8), child: ChoiceChip(label: Text('${index + 1}. ${labels[index]}'), selected: _step == index, onSelected: (_) => _go(index))))),
        Expanded(child: PageView(controller: _pages, onPageChanged: (index) async { if (index > _step && !_valid(_step)) { await _pages.animateToPage(_step, duration: const Duration(milliseconds: 220), curve: Curves.easeOut); return; } setState(() => _step = index); }, children: [
          _page(Column(children: [
            _InventorySummaryLine('Référence', '${widget.inventory['reference']}'), _InventorySummaryLine('Date', formatDay(widget.inventory['period_date'])),
            _InventorySummaryLine('FOSA / Point', '${widget.inventory['site']?['name'] ?? ''}'), _InventorySummaryLine('Statut', 'Comptage en cours'),
          ])),
          _page(Column(children: [for (final line in _lines) Card(child: Padding(padding: const EdgeInsets.all(12), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${line['product']?['code'] ?? ''} · ${line['product']?['name'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w800)),
            Text('Lot ${line['batch']?['batch_number'] ?? ''} · Exp. ${formatDay(line['batch']?['expires_on'])}'),
            const SizedBox(height: 8), Text('Stock théorique : ${formatQuantity(line['theoretical_quantity'])}'), const SizedBox(height: 8),
            TextField(controller: _quantities['${line['id']}'], keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: const InputDecoration(labelText: 'Quantité comptée *')),
            const SizedBox(height: 8), TextField(controller: _reasons['${line['id']}'], decoration: const InputDecoration(labelText: 'Justification si écart')),
          ])))])),
          _page(Column(children: [for (final line in _lines) _differenceCard(line)])),
          _page(_summary()),
        ])),
        Padding(padding: const EdgeInsets.all(14), child: Row(children: [
          Expanded(child: AppButton.cancel(label: _step == 0 ? 'Retour' : 'Précédent', onPressed: () => _step == 0 ? Navigator.pop(context) : _go(_step - 1))), const SizedBox(width: 10),
          if (_step < 3) Expanded(child: AppButton.primary(label: 'Suivant', icon: Icons.arrow_forward, onPressed: () => _go(_step + 1))) else ...[
            Expanded(child: AppButton.save(label: 'Enregistrer brouillon', onPressed: () => Navigator.pop(context, _InventoryWorkflowResult(_payload(), submit: false)))), const SizedBox(width: 8),
            Expanded(child: AppButton.validate(label: 'Clôturer', onPressed: () { if (_valid(2)) Navigator.pop(context, _InventoryWorkflowResult(_payload(), submit: true)); })),
          ],
        ])),
      ])),
    );
  }
*/
  @override
  Widget build(BuildContext context) {
    const labels = ['Informations', 'Comptage', 'Écarts', 'Résumé'];
    return Scaffold(
      appBar: AppBar(title: const Text('Inventaire physique')),
      body: SafeArea(
        child: Column(
          children: [
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.all(10),
              child: Row(
                children: List.generate(
                  4,
                  (index) => Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text('${index + 1}. ${labels[index]}'),
                      selected: _step == index,
                      onSelected: (_) => _go(index),
                    ),
                  ),
                ),
              ),
            ),
            Expanded(
              child: PageView(
                controller: _pages,
                onPageChanged: (index) async {
                  if (index > _step && !_valid(_step)) {
                    await _pages.animateToPage(
                      _step,
                      duration: const Duration(milliseconds: 220),
                      curve: Curves.easeOut,
                    );
                    return;
                  }
                  setState(() => _step = index);
                },
                children: [
                  _page(
                    Column(
                      children: [
                        _InventorySummaryLine(
                          'Référence',
                          '${widget.inventory['reference']}',
                        ),
                        _InventorySummaryLine(
                          'Date',
                          formatDay(widget.inventory['period_date']),
                        ),
                        _InventorySummaryLine(
                          'FOSA / Point',
                          '${widget.inventory['site']?['name'] ?? ''}',
                        ),
                        const _InventorySummaryLine(
                          'Statut',
                          'Comptage en cours',
                        ),
                      ],
                    ),
                  ),
                  _page(
                    Column(
                      children: [for (final line in _lines) _countCard(line)],
                    ),
                  ),
                  _page(
                    Column(
                      children: [
                        for (final line in _lines) _differenceCard(line),
                      ],
                    ),
                  ),
                  _page(_summary()),
                ],
              ),
            ),
            // Trois boutons côte à côte coupaient leurs libellés : le
            // brouillon passe sur sa propre ligne à la dernière étape.
            if (_step == 3)
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 14, 14, 0),
                child: AppButton.save(
                  label: 'Enregistrer brouillon',
                  expanded: true,
                  onPressed: () => Navigator.pop(
                    context,
                    _InventoryWorkflowResult(_payload(), submit: false),
                  ),
                ),
              ),
            Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  Expanded(
                    child: AppButton.cancel(
                      label: _step == 0 ? 'Retour' : 'Précédent',
                      onPressed: () =>
                          _step == 0 ? Navigator.pop(context) : _go(_step - 1),
                    ),
                  ),
                  const SizedBox(width: 10),
                  if (_step < 3)
                    Expanded(
                      child: AppButton.primary(
                        label: 'Suivant',
                        icon: Icons.arrow_forward,
                        onPressed: () => _go(_step + 1),
                      ),
                    )
                  else ...[
                    Expanded(
                      child: AppButton.validate(
                        label: 'Soumettre',
                        onPressed: () {
                          if (_valid(2)) {
                            Navigator.pop(
                              context,
                              _InventoryWorkflowResult(
                                _payload(),
                                submit: true,
                              ),
                            );
                          }
                        },
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _page(Widget child) =>
      SingleChildScrollView(padding: const EdgeInsets.all(16), child: child);
  Widget _countCard(Map<String, dynamic> line) => Card(
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${line['product']?['code'] ?? ''} · ${line['product']?['name'] ?? ''}',
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
          Text(
            'Lot ${line['batch']?['batch_number'] ?? ''} · Exp. ${formatDay(line['batch']?['expires_on'])}',
          ),
          const SizedBox(height: 8),
          Text('Stock théorique : ${formatQuantity(line['theoretical_quantity'])}'),
          const SizedBox(height: 8),
          TextField(
            controller: _quantities['${line['id']}'],
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Quantité comptée *'),
          ),
          const SizedBox(height: 8),
          TextField(
            controller: _reasons['${line['id']}'],
            decoration: const InputDecoration(
              labelText: 'Justification si écart',
            ),
          ),
        ],
      ),
    ),
  );
  Widget _differenceCard(Map<String, dynamic> line) {
    final physical = _physical(line);
    final gap = physical == null ? null : physical - _theoretical(line);
    return Card(
      child: ListTile(
        title: Text(
          '${line['product']?['name']} · lot ${line['batch']?['batch_number']}',
        ),
        subtitle: Text(
          'Théorique ${formatQuantity(_theoretical(line))} · Compté ${formatQuantity(physical)}',
        ),
        trailing: Text(
          gap == null ? '—' : '${gap > 0 ? '+' : ''}${formatQuantity(gap)}',
          style: TextStyle(
            fontWeight: FontWeight.w900,
            color: gap == 0 ? AppColors.successText : AppColors.dangerText,
          ),
        ),
      ),
    );
  }

  Widget _summary() {
    var positive = 0, negative = 0, equal = 0;
    double theoretical = 0, physical = 0;
    for (final line in _lines) {
      final t = _theoretical(line), p = _physical(line) ?? 0, gap = p - t;
      theoretical += t;
      physical += p;
      if (gap > 0) {
        positive++;
      } else if (gap < 0) {
        negative++;
      } else {
        equal++;
      }
    }
    return Column(
      children: [
        _InventorySummaryLine('Date', formatDay(widget.inventory['period_date'])),
        _InventorySummaryLine('Produits / lots', '${_lines.length}'),
        _InventorySummaryLine(
          'Stock théorique',
          formatQuantity(theoretical),
        ),
        _InventorySummaryLine('Stock compté', formatQuantity(physical)),
        _InventorySummaryLine('Écarts positifs', '$positive'),
        _InventorySummaryLine('Écarts négatifs', '$negative'),
        _InventorySummaryLine('Sans écart', '$equal'),
      ],
    );
  }
}

class _InventorySummaryLine extends StatelessWidget {
  const _InventorySummaryLine(this.label, this.value);
  final String label, value;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 8),
    child: Row(
      children: [
        Expanded(
          child: Text(label, style: TextStyle(color: AppTheme.muted)),
        ),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w800)),
      ],
    ),
  );
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
      if (key.currentState!.validate()) {
        Navigator.pop(c, {
          'site_id': site,
          'reference': reference.text,
          'inventory_type': type,
          'period_date': period.text,
        });
      }
    },
    children: [
      DropdownButtonFormField<String>(
        isExpanded: true,
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
        isExpanded: true,
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
