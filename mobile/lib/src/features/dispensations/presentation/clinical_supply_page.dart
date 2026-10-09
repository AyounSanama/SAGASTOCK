import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../../core/config/app_config.dart';
import 'package:image_picker/image_picker.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'dart:typed_data';
import '../../../core/files/private_attachment_store.dart';
import '../../../core/access/session_scope.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../../core/widgets/app_navigation_drawer.dart';
import '../data/clinical_supply_service.dart';
import '../../auth/data/auth_service.dart';
import '../../../core/format/display_format.dart';

class ClinicalSupplyPage extends StatefulWidget {
  const ClinicalSupplyPage({super.key, this.initialTab = 0});
  final int initialTab;
  @override
  State<ClinicalSupplyPage> createState() => _ClinicalSupplyPageState();
}

class _ClinicalSupplyPageState extends State<ClinicalSupplyPage>
    with SingleTickerProviderStateMixin {
  final service = ClinicalSupplyService();
  late final TabController tabs;
  List<Map<String, dynamic>> patients = [],
      prescriptions = [],
      dispensations = [];
  String? organizationId;
  bool loading = true;
  String? error;
  int pending = 0;
  @override
  void initState() {
    super.initState();
    tabs = TabController(
      length: 3,
      vsync: this,
      initialIndex: widget.initialTab,
    );
    _init();
  }

  @override
  void dispose() {
    tabs.dispose();
    super.dispose();
  }

  Future<void> _init() async {
    try {
      organizationId = SessionScope.organizationId(
        await AuthService().cachedUser(),
      );
      if (organizationId!.isEmpty) {
        throw StateError('missing organization scope');
      }
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
      final v = await Future.wait([
        service.patients(organizationId!),
        service.prescriptions(organizationId!),
        service.dispensations(organizationId!),
      ]);
      patients = v[0];
      prescriptions = v[1];
      dispensations = v[2];
      // Lu après la liste, qui synchronise d'abord la file hors connexion.
      pending = await service.pendingCount();
      error = null;
    } on DioException catch (e) {
      error = e.response?.statusCode == 403
          ? 'Accès non autorisé.'
          : 'Mode hors connexion : données locales affichées.';
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  void message(String text) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
    }
  }

  String apiError(DioException e) {
    final d = e.response?.data;
    if (d is Map && d['errors'] is Map) {
      final value = (d['errors'] as Map).values.first;
      return value is List ? '${value.first}' : '$value';
    }
    return d is Map && d['message'] != null
        ? '${d['message']}'
        : 'Opération impossible.';
  }

  Future<void> execute(
    Future<Object?> Function() action,
    String success,
  ) async {
    try {
      final result = await action();
      message(
        result == false
            ? 'Action conservée hors connexion et à synchroniser.'
            : success,
      );
      await _load();
    } on DioException catch (e) {
      message(apiError(e));
    }
  }

  Future<void> validateClinical(Map<String, dynamic> prescription) async {
    final data = await showAppFormSheet<Map<String, dynamic>>(
      context: context,
      title: 'Validation clinique',
      description:
          'Contrôlez le protocole, la posologie, les allergies et les contre-indications.',
      builder: (_) => const ClinicalValidationForm(),
    );
    if (data != null && organizationId != null) {
      await execute(
        () => service.validatePrescription(
          organizationId!,
          '${prescription['id']}',
          data,
        ),
        data['decision'] == 'reject'
            ? 'Ordonnance rejetée.'
            : 'Ordonnance validée cliniquement.',
      );
    }
  }

  Future<void> addPatient() async {
    final data = await showAppFormSheet<Map<String, dynamic>>(
      context: context,
      title: 'Nouveau patient',
      description: 'Créez un dossier patient rattaché à cette organisation.',
      builder: (_) => const PatientForm(),
    );
    if (data != null && organizationId != null) {
      await execute(
        () => service.createPatient(organizationId!, data),
        'Patient enregistré.',
      );
    }
  }

  Future<void> addPrescription() async {
    if (organizationId == null) return;
    try {
      final options = await service.options(organizationId!);
      if (!mounted) return;
      final data = await showAppFormSheet<Map<String, dynamic>>(
        context: context,
        title: 'Nouvelle ordonnance',
        description: 'Renseignez le patient, le prescripteur et le traitement.',
        builder: (_) => PrescriptionForm(options: options),
      );
      if (data != null) {
        await execute(
          () => service.createPrescription(organizationId!, data),
          'Ordonnance enregistrée.',
        );
      }
    } on DioException catch (e) {
      message(apiError(e));
    }
  }

  Future<void> addDispensation() async {
    if (organizationId == null) return;
    try {
      final options = await service.options(organizationId!);
      if (!mounted) return;
      final data = await showAppFormSheet<Map<String, dynamic>>(
        context: context,
        title: 'Dispenser des médicaments',
        description: 'Les lots sont sélectionnés automatiquement selon FEFO.',
        builder: (_) => DispensationForm(options: options),
      );
      if (data == null) return;
      final online = await service.dispense(organizationId!, data);
      message(
        online
            ? 'Dispensation validée et stock mis à jour.'
            : 'Dispensation conservée hors connexion.',
      );
      await _load();
    } on DioException catch (e) {
      message(apiError(e));
    }
  }

  Future<void> addDispensationFlow() async {
    if (organizationId == null) return;
    final completed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => NewDispensationFlowPage(
          organizationId: organizationId!,
          service: service,
        ),
      ),
    );
    if (completed == true) await _load();
  }

  @override
  Widget build(BuildContext c) => Scaffold(
    drawer: const AppNavigationDrawer(),
    appBar: AppBar(
      title: const Text('Patients et dispensation'),
      bottom: TabBar(
        controller: tabs,
        tabs: const [
          Tab(text: 'Patients'),
          Tab(text: 'Ordonnances'),
          Tab(text: 'Dispensations'),
        ],
      ),
    ),
    floatingActionButton: AppFab(
      tooltip: 'Ajouter',
      onPressed: organizationId == null
          ? null
          : () => switch (tabs.index) {
              0 => addPatient(),
              1 => addPrescription(),
              _ => addDispensationFlow(),
            },
    ),
    body: Column(
      children: [
        if (pending > 0)
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 16),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: AppTheme.orangeSoft,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              '$pending dispensation(s) en attente de synchronisation',
            ),
          ),
        if (error != null)
          Padding(
            padding: const EdgeInsets.all(8),
            child: Text(error!, style: TextStyle(color: AppTheme.red)),
          ),
        Expanded(
          child: loading
              ? const Center(child: CircularProgressIndicator())
              : TabBarView(
                  controller: tabs,
                  children: [
                    listPatients(),
                    listPrescriptions(),
                    listDispensations(),
                  ],
                ),
        ),
      ],
    ),
  );
  Widget empty(String text, IconData icon) => ListView(
    children: [
      const SizedBox(height: 80),
      Icon(icon, size: 52, color: AppTheme.muted),
      const SizedBox(height: 12),
      Center(child: Text(text)),
    ],
  );
  Widget listPatients() => patients.isEmpty
      ? empty('Aucun patient', Icons.people_outline)
      : RefreshIndicator(
          onRefresh: _load,
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: patients.length,
            itemBuilder: (_, i) {
              final p = patients[i];
              return Card(
                child: ListTile(
                  leading: CircleAvatar(
                    backgroundColor: AppTheme.orangeSoft,
                    child: Icon(Icons.person_outline, color: AppTheme.orange),
                  ),
                  title: Text(
                    '${p['last_name']} ${p['first_name']}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  subtitle: Text(
                    '${p['code']} · ${p['phone'] ?? 'Sans téléphone'}',
                  ),
                ),
              );
            },
          ),
        );
  Widget listPrescriptions() => prescriptions.isEmpty
      ? empty('Aucune ordonnance', Icons.description_outlined)
      : RefreshIndicator(
          onRefresh: _load,
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: prescriptions.length,
            itemBuilder: (_, i) {
              final p = prescriptions[i], draft = p['status'] == 'draft';
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
                              '${p['reference']}',
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          Status('${p['status']}'),
                        ],
                      ),
                      Text(
                        '${p['patient']?['last_name'] ?? ''} ${p['patient']?['first_name'] ?? ''}',
                      ),
                      Text(
                        '${(p['items'] as List? ?? []).length} produit(s) · ${p['prescriber_name']}',
                        style: TextStyle(color: AppTheme.muted),
                      ),
                      if (draft && AppConfig.clinicalValidation) ...[
                        const SizedBox(height: 10),
                        AppButton.validate(
                          label: 'Valider l’ordonnance',
                          expanded: true,
                          compact: true,
                          onPressed: () => validateClinical(p),
                        ),
                      ],
                    ],
                  ),
                ),
              );
            },
          ),
        );
  Widget listDispensations() => dispensations.isEmpty
      ? empty('Aucune dispensation', Icons.local_pharmacy_outlined)
      : RefreshIndicator(
          onRefresh: _load,
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: dispensations.length,
            itemBuilder: (_, i) {
              final d = dispensations[i];
              return Card(
                child: ListTile(
                  leading: CircleAvatar(
                    backgroundColor: AppColors.successSurface,
                    child: Icon(
                      Icons.medication_outlined,
                      color: AppTheme.green,
                    ),
                  ),
                  title: Text(
                    '${d['reference']}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  subtitle: Text(
                    '${d['patient']?['last_name'] ?? ''} · ${(d['items'] as List? ?? []).length} lot(s)',
                  ),
                  trailing: Icon(
                    Icons.verified_outlined,
                    color: AppTheme.green,
                  ),
                ),
              );
            },
          ),
        );
}

class NewDispensationFlowPage extends StatefulWidget {
  const NewDispensationFlowPage({
    super.key,
    required this.organizationId,
    required this.service,
  });

  final String organizationId;
  final ClinicalSupplyService service;

  @override
  State<NewDispensationFlowPage> createState() =>
      _NewDispensationFlowPageState();
}

class _NewDispensationFlowPageState extends State<NewDispensationFlowPage> {
  final _pages = PageController();
  final _picker = ImagePicker();
  final _quantity = TextEditingController();
  Map<String, List<Map<String, dynamic>>> _options = const {};
  final List<Map<String, dynamic>> _items = [];
  int _step = 0;
  bool _loading = true;
  bool _submitting = false;
  String? _patientId;
  String? _siteId;
  String? _productId;
  // Niveau 7 : destination de la sortie, couple ONG/Bailleur, service, lot choisi.
  String _destination = 'patient';
  // Projet du couple choisi, ou [_otherOrigin] (stock livré par un tiers).
  String? _originProjectId;
  static const _otherOrigin = 'other';
  String? _batchId;
  final _serviceName = TextEditingController();

  bool get _forPatient => _destination == 'patient';

  List<Map<String, dynamic>> get _origins => [
    for (final origin
        in ((_options['sites'] ?? const [])
                    .where((site) => '${site['id']}' == _siteId)
                    .firstOrNull?['origins']
                as List? ??
            const []))
      Map<String, dynamic>.from(origin as Map),
  ];

  List<Map<String, dynamic>> get _lots => (_options['batches'] ?? const [])
      .where(
        (lot) =>
            '${lot['site_id']}' == _siteId &&
            '${lot['product_id']}' == _productId,
      )
      .where(
        (lot) =>
            _origins.isEmpty ||
            (_originProjectId == _otherOrigin
                ? lot['origin_type'] == _otherOrigin
                : lot['origin_type'] != _otherOrigin &&
                      (lot['origin_project_id'] == null ||
                          '${lot['origin_project_id']}' == _originProjectId)),
      )
      .toList();
  String? _attachmentPath;
  String? _attachmentName;
  Uint8List? _attachmentPreview;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _pages.dispose();
    _quantity.dispose();
    _serviceName.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final options = await widget.service.options(widget.organizationId);
      if (!mounted) return;
      setState(() {
        _options = options;
        if (options['sites']?.length == 1) {
          _siteId = '${options['sites']!.first['id']}';
        }
      });
    } catch (_) {
      _notice('Impossible de charger les données de la formation sanitaire.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _notice(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  bool _stepComplete(int step) => switch (step) {
    0 =>
      _siteId != null &&
          (_origins.isEmpty || _originProjectId != null) &&
          (!_forPatient || _patientId != null) &&
          (_destination != 'hospital_service' ||
              _serviceName.text.trim().isNotEmpty),
    1 => !_forPatient || _attachmentPath != null,
    2 => _items.isNotEmpty,
    _ => true,
  };

  Future<void> _go(int target) async {
    final firstIncomplete = target > _step
        ? [
            for (var step = _step; step < target; step++) step,
          ].where((step) => !_stepComplete(step)).firstOrNull
        : null;
    if (firstIncomplete != null) {
      _notice(switch (firstIncomplete) {
        0 =>
          'Complétez la formation sanitaire, le couple ONG/Bailleur, la destination et le patient ou le service.',
        1 => 'Photographiez ou sélectionnez l’ordonnance.',
        _ => 'Ajoutez au moins un produit à dispenser.',
      });
      await _pages.animateToPage(
        firstIncomplete,
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOut,
      );
      if (mounted) setState(() => _step = firstIncomplete);
      return;
    }
    setState(() => _step = target);
    await _pages.animateToPage(
      target,
      duration: const Duration(milliseconds: 240),
      curve: Curves.easeOutCubic,
    );
  }

  Future<void> _capture(ImageSource source) async {
    final selected = await _picker.pickImage(
      source: source,
      imageQuality: 88,
      maxWidth: 2200,
    );
    if (selected == null) return;
    final path = await persistPrivateAttachment(selected);
    final preview = await selected.readAsBytes();
    if (!mounted) return;
    setState(() {
      _attachmentPath = path;
      _attachmentName = selected.name;
      _attachmentPreview = preview;
    });
  }

  Future<void> _createPatient() async {
    final data = await showAppFormSheet<Map<String, dynamic>>(
      context: context,
      title: 'Nouveau patient',
      description: 'Créez le dossier dans votre formation sanitaire.',
      builder: (_) => const PatientForm(),
    );
    if (data == null) return;
    data['site_id'] = _siteId;
    final saved = await widget.service.createPatient(
      widget.organizationId,
      data,
    );
    if (!saved) {
      final localPatient = Map<String, dynamic>.from(data)
        ..['is_active'] = true
        ..['offline_pending'] = true;
      setState(() {
        _options = {
          ..._options,
          'patients': [...?_options['patients'], localPatient],
        };
        _patientId = '${data['id']}';
      });
      _notice(
        'Patient conservé hors connexion. Vous pouvez poursuivre la dispensation.',
      );
      return;
    }
    await _load();
    final match = _options['patients']?.where(
      (patient) => patient['client_reference'] == data['client_reference'],
    );
    if (match != null && match.isNotEmpty && mounted) {
      setState(() => _patientId = '${match.first['id']}');
    }
  }

  void _addProduct() {
    final quantity = double.tryParse(_quantity.text.replaceAll(',', '.'));
    if (_productId == null || quantity == null || quantity <= 0) {
      _notice('Sélectionnez un produit et une quantité valide.');
      return;
    }
    if (_destination == 'expired_damaged' && _batchId == null) {
      _notice('Choisissez le lot périmé ou détérioré.');
      return;
    }
    final product = _options['products']!.firstWhere(
      (value) => '${value['id']}' == _productId,
    );
    setState(() {
      _items.add({
        'product_id': _productId,
        'quantity': quantity,
        'name': product['name'],
        'batch_id': ?_batchId,
      });
      _productId = null;
      _batchId = null;
      _quantity.clear();
    });
  }

  Future<void> _scanProduct() async {
    final code = await Navigator.of(context).push<String>(
      MaterialPageRoute(builder: (_) => const _ProductBarcodeScannerPage()),
    );
    if (code == null || !mounted) return;

    final products = _options['products'] ?? const [];
    Map<String, dynamic>? matched;
    for (final product in products) {
      final primaryCode = '${product['code'] ?? ''}'.trim();
      final codes = (product['codes'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map((value) => '${value['value'] ?? ''}'.trim());
      if (primaryCode == code || codes.contains(code)) {
        matched = product;
        break;
      }
    }

    if (matched == null) {
      _notice(
        'Ce code-barres ne correspond à aucun produit autorisé pour ce projet.',
      );
      return;
    }
    setState(() => _productId = '${matched!['id']}');
    _notice('${matched['name']} sélectionné.');
  }

  Future<void> _submit() async {
    if (!_stepComplete(2) || _submitting) return;
    setState(() => _submitting = true);
    try {
      final online = await widget.service.dispense(widget.organizationId, {
        'reference': 'DIS-${DateTime.now().millisecondsSinceEpoch}',
        if (_forPatient) 'patient_id': _patientId,
        'site_id': _siteId,
        'destination_type': _destination,
        if (_destination == 'hospital_service')
          'destination_name': _serviceName.text.trim(),
        if (_originProjectId == _otherOrigin)
          'origin_type': _otherOrigin
        else if (_originProjectId != null) ...{
          'origin_type': 'project',
          'origin_project_id': _originProjectId,
        },
        'dispensed_at': DateTime.now().toUtc().toIso8601String(),
        'allow_partial': false,
        '_attachment_path': _attachmentPath,
        '_attachment_name': _attachmentName ?? 'ordonnance.jpg',
        'items': _items
            .map(
              (item) => {
                'product_id': item['product_id'],
                'quantity': item['quantity'],
                'batch_id': ?item['batch_id'],
              },
            )
            .toList(),
      });
      if (!mounted) return;
      _notice(
        online
            ? 'Dispensation enregistrée avec succès.'
            : 'Dispensation sécurisée hors connexion, en attente de synchronisation.',
      );
      Navigator.pop(context, true);
    } on DioException catch (error) {
      final response = error.response?.data;
      _notice(
        response is Map && response['message'] != null
            ? '${response['message']}'
            : 'La dispensation n’a pas pu être enregistrée.',
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Nouvelle dispensation')),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : Column(
            children: [
              _WorkflowSteps(step: _step, onTap: _go),
              Expanded(
                child: PageView(
                  controller: _pages,
                  onPageChanged: (target) {
                    if (target > _step && !_stepComplete(_step)) {
                      _go(_step);
                    } else {
                      setState(() => _step = target);
                    }
                  },
                  children: [
                    _patientStep(),
                    _prescriptionStep(),
                    _productsStep(),
                    _summaryStep(),
                  ],
                ),
              ),
              SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    children: [
                      if (_step > 0)
                        Expanded(
                          child: AppButton.secondary(
                            label: 'Précédent',
                            icon: Icons.arrow_back_rounded,
                            onPressed: () => _go(_step - 1),
                          ),
                        ),
                      if (_step > 0) const SizedBox(width: 12),
                      Expanded(
                        child: _step == 3
                            ? AppButton.validate(
                                label: 'Valider la dispensation',
                                loading: _submitting,
                                onPressed: _submit,
                              )
                            : AppButton.primary(
                                label: 'Continuer',
                                trailingIcon: true,
                                icon: Icons.arrow_forward_rounded,
                                onPressed: () => _go(_step + 1),
                              ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
  );

  Widget _patientStep() => _stepBody(
    'Patient',
    'Recherchez un patient existant ou créez un nouveau dossier.',
    [
      DropdownButtonFormField<String>(
        isExpanded: true,
        initialValue: _siteId,
        decoration: const InputDecoration(labelText: 'Formation sanitaire'),
        items: (_options['sites'] ?? const [])
            .map(
              (site) => DropdownMenuItem(
                value: '${site['id']}',
                child: Text(
                  '${site['health_facility']?['name'] ?? site['name']}',
                ),
              ),
            )
            .toList(),
        onChanged: (value) => setState(() {
          _siteId = value;
          _originProjectId = _origins.length == 1
              ? '${_origins.first['project_id']}'
              : null;
        }),
      ),
      if (_origins.isNotEmpty) ...[
        const SizedBox(height: 14),
        DropdownButtonFormField<String>(
          key: ValueKey('origin-$_siteId'),
          initialValue: _originProjectId,
          isExpanded: true,
          decoration: const InputDecoration(labelText: 'Couple ONG/Bailleur *'),
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
              value: _otherOrigin,
              child: Text('Autre (livré par un tiers)'),
            ),
          ],
          onChanged: (value) => setState(() => _originProjectId = value),
        ),
      ],
      const SizedBox(height: 14),
      DropdownButtonFormField<String>(
        isExpanded: true,
        initialValue: _destination,
        decoration: const InputDecoration(labelText: 'Destination *'),
        items: [
          for (final destination
              in (_options['destinations'] ??
                  const <Map<String, dynamic>>[
                    {'value': 'patient', 'label': 'Patient'},
                  ]))
            DropdownMenuItem(
              value: '${destination['value']}',
              child: Text('${destination['label']}'),
            ),
        ],
        onChanged: (value) => setState(() {
          _destination = value ?? 'patient';
          _batchId = null;
        }),
      ),
      if (_destination == 'hospital_service') ...[
        const SizedBox(height: 14),
        TextField(
          controller: _serviceName,
          decoration: const InputDecoration(
            labelText: 'Service hospitalier *',
            hintText: 'Ex. Maternité, Pédiatrie',
          ),
          onChanged: (_) => setState(() {}),
        ),
      ],
      if (_forPatient) ...[
        const SizedBox(height: 14),
        DropdownButtonFormField<String>(
          isExpanded: true,
          initialValue: _patientId,
          decoration: const InputDecoration(labelText: 'Patient'),
          items: (_options['patients'] ?? const [])
              .map(
                (patient) => DropdownMenuItem(
                  value: '${patient['id']}',
                  child: Text(
                    '${patient['code']} · ${patient['last_name']} ${patient['first_name']}',
                  ),
                ),
              )
              .toList(),
          onChanged: (value) => setState(() => _patientId = value),
        ),
        const SizedBox(height: 14),
        AppButton.add(
          label: 'Nouveau patient',
          expanded: true,
          onPressed: _siteId == null ? null : _createPatient,
        ),
      ],
    ],
  );

  Widget _prescriptionStep() => _stepBody(
    'Scanner / Photographier l’ordonnance',
    'La photo est conservée dans l’espace privé de PharmaCare.',
    [
      Container(
        height: 280,
        width: double.infinity,
        decoration: BoxDecoration(
          color: AppTheme.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppTheme.border),
        ),
        clipBehavior: Clip.antiAlias,
        child: _attachmentPreview == null
            ? Center(
                child: Icon(
                  Icons.document_scanner_outlined,
                  size: 72,
                  color: AppTheme.muted,
                ),
              )
            : Image.memory(_attachmentPreview!, fit: BoxFit.contain),
      ),
      const SizedBox(height: 14),
      Row(
        children: [
          Expanded(
            child: AppButton.primary(
              label: 'Caméra',
              icon: Icons.camera_alt_outlined,
              onPressed: () => _capture(ImageSource.camera),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: AppButton.secondary(
              label: 'Galerie',
              icon: Icons.image_outlined,
              onPressed: () => _capture(ImageSource.gallery),
            ),
          ),
        ],
      ),
    ],
  );

  Widget _productsStep() => _stepBody(
    'Produits à dispenser',
    'Les lots seront attribués automatiquement selon FEFO.',
    [
      AppButton.secondary(
        label: 'Scanner un code-barres',
        icon: Icons.qr_code_scanner_rounded,
        expanded: true,
        onPressed: _scanProduct,
      ),
      const SizedBox(height: 12),
      DropdownButtonFormField<String>(
        isExpanded: true,
        initialValue: _productId,
        decoration: const InputDecoration(labelText: 'Produit'),
        items: (_options['products'] ?? const [])
            .map(
              (product) => DropdownMenuItem(
                value: '${product['id']}',
                child: Text('${product['code']} · ${product['name']}'),
              ),
            )
            .toList(),
        onChanged: (value) => setState(() {
          _productId = value;
          _batchId = null;
        }),
      ),
      if (_destination == 'expired_damaged') ...[
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          key: ValueKey('lot-$_productId'),
          initialValue: _batchId,
          isExpanded: true,
          decoration: const InputDecoration(
            labelText: 'Lot périmé ou détérioré *',
          ),
          items: [
            for (final lot in _lots)
              DropdownMenuItem(
                value: '${lot['batch_id']}',
                child: Text(
                  '${lot['batch_number']} · ${lot['expires_on'] ?? ''}${lot['expired'] == true ? ' (périmé)' : ''} · ${lot['available']} en stock',
                  overflow: TextOverflow.ellipsis,
                ),
              ),
          ],
          onChanged: (value) => setState(() => _batchId = value),
        ),
      ],
      const SizedBox(height: 12),
      TextField(
        controller: _quantity,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        decoration: const InputDecoration(labelText: 'Quantité'),
      ),
      const SizedBox(height: 12),
      AppButton.add(
        label: 'Ajouter le produit',
        expanded: true,
        onPressed: _addProduct,
      ),
      const SizedBox(height: 16),
      ..._items.asMap().entries.map(
        (entry) => Card(
          child: ListTile(
            title: Text('${entry.value['name']}'),
            subtitle: Text('Quantité : ${formatQuantity(entry.value['quantity'])}'),
            trailing: IconButton(
              icon: Icon(Icons.delete_outline, color: AppTheme.red),
              onPressed: () => setState(() => _items.removeAt(entry.key)),
            ),
          ),
        ),
      ),
    ],
  );

  Widget _summaryStep() {
    final patient = (_options['patients'] ?? const []).where(
      (value) => '${value['id']}' == _patientId,
    );
    return _stepBody('Résumé', 'Vérifiez les informations avant validation.', [
      // Niveau 7 : destination et couple ONG/Bailleur du stock délivré.
      ListTile(
        leading: const Icon(Icons.call_split_outlined),
        title: const Text('Destination'),
        subtitle: Text(
          '${(_options['destinations'] ?? const []).where((d) => d['value'] == _destination).firstOrNull?['label'] ?? 'Patient'}'
          '${_destination == 'hospital_service' ? ' · ${_serviceName.text.trim()}' : ''}',
        ),
      ),
      if (_originProjectId != null)
        ListTile(
          leading: const Icon(Icons.handshake_outlined),
          title: const Text('Couple ONG/Bailleur'),
          subtitle: Text(
            _originProjectId == _otherOrigin
                ? 'Autre (livré par un tiers)'
                : '${_origins.where((o) => '${o['project_id']}' == _originProjectId).firstOrNull?['label'] ?? ''}',
          ),
        ),
      ListTile(
        leading: const Icon(Icons.person_outline),
        title: const Text('Patient'),
        subtitle: Text(
          patient.isEmpty
              ? '—'
              : '${patient.first['last_name']} ${patient.first['first_name']}',
        ),
      ),
      ListTile(
        leading: const Icon(Icons.description_outlined),
        title: const Text('Ordonnance'),
        subtitle: Text(_attachmentName ?? 'Photo enregistrée'),
      ),
      ListTile(
        leading: const Icon(Icons.medication_outlined),
        title: const Text('Produits'),
        subtitle: Text(
          '${_items.length} produit(s) · ${formatQuantity(_items.fold<double>(0, (sum, item) => sum + (item['quantity'] as num).toDouble()))} unité(s)',
        ),
      ),
      Card(
        child: Padding(
          padding: EdgeInsets.all(14),
          child: Row(
            children: [
              Icon(Icons.verified_user_outlined, color: AppTheme.blue),
              SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Les lots sont contrôlés et sortis selon la règle FEFO.',
                ),
              ),
            ],
          ),
        ),
      ),
    ]);
  }

  Widget _stepBody(String title, String subtitle, List<Widget> children) =>
      ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Text(
            title,
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          Text(subtitle, style: TextStyle(color: AppTheme.muted)),
          const SizedBox(height: 20),
          ...children,
        ],
      );
}

class _WorkflowSteps extends StatelessWidget {
  const _WorkflowSteps({required this.step, required this.onTap});
  final int step;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
    child: Row(
      children: List.generate(4, (index) {
        final active = index == step;
        final done = index < step;
        return Expanded(
          child: InkWell(
            onTap: () => onTap(index),
            borderRadius: BorderRadius.circular(12),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 15,
                    backgroundColor: done
                        ? AppTheme.green
                        : active
                        ? AppTheme.orange
                        : AppTheme.border,
                    child: done
                        ? const Icon(Icons.check, color: Colors.white, size: 17)
                        : Text(
                            '${index + 1}',
                            style: TextStyle(
                              color: active ? Colors.white : AppTheme.muted,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    const [
                      'Patient',
                      'Ordonnance',
                      'Dispensation',
                      'Résumé',
                    ][index],
                    maxLines: 1,
                    style: TextStyle(
                      fontSize: 10,
                      color: active ? AppTheme.orange : AppTheme.muted,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      }),
    ),
  );
}

class _ProductBarcodeScannerPage extends StatefulWidget {
  const _ProductBarcodeScannerPage();

  @override
  State<_ProductBarcodeScannerPage> createState() =>
      _ProductBarcodeScannerPageState();
}

class _ProductBarcodeScannerPageState
    extends State<_ProductBarcodeScannerPage> {
  bool _handled = false;

  void _onDetect(BarcodeCapture capture) {
    if (_handled) return;
    final value = capture.barcodes
        .map((barcode) => barcode.rawValue?.trim())
        .whereType<String>()
        .where((code) => code.isNotEmpty)
        .firstOrNull;
    if (value == null) return;
    _handled = true;
    Navigator.of(context).pop(value);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Scanner un produit')),
    body: Stack(
      fit: StackFit.expand,
      children: [
        MobileScanner(onDetect: _onDetect),
        Center(
          child: Container(
            width: 270,
            height: 170,
            decoration: BoxDecoration(
              border: Border.all(color: AppTheme.primary, width: 3),
              borderRadius: BorderRadius.circular(16),
            ),
          ),
        ),
        const SafeArea(
          child: Align(
            alignment: Alignment.bottomCenter,
            child: Padding(
              padding: EdgeInsets.all(24),
              child: Card(
                child: Padding(
                  padding: EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                  child: Text(
                    'Placez le code-barres du produit dans le cadre.',
                    textAlign: TextAlign.center,
                  ),
                ),
              ),
            ),
          ),
        ),
      ],
    ),
  );
}

class Status extends StatelessWidget {
  const Status(this.value, {super.key});
  final String value;

  /// Libellé et couleur d'état ; « validation non requise (V1) » n'est pas
  /// une validation pharmaceutique (statut neutre).
  static (String, Color, Color) describe(String value) => switch (value) {
    'draft' => (
      'En attente de validation',
      AppColors.infoSurface,
      AppColors.infoText,
    ),
    'validation_not_required' => (
      'Validation non requise (V1)',
      AppColors.neutralSurface,
      AppColors.neutralText,
    ),
    'validated' => ('Validée', AppColors.successSurface, AppColors.successText),
    'rejected' => ('Refusée', AppColors.dangerSurface, AppColors.dangerText),
    'partially_dispensed' => (
      'Partiellement dispensée',
      AppColors.infoSurface,
      AppColors.infoText,
    ),
    'waiting_stock' => (
      'En attente de stock',
      AppColors.infoSurface,
      AppColors.infoText,
    ),
    'dispensed' => (
      'Dispensée',
      AppColors.successSurface,
      AppColors.successText,
    ),
    _ => (value, AppColors.neutralSurface, AppColors.neutralText),
  };

  @override
  Widget build(BuildContext c) {
    final (label, surface, text) = describe(value);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: surface,
        borderRadius: BorderRadius.circular(99),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: text,
          fontSize: 10,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class PatientForm extends StatefulWidget {
  const PatientForm({super.key});
  @override
  State<PatientForm> createState() => _PatientFormState();
}

class _PatientFormState extends State<PatientForm> {
  final key = GlobalKey<FormState>(),
      code = TextEditingController(
        text: 'PAT-${DateTime.now().millisecondsSinceEpoch}',
      ),
      first = TextEditingController(),
      last = TextEditingController(),
      phone = TextEditingController(),
      birth = TextEditingController(),
      allergies = TextEditingController();
  String? sex;
  @override
  Widget build(BuildContext c) => FormBody(
    keyForm: key,
    onSave: () {
      if (key.currentState!.validate()) {
        Navigator.pop(c, {
          'code': code.text,
          'first_name': first.text,
          'last_name': last.text,
          if (phone.text.isNotEmpty) 'phone': phone.text,
          if (birth.text.isNotEmpty) 'date_of_birth': birth.text,
          if (sex != null) 'sex': sex,
          if (allergies.text.isNotEmpty) 'allergies': allergies.text,
        });
      }
    },
    children: [
      field(code, 'Code patient'),
      field(last, 'Nom'),
      field(first, 'Prénom'),
      DropdownButtonFormField<String>(
        isExpanded: true,
        decoration: const InputDecoration(labelText: 'Sexe'),
        items: const [
          DropdownMenuItem(value: 'female', child: Text('Féminin')),
          DropdownMenuItem(value: 'male', child: Text('Masculin')),
          DropdownMenuItem(value: 'other', child: Text('Autre')),
        ],
        onChanged: (v) => sex = v,
      ),
      field(birth, 'Date de naissance (AAAA-MM-JJ)', required: false),
      field(phone, 'Téléphone', required: false),
      field(allergies, 'Allergies connues', required: false),
    ],
  );
}

class PrescriptionForm extends StatefulWidget {
  const PrescriptionForm({super.key, required this.options});
  final Map<String, List<Map<String, dynamic>>> options;
  @override
  State<PrescriptionForm> createState() => _PrescriptionFormState();
}

class _PrescriptionFormState extends State<PrescriptionForm> {
  final key = GlobalKey<FormState>(),
      ref = TextEditingController(
        text: 'ORD-${DateTime.now().millisecondsSinceEpoch}',
      ),
      doctor = TextEditingController(),
      qty = TextEditingController(),
      dosage = TextEditingController(),
      frequency = TextEditingController(),
      duration = TextEditingController();
  String? patient, site, product;
  @override
  Widget build(BuildContext c) => FormBody(
    keyForm: key,
    onSave: () {
      if (key.currentState!.validate()) {
        Navigator.pop(c, {
          'patient_id': patient,
          'site_id': site,
          'reference': ref.text,
          'prescribed_on': DateTime.now().toIso8601String().substring(0, 10),
          'prescriber_name': doctor.text,
          'items': [
            {
              'product_id': product,
              'quantity_prescribed': double.parse(
                qty.text.replaceAll(',', '.'),
              ),
              if (dosage.text.isNotEmpty) 'dosage': dosage.text,
              'frequency': frequency.text,
              'duration': duration.text,
            },
          ],
        });
      }
    },
    children: [
      drop(
        'Patient',
        widget.options['patients']!,
        patient,
        (v) => patient = v,
        (v) => '${v['last_name']} ${v['first_name']}',
      ),
      drop(
        'Site',
        widget.options['sites']!,
        site,
        (v) => site = v,
        (v) => '${v['name']}',
      ),
      field(ref, 'Référence'),
      field(doctor, 'Prescripteur'),
      drop(
        'Médicament',
        widget.options['products']!,
        product,
        (v) => product = v,
        (v) => '${v['code']} · ${v['name']}',
      ),
      field(qty, 'Quantité prescrite'),
      field(dosage, 'Posologie', required: false),
      field(frequency, 'Fréquence'),
      field(duration, 'Durée du traitement'),
    ],
  );
}

class DispensationForm extends StatefulWidget {
  const DispensationForm({super.key, required this.options});
  final Map<String, List<Map<String, dynamic>>> options;
  @override
  State<DispensationForm> createState() => _DispensationFormState();
}

class _DispensationFormState extends State<DispensationForm> {
  final key = GlobalKey<FormState>(),
      ref = TextEditingController(
        text: 'DIS-${DateTime.now().millisecondsSinceEpoch}',
      ),
      qty = TextEditingController();
  String? prescription, patient, site, item, product;
  @override
  Widget build(BuildContext c) {
    final all = widget.options['prescriptions']!;
    final lines = prescription == null
        ? <Map<String, dynamic>>[]
        : (all.firstWhere((p) => '${p['id']}' == prescription)['items'] as List)
              .cast<Map<String, dynamic>>();
    return FormBody(
      keyForm: key,
      onSave: () {
        if (key.currentState!.validate()) {
          Navigator.pop(c, {
            'reference': ref.text,
            'patient_id': patient,
            'prescription_id': prescription,
            'site_id': site,
            'dispensed_at': DateTime.now().toIso8601String(),
            'allow_partial': true,
            'items': [
              {
                'prescription_item_id': item,
                'product_id': product,
                'quantity': double.parse(qty.text.replaceAll(',', '.')),
              },
            ],
          });
        }
      },
      children: [
        drop(
          'Ordonnance validée',
          all,
          prescription,
          (v) {
            setState(() {
              prescription = v;
              final p = all.firstWhere((x) => '${x['id']}' == v);
              patient = '${p['patient_id']}';
              site = '${p['site_id']}';
              item = null;
            });
          },
          (v) => '${v['reference']} · ${v['patient']?['last_name'] ?? ''}',
        ),
        field(ref, 'Référence'),
        if (prescription != null)
          drop(
            'Produit prescrit',
            lines,
            item,
            (v) {
              item = v;
              product =
                  '${lines.firstWhere((x) => '${x['id']}' == v)['product_id']}';
            },
            (v) =>
                '${v['product']?['name']} · prescrit ${v['quantity_prescribed']}',
          ),
        field(qty, 'Quantité à dispenser'),
      ],
    );
  }
}

class ClinicalValidationForm extends StatefulWidget {
  const ClinicalValidationForm({super.key});
  @override
  State<ClinicalValidationForm> createState() => _ClinicalValidationFormState();
}

class _ClinicalValidationFormState extends State<ClinicalValidationForm> {
  final key = GlobalKey<FormState>();
  final notes = TextEditingController();
  final reason = TextEditingController();
  String decision = 'approve';
  bool protocol = false, dosage = false, contraindications = false;

  @override
  Widget build(BuildContext context) => FormBody(
    keyForm: key,
    onSave: () {
      if (!key.currentState!.validate()) return;
      if (decision == 'approve' &&
          (!protocol || !dosage || !contraindications)) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Confirmez les trois contrôles cliniques.'),
          ),
        );
        return;
      }
      Navigator.pop(context, {
        'decision': decision,
        'protocol_confirmed': protocol,
        'dosage_confirmed': dosage,
        'contraindications_checked': contraindications,
        if (notes.text.trim().isNotEmpty)
          'clinical_validation_notes': notes.text.trim(),
        if (decision == 'reject') 'rejection_reason': reason.text.trim(),
      });
    },
    children: [
      DropdownButtonFormField<String>(
        isExpanded: true,
        initialValue: decision,
        decoration: const InputDecoration(labelText: 'Décision clinique'),
        items: const [
          DropdownMenuItem(value: 'approve', child: Text('Approuver')),
          DropdownMenuItem(value: 'reject', child: Text('Rejeter')),
        ],
        onChanged: (value) => setState(() => decision = value ?? 'approve'),
      ),
      if (decision == 'approve') ...[
        CheckboxListTile(
          value: protocol,
          title: const Text('Protocole thérapeutique vérifié'),
          onChanged: (value) => setState(() => protocol = value ?? false),
        ),
        CheckboxListTile(
          value: dosage,
          title: const Text('Posologie et durée vérifiées'),
          onChanged: (value) => setState(() => dosage = value ?? false),
        ),
        CheckboxListTile(
          value: contraindications,
          title: const Text('Allergies et contre-indications vérifiées'),
          onChanged: (value) =>
              setState(() => contraindications = value ?? false),
        ),
      ] else
        TextFormField(
          controller: reason,
          decoration: const InputDecoration(labelText: 'Motif du rejet'),
          minLines: 2,
          maxLines: 4,
          validator: (value) => value == null || value.trim().length < 5
              ? 'Indiquez un motif précis.'
              : null,
        ),
      TextFormField(
        controller: notes,
        decoration: const InputDecoration(labelText: 'Note clinique'),
        minLines: 2,
        maxLines: 4,
      ),
    ],
  );
}

class FormBody extends StatelessWidget {
  const FormBody({
    super.key,
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
        crossAxisAlignment: CrossAxisAlignment.stretch,
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

Widget field(TextEditingController c, String label, {bool required = true}) =>
    TextFormField(
      controller: c,
      decoration: InputDecoration(labelText: label),
      validator: (v) => required && (v == null || v.trim().isEmpty)
          ? 'Champ obligatoire.'
          : null,
    );
Widget drop(
  String label,
  List<Map<String, dynamic>> values,
  String? value,
  ValueChanged<String?> changed,
  String Function(Map<String, dynamic>) labelOf,
) => DropdownButtonFormField<String>(
  isExpanded: true,
  initialValue: value,
  decoration: InputDecoration(labelText: label),
  items: values
      .map(
        (v) => DropdownMenuItem(
          value: '${v['id']}',
          child: Text(labelOf(v), overflow: TextOverflow.ellipsis),
        ),
      )
      .toList(),
  onChanged: changed,
  validator: (v) => v == null ? 'Sélection obligatoire.' : null,
);
