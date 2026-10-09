import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../../core/widgets/app_navigation_drawer.dart';
import '../data/receipt_service.dart';

class ReceiptsPage extends StatefulWidget {
  const ReceiptsPage({super.key});

  @override
  State<ReceiptsPage> createState() => _ReceiptsPageState();
}

class _ReceiptsPageState extends State<ReceiptsPage> {
  final _service = ReceiptService();
  List<Map<String, dynamic>> _organizations = [];
  List<Map<String, dynamic>> _receipts = [];
  String? _organizationId;
  bool _loading = true;
  String? _error;
  int _pending = 0;

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
            : '${organizations.first['id']}';
      });
      if (_organizationId != null) {
        await _loadReceipts();
      }
    } on DioException {
      if (mounted) {
        setState(() => _error = 'Impossible de charger les organisations.');
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadReceipts() async {
    final organizationId = _organizationId;
    if (organizationId == null) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      // Le compteur est lu après la liste, qui synchronise d'abord la file.
      final receipts = await _service.list(organizationId);
      final pending = await _service.pendingCount();
      if (!mounted) return;
      setState(() {
        _receipts = receipts;
        _pending = pending;
      });
    } on DioException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.response?.statusCode == 403
              ? 'Le module Réceptions est désactivé ou votre rôle ne permet pas cet accès.'
              : 'Chargement impossible. Les données hors connexion restent disponibles.';
        });
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openCreate() async {
    final organizationId = _organizationId;
    if (organizationId == null) return;
    try {
      final options = await _service.options(organizationId);
      if (!mounted) return;
      final result = await showAppFormSheet<_ReceiptResult>(
        context: context,
        title: 'Nouvelle réception',
        description:
            'Enregistrez les produits et les lots reçus. Le stock sera crédité après validation.',
        builder: (context) => _ReceiptForm(
          sites: options['sites']!,
          suppliers: options['suppliers']!,
          products: options['products']!,
          onSubmit: (data) => _service.create(organizationId, data: data),
        ),
      );
      if (result != null && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              result.online
                  ? 'Réception enregistrée comme brouillon.'
                  : 'Réception enregistrée hors connexion. Elle sera synchronisée automatiquement.',
            ),
          ),
        );
        await _loadReceipts();
      }
    } on DioException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            error.response?.statusCode == 403
                ? 'Vous n’avez pas la permission de créer une réception.'
                : 'Le formulaire ne peut pas être préparé sans données de référence locales.',
          ),
        ),
      );
    }
  }

  Future<void> _validate(Map<String, dynamic> receipt) async {
    final organizationId = _organizationId;
    if (organizationId == null) return;
    final confirmed = await showAppDialogAsFormSheet<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Valider la réception ?'),
        content: const Text(
          'Les quantités acceptées seront ajoutées au stock. Cette opération ne peut pas être annulée directement.',
        ),
        actions: [
          AppButton.cancel(
            onPressed: () => Navigator.pop(context, false),
            compact: true,
          ),
          AppButton.validate(
            onPressed: () => Navigator.pop(context, true),
            compact: true,
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await _service.validate(organizationId, '${receipt['id']}');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Réception validée et stock mis à jour.')),
      );
      await _loadReceipts();
    } on DioException catch (error) {
      if (mounted) {
        // Refus du serveur (stock gelé par un inventaire…) : son motif ;
        // sinon, absence de réseau.
        final data = error.response?.data;
        final reason = data is Map ? data['message']?.toString() : null;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              error.response == null
                  ? 'La validation nécessite une connexion au serveur. Le brouillon est conservé.'
                  : '${reason ?? 'Validation refusée.'} Le brouillon est conservé.',
            ),
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final draftCount = _receipts
        .where((item) => item['status'] == 'draft')
        .length;
    return Scaffold(
      drawer: const AppNavigationDrawer(),
      appBar: AppBar(title: const Text('Réceptions')),
      floatingActionButton: AppFab(
        tooltip: 'Nouvelle réception',
        onPressed: _organizationId == null ? null : _openCreate,
      ),
      body: RefreshIndicator(
        onRefresh: _loadReceipts,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            DropdownButtonFormField<String>(
              isExpanded: true,
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
                _loadReceipts();
              },
            ),
            if (_pending > 0) ...[
              const SizedBox(height: 12),
              Card(
                color: AppTheme.orange.withValues(alpha: .07),
                child: ListTile(
                  leading: const Icon(
                    Icons.cloud_upload_outlined,
                    color: AppTheme.orange,
                  ),
                  title: Text(
                    '$_pending réception(s) en attente de synchronisation',
                  ),
                  subtitle: const Text(
                    'La synchronisation sera retentée automatiquement.',
                  ),
                ),
              ),
            ],
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: _Summary(
                    label: 'Réceptions',
                    value: '${_receipts.length}',
                    icon: Icons.move_to_inbox_outlined,
                    color: AppTheme.blue,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _Summary(
                    label: 'Brouillons',
                    value: '$draftCount',
                    icon: Icons.pending_actions_outlined,
                    color: AppTheme.orange,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),
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
            else if (_receipts.isEmpty)
              Card(
                child: Padding(
                  padding: EdgeInsets.all(24),
                  child: Column(
                    children: [
                      Icon(
                        Icons.move_to_inbox_outlined,
                        size: 42,
                        color: AppTheme.muted,
                      ),
                      SizedBox(height: 10),
                      Text('Aucune réception enregistrée'),
                    ],
                  ),
                ),
              )
            else
              for (final receipt in _receipts)
                _ReceiptCard(
                  receipt: receipt,
                  onValidate: receipt['status'] == 'draft'
                      ? () => _validate(receipt)
                      : null,
                ),
            const SizedBox(height: 90),
          ],
        ),
      ),
    );
  }
}

class _Summary extends StatelessWidget {
  const _Summary({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            CircleAvatar(
              backgroundColor: color.withValues(alpha: .10),
              foregroundColor: color,
              child: Icon(icon),
            ),
            const SizedBox(width: 12),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  value,
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  label,
                  style: TextStyle(fontSize: 11, color: AppTheme.muted),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _ReceiptCard extends StatelessWidget {
  const _ReceiptCard({required this.receipt, this.onValidate});

  final Map<String, dynamic> receipt;
  final VoidCallback? onValidate;

  @override
  Widget build(BuildContext context) {
    final validated = receipt['status'] == 'validated';
    final items = receipt['items'] as List? ?? const [];
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${receipt['reference']}',
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 9,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: validated
                        ? AppColors.successSurface
                        : AppColors.neutralSurface,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    validated ? 'Validée' : 'Brouillon',
                    style: TextStyle(
                      color: validated
                          ? AppColors.successText
                          : AppColors.neutralText,
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 9),
            Text(
              // Date de réception seule, sans l'heure ajoutée par le serveur.
              '${receipt['site']?['name'] ?? 'Site'} · ${'${receipt['received_on'] ?? ''}'.split('T').first}',
              style: TextStyle(color: AppTheme.muted, fontSize: 12),
            ),
            const SizedBox(height: 3),
            Text(
              '${items.length} ligne(s) · ${receipt['origin_display'] ?? receipt['supplier']?['name'] ?? 'Origine non renseignée'}',
              style: TextStyle(color: AppTheme.muted, fontSize: 11),
            ),
            if (onValidate != null) ...[
              const SizedBox(height: 12),
              AppButton.validate(
                label: 'Valider et créditer le stock',
                onPressed: onValidate,
                expanded: true,
                compact: true,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ReceiptResult {
  const _ReceiptResult(this.online);
  final bool online;
}

typedef _ReceiptSubmit = Future<bool> Function(Map<String, dynamic> data);

class _ReceiptForm extends StatefulWidget {
  const _ReceiptForm({
    required this.sites,
    required this.suppliers,
    required this.products,
    required this.onSubmit,
  });

  final List<Map<String, dynamic>> sites;
  final List<Map<String, dynamic>> suppliers;
  final List<Map<String, dynamic>> products;
  final _ReceiptSubmit onSubmit;

  @override
  State<_ReceiptForm> createState() => _ReceiptFormState();
}

class _ReceiptFormState extends State<_ReceiptForm> {
  final _informationKey = GlobalKey<FormState>();
  final _productsKey = GlobalKey<FormState>();
  final _lotsKey = GlobalKey<FormState>();
  final _pages = PageController();
  int _step = 0;
  final _reference = TextEditingController(
    text: 'REC-${DateTime.now().millisecondsSinceEpoch}',
  );
  final _orderReference = TextEditingController();
  final _notes = TextEditingController();
  final _originLabel = TextEditingController();
  final _lines = <_ReceiptLine>[_ReceiptLine()];
  String? _siteId;
  String? _supplierId;
  // Niveau 7 : origine = couple ONG/Bailleur (projet de la FOSA) ou « other ».
  String? _originChoice;

  List<Map<String, dynamic>> get _origins => [
    for (final origin
        in (widget.sites
                    .where((site) => '${site['id']}' == _siteId)
                    .firstOrNull?['origins']
                as List? ??
            const []))
      Map<String, dynamic>.from(origin as Map),
  ];

  String get _originSummary => _originChoice == 'other'
      ? 'Autre · ${_originLabel.text.trim()}'
      : '${_origins.where((origin) => '${origin['project_id']}' == _originChoice).firstOrNull?['label'] ?? 'Non renseignée'}';
  DateTime _receivedOn = DateTime.now();
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _pages.dispose();
    _reference.dispose();
    _orderReference.dispose();
    _notes.dispose();
    _originLabel.dispose();
    for (final line in _lines) {
      line.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    // Une étape qui n'est plus affichée n'a plus de formulaire monté
    // (currentState null) : elle a été validée au passage à la suivante.
    // Avant : « null » comptait comme invalide et l'enregistrement
    // s'arrêtait sans aucun message.
    if (!(_informationKey.currentState?.validate() ?? true) ||
        !(_productsKey.currentState?.validate() ?? true) ||
        !(_lotsKey.currentState?.validate() ?? true)) {
      return;
    }
    for (var index = 0; index < _lines.length; index++) {
      final line = _lines[index];
      final received = line.number(line.received.text);
      final accepted = line.number(line.accepted.text);
      final rejected = line.number(line.rejected.text);
      if ((accepted + rejected - received).abs() > .0001) {
        setState(() {
          _error =
              'Ligne ${index + 1} : la quantité acceptée et la quantité rejetée doivent correspondre à la quantité reçue.';
        });
        return;
      }
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final online = await widget.onSubmit({
        'site_id': _siteId,
        if (_supplierId != null) 'supplier_id': _supplierId,
        'origin_type': _originChoice == 'other' ? 'other' : 'project',
        if (_originChoice != 'other') 'origin_project_id': _originChoice,
        if (_originChoice == 'other') 'origin_label': _originLabel.text.trim(),
        'reference': _reference.text.trim(),
        if (_orderReference.text.trim().isNotEmpty)
          'order_reference': _orderReference.text.trim(),
        'received_on': _date(_receivedOn),
        if (_notes.text.trim().isNotEmpty) 'notes': _notes.text.trim(),
        'items': _lines.map((line) => line.toJson()).toList(),
      });
      if (mounted) Navigator.pop(context, _ReceiptResult(online));
    } on DioException catch (error) {
      final errors = error.response?.data is Map
          ? (error.response?.data as Map)['errors']
          : null;
      setState(() {
        _saving = false;
        _error = errors is Map && errors.isNotEmpty
            ? '${(errors.values.first as List).first}'
            : 'La réception n’a pas pu être enregistrée.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    const labels = ['Informations', 'Produits', 'Lots & péremptions', 'Résumé'];
    return SizedBox(
      height: MediaQuery.sizeOf(context).height * .74,
      child: Column(
        children: [
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              children: List.generate(
                labels.length,
                (index) => Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text('${index + 1}. ${labels[index]}'),
                    selected: _step == index,
                    onSelected: (_) => _goTo(index),
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 8),
          Expanded(
            child: PageView(
              controller: _pages,
              onPageChanged: (index) async {
                if (index > _step && !_validStep(_step)) {
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
                _body(
                  Form(
                    key: _informationKey,
                    child: Column(
                      children: [
                        DropdownButtonFormField<String>(
                          isExpanded: true,
                          initialValue: _siteId,
                          decoration: const InputDecoration(
                            labelText: 'Point de dispensation destinataire *',
                            prefixIcon: Icon(Icons.location_on_outlined),
                          ),
                          items: widget.sites
                              .map(
                                (site) => DropdownMenuItem(
                                  value: '${site['id']}',
                                  child: Text(
                                    '${site['name']}',
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              )
                              .toList(),
                          onChanged: (value) => setState(() {
                            _siteId = value;
                            // Les couples ONG/Bailleur dépendent de la FOSA du site.
                            if (_originChoice != 'other') _originChoice = null;
                          }),
                          validator: (value) => value == null
                              ? 'Sélectionnez un point de dispensation.'
                              : null,
                        ),
                        const SizedBox(height: 12),
                        DropdownButtonFormField<String>(
                          key: ValueKey('origin-$_siteId'),
                          initialValue: _originChoice,
                          isExpanded: true,
                          decoration: InputDecoration(
                            labelText: 'Origine (couple ONG/Bailleur) *',
                            prefixIcon: const Icon(Icons.handshake_outlined),
                            helperText: _siteId != null && _origins.isEmpty
                                ? 'Aucun projet pour ce site : choisissez « Autre ».'
                                : null,
                          ),
                          items: [
                            for (final origin in _origins)
                              DropdownMenuItem(
                                value: '${origin['project_id']}',
                                child: Text(
                                  '${origin['label']}',
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            const DropdownMenuItem(
                              value: 'other',
                              child: Text('Autre (fournisseur tiers)'),
                            ),
                          ],
                          onChanged: _siteId == null
                              ? null
                              : (value) =>
                                    setState(() => _originChoice = value),
                          validator: (value) => value == null
                              ? 'Choisissez l’origine de l’entrée.'
                              : null,
                        ),
                        if (_originChoice == 'other') ...[
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _originLabel,
                            decoration: const InputDecoration(
                              labelText: 'Nom du fournisseur tiers *',
                              hintText: 'Ex. Pharmacie régionale, don ponctuel',
                            ),
                            validator: (value) =>
                                value == null || value.trim().isEmpty
                                ? 'Indiquez qui a livré ces produits.'
                                : null,
                          ),
                        ],
                        const SizedBox(height: 12),
                        DropdownButtonFormField<String>(
                          isExpanded: true,
                          initialValue: _supplierId,
                          decoration: const InputDecoration(
                            labelText: 'Fournisseur',
                            prefixIcon: Icon(Icons.local_shipping_outlined),
                          ),
                          items: widget.suppliers
                              .map(
                                (supplier) => DropdownMenuItem(
                                  value: '${supplier['id']}',
                                  child: Text('${supplier['name']}'),
                                ),
                              )
                              .toList(),
                          onChanged: (value) => _supplierId = value,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _reference,
                          decoration: const InputDecoration(
                            labelText: 'Référence de réception *',
                          ),
                          validator: (value) =>
                              value == null || value.trim().isEmpty
                              ? 'La référence est obligatoire.'
                              : null,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _orderReference,
                          decoration: const InputDecoration(
                            labelText: 'Référence du bon de commande',
                          ),
                        ),
                        const SizedBox(height: 12),
                        InkWell(
                          onTap: _saving ? null : _pickReceivedDate,
                          child: InputDecorator(
                            decoration: const InputDecoration(
                              labelText: 'Date de réception',
                              prefixIcon: Icon(Icons.calendar_today_outlined),
                            ),
                            child: Text(_date(_receivedOn)),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                _body(
                  Form(
                    key: _productsKey,
                    child: Column(
                      children: [
                        Row(
                          children: [
                            const Expanded(
                              child: Text(
                                'Produits reçus',
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            AppButton.add(
                              label: 'Produit',
                              compact: true,
                              onPressed: () =>
                                  setState(() => _lines.add(_ReceiptLine())),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        for (var index = 0; index < _lines.length; index++)
                          _productCard(index),
                      ],
                    ),
                  ),
                ),
                _body(
                  Form(
                    key: _lotsKey,
                    child: Column(
                      children: [
                        const Align(
                          alignment: Alignment.centerLeft,
                          child: Text(
                            'Lots et péremptions',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        const SizedBox(height: 10),
                        for (var index = 0; index < _lines.length; index++)
                          _lotCard(index),
                      ],
                    ),
                  ),
                ),
                _body(
                  Column(
                    children: [
                      _ReceiptSummaryLine('Origine', _originSummary),
                      _ReceiptSummaryLine(
                        'Fournisseur',
                        widget.suppliers
                                .where((s) => '${s['id']}' == _supplierId)
                                .firstOrNull?['name']
                                ?.toString() ??
                            'Non renseigné',
                      ),
                      _ReceiptSummaryLine('Date', _date(_receivedOn)),
                      _ReceiptSummaryLine('Référence', _reference.text),
                      _ReceiptSummaryLine(
                        'Commande liée',
                        _orderReference.text,
                      ),
                      for (var index = 0; index < _lines.length; index++)
                        Card(
                          child: ListTile(
                            title: Text(
                              widget.products
                                      .where(
                                        (p) =>
                                            '${p['id']}' ==
                                            _lines[index].productId,
                                      )
                                      .firstOrNull?['name']
                                      ?.toString() ??
                                  'Produit ${index + 1}',
                            ),
                            subtitle: Text(
                              'Lot ${_lines[index].batch.text} · reçu ${_lines[index].received.text} · accepté ${_lines[index].accepted.text}\nPéremption ${_lines[index].expiry.text}',
                            ),
                          ),
                        ),
                      TextFormField(
                        controller: _notes,
                        minLines: 2,
                        maxLines: 4,
                        decoration: const InputDecoration(
                          labelText: 'Observations',
                        ),
                      ),
                      if (_error != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 10),
                          child: Text(
                            _error!,
                            style: TextStyle(color: AppTheme.red),
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 10, 20, 18),
            child: Row(
              children: [
                Expanded(
                  child: AppButton.cancel(
                    label: _step == 0 ? 'Annuler' : 'Précédent',
                    onPressed: _saving
                        ? null
                        : () => _step == 0
                              ? Navigator.pop(context)
                              : _goTo(_step - 1),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _step == 3
                      ? AppButton.save(
                          label: 'Enregistrer la réception',
                          loading: _saving,
                          onPressed: _save,
                        )
                      : AppButton.primary(
                          label: 'Suivant',
                          icon: Icons.arrow_forward_rounded,
                          onPressed: () => _goTo(_step + 1),
                        ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _body(Widget child) => SingleChildScrollView(
    padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
    child: child,
  );

  Widget _productCard(int index) {
    final line = _lines[index];
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Produit ${index + 1}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
                if (_lines.length > 1)
                  IconButton(
                    onPressed: () => setState(() {
                      final removed = _lines.removeAt(index);
                      // Libérée après le retrait de ses champs de l’écran.
                      WidgetsBinding.instance.addPostFrameCallback((_) => removed.dispose());
                    }),
                    icon: const Icon(Icons.delete_outline),
                  ),
              ],
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              isExpanded: true,
              initialValue: line.productId,
              decoration: const InputDecoration(labelText: 'Produit *'),
              items: widget.products
                  .map(
                    (product) => DropdownMenuItem(
                      value: '${product['id']}',
                      child: Text(
                        '${product['code']} · ${product['name']}',
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  )
                  .toList(),
              onChanged: (value) => line.productId = value,
              validator: (value) =>
                  value == null ? 'Sélectionnez un produit.' : null,
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: _numberField(
                    line.ordered,
                    'Commandée',
                    allowZero: true,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _numberField(
                    line.received,
                    'Livrée',
                    onChanged: (value) {
                      // La quantité acceptée suit la quantité livrée tant que
                      // l’utilisateur ne l’a pas saisie lui-même.
                      if (!line.acceptedEdited) line.accepted.text = value;
                    },
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _lotCard(int index) {
    final line = _lines[index];
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          children: [
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'Lot — produit ${index + 1}',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: line.batch,
              decoration: const InputDecoration(labelText: 'Numéro de lot *'),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Numéro obligatoire.'
                  : null,
            ),
            const SizedBox(height: 10),
            TextFormField(
              controller: line.expiry,
              readOnly: true,
              onTap: () => _pickExpiry(line),
              decoration: const InputDecoration(
                labelText: 'Date de péremption *',
                hintText: 'AAAA-MM-JJ',
                suffixIcon: Icon(Icons.event_outlined),
              ),
              validator: (value) {
                final date = DateTime.tryParse(value ?? '');
                return date == null || !date.isAfter(DateTime.now())
                    ? 'Date future obligatoire.'
                    : null;
              },
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: _numberField(
                    line.accepted,
                    'Acceptée',
                    allowZero: true,
                    onChanged: (_) => line.acceptedEdited = true,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _numberField(
                    line.rejected,
                    'Rejetée',
                    allowZero: true,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            _numberField(
              line.cost,
              'Coût unitaire',
              allowZero: true,
              optional: true,
            ),
            const SizedBox(height: 10),
            TextFormField(
              controller: line.reason,
              minLines: 2,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Justification si écart ou rejet',
              ),
              validator: (value) {
                final hasGap =
                    (line.number(line.ordered.text) -
                                line.number(line.received.text))
                            .abs() >
                        .0001 ||
                    line.number(line.rejected.text) > 0;
                return hasGap && (value == null || value.trim().length < 5)
                    ? 'Justifiez l’écart ou le rejet.'
                    : null;
              },
            ),
          ],
        ),
      ),
    );
  }

  bool _validStep(int step) {
    final valid = switch (step) {
      0 => _informationKey.currentState?.validate() ?? false,
      1 => _productsKey.currentState?.validate() ?? false,
      2 => _lotsKey.currentState?.validate() ?? false,
      _ => true,
    };
    if (!valid || step != 2) return valid;
    for (final line in _lines) {
      if ((line.number(line.accepted.text) +
                  line.number(line.rejected.text) -
                  line.number(line.received.text))
              .abs() >
          .0001) {
        setState(
          () => _error =
              'La somme acceptée + rejetée doit correspondre à la quantité livrée.',
        );
        return false;
      }
    }
    setState(() => _error = null);
    return true;
  }

  Future<void> _pickExpiry(_ReceiptLine line) async {
    final now = DateTime.now();
    final selected = await showDatePicker(
      context: context,
      initialDate: DateTime.tryParse(line.expiry.text) ??
          DateTime(now.year + 1, now.month, now.day),
      firstDate: now.add(const Duration(days: 1)),
      lastDate: DateTime(now.year + 15),
      helpText: 'Date de péremption',
    );
    if (selected != null && mounted) {
      line.expiry.text = selected.toIso8601String().substring(0, 10);
    }
  }

  Widget _numberField(
    TextEditingController controller,
    String label, {
    bool allowZero = false,
    bool optional = false,
    ValueChanged<String>? onChanged,
  }) => TextFormField(
    controller: controller,
    keyboardType: const TextInputType.numberWithOptions(decimal: true),
    decoration: InputDecoration(labelText: label),
    onChanged: onChanged,
    validator: (value) {
      if (optional && (value ?? '').trim().isEmpty) return null;
      final number = double.tryParse((value ?? '').replaceAll(',', '.'));
      if (number == null || (allowZero ? number < 0 : number <= 0)) {
        return allowZero ? '≥ 0 requis' : '> 0 requis';
      }
      return null;
    },
  );

  Future<void> _goTo(int target) async {
    if (target > _step && !_validStep(_step)) return;
    await _pages.animateToPage(
      target,
      duration: const Duration(milliseconds: 240),
      curve: Curves.easeOut,
    );
  }

  /*
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            DropdownButtonFormField<String>(
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Site destinataire',
                prefixIcon: Icon(Icons.location_on_outlined),
              ),
              items: widget.sites
                  .map(
                    (site) => DropdownMenuItem(
                      value: '${site['id']}',
                      child: Text('${site['name']}', overflow: TextOverflow.ellipsis),
                    ),
                  )
                  .toList(),
              onChanged: (value) => _siteId = value,
              validator: (value) => value == null ? 'Sélectionnez un site.' : null,
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Fournisseur / origine',
                prefixIcon: Icon(Icons.local_shipping_outlined),
              ),
              items: widget.suppliers
                  .map(
                    (supplier) => DropdownMenuItem(
                      value: '${supplier['id']}',
                      child: Text('${supplier['name']}'),
                    ),
                  )
                  .toList(),
              onChanged: (value) => _supplierId = value,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _reference,
              decoration: const InputDecoration(labelText: 'Référence de réception'),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'La référence est obligatoire.'
                  : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _orderReference,
              decoration: const InputDecoration(labelText: 'Référence du bon de commande'),
            ),
            const SizedBox(height: 12),
            InkWell(
              onTap: _saving ? null : _pickReceivedDate,
              borderRadius: BorderRadius.circular(12),
              child: InputDecorator(
                decoration: const InputDecoration(
                  labelText: 'Date de réception',
                  prefixIcon: Icon(Icons.calendar_today_outlined),
                ),
                child: Text(_date(_receivedOn)),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'Produits et lots',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                  ),
                ),
                AppButton.add(
                  label: 'Ligne',
                  compact: true,
                  onPressed: () => setState(() => _lines.add(_ReceiptLine())),
                ),
              ],
            ),
            const SizedBox(height: 10),
            for (var index = 0; index < _lines.length; index++)
              _ReceiptLineCard(
                key: ObjectKey(_lines[index]),
                index: index,
                line: _lines[index],
                products: widget.products,
                onRemove: _lines.length == 1
                    ? null
                    : () => setState(() {
                        final removed = _lines.removeAt(index);
                        // Libérée après le retrait de ses champs de l’écran.
                        WidgetsBinding.instance.addPostFrameCallback((_) => removed.dispose());
                      }),
              ),
            TextFormField(
              controller: _notes,
              minLines: 2,
              maxLines: 4,
              decoration: const InputDecoration(labelText: 'Observations'),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: const TextStyle(color: AppTheme.red)),
            ],
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(
                  child: AppButton.cancel(
                    onPressed: _saving ? null : () => Navigator.pop(context),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: AppButton.save(
                    label: 'Enregistrer',
                    loading: _saving,
                    onPressed: _save,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

*/
  static String _date(DateTime value) =>
      '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';

  Future<void> _pickReceivedDate() async {
    final selected = await showDatePicker(
      context: context,
      initialDate: _receivedOn,
      firstDate: DateTime.now().subtract(const Duration(days: 3650)),
      lastDate: DateTime.now(),
      helpText: 'Date de réception',
    );
    if (selected != null && mounted) {
      setState(() => _receivedOn = selected);
    }
  }
}

class _ReceiptLine {
  final batch = TextEditingController();
  final expiry = TextEditingController();
  final ordered = TextEditingController(text: '0');
  final received = TextEditingController();
  final accepted = TextEditingController();
  final rejected = TextEditingController(text: '0');
  final cost = TextEditingController();
  final reason = TextEditingController();
  String? productId;
  bool acceptedEdited = false;

  Map<String, dynamic> toJson() => {
    'product_id': productId,
    'batch_number': batch.text.trim(),
    'expires_on': expiry.text.trim(),
    'quantity_ordered': number(ordered.text),
    'quantity_received': number(received.text),
    'quantity_accepted': number(accepted.text),
    'quantity_rejected': number(rejected.text),
    if (cost.text.trim().isNotEmpty) 'unit_cost': number(cost.text),
    if (reason.text.trim().isNotEmpty) 'discrepancy_reason': reason.text.trim(),
  };

  double number(String value) =>
      double.tryParse(value.replaceAll(',', '.')) ?? 0;

  void dispose() {
    batch.dispose();
    expiry.dispose();
    ordered.dispose();
    received.dispose();
    accepted.dispose();
    rejected.dispose();
    cost.dispose();
    reason.dispose();
  }
}

class _ReceiptSummaryLine extends StatelessWidget {
  const _ReceiptSummaryLine(this.label, this.value);
  final String label, value;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Row(
      children: [
        SizedBox(
          width: 120,
          child: Text(label, style: TextStyle(color: AppTheme.muted)),
        ),
        Expanded(
          child: Text(
            value.trim().isEmpty ? '—' : value,
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
      ],
    ),
  );
}

/* Legacy line card retained temporarily as migration reference.
class _ReceiptLineCard extends StatelessWidget {
  const _ReceiptLineCard({
    super.key,
    required this.index,
    required this.line,
    required this.products,
    this.onRemove,
  });

  final int index;
  final _ReceiptLine line;
  final List<Map<String, dynamic>> products;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Ligne ${index + 1}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
                if (onRemove != null)
                  AppIconAction(
                    icon: Icons.delete_outline_rounded,
                    tooltip: 'Retirer la ligne',
                    color: AppActionColor.red,
                    onPressed: onRemove,
                  ),
              ],
            ),
            const SizedBox(height: 8),
            DropdownButtonFormField<String>(
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Médicament / produit',
              ),
              items: products
                  .map(
                    (product) => DropdownMenuItem(
                      value: '${product['id']}',
                      child: Text(
                        '${product['code']} · ${product['name']}',
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  )
                  .toList(),
              onChanged: (value) => line.productId = value,
              validator: (value) =>
                  value == null ? 'Sélectionnez un produit.' : null,
            ),
            const SizedBox(height: 10),
            TextFormField(
              controller: line.batch,
              decoration: const InputDecoration(labelText: 'Numéro de lot'),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Le numéro de lot est obligatoire.'
                  : null,
            ),
            const SizedBox(height: 10),
            TextFormField(
              controller: line.expiry,
              keyboardType: TextInputType.datetime,
              decoration: const InputDecoration(
                labelText: 'Date de péremption',
                hintText: 'AAAA-MM-JJ',
              ),
              validator: (value) {
                final parsed = DateTime.tryParse(value ?? '');
                return parsed == null || !parsed.isAfter(DateTime.now())
                    ? 'Saisissez une date future au format AAAA-MM-JJ.'
                    : null;
              },
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: _numberField(
                    line.ordered,
                    'Commandée',
                    allowZero: true,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _numberField(
                    line.received,
                    'Reçue',
                    onChanged: (value) {
                      if (line.accepted.text.isEmpty)
                        line.accepted.text = value;
                    },
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: _numberField(
                    line.accepted,
                    'Acceptée',
                    allowZero: true,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _numberField(
                    line.rejected,
                    'Rejetée',
                    allowZero: true,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            _numberField(line.cost, 'Coût unitaire', allowZero: true),
            const SizedBox(height: 10),
            TextFormField(
              controller: line.reason,
              minLines: 2,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Justification si écart ou rejet',
              ),
              validator: (value) {
                final ordered = line.number(line.ordered.text);
                final received = line.number(line.received.text);
                final rejected = line.number(line.rejected.text);
                final hasDiscrepancy =
                    (ordered - received).abs() > .0001 || rejected > 0;
                return hasDiscrepancy &&
                        (value == null || value.trim().length < 5)
                    ? 'Justifiez l’écart ou le rejet.'
                    : null;
              },
            ),
          ],
        ),
      ),
    );
  }

  static Widget _numberField(
    TextEditingController controller,
    String label, {
    bool allowZero = false,
    ValueChanged<String>? onChanged,
  }) {
    return TextFormField(
      controller: controller,
      keyboardType: const TextInputType.numberWithOptions(decimal: true),
      decoration: InputDecoration(labelText: label),
      onChanged: onChanged,
      validator: (value) {
        final number = double.tryParse((value ?? '').replaceAll(',', '.'));
        if (number == null || (allowZero ? number < 0 : number <= 0)) {
          return allowZero ? '≥ 0 requis' : '> 0 requis';
        }
        return null;
      },
    );
  }
}
*/
