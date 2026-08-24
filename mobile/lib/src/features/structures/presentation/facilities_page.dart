import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../data/structure_service.dart';

class FacilitiesPage extends StatefulWidget {
  const FacilitiesPage({
    required this.organizationId,
    required this.organizationName,
    super.key,
  });

  final String organizationId;
  final String organizationName;

  @override
  State<FacilitiesPage> createState() => _FacilitiesPageState();
}

class _FacilitiesPageState extends State<FacilitiesPage>
    with SingleTickerProviderStateMixin {
  final _service = StructureService();
  final _search = TextEditingController();
  late final TabController _tabs;
  List<Map<String, dynamic>> _active = [];
  List<Map<String, dynamic>> _archived = [];
  bool _loading = true;
  bool _offline = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    _load();
  }

  @override
  void dispose() {
    _tabs.dispose();
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final result = await _service.list(
        widget.organizationId,
        search: _search.text.trim(),
      );
      final pagination =
          result['facilities'] as Map<String, dynamic>? ?? const {};
      if (!mounted) return;
      setState(() {
        _active = (pagination['data'] as List<dynamic>? ?? [])
            .cast<Map<String, dynamic>>();
        _archived =
            (result['archived_facilities'] as List<dynamic>? ?? [])
                .cast<Map<String, dynamic>>();
        _offline = result['offline'] == true;
      });
    } on DioException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.response?.statusCode == 403
              ? 'Vous n’avez pas accès aux formations sanitaires.'
              : 'Impossible de charger les formations sanitaires.';
        });
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openFacilityForm([Map<String, dynamic>? facility]) async {
    final saved = await showAppFormSheet<bool>(
      context: context,
      title: facility == null
          ? 'Nouvelle formation sanitaire'
          : 'Modifier la formation sanitaire',
      description:
          'Informations administratives et niveau de prise en charge.',
      builder: (sheetContext) => _FacilityForm(
        organizationId: widget.organizationId,
        facility: facility,
        service: _service,
      ),
    );
    if (saved == true) {
      _success(
        facility == null
            ? 'Formation sanitaire créée.'
            : 'Formation sanitaire modifiée.',
      );
      await _load();
    }
  }

  Future<void> _archive(Map<String, dynamic> facility) async {
    final confirmed = await _confirm(
      title: 'Archiver cette formation ?',
      message:
          '${facility['name']} sera déplacée dans les formations archivées. Ses données seront conservées.',
      action: 'Archiver',
    );
    if (!confirmed) return;
    await _run(
      () => _service.archiveFacility(
        widget.organizationId,
        facility['id'] as String,
      ),
      'Formation sanitaire archivée.',
    );
  }

  Future<void> _restore(Map<String, dynamic> facility) async {
    final confirmed = await _confirm(
      title: 'Restaurer cette formation ?',
      message: '${facility['name']} réapparaîtra dans la liste active.',
      action: 'Restaurer',
    );
    if (!confirmed) return;
    await _run(
      () => _service.restoreFacility(
        widget.organizationId,
        facility['id'] as String,
      ),
      'Formation sanitaire restaurée.',
    );
  }

  Future<void> _run(Future<void> Function() action, String message) async {
    try {
      await action();
      _success(message);
      await _load();
    } on DioException catch (error) {
      if (!mounted) return;
      _success(
        error.response?.statusCode == 403
            ? 'Action non autorisée pour votre rôle.'
            : 'L’action n’a pas pu être effectuée.',
        error: true,
      );
    }
  }

  Future<bool> _confirm({
    required String title,
    required String message,
    required String action,
  }) async {
    return await showAppDialogAsFormSheet<bool>(
          context: context,
          builder: (context) => AlertDialog(
            icon: const Icon(
              Icons.archive_outlined,
              color: AppTheme.red,
              size: 34,
            ),
            title: Text(title),
            content: Text(message),
            actions: [
              AppButton.cancel(
                compact: true,
                onPressed: () => Navigator.pop(context, false),
              ),
              AppButton.archive(
                compact: true,
                label: action,
                onPressed: () => Navigator.pop(context, true),
              ),
            ],
          ),
        ) ??
        false;
  }

  void _success(String message, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: error ? AppTheme.red : AppTheme.green,
        content: Text(message),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Formations sanitaires'),
            Text(
              widget.organizationName,
              style: const TextStyle(
                color: AppTheme.muted,
                fontSize: 11,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
        bottom: TabBar(
          controller: _tabs,
          tabs: [
            Tab(text: 'Actives (${_active.length})'),
            Tab(text: 'Archivées (${_archived.length})'),
          ],
        ),
      ),
      floatingActionButton: _offline
          ? null
          : AppFab(
              tooltip: 'Ajouter une formation sanitaire',
              onPressed: _openFacilityForm,
            ),
      body: Column(
        children: [
          if (_offline)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
              color: AppTheme.blue.withValues(alpha: .09),
              child: const Row(
                children: [
                  Icon(
                    Icons.cloud_off_rounded,
                    color: AppTheme.blue,
                    size: 18,
                  ),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Consultation hors connexion — modifications désactivées.',
                      style: TextStyle(
                        color: AppTheme.blue,
                        fontWeight: FontWeight.w600,
                        fontSize: 12,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 10),
            child: SearchBar(
              controller: _search,
              hintText: 'Rechercher par nom ou code',
              leading: const Icon(Icons.search_rounded),
              trailing: [
                AppIconAction(
                  icon: Icons.arrow_forward_rounded,
                  tooltip: 'Rechercher',
                  color: AppActionColor.blue,
                  onPressed: _load,
                ),
              ],
              onSubmitted: (_) => _load(),
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                ? _EmptyState(
                    icon: Icons.cloud_off_rounded,
                    title: 'Chargement impossible',
                    message: _error!,
                    action: AppButton.text(
                      label: 'Réessayer',
                      icon: Icons.refresh_rounded,
                      onPressed: _load,
                    ),
                  )
                : TabBarView(
                    controller: _tabs,
                    children: [
                      _FacilityList(
                        facilities: _active,
                        emptyMessage:
                            'Aucune formation sanitaire active pour cette organisation.',
                        onTap: (facility) async {
                          await Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => FacilityDetailsPage(
                                organizationId: widget.organizationId,
                                facility: facility,
                                service: _service,
                              ),
                            ),
                          );
                          await _load();
                        },
                        onEdit: _openFacilityForm,
                        onArchive: _archive,
                      ),
                      _FacilityList(
                        facilities: _archived,
                        emptyMessage: 'Aucune formation sanitaire archivée.',
                        archived: true,
                        onRestore: _restore,
                      ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}

class _FacilityForm extends StatefulWidget {
  const _FacilityForm({
    required this.organizationId,
    required this.service,
    this.facility,
  });

  final String organizationId;
  final StructureService service;
  final Map<String, dynamic>? facility;

  @override
  State<_FacilityForm> createState() => _FacilityFormState();
}

class _FacilityFormState extends State<_FacilityForm> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _code;
  late final TextEditingController _name;
  late final TextEditingController _careLevel;
  late final TextEditingController _email;
  late final TextEditingController _phone;
  late final TextEditingController _address;
  late String _type;
  bool _saving = false;

  static const _types = {
    'hospital': 'Hôpital',
    'health_center': 'Centre de santé',
    'clinic': 'Clinique',
    'warehouse': 'Dépôt sanitaire',
    'community': 'Structure communautaire',
    'other': 'Autre',
  };

  @override
  void initState() {
    super.initState();
    final value = widget.facility ?? const <String, dynamic>{};
    _code = TextEditingController(text: '${value['code'] ?? ''}');
    _name = TextEditingController(text: '${value['name'] ?? ''}');
    _careLevel = TextEditingController(text: '${value['care_level'] ?? ''}');
    _email = TextEditingController(text: '${value['email'] ?? ''}');
    _phone = TextEditingController(text: '${value['phone'] ?? ''}');
    _address = TextEditingController(text: '${value['address'] ?? ''}');
    _type = '${value['facility_type'] ?? 'health_center'}';
  }

  @override
  void dispose() {
    _code.dispose();
    _name.dispose();
    _careLevel.dispose();
    _email.dispose();
    _phone.dispose();
    _address.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _saving = true);
    try {
      await widget.service.saveFacility(
        organizationId: widget.organizationId,
        facilityId: widget.facility?['id'] as String?,
        data: {
          'code': _code.text.trim(),
          'name': _name.text.trim(),
          'facility_type': _type,
          'care_level': _careLevel.text.trim(),
          'email': _email.text.trim(),
          'phone': _phone.text.trim(),
          'address': _address.text.trim(),
          'is_active': true,
        },
      );
      if (mounted) Navigator.pop(context, true);
    } on DioException catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: AppTheme.red,
          content: Text(
            error.response?.statusCode == 422
                ? 'Vérifiez les informations et l’unicité du code.'
                : 'Enregistrement impossible.',
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Form(
        key: _formKey,
        child: Column(
          children: [
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _code,
                    decoration: const InputDecoration(
                      labelText: 'Code établissement *',
                      prefixIcon: Icon(Icons.tag_rounded),
                    ),
                    validator: _required,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: DropdownButtonFormField<String>(
                    initialValue: _type,
                    decoration: const InputDecoration(
                      labelText: 'Type *',
                      prefixIcon: Icon(Icons.local_hospital_outlined),
                    ),
                    items: _types.entries
                        .map(
                          (entry) => DropdownMenuItem(
                            value: entry.key,
                            child: Text(entry.value),
                          ),
                        )
                        .toList(),
                    onChanged: (value) => _type = value ?? _type,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _name,
              decoration: const InputDecoration(
                labelText: 'Nom officiel *',
                prefixIcon: Icon(Icons.business_outlined),
              ),
              validator: _required,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _careLevel,
              decoration: const InputDecoration(
                labelText: 'Niveau de soins',
                hintText: 'Primaire, secondaire, communautaire…',
                prefixIcon: Icon(Icons.health_and_safety_outlined),
              ),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(
                labelText: 'Adresse e-mail',
                prefixIcon: Icon(Icons.email_outlined),
              ),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                labelText: 'Téléphone',
                prefixIcon: Icon(Icons.phone_outlined),
              ),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _address,
              minLines: 2,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Adresse / localisation',
                prefixIcon: Icon(Icons.location_on_outlined),
              ),
            ),
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
                  child: AppButton.save(
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

  String? _required(String? value) =>
      value == null || value.trim().isEmpty ? 'Champ obligatoire' : null;
}

class FacilityDetailsPage extends StatefulWidget {
  const FacilityDetailsPage({
    required this.organizationId,
    required this.facility,
    required this.service,
    super.key,
  });

  final String organizationId;
  final Map<String, dynamic> facility;
  final StructureService service;

  @override
  State<FacilityDetailsPage> createState() => _FacilityDetailsPageState();
}

class _FacilityDetailsPageState extends State<FacilityDetailsPage> {
  late Map<String, dynamic> _facility;

  @override
  void initState() {
    super.initState();
    _facility = widget.facility;
  }

  Future<void> _refresh() async {
    final data = await widget.service.list(widget.organizationId);
    final values =
        ((data['facilities'] as Map<String, dynamic>?)?['data']
                    as List<dynamic>? ??
                [])
            .cast<Map<String, dynamic>>();
    final current = values.where((item) => item['id'] == _facility['id']);
    if (mounted && current.isNotEmpty) {
      setState(() => _facility = current.first);
    }
  }

  Future<void> _openChild(String kind, [Map<String, dynamic>? existing]) async {
    final saved = await showAppFormSheet<bool>(
      context: context,
      title: existing == null
          ? 'Ajouter ${_kindLabel(kind).toLowerCase()}'
          : 'Modifier ${_kindLabel(kind).toLowerCase()}',
      description: 'Cette structure sera rattachée à ${_facility['name']}.',
      builder: (_) => _ChildForm(
        organizationId: widget.organizationId,
        facilityId: _facility['id'] as String,
        kind: kind,
        existing: existing,
        service: widget.service,
      ),
    );
    if (saved == true) await _refresh();
  }

  Future<void> _archiveChild(
    String kind,
    Map<String, dynamic> child,
  ) async {
    final confirmed =
        await showAppDialogAsFormSheet<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: Text('Archiver ${child['name']} ?'),
            content: const Text(
              'Les données seront conservées et pourront être restaurées.',
            ),
            actions: [
              AppButton.cancel(
                compact: true,
                onPressed: () => Navigator.pop(context, false),
              ),
              AppButton.archive(
                compact: true,
                onPressed: () => Navigator.pop(context, true),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed) return;
    await widget.service.archiveChild(
      organizationId: widget.organizationId,
      facilityId: _facility['id'] as String,
      kind: kind,
      childId: child['id'] as String,
    );
    await _refresh();
  }

  Future<void> _restoreChild(
    String kind,
    Map<String, dynamic> child,
  ) async {
    await widget.service.restoreChild(
      organizationId: widget.organizationId,
      facilityId: _facility['id'] as String,
      kind: kind,
      childId: child['id'] as String,
    );
    await _refresh();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('${_facility['name']}')),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 30),
          children: [
            _FacilityHeader(facility: _facility),
            const SizedBox(height: 18),
            _ChildSection(
              title: 'Départements',
              icon: Icons.account_tree_outlined,
              color: AppTheme.blue,
              values: _list('departments'),
              archivedValues: _list('archived_departments'),
              onAdd: () => _openChild('department'),
              onEdit: (value) => _openChild('department', value),
              onArchive: (value) => _archiveChild('department', value),
              onRestore: (value) => _restoreChild('department', value),
            ),
            const SizedBox(height: 14),
            _ChildSection(
              title: 'Pharmacies',
              icon: Icons.local_pharmacy_outlined,
              color: AppTheme.green,
              values: _list('pharmacies'),
              archivedValues: _list('archived_pharmacies'),
              onAdd: () => _openChild('pharmacy'),
              onEdit: (value) => _openChild('pharmacy', value),
              onArchive: (value) => _archiveChild('pharmacy', value),
              onRestore: (value) => _restoreChild('pharmacy', value),
            ),
            const SizedBox(height: 14),
            _ChildSection(
              title: 'Sites de stockage et dispensation',
              icon: Icons.inventory_2_outlined,
              color: AppTheme.purple,
              values: _list('sites'),
              archivedValues: _list('archived_sites'),
              onAdd: () => _openChild('site'),
              onEdit: (value) => _openChild('site', value),
              onArchive: (value) => _archiveChild('site', value),
              onRestore: (value) => _restoreChild('site', value),
            ),
          ],
        ),
      ),
    );
  }

  List<Map<String, dynamic>> _list(String key) =>
      (_facility[key] as List<dynamic>? ?? []).cast<Map<String, dynamic>>();
}

class _ChildForm extends StatefulWidget {
  const _ChildForm({
    required this.organizationId,
    required this.facilityId,
    required this.kind,
    required this.service,
    this.existing,
  });

  final String organizationId;
  final String facilityId;
  final String kind;
  final StructureService service;
  final Map<String, dynamic>? existing;

  @override
  State<_ChildForm> createState() => _ChildFormState();
}

class _ChildFormState extends State<_ChildForm> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _code;
  late final TextEditingController _name;
  late final TextEditingController _location;
  late String _type;
  bool _saving = false;

  Map<String, String> get _types => switch (widget.kind) {
    'department' => {
      'clinical': 'Clinique',
      'pharmacy': 'Pharmacie',
      'laboratory': 'Laboratoire',
      'logistics': 'Logistique',
      'administration': 'Administration',
      'other': 'Autre',
    },
    'pharmacy' => {
      'central': 'Centrale',
      'hospital': 'Hospitalière',
      'dispensary': 'Dispensaire',
      'community': 'Communautaire',
      'other': 'Autre',
    },
    _ => {
      'stock': 'Stockage',
      'dispensing': 'Dispensation',
      'stock_and_dispensing': 'Stockage et dispensation',
      'quarantine': 'Quarantaine',
      'other': 'Autre',
    },
  };

  String get _typeField => switch (widget.kind) {
    'department' => 'department_type',
    'pharmacy' => 'pharmacy_type',
    _ => 'site_type',
  };

  @override
  void initState() {
    super.initState();
    final value = widget.existing ?? const <String, dynamic>{};
    _code = TextEditingController(text: '${value['code'] ?? ''}');
    _name = TextEditingController(text: '${value['name'] ?? ''}');
    _location = TextEditingController(text: '${value['location'] ?? ''}');
    _type = '${value[_typeField] ?? _types.keys.first}';
  }

  @override
  void dispose() {
    _code.dispose();
    _name.dispose();
    _location.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _saving = true);
    try {
      await widget.service.saveChild(
        organizationId: widget.organizationId,
        facilityId: widget.facilityId,
        kind: widget.kind,
        childId: widget.existing?['id'] as String?,
        data: {
          'code': _code.text.trim(),
          'name': _name.text.trim(),
          _typeField: _type,
          if (widget.kind == 'site') 'location': _location.text.trim(),
          'is_active': true,
        },
      );
      if (mounted) Navigator.pop(context, true);
    } on DioException catch (_) {
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          backgroundColor: AppTheme.red,
          content: Text('Enregistrement impossible. Vérifiez le code.'),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Form(
        key: _formKey,
        child: Column(
          children: [
            TextFormField(
              controller: _code,
              decoration: const InputDecoration(
                labelText: 'Code *',
                prefixIcon: Icon(Icons.tag_rounded),
              ),
              validator: _required,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _name,
              decoration: const InputDecoration(
                labelText: 'Nom *',
                prefixIcon: Icon(Icons.badge_outlined),
              ),
              validator: _required,
            ),
            const SizedBox(height: 14),
            DropdownButtonFormField<String>(
              initialValue: _type,
              decoration: const InputDecoration(
                labelText: 'Type *',
                prefixIcon: Icon(Icons.category_outlined),
              ),
              items: _types.entries
                  .map(
                    (entry) => DropdownMenuItem(
                      value: entry.key,
                      child: Text(entry.value),
                    ),
                  )
                  .toList(),
              onChanged: (value) => _type = value ?? _type,
            ),
            if (widget.kind == 'site') ...[
              const SizedBox(height: 14),
              TextFormField(
                controller: _location,
                decoration: const InputDecoration(
                  labelText: 'Localisation',
                  prefixIcon: Icon(Icons.location_on_outlined),
                ),
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
                  child: AppButton.save(
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

  String? _required(String? value) =>
      value == null || value.trim().isEmpty ? 'Champ obligatoire' : null;
}

class _FacilityList extends StatelessWidget {
  const _FacilityList({
    required this.facilities,
    required this.emptyMessage,
    this.archived = false,
    this.onTap,
    this.onEdit,
    this.onArchive,
    this.onRestore,
  });

  final List<Map<String, dynamic>> facilities;
  final String emptyMessage;
  final bool archived;
  final ValueChanged<Map<String, dynamic>>? onTap;
  final ValueChanged<Map<String, dynamic>>? onEdit;
  final ValueChanged<Map<String, dynamic>>? onArchive;
  final ValueChanged<Map<String, dynamic>>? onRestore;

  @override
  Widget build(BuildContext context) {
    if (facilities.isEmpty) {
      return _EmptyState(
        icon: archived
            ? Icons.inventory_2_outlined
            : Icons.local_hospital_outlined,
        title: archived ? 'Aucune archive' : 'Aucune formation sanitaire',
        message: emptyMessage,
      );
    }
    return RefreshIndicator(
      onRefresh: () async {},
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
        itemCount: facilities.length,
        separatorBuilder: (_, _) => const SizedBox(height: 12),
        itemBuilder: (context, index) {
          final facility = facilities[index];
          return _FacilityCard(
            facility: facility,
            archived: archived,
            onTap: onTap == null ? null : () => onTap!(facility),
            onEdit: onEdit == null ? null : () => onEdit!(facility),
            onArchive: onArchive == null ? null : () => onArchive!(facility),
            onRestore: onRestore == null ? null : () => onRestore!(facility),
          );
        },
      ),
    );
  }
}

class _FacilityCard extends StatelessWidget {
  const _FacilityCard({
    required this.facility,
    required this.archived,
    this.onTap,
    this.onEdit,
    this.onArchive,
    this.onRestore,
  });

  final Map<String, dynamic> facility;
  final bool archived;
  final VoidCallback? onTap;
  final VoidCallback? onEdit;
  final VoidCallback? onArchive;
  final VoidCallback? onRestore;

  @override
  Widget build(BuildContext context) {
    final projects = facility['projects'] as List<dynamic>? ?? [];
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              Row(
                children: [
                  Container(
                    width: 50,
                    height: 50,
                    decoration: BoxDecoration(
                      color: AppTheme.blue.withValues(alpha: .10),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: const Icon(
                      Icons.local_hospital_outlined,
                      color: AppTheme.blue,
                    ),
                  ),
                  const SizedBox(width: 13),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${facility['name']}',
                          style: const TextStyle(
                            color: AppTheme.ink,
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${facility['code']} • ${_facilityType('${facility['facility_type']}')}',
                          style: const TextStyle(
                            color: AppTheme.muted,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (onTap != null)
                    const Icon(
                      Icons.chevron_right_rounded,
                      color: AppTheme.gray,
                    ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  _CountChip(
                    icon: Icons.account_tree_outlined,
                    label: '${_count(facility, 'departments')} départ.',
                    color: AppTheme.blue,
                  ),
                  const SizedBox(width: 7),
                  _CountChip(
                    icon: Icons.local_pharmacy_outlined,
                    label: '${_count(facility, 'pharmacies')} pharm.',
                    color: AppTheme.green,
                  ),
                  const SizedBox(width: 7),
                  _CountChip(
                    icon: Icons.inventory_2_outlined,
                    label: '${_count(facility, 'sites')} sites',
                    color: AppTheme.purple,
                  ),
                ],
              ),
              if (projects.isNotEmpty) ...[
                const SizedBox(height: 10),
                Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Projet : ${projects.map((item) => item['name']).join(', ')}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: AppTheme.muted,
                      fontSize: 11,
                    ),
                  ),
                ),
              ],
              const Divider(height: 22),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: archived
                    ? [
                        AppButton.validate(
                          compact: true,
                          label: 'Restaurer',
                          icon: Icons.restore_rounded,
                          onPressed: onRestore,
                        ),
                      ]
                    : [
                        AppButton.edit(
                          compact: true,
                          onPressed: onEdit,
                        ),
                        const SizedBox(width: 8),
                        AppButton.archive(
                          compact: true,
                          onPressed: onArchive,
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

class _FacilityHeader extends StatelessWidget {
  const _FacilityHeader({required this.facility});
  final Map<String, dynamic> facility;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [AppTheme.blue, Color(0xFF4F86F7)],
        ),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(
            Icons.local_hospital_rounded,
            color: Colors.white,
            size: 34,
          ),
          const SizedBox(height: 12),
          Text(
            '${facility['name']}',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 20,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            '${facility['code']} • ${_facilityType('${facility['facility_type']}')}'
            '${facility['care_level'] == null || '${facility['care_level']}'.isEmpty ? '' : ' • ${facility['care_level']}'}',
            style: const TextStyle(color: Colors.white, fontSize: 12),
          ),
        ],
      ),
    );
  }
}

class _ChildSection extends StatelessWidget {
  const _ChildSection({
    required this.title,
    required this.icon,
    required this.color,
    required this.values,
    required this.archivedValues,
    required this.onAdd,
    required this.onEdit,
    required this.onArchive,
    required this.onRestore,
  });

  final String title;
  final IconData icon;
  final Color color;
  final List<Map<String, dynamic>> values;
  final List<Map<String, dynamic>> archivedValues;
  final VoidCallback onAdd;
  final ValueChanged<Map<String, dynamic>> onEdit;
  final ValueChanged<Map<String, dynamic>> onArchive;
  final ValueChanged<Map<String, dynamic>> onRestore;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(
          children: [
            Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: .10),
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child: Icon(icon, color: color, size: 21),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    '$title (${values.length})',
                    style: const TextStyle(
                      color: AppTheme.ink,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                AppIconAction(
                  icon: Icons.add_rounded,
                  tooltip: 'Ajouter',
                  color: AppActionColor.orange,
                  onPressed: onAdd,
                ),
              ],
            ),
            if (values.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 16),
                child: Text(
                  'Aucun élément enregistré.',
                  style: TextStyle(color: AppTheme.muted),
                ),
              )
            else
              for (final value in values)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(icon, color: color, size: 20),
                  title: Text(
                    '${value['name']}',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  subtitle: Text('${value['code']}'),
                  trailing: PopupMenuButton<String>(
                    tooltip: 'Actions',
                    onSelected: (action) => action == 'edit'
                        ? onEdit(value)
                        : onArchive(value),
                    itemBuilder: (_) => const [
                      PopupMenuItem(
                        value: 'edit',
                        child: ListTile(
                          leading: Icon(
                            Icons.edit_outlined,
                            color: AppTheme.orange,
                          ),
                          title: Text('Modifier'),
                        ),
                      ),
                      PopupMenuItem(
                        value: 'archive',
                        child: ListTile(
                          leading: Icon(
                            Icons.archive_outlined,
                            color: AppTheme.red,
                          ),
                          title: Text('Archiver'),
                        ),
                      ),
                    ],
                  ),
                ),
            if (archivedValues.isNotEmpty) ...[
              const Divider(),
              ExpansionTile(
                tilePadding: EdgeInsets.zero,
                leading: const Icon(
                  Icons.inventory_2_outlined,
                  color: AppTheme.gray,
                ),
                title: Text(
                  'Archivés (${archivedValues.length})',
                  style: const TextStyle(
                    color: AppTheme.muted,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                children: [
                  for (final value in archivedValues)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text('${value['name']}'),
                      subtitle: Text('${value['code']}'),
                      trailing: AppIconAction(
                        icon: Icons.restore_rounded,
                        tooltip: 'Restaurer',
                        color: AppActionColor.green,
                        onPressed: () => onRestore(value),
                      ),
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

class _CountChip extends StatelessWidget {
  const _CountChip({
    required this.icon,
    required this.label,
    required this.color,
  });
  final IconData icon;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 7),
        decoration: BoxDecoration(
          color: color.withValues(alpha: .08),
          borderRadius: BorderRadius.circular(9),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 14, color: color),
            const SizedBox(width: 4),
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: color,
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.title,
    required this.message,
    this.action,
  });
  final IconData icon;
  final String title;
  final String message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(32),
      children: [
        const SizedBox(height: 45),
        Icon(icon, size: 52, color: AppTheme.gray),
        const SizedBox(height: 14),
        Text(
          title,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: AppTheme.ink,
            fontWeight: FontWeight.w800,
            fontSize: 17,
          ),
        ),
        const SizedBox(height: 7),
        Text(
          message,
          textAlign: TextAlign.center,
          style: const TextStyle(color: AppTheme.muted),
        ),
        if (action != null) ...[const SizedBox(height: 18), Center(child: action)],
      ],
    );
  }
}

int _count(Map<String, dynamic> value, String key) =>
    (value[key] as List<dynamic>? ?? []).length;

String _facilityType(String type) => const {
      'hospital': 'Hôpital',
      'health_center': 'Centre de santé',
      'clinic': 'Clinique',
      'warehouse': 'Dépôt sanitaire',
      'community': 'Structure communautaire',
      'other': 'Autre',
    }[type] ??
    type;

String _kindLabel(String kind) => const {
      'department': 'Département',
      'pharmacy': 'Pharmacie',
      'site': 'Site',
    }[kind] ??
    kind;
