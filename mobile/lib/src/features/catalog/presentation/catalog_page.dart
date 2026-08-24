import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../../core/access/application_access.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../../core/widgets/app_navigation_drawer.dart';
import '../../auth/data/auth_service.dart';
import '../data/catalog_service.dart';

class CatalogPage extends StatefulWidget {
  const CatalogPage({this.initialTab = 0, super.key});

  final int initialTab;

  @override
  State<CatalogPage> createState() => _CatalogPageState();
}

class _CatalogPageState extends State<CatalogPage>
    with SingleTickerProviderStateMixin {
  final _service = CatalogService();
  final _search = TextEditingController();
  late final TabController _tabs;
  List<Map<String, dynamic>> _organizations = [];
  List<Map<String, dynamic>> _items = [];
  List<Map<String, dynamic>> _references = [];
  List<Map<String, dynamic>> _products = [];
  Map<String, dynamic>? _user;
  String? _organizationId;
  String _status = 'active';
  String _productType = '';
  String _referenceType = '';
  bool _loading = true;
  String? _error;

  static const referenceTypes = <String, String>{
    'category': 'Catégorie',
    'therapeutic_family': 'Famille thérapeutique',
    'unit': 'Unité',
    'dosage_form': 'Forme pharmaceutique',
    'dosage': 'Dosage',
    'administration_route': 'Voie d’administration',
    'pathology': 'Pathologie',
    'target_population': 'Population cible',
    'protocol': 'Protocole',
    'activity_type': 'Type d’activité',
    'care_level': 'Niveau de soins',
  };

  static const productTypes = <String, String>{
    'medicine': 'Médicament',
    'consumable': 'Consommable',
    'device': 'Dispositif médical',
    'reagent': 'Réactif',
    'program_input': 'Intrant de programme',
    'other': 'Autre',
  };

  @override
  void initState() {
    super.initState();
    _tabs =
        TabController(length: 3, vsync: this, initialIndex: widget.initialTab)
          ..addListener(() {
            if (!_tabs.indexIsChanging) _load();
          });
    _initialize();
  }

  @override
  void dispose() {
    _tabs.dispose();
    _search.dispose();
    super.dispose();
  }

  bool get _canManage => switch (_tabs.index) {
    0 =>
      ApplicationAccess.allows(_user, 'products.manage') ||
          ApplicationAccess.allows(_user, 'catalog.manage'),
    1 => ApplicationAccess.allows(_user, 'catalog.manage'),
    _ =>
      ApplicationAccess.allows(_user, 'standard_lists.manage') ||
          ApplicationAccess.allows(_user, 'catalog.manage'),
  };

  bool get _canPublish => ApplicationAccess.allows(_user, 'catalog.publish');

  Future<void> _initialize() async {
    try {
      final values = await Future.wait([
        AuthService().cachedUser(),
        _service.organizations(),
      ]);
      _user = values[0] as Map<String, dynamic>?;
      _organizations = values[1] as List<Map<String, dynamic>>;
      _organizationId = _organizations.isEmpty
          ? null
          : '${_organizations.first['id']}';
      await _load();
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'Impossible de charger le catalogue.');
      }
    }
  }

  Future<void> _load() async {
    final organizationId = _organizationId;
    if (organizationId == null) {
      if (mounted) setState(() => _loading = false);
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final search = _search.text.trim();
      if (_tabs.index == 0) {
        _items = await _service.products(
          organizationId,
          search: search,
          type: _productType,
          status: _status,
        );
        _references = await _service.references(organizationId);
      } else if (_tabs.index == 1) {
        _items = await _service.references(
          organizationId,
          search: search,
          type: _referenceType,
          status: _status,
        );
      } else {
        _items = await _service.lists(
          organizationId,
          search: search,
          status: _status,
        );
        _products = await _service.products(organizationId, status: 'active');
      }
    } on DioException catch (error) {
      _error = error.response?.statusCode == 403
          ? 'Vous n’avez pas la permission d’accéder à ce catalogue.'
          : 'Impossible de charger les données.';
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _confirmAction(Map<String, dynamic> item) async {
    final archived = _status == 'archived';
    final accepted = await showAppDialogAsFormSheet<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(
          archived ? 'Restaurer cet élément ?' : 'Archiver cet élément ?',
        ),
        content: Text(
          archived
              ? 'Les données seront conservées et l’élément redeviendra actif.'
              : 'Aucune donnée ne sera supprimée définitivement.',
        ),
        actions: [
          AppButton.cancel(
            label: 'Annuler',
            compact: true,
            onPressed: () => Navigator.pop(context, false),
          ),
          AppButton.archive(
            label: archived ? 'Restaurer' : 'Archiver',
            compact: true,
            onPressed: () => Navigator.pop(context, true),
          ),
        ],
      ),
    );
    if (accepted != true) return;
    final resource = switch (_tabs.index) {
      0 => 'products',
      1 => 'references',
      _ => 'lists',
    };
    archived
        ? await _service.restore(_organizationId!, resource, '${item['id']}')
        : await _service.archive(_organizationId!, resource, '${item['id']}');
    await _load();
  }

  Future<void> _openForm([Map<String, dynamic>? item]) async {
    if (_tabs.index == 0) return _productForm(item);
    if (_tabs.index == 1) return _referenceForm(item);
    return _listForm(item);
  }

  Future<void> _referenceForm(Map<String, dynamic>? item) async {
    final key = GlobalKey<FormState>();
    final code = TextEditingController(text: item?['code']?.toString());
    final name = TextEditingController(text: item?['name']?.toString());
    final description = TextEditingController(
      text: item?['description']?.toString(),
    );
    var type = item?['reference_type']?.toString() ?? 'category';
    var active = item?['is_active'] != false;
    var saving = false;
    final saved = await showAppFormSheet<bool>(
      context: context,
      title: item == null
          ? 'Ajouter un référentiel'
          : 'Modifier le référentiel',
      description: 'Les choix contrôlés garantissent la qualité des données.',
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: key,
            child: Column(
              children: [
                DropdownButtonFormField<String>(
                  initialValue: type,
                  decoration: const InputDecoration(
                    labelText: 'Type de référentiel',
                  ),
                  items: referenceTypes.entries
                      .map(
                        (entry) => DropdownMenuItem(
                          value: entry.key,
                          child: Text(entry.value),
                        ),
                      )
                      .toList(),
                  onChanged: (value) => type = value ?? type,
                ),
                const SizedBox(height: 14),
                _requiredField(code, 'Code'),
                const SizedBox(height: 14),
                _requiredField(name, 'Libellé'),
                const SizedBox(height: 14),
                TextFormField(
                  controller: description,
                  maxLines: 3,
                  decoration: const InputDecoration(labelText: 'Description'),
                ),
                SwitchListTile.adaptive(
                  value: active,
                  title: const Text('Référentiel actif'),
                  onChanged: (value) => setSheetState(() => active = value),
                ),
                _formActions(sheetContext, saving, () async {
                  if (!(key.currentState?.validate() ?? false)) return;
                  setSheetState(() => saving = true);
                  try {
                    await _service.saveReference(
                      organizationId: _organizationId!,
                      id: item?['id']?.toString(),
                      type: type,
                      code: code.text.trim(),
                      name: name.text.trim(),
                      description: description.text.trim(),
                      active: active,
                    );
                    if (sheetContext.mounted) Navigator.pop(sheetContext, true);
                  } catch (_) {
                    setSheetState(() => saving = false);
                    if (sheetContext.mounted) {
                      ScaffoldMessenger.of(sheetContext).showSnackBar(
                        const SnackBar(
                          content: Text(
                            'Enregistrement impossible. Vérifiez les informations.',
                          ),
                        ),
                      );
                    }
                  }
                }),
              ],
            ),
          ),
        ),
      ),
    );
    code.dispose();
    name.dispose();
    description.dispose();
    if (saved == true) await _load();
  }

  Future<void> _productForm(Map<String, dynamic>? item) async {
    final key = GlobalKey<FormState>();
    final code = TextEditingController(text: item?['code']?.toString());
    final name = TextEditingController(text: item?['name']?.toString());
    final generic = TextEditingController(
      text: item?['generic_name']?.toString(),
    );
    final strength = TextEditingController(text: item?['strength']?.toString());
    final barcode = TextEditingController(text: _barcode(item));
    final description = TextEditingController(
      text: item?['description']?.toString(),
    );
    var type = item?['product_type']?.toString() ?? 'medicine';
    var category = item?['category_id']?.toString();
    var family = item?['therapeutic_family_id']?.toString();
    var unit = item?['base_unit_id']?.toString();
    var form = item?['dosage_form_id']?.toString();
    var route = item?['administration_route_id']?.toString();
    var controlled = item?['is_controlled'] == true;
    var active = item?['is_active'] != false;
    var saving = false;
    final saved = await showAppFormSheet<bool>(
      context: context,
      title: item == null
          ? 'Ajouter un produit médical'
          : 'Modifier le produit',
      description: 'Médicament, consommable, dispositif, réactif ou intrant.',
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: key,
            child: Column(
              children: [
                _requiredField(code, 'Code interne'),
                const SizedBox(height: 14),
                _requiredField(name, 'Désignation'),
                const SizedBox(height: 14),
                TextFormField(
                  controller: generic,
                  decoration: const InputDecoration(
                    labelText: 'DCI / nom générique',
                  ),
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: type,
                  decoration: const InputDecoration(
                    labelText: 'Type de produit',
                  ),
                  items: productTypes.entries
                      .map(
                        (e) => DropdownMenuItem(
                          value: e.key,
                          child: Text(e.value),
                        ),
                      )
                      .toList(),
                  onChanged: (value) => type = value ?? type,
                ),
                const SizedBox(height: 14),
                _referenceSelect(
                  'Catégorie',
                  'category',
                  category,
                  (value) => category = value,
                ),
                const SizedBox(height: 14),
                _referenceSelect(
                  'Famille thérapeutique',
                  'therapeutic_family',
                  family,
                  (value) => family = value,
                ),
                const SizedBox(height: 14),
                _referenceSelect(
                  'Unité de base',
                  'unit',
                  unit,
                  (value) => unit = value,
                ),
                const SizedBox(height: 14),
                _referenceSelect(
                  'Forme pharmaceutique',
                  'dosage_form',
                  form,
                  (value) => form = value,
                ),
                const SizedBox(height: 14),
                _referenceSelect(
                  'Voie d’administration',
                  'administration_route',
                  route,
                  (value) => route = value,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: strength,
                  decoration: const InputDecoration(
                    labelText: 'Dosage / concentration',
                  ),
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: barcode,
                  decoration: const InputDecoration(labelText: 'Code-barres'),
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: description,
                  maxLines: 3,
                  decoration: const InputDecoration(labelText: 'Description'),
                ),
                SwitchListTile.adaptive(
                  value: controlled,
                  title: const Text('Produit contrôlé'),
                  onChanged: (value) => setSheetState(() => controlled = value),
                ),
                SwitchListTile.adaptive(
                  value: active,
                  title: const Text('Produit actif'),
                  onChanged: (value) => setSheetState(() => active = value),
                ),
                _formActions(sheetContext, saving, () async {
                  if (!(key.currentState?.validate() ?? false)) return;
                  setSheetState(() => saving = true);
                  try {
                    await _service.saveProduct(
                      organizationId: _organizationId!,
                      id: item?['id']?.toString(),
                      data: {
                        'code': code.text.trim(),
                        'name': name.text.trim(),
                        'generic_name': generic.text.trim(),
                        'product_type': type,
                        'category_id': category,
                        'therapeutic_family_id': family,
                        'base_unit_id': unit,
                        'dosage_form_id': form,
                        'administration_route_id': route,
                        'strength': strength.text.trim(),
                        'description': description.text.trim(),
                        'is_controlled': controlled,
                        'is_active': active,
                        'codes': barcode.text.trim().isEmpty
                            ? <dynamic>[]
                            : [
                                {
                                  'code_type': 'barcode',
                                  'value': barcode.text.trim(),
                                  'is_primary': true,
                                },
                              ],
                      },
                    );
                    if (sheetContext.mounted) Navigator.pop(sheetContext, true);
                  } catch (_) {
                    setSheetState(() => saving = false);
                    if (sheetContext.mounted) {
                      ScaffoldMessenger.of(sheetContext).showSnackBar(
                        const SnackBar(
                          content: Text(
                            'Enregistrement impossible. Vérifiez les champs et les codes uniques.',
                          ),
                        ),
                      );
                    }
                  }
                }),
              ],
            ),
          ),
        ),
      ),
    );
    for (final controller in [
      code,
      name,
      generic,
      strength,
      barcode,
      description,
    ]) {
      controller.dispose();
    }
    if (saved == true) await _load();
  }

  Future<void> _listForm([Map<String, dynamic>? item]) async {
    final key = GlobalKey<FormState>();
    final code = TextEditingController(text: item?['code']?.toString());
    final name = TextEditingController(text: item?['name']?.toString());
    final description = TextEditingController(
      text: item?['description']?.toString(),
    );
    final selected = _listProductIds(item);
    var saving = false;
    final saved = await showAppFormSheet<bool>(
      context: context,
      title: item == null
          ? 'Nouvelle liste standard'
          : 'Modifier la liste standard',
      description: 'Créez une première version rattachée à l’organisation.',
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: key,
            child: Column(
              children: [
                _requiredField(code, 'Code'),
                const SizedBox(height: 14),
                _requiredField(name, 'Nom de la liste'),
                const SizedBox(height: 14),
                TextFormField(
                  controller: description,
                  maxLines: 3,
                  decoration: const InputDecoration(labelText: 'Description'),
                ),
                const SizedBox(height: 12),
                Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Produits inclus',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                ..._products.map(
                  (product) => CheckboxListTile(
                    value: selected.contains('${product['id']}'),
                    title: Text('${product['name']}'),
                    subtitle: Text('${product['code']}'),
                    onChanged: (value) => setSheetState(
                      () => value == true
                          ? selected.add('${product['id']}')
                          : selected.remove('${product['id']}'),
                    ),
                  ),
                ),
                _formActions(sheetContext, saving, () async {
                  if (!(key.currentState?.validate() ?? false)) return;
                  if (selected.isEmpty) {
                    ScaffoldMessenger.of(sheetContext).showSnackBar(
                      const SnackBar(
                        content: Text('Sélectionnez au moins un produit.'),
                      ),
                    );
                    return;
                  }
                  setSheetState(() => saving = true);
                  try {
                    if (item == null) {
                      await _service.saveList(
                        organizationId: _organizationId!,
                        code: code.text.trim(),
                        name: name.text.trim(),
                        description: description.text.trim(),
                        productIds: selected.toList(),
                      );
                    } else {
                      await _service.updateList(
                        organizationId: _organizationId!,
                        listId: '${item['id']}',
                        code: code.text.trim(),
                        name: name.text.trim(),
                        description: description.text.trim(),
                        productIds: selected.toList(),
                      );
                    }
                    if (sheetContext.mounted) Navigator.pop(sheetContext, true);
                  } catch (_) {
                    setSheetState(() => saving = false);
                    if (sheetContext.mounted) {
                      ScaffoldMessenger.of(sheetContext).showSnackBar(
                        const SnackBar(
                          content: Text('Création de la liste impossible.'),
                        ),
                      );
                    }
                  }
                }),
              ],
            ),
          ),
        ),
      ),
    );
    code.dispose();
    name.dispose();
    description.dispose();
    if (saved == true) await _load();
  }

  Widget _requiredField(TextEditingController controller, String label) =>
      TextFormField(
        controller: controller,
        decoration: InputDecoration(labelText: label),
        validator: (value) =>
            value == null || value.trim().isEmpty ? 'Champ obligatoire' : null,
      );

  Widget _referenceSelect(
    String label,
    String type,
    String? value,
    ValueChanged<String?> changed,
  ) {
    final values = _references
        .where((item) => item['reference_type'] == type)
        .toList();
    return DropdownButtonFormField<String>(
      initialValue: values.any((item) => '${item['id']}' == value)
          ? value
          : null,
      decoration: InputDecoration(labelText: label),
      items: [
        const DropdownMenuItem<String>(
          value: null,
          child: Text('Non renseigné'),
        ),
        ...values.map(
          (item) => DropdownMenuItem(
            value: '${item['id']}',
            child: Text('${item['name']}'),
          ),
        ),
      ],
      onChanged: changed,
    );
  }

  Widget _formActions(
    BuildContext context,
    bool saving,
    Future<void> Function() save,
  ) => Padding(
    padding: const EdgeInsets.only(top: 18),
    child: Row(
      children: [
        Expanded(
          child: AppButton.cancel(
            onPressed: saving ? null : () => Navigator.pop(context, false),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: AppButton.save(loading: saving, onPressed: save),
        ),
      ],
    ),
  );

  String _barcode(Map<String, dynamic>? item) {
    final codes = item?['codes'] as List<dynamic>? ?? const <dynamic>[];
    for (final code in codes) {
      if (code is Map && code['code_type'] == 'barcode') {
        return '${code['value']}';
      }
    }
    return '';
  }

  Set<String> _listProductIds(Map<String, dynamic>? item) {
    final versions =
        (item?['versions'] as List<dynamic>? ?? const <dynamic>[])
            .cast<Map<String, dynamic>>()
            .toList()
          ..sort(
            (a, b) => ((b['version_number'] as num?) ?? 0).compareTo(
              (a['version_number'] as num?) ?? 0,
            ),
          );
    if (versions.isEmpty) return <String>{};
    final products =
        versions.first['products'] as List<dynamic>? ?? const <dynamic>[];
    return products.map((product) => '${(product as Map)['id']}').toSet();
  }

  Map<String, dynamic>? _latestVersion(Map<String, dynamic> item) {
    final versions =
        (item['versions'] as List<dynamic>? ?? const <dynamic>[])
            .cast<Map<String, dynamic>>()
            .toList()
          ..sort(
            (a, b) => ((b['version_number'] as num?) ?? 0).compareTo(
              (a['version_number'] as num?) ?? 0,
            ),
          );
    return versions.isEmpty ? null : versions.first;
  }

  Future<void> _publish(Map<String, dynamic> item) async {
    final version = _latestVersion(item);
    if (version == null || version['status'] != 'draft') return;
    try {
      await _service.publishListVersion(
        organizationId: _organizationId!,
        listId: '${item['id']}',
        versionId: '${version['id']}',
      );
      await _load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Liste standard publiée avec succès.')),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Publication impossible.')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    drawer: const AppNavigationDrawer(),
    appBar: AppBar(
      title: const Text('Référentiels et produits'),
      bottom: TabBar(
        controller: _tabs,
        tabs: const [
          Tab(text: 'Produits'),
          Tab(text: 'Référentiels'),
          Tab(text: 'Listes standards'),
        ],
      ),
    ),
    floatingActionButton: _canManage && _status != 'archived'
        ? AppFab(onPressed: _openForm, tooltip: 'Ajouter')
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
                  (organization) => DropdownMenuItem(
                    value: '${organization['id']}',
                    child: Text('${organization['name']}'),
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
              labelText: 'Rechercher',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: IconButton(
                onPressed: _load,
                icon: const Icon(Icons.arrow_forward),
              ),
            ),
            onSubmitted: (_) => _load(),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              SizedBox(
                width: 190,
                child: DropdownButtonFormField<String>(
                  initialValue: _status,
                  decoration: const InputDecoration(labelText: 'Statut'),
                  items: const [
                    DropdownMenuItem(value: 'active', child: Text('Actifs')),
                    DropdownMenuItem(
                      value: 'inactive',
                      child: Text('Inactifs'),
                    ),
                    DropdownMenuItem(
                      value: 'archived',
                      child: Text('Archivés'),
                    ),
                  ],
                  onChanged: (value) {
                    _status = value ?? 'active';
                    _load();
                  },
                ),
              ),
              if (_tabs.index == 0)
                SizedBox(
                  width: 230,
                  child: DropdownButtonFormField<String>(
                    initialValue: _productType,
                    decoration: const InputDecoration(labelText: 'Type'),
                    items: [
                      const DropdownMenuItem(
                        value: '',
                        child: Text('Tous les types'),
                      ),
                      ...productTypes.entries.map(
                        (e) => DropdownMenuItem(
                          value: e.key,
                          child: Text(e.value),
                        ),
                      ),
                    ],
                    onChanged: (value) {
                      _productType = value ?? '';
                      _load();
                    },
                  ),
                ),
              if (_tabs.index == 1)
                SizedBox(
                  width: 240,
                  child: DropdownButtonFormField<String>(
                    initialValue: _referenceType,
                    decoration: const InputDecoration(labelText: 'Type'),
                    items: [
                      const DropdownMenuItem(
                        value: '',
                        child: Text('Tous les référentiels'),
                      ),
                      ...referenceTypes.entries.map(
                        (e) => DropdownMenuItem(
                          value: e.key,
                          child: Text(e.value),
                        ),
                      ),
                    ],
                    onChanged: (value) {
                      _referenceType = value ?? '';
                      _load();
                    },
                  ),
                ),
            ],
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
            _MessageCard(icon: Icons.error_outline, text: _error!)
          else if (_organizationId == null)
            const _MessageCard(
              icon: Icons.business_outlined,
              text: 'Aucune organisation accessible.',
            )
          else if (_items.isEmpty)
            const _MessageCard(
              icon: Icons.inventory_2_outlined,
              text: 'Aucun élément ne correspond aux critères.',
            )
          else
            ..._items.map(_itemCard),
        ],
      ),
    ),
  );

  Widget _itemCard(Map<String, dynamic> item) {
    final subtitle = switch (_tabs.index) {
      0 =>
        '${item['code']} • ${productTypes[item['product_type']] ?? item['product_type']}${item['generic_name']?.toString().isNotEmpty == true ? ' • ${item['generic_name']}' : ''}',
      1 =>
        '${item['code']} • ${referenceTypes[item['reference_type']] ?? item['reference_type']}',
      _ =>
        '${item['code']} • Version ${item['latest_version']?['version_number'] ?? 1}',
    };
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
                  child: Icon(
                    _tabs.index == 0
                        ? Icons.medication_outlined
                        : _tabs.index == 1
                        ? Icons.list_alt_outlined
                        : Icons.fact_check_outlined,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${item['name']}',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 3),
                      Text(subtitle),
                    ],
                  ),
                ),
                if (item['_sync_status'] != null) ...[
                  const SizedBox(width: 8),
                  _SyncStatusBadge(status: '${item['_sync_status']}'),
                ],
              ],
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
                      onPressed: () => _openForm(item),
                    ),
                  if (_tabs.index == 2 &&
                      _status != 'archived' &&
                      _canPublish &&
                      _latestVersion(item)?['status'] == 'draft')
                    AppButton.validate(
                      label: 'Publier',
                      compact: true,
                      onPressed: () => _publish(item),
                    ),
                  AppButton.archive(
                    label: _status == 'archived' ? 'Restaurer' : 'Archiver',
                    compact: true,
                    onPressed: () => _confirmAction(item),
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

class _SyncStatusBadge extends StatelessWidget {
  const _SyncStatusBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final (label, icon, color) = switch (status) {
      'conflict' => ('Conflit', Icons.warning_amber_rounded, Colors.deepOrange),
      'failed' => ('Échec', Icons.error_outline_rounded, Colors.red),
      'synced' => ('Synchronisé', Icons.cloud_done_outlined, Colors.green),
      _ => ('En attente', Icons.cloud_upload_outlined, Colors.orange),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: color),
          const SizedBox(width: 5),
          Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _MessageCard extends StatelessWidget {
  const _MessageCard({required this.icon, required this.text});
  final IconData icon;
  final String text;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        children: [
          Icon(icon, size: 38),
          const SizedBox(height: 10),
          Text(text, textAlign: TextAlign.center),
        ],
      ),
    ),
  );
}
