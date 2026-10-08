import '../../../core/catalog/care_level_paths.dart';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/access/application_access.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../auth/data/auth_service.dart';
import '../../organizations/data/organization_service.dart';
import '../data/structure_service.dart';

@visibleForTesting
int? facilityFormFirstInvalidStep({
  required String code,
  required String name,
  required String? careLevel,
  required String facilityType,
}) {
  if (code.trim().isEmpty || name.trim().isEmpty) return 0;
  if (careLevel == null ||
      !_FacilityFormState._types.containsKey(facilityType)) {
    return 1;
  }
  return null;
}

Future<String?> showDispensingSiteFormSheet({
  required BuildContext context,
  required String organizationId,
  required Map<String, dynamic> facility,
  required StructureService service,
}) => showAppFormSheet<String>(
  context: context,
  title: 'Ajouter un point de dispensation',
  description: 'Ce site sera rattaché à ${facility['name']}.',
  builder: (_) => _ChildForm(
    organizationId: organizationId,
    facilityId: '${facility['id']}',
    kind: 'site',
    service: service,
  ),
);

class FacilitiesPage extends StatefulWidget {
  const FacilitiesPage({
    required this.organizationId,
    required this.organizationName,
    this.scopedProject,
    super.key,
  });

  final String organizationId;
  final String organizationName;
  final Map<String, dynamic>? scopedProject;

  @override
  State<FacilitiesPage> createState() => _FacilitiesPageState();
}

class _FacilitiesPageState extends State<FacilitiesPage>
    with SingleTickerProviderStateMixin {
  final _service = StructureService();
  final _organizationService = OrganizationService();
  final _search = TextEditingController();
  late final TabController _tabs;
  List<Map<String, dynamic>> _active = [];
  List<Map<String, dynamic>> _archived = [];
  List<Map<String, dynamic>> _missions = [];
  List<Map<String, dynamic>> _projects = [];
  bool _loading = true;
  bool _offline = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    AuthService().cachedUser().then((user) {
      if (mounted) {
        setState(
          () => _canManage = ApplicationAccess.allows(
            user,
            'health_facilities.manage',
          ),
        );
      }
    });
    _load();
  }

  /// Ajout réservé aux comptes qui gèrent les formations sanitaires.
  bool _canManage = false;

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
      final values = await Future.wait([
        _service.list(widget.organizationId, search: _search.text.trim()),
        _organizationService.missions(widget.organizationId),
        _organizationService.projects(organizationId: widget.organizationId),
      ]);
      final result = values[0] as Map<String, dynamic>;
      final pagination =
          result['facilities'] as Map<String, dynamic>? ?? const {};
      if (!mounted) return;
      setState(() {
        _active = (pagination['data'] as List<dynamic>? ?? [])
            .cast<Map<String, dynamic>>();
        _archived = (result['archived_facilities'] as List<dynamic>? ?? [])
            .cast<Map<String, dynamic>>();
        _offline = result['offline'] == true;
        _missions = values[1] as List<Map<String, dynamic>>;
        _projects = values[2] as List<Map<String, dynamic>>;
        if (widget.scopedProject != null &&
            !_projects.any(
              (item) => '${item['id']}' == '${widget.scopedProject!['id']}',
            )) {
          _projects = [widget.scopedProject!, ..._projects];
        }
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
    Map<String, dynamic>? v1Options;
    if (widget.scopedProject != null) {
      try {
        v1Options = await _service.facilityOptions(widget.organizationId);
      } catch (_) {
        v1Options = null;
      }
      if (!mounted) return;
      if (v1Options == null) {
        _success(
          'Les choix de la FOSA ne sont pas encore disponibles hors ligne : connectez-vous une première fois.',
        );
        return;
      }
    }
    if (!mounted) return;
    final saved = await showAppFormSheet<String>(
      context: context,
      title: facility == null
          ? 'Nouvelle formation sanitaire'
          : 'Modifier la formation sanitaire',
      description: 'Informations administratives et niveau de prise en charge.',
      builder: (sheetContext) => _FacilityForm(
        organizationId: widget.organizationId,
        facility: facility,
        service: _service,
        missions: _missions,
        projects: _projects,
        v1Options: v1Options,
      ),
    );
    if (saved != null) {
      _success(
        saved == 'pending'
            ? 'Formation enregistrée hors connexion. Synchronisation en attente.'
            : facility == null
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

  Future<void> _run(Future<Object?> Function() action, String message) async {
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
            icon: Icon(
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
              style: TextStyle(
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
      floatingActionButton: _canManage
          ? AppFab(
              tooltip: 'Ajouter une formation sanitaire',
              onPressed: _openFacilityForm,
            )
          : null,
      body: Column(
        children: [
          if (_offline)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
              color: AppTheme.blue.withValues(alpha: .09),
              child: Row(
                children: [
                  Icon(Icons.cloud_off_rounded, color: AppTheme.blue, size: 18),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Mode hors connexion — les modifications seront synchronisées automatiquement.',
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
    required this.missions,
    required this.projects,
    this.facility,
    this.v1Options,
  });

  final String organizationId;
  final StructureService service;
  final List<Map<String, dynamic>> missions;
  final List<Map<String, dynamic>> projects;
  final Map<String, dynamic>? facility;

  /// Admin Projet (V1) : choix issus de la configuration validée du projet.
  final Map<String, dynamic>? v1Options;

  @override
  State<_FacilityForm> createState() => _FacilityFormState();
}

class _FacilityFormState extends State<_FacilityForm> {
  final _informationKey = GlobalKey<FormState>();
  final _classificationKey = GlobalKey<FormState>();
  final _locationKey = GlobalKey<FormState>();
  final _pages = PageController();
  int _step = 0;
  late final TextEditingController _code;
  late final TextEditingController _name;
  String? _careLevel;
  late final TextEditingController _email;
  late final TextEditingController _phone;
  late final TextEditingController _address;
  late final TextEditingController _region;
  late final TextEditingController _district;
  late final TextEditingController _locality;
  late final TextEditingController _latitude;
  late final TextEditingController _longitude;
  late String _type;
  String? _missionId;
  late Set<String> _projectIds;
  bool _isActive = true;
  bool _saving = false;
  late final String _initialDraft;
  // AM-162 — Champs V1 de l'Admin Projet.
  String? _careLevelId;
  String? _categoryId;
  final Set<String> _populationIds = {};
  final Set<String> _pathologyIds = {};
  int? _orderPeriod;
  int? _leadTime;
  double? _safetyStock;
  DateTime? _inventoryDate;
  DateTime? _submissionDate;
  DateTime? _receiptDate;

  bool get _v1 => widget.v1Options != null;

  static const _safetyStockOptions = [0.25, 0.5, 0.75, 1.0, 1.5, 2.0];

  List<Map<String, dynamic>> _option(String key) =>
      ((widget.v1Options?[key] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList(growable: false);

  String get _draft => jsonEncode([
    _code.text,
    _name.text,
    _careLevel,
    _email.text,
    _phone.text,
    _address.text,
    _region.text,
    _district.text,
    _locality.text,
    _latitude.text,
    _longitude.text,
    _type,
    _missionId,
    _projectIds.toList()..sort(),
    _isActive,
  ]);

  static const _types = {
    'hospital': 'Hôpital',
    'health_center': 'Centre de santé',
    'clinic': 'Clinique',
    'warehouse': 'Dépôt sanitaire',
    'community': 'Structure communautaire',
    'other': 'Autre',
  };
  static const _careLevels = {
    'primary': 'Primaire',
    'secondary': 'Secondaire',
    'tertiary': 'Tertiaire',
    'national': 'National',
  };

  @override
  void initState() {
    super.initState();
    final value = widget.facility ?? const <String, dynamic>{};
    _code = TextEditingController(text: '${value['code'] ?? ''}');
    _name = TextEditingController(text: '${value['name'] ?? ''}');
    final savedCareLevel = '${value['care_level'] ?? ''}';
    _careLevel = _careLevels.containsKey(savedCareLevel)
        ? savedCareLevel
        : null;
    _email = TextEditingController(text: '${value['email'] ?? ''}');
    _phone = TextEditingController(text: '${value['phone'] ?? ''}');
    _address = TextEditingController(text: '${value['address'] ?? ''}');
    _region = TextEditingController(text: '${value['region'] ?? ''}');
    _district = TextEditingController(text: '${value['district'] ?? ''}');
    _locality = TextEditingController(text: '${value['locality'] ?? ''}');
    _latitude = TextEditingController(text: '${value['latitude'] ?? ''}');
    _longitude = TextEditingController(text: '${value['longitude'] ?? ''}');
    _type = '${value['facility_type'] ?? 'health_center'}';
    final savedMissionId = value['mission_id']?.toString();
    _missionId =
        widget.missions.any((mission) => '${mission['id']}' == savedMissionId)
        ? savedMissionId
        : null;
    final availableProjectIds = widget.projects
        .map((project) => '${project['id']}')
        .toSet();
    _projectIds = (value['projects'] as List<dynamic>? ?? const [])
        .map((item) => '${(item as Map)['id']}')
        .where(availableProjectIds.contains)
        .toSet();
    if (widget.facility == null &&
        _projectIds.isEmpty &&
        widget.projects.length == 1) {
      _projectIds = {'${widget.projects.single['id']}'};
      _missionId ??= widget.projects.single['mission_id']?.toString();
    }
    _isActive = value['is_active'] != false;
    if (_v1) {
      final defaults = Map<String, dynamic>.from(
        (widget.v1Options!['supply_defaults'] as Map?) ?? const {},
      );
      _careLevelId = value['care_level_id']?.toString();
      _categoryId = value['facility_category_id']?.toString();
      _populationIds.addAll(
        ((value['target_populations'] as List?) ?? const []).map(
          (item) => '${(item as Map)['id']}',
        ),
      );
      _pathologyIds.addAll(
        ((value['pathologies'] as List?) ?? const []).map(
          (item) => '${(item as Map)['id']}',
        ),
      );
      _orderPeriod = int.tryParse(
        '${value['order_period_months'] ?? defaults['order_period_months'] ?? ''}',
      );
      _leadTime = int.tryParse(
        '${value['delivery_lead_time_months'] ?? defaults['delivery_lead_time_months'] ?? ''}',
      );
      final safety = double.tryParse(
        '${value['safety_stock_months'] ?? defaults['safety_stock_months'] ?? ''}',
      );
      _safetyStock = _safetyStockOptions.contains(safety) ? safety : null;
      _inventoryDate = DateTime.tryParse('${value['inventory_date'] ?? ''}');
      _submissionDate = DateTime.tryParse(
        '${value['order_submission_date'] ?? ''}',
      );
      _receiptDate = DateTime.tryParse('${value['order_receipt_date'] ?? ''}');
    }
    _initialDraft = _draft;
  }

  String get _selectedProjectNames => widget.projects
      .where((project) => _projectIds.contains('${project['id']}'))
      .map((project) => '${project['name']}')
      .where((name) => name.trim().isNotEmpty)
      .join(', ');

  @override
  void dispose() {
    _pages.dispose();
    _code.dispose();
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    _address.dispose();
    _region.dispose();
    _district.dispose();
    _locality.dispose();
    _latitude.dispose();
    _longitude.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_saving) return;

    final invalidStep = _firstInvalidStep();
    if (invalidStep != null) {
      await _goTo(invalidStep, validateCurrentStep: false);
      if (!mounted) return;
      _formKeyFor(invalidStep)?.currentState?.validate();
      return;
    }
    setState(() => _saving = true);
    try {
      final synchronized = await widget.service.saveFacility(
        organizationId: widget.organizationId,
        facilityId: widget.facility?['id'] as String?,
        data: {
          'code': _code.text.trim(),
          'name': _name.text.trim(),
          if (!_v1) 'facility_type': _type,
          if (!_v1) 'care_level': _careLevel,
          if (_v1) ..._v1Payload(),
          'email': _email.text.trim(),
          'phone': _phone.text.trim(),
          'address': _address.text.trim(),
          'region': _region.text.trim(),
          'district': _district.text.trim(),
          'locality': _locality.text.trim(),
          'latitude': double.tryParse(_latitude.text.replaceAll(',', '.')),
          'longitude': double.tryParse(_longitude.text.replaceAll(',', '.')),
          if (!_v1) 'mission_id': _missionId,
          if (!_v1) 'project_ids': _projectIds.toList(growable: false),
          'is_active': _isActive,
        },
      );
      if (mounted) {
        Navigator.pop(context, synchronized ? 'saved' : 'pending');
      }
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
    const labels = ['Informations', 'Classification', 'Localisation', 'Résumé'];
    return AppFormSheetGuard(
      isDirty: () => _draft != _initialDraft,
      isBusy: _saving,
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * .72,
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
                  _stepBody(
                    Form(
                      key: _informationKey,
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
                                child: TextFormField(
                                  controller: _name,
                                  decoration: const InputDecoration(
                                    labelText: 'Nom officiel *',
                                    prefixIcon: Icon(Icons.business_outlined),
                                  ),
                                  validator: _required,
                                ),
                              ),
                            ],
                          ),
                          if (widget.projects.isNotEmpty) ...[
                            const SizedBox(height: 16),
                            _InheritedValue(
                              label: 'Projet hérité',
                              value: _selectedProjectNames,
                            ),
                          ],
                          if (widget.missions.isNotEmpty) ...[
                            const SizedBox(height: 12),
                            DropdownButtonFormField<String?>(
                              initialValue: _missionId,
                              decoration: const InputDecoration(
                                labelText: 'Coordination / Mission',
                              ),
                              items: [
                                const DropdownMenuItem<String?>(
                                  value: null,
                                  child: Text('Mission héritée du projet'),
                                ),
                                for (final mission in widget.missions)
                                  DropdownMenuItem<String?>(
                                    value: '${mission['id']}',
                                    child: Text('${mission['name']}'),
                                  ),
                              ],
                              onChanged: (value) => _missionId = value,
                            ),
                          ],
                          SwitchListTile.adaptive(
                            contentPadding: EdgeInsets.zero,
                            title: const Text('Formation sanitaire active'),
                            value: _isActive,
                            onChanged: _saving
                                ? null
                                : (value) => setState(() => _isActive = value),
                          ),
                        ],
                      ),
                    ),
                  ),
                  _stepBody(
                    _v1
                        ? _v1Classification()
                        : Form(
                            key: _classificationKey,
                            child: Column(
                              children: [
                                DropdownButtonFormField<String>(
                                  initialValue: _careLevel,
                                  decoration: const InputDecoration(
                                    labelText: 'Niveau de soins *',
                                    prefixIcon: Icon(
                                      Icons.health_and_safety_outlined,
                                    ),
                                  ),
                                  items: _careLevels.entries
                                      .map(
                                        (entry) => DropdownMenuItem(
                                          value: entry.key,
                                          child: Text(entry.value),
                                        ),
                                      )
                                      .toList(),
                                  onChanged: (value) =>
                                      setState(() => _careLevel = value),
                                  validator: (value) => value == null
                                      ? 'Sélection obligatoire'
                                      : null,
                                ),
                                const SizedBox(height: 14),
                                DropdownButtonFormField<String>(
                                  initialValue: _type,
                                  decoration: const InputDecoration(
                                    labelText:
                                        'Catégorie de formation sanitaire *',
                                    prefixIcon: Icon(
                                      Icons.local_hospital_outlined,
                                    ),
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
                              ],
                            ),
                          ),
                  ),
                  _stepBody(
                    Form(
                      key: _locationKey,
                      child: Column(
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: TextFormField(
                                  controller: _region,
                                  decoration: const InputDecoration(
                                    labelText: 'Région',
                                  ),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: TextFormField(
                                  controller: _district,
                                  decoration: const InputDecoration(
                                    labelText: 'District sanitaire',
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 14),
                          TextFormField(
                            controller: _locality,
                            decoration: const InputDecoration(
                              labelText: 'Localité',
                              prefixIcon: Icon(Icons.place_outlined),
                            ),
                          ),
                          const SizedBox(height: 14),
                          TextFormField(
                            controller: _address,
                            minLines: 2,
                            maxLines: 3,
                            decoration: const InputDecoration(
                              labelText: 'Adresse',
                              prefixIcon: Icon(Icons.location_on_outlined),
                            ),
                          ),
                          const SizedBox(height: 14),
                          Row(
                            children: [
                              Expanded(
                                child: TextFormField(
                                  controller: _latitude,
                                  keyboardType:
                                      const TextInputType.numberWithOptions(
                                        decimal: true,
                                        signed: true,
                                      ),
                                  decoration: const InputDecoration(
                                    labelText: 'Latitude (facultatif)',
                                  ),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: TextFormField(
                                  controller: _longitude,
                                  keyboardType:
                                      const TextInputType.numberWithOptions(
                                        decimal: true,
                                        signed: true,
                                      ),
                                  decoration: const InputDecoration(
                                    labelText: 'Longitude (facultatif)',
                                  ),
                                ),
                              ),
                            ],
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
                        ],
                      ),
                    ),
                  ),
                  _stepBody(
                    Column(
                      children: [
                        _SummaryLine('Nom', _name.text),
                        _SummaryLine('Code', _code.text),
                        _SummaryLine(
                          'Niveau de soins',
                          _careLevels[_careLevel] ?? '—',
                        ),
                        _SummaryLine('Catégorie', _types[_type] ?? '—'),
                        _SummaryLine('Région', _region.text),
                        _SummaryLine('District', _district.text),
                        _SummaryLine('Localité', _locality.text),
                        _SummaryLine('Adresse', _address.text),
                        _SummaryLine(
                          'GPS',
                          [
                            _latitude.text,
                            _longitude.text,
                          ].where((v) => v.isNotEmpty).join(', '),
                        ),
                        _SummaryLine('Projet', _selectedProjectNames),
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
                  const SizedBox(width: 12),
                  Expanded(
                    child: _step == 3
                        ? AppButton.save(
                            label: widget.facility == null
                                ? 'Créer'
                                : 'Enregistrer',
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
      ),
    );
  }

  Widget _stepBody(Widget child) => SingleChildScrollView(
    padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
    child: child,
  );

  bool _validStep(int step) => switch (step) {
    0 => _informationKey.currentState?.validate() ?? _informationIsValid,
    1 when _v1 => _v1ClassificationIsValid,
    1 => _classificationKey.currentState?.validate() ?? _classificationIsValid,
    2 => _locationKey.currentState?.validate() ?? true,
    _ => true,
  };

  bool get _informationIsValid =>
      _code.text.trim().isNotEmpty && _name.text.trim().isNotEmpty;

  bool get _classificationIsValid =>
      _careLevel != null && _types.containsKey(_type);

  bool get _v1ClassificationIsValid =>
      _careLevelId != null &&
      _categoryId != null &&
      _populationIds.isNotEmpty &&
      _pathologyIds.isNotEmpty;

  Map<String, dynamic> _v1Payload() {
    String? day(DateTime? value) => value?.toIso8601String().substring(0, 10);
    return {
      'care_level_id': _careLevelId,
      'facility_category_id': _categoryId,
      'target_population_ids': _populationIds.toList(growable: false),
      'pathology_ids': _pathologyIds.toList(growable: false),
      'order_period_months': _orderPeriod,
      'delivery_lead_time_months': _leadTime,
      'safety_stock_months': _safetyStock,
      'inventory_date': day(_inventoryDate),
      'order_submission_date': day(_submissionDate),
      'order_receipt_date': day(_receiptDate),
    };
  }

  Widget _v1Classification() {
    String months(num value) =>
        '${value.toString().replaceAll('.0', '').replaceAll('.', ',')} mois';
    Widget chips(
      String title,
      List<Map<String, dynamic>> items,
      Set<String> selected,
    ) => Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
        const SizedBox(height: 6),
        if (items.isEmpty)
          const Text('Non configuré par la Coordination pour ce projet.')
        else
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final item in items)
                FilterChip(
                  label: Text('${item['name']}'),
                  selected: selected.contains('${item['id']}'),
                  onSelected: _saving
                      ? null
                      : (value) => setState(
                          () => value
                              ? selected.add('${item['id']}')
                              : selected.remove('${item['id']}'),
                        ),
                ),
            ],
          ),
      ],
    );
    Widget date(
      String label,
      DateTime? value,
      ValueChanged<DateTime?> set,
    ) => ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(label),
      subtitle: Text(
        value == null
            ? 'Non renseignée'
            : '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year}',
      ),
      trailing: const Icon(Icons.event_outlined),
      onTap: _saving
          ? null
          : () async {
              final picked = await showDatePicker(
                context: context,
                initialDate: value ?? DateTime.now(),
                firstDate: DateTime(2020),
                lastDate: DateTime(2100),
              );
              if (picked != null) setState(() => set(picked));
            },
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        DropdownButtonFormField<String>(
          initialValue: _careLevelId,
          isExpanded: true,
          decoration: const InputDecoration(labelText: 'Niveau de soins *'),
          items: [
            for (final (index, path) in careLevelPaths(_option('care_levels')).indexed)
              DropdownMenuItem(
                value: '${_option('care_levels')[index]['id']}',
                child: Text(path, overflow: TextOverflow.ellipsis),
              ),
          ],
          onChanged: (value) => setState(() => _careLevelId = value),
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: _categoryId,
          isExpanded: true,
          decoration: const InputDecoration(labelText: 'Catégorie de FOSA *'),
          items: [
            for (final category in _option('facility_categories'))
              DropdownMenuItem(
                value: '${category['id']}',
                child: Text('${category['name']}'),
              ),
          ],
          onChanged: (value) => setState(() => _categoryId = value),
        ),
        const SizedBox(height: 16),
        chips(
          'Population cible *',
          _option('target_populations'),
          _populationIds,
        ),
        const SizedBox(height: 16),
        chips(
          'Pathologies / activités *',
          _option('pathologies'),
          _pathologyIds,
        ),
        const SizedBox(height: 20),
        const Text(
          'Paramètres d’approvisionnement (préremplis depuis le projet)',
          style: TextStyle(fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 8),
        DropdownButtonFormField<int>(
          initialValue: _orderPeriod,
          decoration: const InputDecoration(
            labelText: 'Périodicité de commande',
          ),
          items: [
            for (var month = 1; month <= 12; month++)
              DropdownMenuItem(value: month, child: Text(months(month))),
          ],
          onChanged: (value) => setState(() => _orderPeriod = value),
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<int>(
          initialValue: _leadTime,
          decoration: const InputDecoration(
            labelText: 'Délai de livraison (DL)',
          ),
          items: [
            for (var month = 1; month <= 12; month++)
              DropdownMenuItem(value: month, child: Text(months(month))),
          ],
          onChanged: (value) => setState(() => _leadTime = value),
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<double>(
          initialValue: _safetyStock,
          decoration: const InputDecoration(labelText: 'Stock de sécurité'),
          items: [
            for (final value in _safetyStockOptions)
              DropdownMenuItem(value: value, child: Text(months(value))),
          ],
          onChanged: (value) => setState(() => _safetyStock = value),
        ),
        date(
          'Date d’inventaire',
          _inventoryDate,
          (value) => _inventoryDate = value,
        ),
        date(
          'Date de soumission de commande',
          _submissionDate,
          (value) => _submissionDate = value,
        ),
        date(
          'Date de réception de commande',
          _receiptDate,
          (value) => _receiptDate = value,
        ),
        if (!_v1ClassificationIsValid)
          const Padding(
            padding: EdgeInsets.only(top: 8),
            child: Text(
              'Choisissez le niveau de soins, la catégorie, au moins une population et une pathologie.',
            ),
          ),
      ],
    );
  }

  int? _firstInvalidStep() {
    if (_v1) {
      if (!_informationIsValid) return 0;
      if (!_v1ClassificationIsValid) return 1;
      return null;
    }
    return facilityFormFirstInvalidStep(
      code: _code.text,
      name: _name.text,
      careLevel: _careLevel,
      facilityType: _type,
    );
  }

  GlobalKey<FormState>? _formKeyFor(int step) => switch (step) {
    0 => _informationKey,
    1 => _classificationKey,
    2 => _locationKey,
    _ => null,
  };

  Future<void> _goTo(int target, {bool validateCurrentStep = true}) async {
    if (_saving) return;
    if (validateCurrentStep && target > _step && !_validStep(_step)) return;
    if (validateCurrentStep && target > _step) {
      final invalid = _firstInvalidStep();
      if (invalid != null && invalid < target) target = invalid;
    }
    await _pages.animateToPage(
      target,
      duration: const Duration(milliseconds: 240),
      curve: Curves.easeOut,
    );
  }

  String? _required(String? value) =>
      value == null || value.trim().isEmpty ? 'Champ obligatoire' : null;
}

class _InheritedValue extends StatelessWidget {
  const _InheritedValue({required this.label, required this.value});
  final String label, value;
  @override
  Widget build(BuildContext context) => InputDecorator(
    decoration: InputDecoration(
      labelText: label,
      prefixIcon: const Icon(Icons.lock_outline_rounded),
    ),
    child: Text(value.isEmpty ? 'Déterminé automatiquement' : value),
  );
}

class _SummaryLine extends StatelessWidget {
  const _SummaryLine(this.label, this.value);
  final String label, value;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 7),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 125,
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
    final saved = await showAppFormSheet<String>(
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
    if (saved != null) {
      await _refresh();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: saved == 'pending'
              ? AppColors.infoText
              : AppColors.successText,
          content: Text(
            saved == 'pending'
                ? '${_kindLabel(kind)} enregistré hors connexion. Synchronisation en attente.'
                : '${_kindLabel(kind)} enregistré avec succès.',
          ),
        ),
      );
    }
  }

  Future<void> _archiveChild(String kind, Map<String, dynamic> child) async {
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

  Future<void> _restoreChild(String kind, Map<String, dynamic> child) async {
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
  late final String _initialDraft;
  String get _draft =>
      jsonEncode([_code.text, _name.text, _location.text, _type]);

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
    _initialDraft = _draft;
  }

  @override
  void dispose() {
    _code.dispose();
    _name.dispose();
    _location.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_saving || !(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _saving = true);
    try {
      final synchronized = await widget.service.saveChild(
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
      if (mounted) Navigator.pop(context, synchronized ? 'saved' : 'pending');
    } on DioException catch (_) {
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: AppTheme.red,
          content: Text('Enregistrement impossible. Vérifiez le code.'),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return AppFormSheetGuard(
      isDirty: () => _draft != _initialDraft,
      isBusy: _saving,
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Flexible(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: Column(
                  children: [
                    TextFormField(
                      controller: _code,
                      decoration: const InputDecoration(
                        labelText: 'Code *',
                        prefixIcon: Icon(Icons.tag_outlined),
                      ),
                      validator: _required,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _name,
                      decoration: const InputDecoration(
                        labelText: 'Nom *',
                        prefixIcon: Icon(Icons.badge_outlined),
                      ),
                      validator: _required,
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<String>(
                      initialValue: _type,
                      isExpanded: true,
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
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _location,
                        decoration: const InputDecoration(
                          labelText: 'Localisation',
                          prefixIcon: Icon(Icons.location_on_outlined),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
            const Divider(height: 1),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
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
                    child: Icon(
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
                          style: TextStyle(
                            color: AppTheme.ink,
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${facility['code']} • ${_facilityType('${facility['facility_type']}')}',
                          style: TextStyle(
                            color: AppTheme.muted,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (onTap != null)
                    Icon(
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
                    style: TextStyle(color: AppTheme.muted, fontSize: 11),
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
                        AppButton.edit(compact: true, onPressed: onEdit),
                        const SizedBox(width: 8),
                        AppButton.archive(compact: true, onPressed: onArchive),
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
        gradient: LinearGradient(
          colors: [AppColors.sidebar, AppColors.text],
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
                    style: TextStyle(
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
              Padding(
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
                    onSelected: (action) =>
                        action == 'edit' ? onEdit(value) : onArchive(value),
                    itemBuilder: (_) => [
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
                leading: Icon(
                  Icons.inventory_2_outlined,
                  color: AppTheme.gray,
                ),
                title: Text(
                  'Archivés (${archivedValues.length})',
                  style: TextStyle(
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
          style: TextStyle(
            color: AppTheme.ink,
            fontWeight: FontWeight.w800,
            fontSize: 17,
          ),
        ),
        const SizedBox(height: 7),
        Text(
          message,
          textAlign: TextAlign.center,
          style: TextStyle(color: AppTheme.muted),
        ),
        if (action != null) ...[
          const SizedBox(height: 18),
          Center(child: action),
        ],
      ],
    );
  }
}

int _count(Map<String, dynamic> value, String key) =>
    (value[key] as List<dynamic>? ?? []).length;

String _facilityType(String type) =>
    const {
      'hospital': 'Hôpital',
      'health_center': 'Centre de santé',
      'clinic': 'Clinique',
      'warehouse': 'Dépôt sanitaire',
      'community': 'Structure communautaire',
      'other': 'Autre',
    }[type] ??
    type;

String _kindLabel(String kind) =>
    const {
      'department': 'Département',
      'pharmacy': 'Pharmacie',
      'site': 'Site',
    }[kind] ??
    kind;
