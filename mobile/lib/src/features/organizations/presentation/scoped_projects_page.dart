import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/access/application_access.dart';
import '../../../core/security/password_policy.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_empty_state.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../auth/data/auth_service.dart';
import '../data/organization_service.dart';
import 'project_medical_configuration.dart';

class ScopedProjectsPage extends StatefulWidget {
  const ScopedProjectsPage({
    super.key,
    this.openCreate = false,
    this.embedded = false,
    this.openProjectId,
  });

  final bool openCreate;

  /// Projet dont le détail s'ouvre à l'arrivée (carte de « Ma Coordination »).
  final String? openProjectId;
  final bool embedded;
  @override
  State<ScopedProjectsPage> createState() => _ScopedProjectsPageState();
}

class _ScopedProjectsPageState extends State<ScopedProjectsPage> {
  final _service = OrganizationService();
  final _search = TextEditingController();
  String? _organizationId;
  List<Map<String, dynamic>> _missions = [], _projects = [];
  Map<String, dynamic> _setup = {};
  bool _loading = true, _archived = false;
  bool _coordinationMode = false;
  bool _projectMode = false;
  bool _canManage = false;
  bool _createOpened = false;
  bool _projectOpened = false;
  String _status = '', _missionId = '';
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final user = await AuthService().cachedUser() ?? {};
      final organization = user['organization'] as Map?;
      _coordinationMode =
          '${user['role'] ?? ''}'.toLowerCase() == 'coordination_admin';
      _projectMode = '${user['role'] ?? ''}'.toLowerCase() == 'project_admin';
      _canManage = ApplicationAccess.allows(user, 'projects.manage');
      final cachedCoordination = user['coordination'] is Map
          ? Map<String, dynamic>.from(user['coordination'] as Map)
          : null;
      if (_coordinationMode && cachedCoordination != null) {
        _missionId = '${cachedCoordination['id'] ?? ''}';
      }
      _organizationId =
          '${user['organization_id'] ?? organization?['id'] ?? ''}';
      if (_organizationId!.isEmpty) throw StateError('missing scope');
      final values = await Future.wait([
        _service.missions(_organizationId!),
        _service.projects(
          organizationId: _organizationId!,
          missionId: _missionId.isEmpty ? null : _missionId,
          search: _search.text.trim(),
          status: _status,
          archived: _archived,
        ),
        _service.projectSetup(_organizationId!),
      ]);
      if (mounted) {
        setState(() {
          final loadedMissions = values[0] as List<Map<String, dynamic>>;
          _missions = loadedMissions.isEmpty && cachedCoordination != null
              ? [cachedCoordination]
              : loadedMissions;
          _projects = values[1] as List<Map<String, dynamic>>;
          _setup = values[2] as Map<String, dynamic>;
        });
      }
    } on DioException catch (error) {
      if (mounted) {
        setState(
          () => _error = error.response?.statusCode == 403
              ? 'Vous n’avez pas la permission de consulter les projets.'
              : 'Impossible de charger les projets.',
        );
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = 'Aucune organisation n’est rattachée à ce compte.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
      if (mounted &&
          widget.openCreate &&
          !_createOpened &&
          _canManage &&
          _error == null) {
        _createOpened = true;
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted) _openForm();
        });
      }
      final opened = _projects
          .where((project) => '${project['id']}' == widget.openProjectId)
          .firstOrNull;
      if (mounted && opened != null && !_projectOpened) {
        _projectOpened = true;
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted) _showProject(opened);
        });
      }
    }
  }

  Future<void> _openForm([Map<String, dynamic>? project]) async {
    if (_missions.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Créez d’abord une mission.')),
      );
      return;
    }
    final editing = project != null;
    final key = GlobalKey<FormState>();
    final code = TextEditingController(text: '${project?['code'] ?? ''}');
    final name = TextEditingController(text: '${project?['name'] ?? ''}');
    final description = TextEditingController(
      text: '${project?['description'] ?? ''}',
    );
    final partner = TextEditingController(
      text: '${project?['implementing_partner'] ?? ''}',
    );
    final donorCode = TextEditingController(
      text: '${project?['donor_reference_code'] ?? ''}',
    );
    final mohCode = TextEditingController(
      text: '${project?['moh_program_code'] ?? ''}',
    );
    final responsibleName = TextEditingController(
      text: '${project?['responsible_name'] ?? ''}',
    );
    final responsibleContact = TextEditingController(
      text: '${project?['responsible_contact'] ?? ''}',
    );
    final adminFirstName = TextEditingController();
    final adminLastName = TextEditingController();
    final adminEmail = TextEditingController();
    final adminPhone = TextEditingController();
    final adminUsername = TextEditingController();
    final adminPassword = TextEditingController();
    final donors = ((_setup['donors'] as List?) ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final programs = ((_setup['programs'] as List?) ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final selectedDonors = ((project?['donors'] as List?) ?? const [])
        .map((item) => '${(item as Map)['id']}')
        .toSet();
    final selectedPrograms = ((project?['programs'] as List?) ?? const [])
        .map((item) => '${(item as Map)['id']}')
        .toSet();
    String missionId =
        '${project?['mission_id'] ?? (project?['mission'] as Map?)?['id'] ?? _missions.first['id']}';
    DateTime? startsOn = DateTime.tryParse('${project?['starts_on'] ?? ''}');
    DateTime? endsOn = DateTime.tryParse('${project?['ends_on'] ?? ''}');
    bool saving = false;
    String status =
        '${project?['status'] ?? (project?['is_active'] == false ? 'suspended' : 'active')}';
    Map<String, dynamic> identity() => {
      'implementing_partner': partner.text.trim(),
      'donor_reference_code': donorCode.text.trim(),
      'moh_program_code': mohCode.text.trim(),
      'responsible_name': responsibleName.text.trim(),
      'responsible_contact': responsibleContact.text.trim(),
      'status': status,
    };
    String? adminRequired(String? value) =>
        adminEmail.text.trim().isEmpty ? null : _required(value);
    int? orderPeriodMonths = project?['order_period_months'] as int?;
    int? deliveryLeadTimeMonths = project?['delivery_lead_time_months'] as int?;
    num? safetyStockMonths = _safetyValue(project?['safety_stock_months']);
    final result = await showAppFormSheet<String>(
      context: context,
      title: editing ? 'Modifier le projet' : 'Nouveau projet',
      description: 'Le projet reste limité au périmètre de votre organisation.',
      builder: (sheetContext) => StatefulBuilder(
        builder: (_, setSheetState) => SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: key,
            child: Column(
              children: [
                if (_coordinationMode)
                  TextFormField(
                    initialValue: '${_missions.first['name']}',
                    readOnly: true,
                    decoration: const InputDecoration(
                      labelText: 'Coordination pays',
                      prefixIcon: Icon(Icons.lock_outline_rounded),
                    ),
                  )
                else
                  DropdownButtonFormField<String>(
                    isExpanded: true,
                    initialValue: missionId,
                    decoration: const InputDecoration(labelText: 'Mission *'),
                    items: _missions
                        .map(
                          (item) => DropdownMenuItem(
                            value: '${item['id']}',
                            child: Text('${item['name']}'),
                          ),
                        )
                        .toList(),
                    onChanged: (value) => missionId = value ?? missionId,
                  ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: code,
                  decoration: const InputDecoration(labelText: 'Code *'),
                  validator: _required,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: name,
                  decoration: const InputDecoration(labelText: 'Nom *'),
                  validator: _required,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: partner,
                  decoration: const InputDecoration(
                    labelText: 'Organisation / programme de mise en œuvre',
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: donorCode,
                        decoration: const InputDecoration(
                          labelText: 'Code bailleur',
                          hintText: 'ex. GFFO5, FH4',
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: TextFormField(
                        controller: mohCode,
                        decoration: const InputDecoration(
                          labelText: 'Code programme du Ministère de la Santé',
                          hintText: 'ex. PNLT',
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: responsibleName,
                  decoration: const InputDecoration(
                    labelText: 'Responsable du projet',
                  ),
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: responsibleContact,
                  decoration: const InputDecoration(
                    labelText: 'Contact du responsable',
                    hintText: 'E-mail ou téléphone',
                  ),
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: description,
                  decoration: const InputDecoration(labelText: 'Description'),
                  maxLines: 3,
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: _DateField(
                        label: 'Date de début',
                        value: startsOn,
                        onChanged: (value) =>
                            setSheetState(() => startsOn = value),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _DateField(
                        label: 'Date de fin',
                        value: endsOn,
                        firstDate: startsOn,
                        onChanged: (value) =>
                            setSheetState(() => endsOn = value),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  isExpanded: true,
                  initialValue: _projectStatuses.containsKey(status)
                      ? status
                      : 'active',
                  decoration: const InputDecoration(labelText: 'Statut *'),
                  items: _projectStatuses.entries
                      .map(
                        (entry) => DropdownMenuItem(
                          value: entry.key,
                          child: Text(entry.value),
                        ),
                      )
                      .toList(),
                  onChanged: saving
                      ? null
                      : (value) =>
                            setSheetState(() => status = value ?? status),
                ),
                if (editing) ...[
                  const Divider(height: 28),
                  const Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Paramètres d’approvisionnement',
                      style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    isExpanded: true,
                    initialValue: orderPeriodMonths,
                    decoration: const InputDecoration(
                      labelText: 'Périodicité de commande',
                    ),
                    items: _monthOptions,
                    validator: (value) => status == 'active' && value == null
                        ? 'Obligatoire pour un projet actif'
                        : null,
                    onChanged: saving
                        ? null
                        : (value) => orderPeriodMonths = value,
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    isExpanded: true,
                    initialValue: deliveryLeadTimeMonths,
                    decoration: const InputDecoration(
                      labelText: 'Délai de livraison',
                    ),
                    items: _monthOptions,
                    validator: (value) => status == 'active' && value == null
                        ? 'Obligatoire pour un projet actif'
                        : null,
                    onChanged: saving
                        ? null
                        : (value) => deliveryLeadTimeMonths = value,
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<num>(
                    isExpanded: true,
                    initialValue: safetyStockMonths,
                    decoration: const InputDecoration(
                      labelText: 'Stock de sécurité',
                    ),
                    items: _safetyOptions,
                    validator: (value) => status == 'active' && value == null
                        ? 'Obligatoire pour un projet actif'
                        : null,
                    onChanged: saving
                        ? null
                        : (value) => safetyStockMonths = value,
                  ),
                ],
                if (!editing) ...[
                  const Divider(height: 28),
                  const Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Paramètres d’approvisionnement',
                      style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    isExpanded: true,
                    initialValue: orderPeriodMonths,
                    decoration: const InputDecoration(
                      labelText: 'Périodicité de commande',
                    ),
                    items: _monthOptions,
                    validator: (value) => status == 'active' && value == null
                        ? 'Obligatoire pour un projet actif'
                        : null,
                    onChanged: saving
                        ? null
                        : (value) => orderPeriodMonths = value,
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    isExpanded: true,
                    initialValue: deliveryLeadTimeMonths,
                    decoration: const InputDecoration(
                      labelText: 'Délai de livraison',
                    ),
                    items: _monthOptions,
                    validator: (value) => status == 'active' && value == null
                        ? 'Obligatoire pour un projet actif'
                        : null,
                    onChanged: saving
                        ? null
                        : (value) => deliveryLeadTimeMonths = value,
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<num>(
                    isExpanded: true,
                    initialValue: safetyStockMonths,
                    decoration: const InputDecoration(
                      labelText: 'Stock de sécurité',
                    ),
                    items: _safetyOptions,
                    validator: (value) => status == 'active' && value == null
                        ? 'Obligatoire pour un projet actif'
                        : null,
                    onChanged: saving
                        ? null
                        : (value) => safetyStockMonths = value,
                  ),
                  const Divider(height: 28),
                  const Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Bailleurs et programme',
                      style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  if (donors.isEmpty)
                    const Align(
                      alignment: Alignment.centerLeft,
                      child: Text('Aucun bailleur actif disponible.'),
                    )
                  else
                    // Un projet par code bailleur : un seul bailleur sélectionnable.
                    ...donors.map(
                      (donor) => CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text('${donor['name']}'),
                        subtitle: Text('${donor['code']}'),
                        value: selectedDonors.contains('${donor['id']}'),
                        onChanged: saving
                            ? null
                            : (checked) => setSheetState(() {
                                selectedDonors.clear();
                                if (checked == true) {
                                  selectedDonors.add('${donor['id']}');
                                }
                              }),
                      ),
                    ),
                  if (programs.isNotEmpty) ...[
                    const Align(
                      alignment: Alignment.centerLeft,
                      child: Text(
                        'Programmes',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ),
                    ...programs.map(
                      (program) => CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text('${program['name']}'),
                        subtitle: Text('${program['code']}'),
                        value: selectedPrograms.contains('${program['id']}'),
                        onChanged: saving
                            ? null
                            : (checked) => setSheetState(() {
                                checked == true
                                    ? selectedPrograms.add('${program['id']}')
                                    : selectedPrograms.remove(
                                        '${program['id']}',
                                      );
                              }),
                      ),
                    ),
                  ],
                  const Divider(height: 28),
                  const Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Premier Admin Projet (facultatif)',
                      style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Renseignez l’e-mail pour créer le compte maintenant. Le rôle Admin Projet est automatique ; le mot de passe devra être changé à la première connexion.',
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: adminFirstName,
                    decoration: const InputDecoration(labelText: 'Prénom'),
                    validator: adminRequired,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: adminLastName,
                    decoration: const InputDecoration(labelText: 'Nom'),
                    validator: adminRequired,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: adminEmail,
                    keyboardType: TextInputType.emailAddress,
                    decoration: const InputDecoration(
                      labelText: 'Email de connexion',
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: adminPhone,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(labelText: 'Téléphone'),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: adminUsername,
                    decoration: const InputDecoration(labelText: 'Identifiant'),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: adminPassword,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'Mot de passe',
                      helperText: PasswordPolicy.helperText,
                    ),
                    validator: (value) => adminEmail.text.trim().isEmpty
                        ? null
                        : PasswordPolicy.validate(value),
                  ),
                  const SizedBox(height: 14),
                  Card(
                    color: AppTheme.orange.withValues(alpha: .06),
                    child: const Padding(
                      padding: EdgeInsets.all(14),
                      child: Row(
                        children: [
                          Icon(Icons.info_outline_rounded),
                          SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              'Connexion requise : le mot de passe ne sera jamais stocké dans la file hors connexion.',
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 18),
                Row(
                  children: [
                    Expanded(
                      child: AppButton.cancel(
                        onPressed: saving
                            ? null
                            : () => Navigator.pop(sheetContext),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: AppButton.save(
                        label: editing ? 'Enregistrer' : 'Créer le projet',
                        loading: saving,
                        onPressed: saving
                            ? null
                            : () async {
                                if (!(key.currentState?.validate() ?? false)) {
                                  return;
                                }
                                setSheetState(() => saving = true);
                                try {
                                  bool? synced;
                                  if (editing) {
                                    synced = await _service.updateProject(
                                      organizationId: _organizationId!,
                                      projectId: '${project['id']}',
                                      missionId: missionId,
                                      code: code.text.trim(),
                                      name: name.text.trim(),
                                      description: description.text.trim(),
                                      startsOn: startsOn,
                                      endsOn: endsOn,
                                      orderPeriodMonths: orderPeriodMonths,
                                      deliveryLeadTimeMonths:
                                          deliveryLeadTimeMonths,
                                      safetyStockMonths: safetyStockMonths,
                                      donorIds: selectedDonors.toList(),
                                      programIds: selectedPrograms.toList(),
                                      donorRecords: donors
                                          .where(
                                            (item) => selectedDonors.contains(
                                              '${item['id']}',
                                            ),
                                          )
                                          .toList(),
                                      programRecords: programs
                                          .where(
                                            (item) => selectedPrograms.contains(
                                              '${item['id']}',
                                            ),
                                          )
                                          .toList(),
                                      isActive: status == 'active',
                                      identity: identity(),
                                    );
                                  } else {
                                    await _service.createConfiguredProject(
                                      organizationId: _organizationId!,
                                      missionId: missionId,
                                      code: code.text.trim(),
                                      name: name.text.trim(),
                                      description: description.text.trim(),
                                      startsOn: startsOn,
                                      endsOn: endsOn,
                                      donorIds: selectedDonors.toList(),
                                      programIds: selectedPrograms.toList(),
                                      adminFirstName: adminFirstName.text
                                          .trim(),
                                      adminLastName: adminLastName.text.trim(),
                                      adminEmail: adminEmail.text.trim(),
                                      adminPhone: adminPhone.text.trim(),
                                      adminUsername: adminUsername.text.trim(),
                                      adminPassword: adminPassword.text,
                                      orderPeriodMonths: orderPeriodMonths,
                                      deliveryLeadTimeMonths:
                                          deliveryLeadTimeMonths,
                                      safetyStockMonths: safetyStockMonths,
                                      identity: identity(),
                                    );
                                  }
                                  if (sheetContext.mounted) {
                                    Navigator.pop(
                                      sheetContext,
                                      synced == false ? 'pending' : 'saved',
                                    );
                                  }
                                } on DioException catch (error) {
                                  if (!sheetContext.mounted) return;
                                  setSheetState(() => saving = false);
                                  ScaffoldMessenger.of(
                                    sheetContext,
                                  ).showSnackBar(
                                    SnackBar(
                                      content: Text(
                                        error.response?.statusCode == 403
                                            ? 'Action non autorisée.'
                                            : 'Enregistrement impossible. Vérifiez les informations.',
                                      ),
                                    ),
                                  );
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
    // Le pop termine la Future avant la fin visuelle de l'animation inverse.
    // Garder les dependances du formulaire vivantes jusqu'au retrait complet.
    await Future<void>.delayed(const Duration(milliseconds: 350));
    // Champs de la fenêtre : jamais libérés pendant sa fermeture animée
    // (écran rouge « _dependents.isEmpty ») ; la mémoire les récupère.
    if (result == null || !mounted) return;
    await _load();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          result == 'pending'
              ? 'Projet enregistré hors connexion. Synchronisation en attente.'
              : editing
              ? 'Projet modifié avec succès.'
              : 'Projet créé avec succès.',
        ),
      ),
    );
  }

  String? _required(String? value) =>
      value?.trim().isEmpty == true ? 'Champ obligatoire' : null;

  static const _projectStatuses = <String, String>{
    'draft': 'Brouillon',
    'active': 'Actif',
    'suspended': 'Suspendu',
    'closed': 'Clôturé',
  };

  /// Durées en mois (1 = 1 mois), de 1 à 12, comme sur le Web.
  static final _monthOptions = <DropdownMenuItem<int>>[
    for (var month = 1; month <= 12; month++)
      DropdownMenuItem(value: month, child: Text('$month mois')),
  ];

  // Niveau 2 : stock de sécurité en mois décimaux, comme la FOSA (0,25 à 2).
  static const _safetyStockValues = <num>[0.25, 0.5, 0.75, 1, 1.5, 2];
  static final _safetyOptions = <DropdownMenuItem<num>>[
    for (final months in _safetyStockValues)
      DropdownMenuItem(
        value: months,
        child: Text('${months.toString().replaceAll('.', ',')} mois'),
      ),
  ];

  /// Valeur reprise seulement si elle fait partie de la liste officielle.
  static num? _safetyValue(Object? value) {
    final number = num.tryParse('${value ?? ''}');
    for (final option in _safetyStockValues) {
      if (option == number) return option;
    }
    return null;
  }

  Future<void> _changeArchiveState(Map<String, dynamic> project) async {
    final restoring = _archived;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          restoring ? 'Restaurer le projet ?' : 'Archiver le projet ?',
        ),
        content: Text(
          restoring
              ? 'Le projet réapparaîtra dans la liste active.'
              : 'Le projet et son historique seront conservés.',
        ),
        actions: [
          AppButton.cancel(
            onPressed: () => Navigator.pop(dialogContext, false),
          ),
          restoring
              ? AppButton.validate(
                  label: 'Restaurer',
                  onPressed: () => Navigator.pop(dialogContext, true),
                )
              : AppButton.archive(
                  onPressed: () => Navigator.pop(dialogContext, true),
                ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      final synced = restoring
          ? await _service.restoreProject(
              organizationId: _organizationId!,
              projectId: '${project['id']}',
            )
          : await _service.archiveProject(
              organizationId: _organizationId!,
              projectId: '${project['id']}',
            );
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            synced
                ? (restoring ? 'Projet restauré.' : 'Projet archivé.')
                : 'Action enregistrée hors connexion. Synchronisation en attente.',
          ),
        ),
      );
    } on DioException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            error.response?.statusCode == 403
                ? 'Vous n’avez pas la permission d’effectuer cette action.'
                : 'Impossible d’effectuer cette action pour le moment.',
          ),
        ),
      );
    }
  }

  Future<void> _openMedicalConfiguration(Map<String, dynamic> project) async {
    final saved = await showMedicalConfigurationEditor(
      context,
      service: _service,
      projectId: '${project['id']}',
      organizationId: _organizationId!,
      projectName: '${project['name']}',
    );
    if (saved == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Configuration médicale enregistrée.')),
      );
    }
  }

  Future<void> _showProject(Map<String, dynamic> project) => showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: Text('${project['name']}'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Code : ${project['code'] ?? '\u2014'}'),
          const SizedBox(height: 8),
          Text(
            'Coordination : ${(project['mission'] as Map?)?['name'] ?? '\u2014'}',
          ),
          Text(
            'Organisation : ${(project['organization'] as Map?)?['name'] ?? '\u2014'}',
          ),
          Text(
            'Pays : ${((project['mission'] as Map?)?['country'] as Map?)?['name'] ?? '\u2014'}',
          ),
          const SizedBox(height: 8),
          Text(
            'Statut : ${_archived ? 'Archiv\u00e9' : (project['status_label'] ?? 'Actif')}',
          ),
          if ('${project['implementing_partner'] ?? ''}'.isNotEmpty)
            Text('Mise en œuvre : ${project['implementing_partner']}'),
          if ('${project['responsible_name'] ?? ''}'.isNotEmpty)
            Text(
              'Responsable : ${project['responsible_name']}'
              '${'${project['responsible_contact'] ?? ''}'.isEmpty ? '' : ' · ${project['responsible_contact']}'}',
            ),
          if ('${project['donor_reference_code'] ?? ''}'.isNotEmpty)
            Text('Code bailleur : ${project['donor_reference_code']}'),
          if ('${project['moh_program_code'] ?? ''}'.isNotEmpty)
            Text('Code programme du Ministère de la Santé : ${project['moh_program_code']}'),
          const SizedBox(height: 8),
          Text('Bailleur : ${_names(project['donors'])}'),
          Text('Programme(s) : ${_names(project['programs'])}'),
          Text(
            'Périodicité de commande : ${_months(project['order_period_months'])}',
          ),
          Text(
            'Délai de livraison : ${_months(project['delivery_lead_time_months'])}',
          ),
          Text(
            'Stock de sécurité : ${_months(project['safety_stock_months'])}',
          ),
          if ('${project['description'] ?? ''}'.trim().isNotEmpty) ...[
            const SizedBox(height: 8),
            Text('${project['description']}'),
          ],
        ],
      ),
      actions: [
        AppButton.text(
          label: 'Fermer',
          onPressed: () => Navigator.pop(dialogContext),
        ),
        if (!_archived && _canManage)
          AppButton.edit(
            label: 'Modifier',
            onPressed: () {
              Navigator.pop(dialogContext);
              _openForm(project);
            },
          ),
      ],
    ),
  );

  String _months(dynamic value) =>
      value == null ? '—' : '${'$value'.replaceAll('.', ',')} mois';

  String _names(dynamic values) {
    final names = (values as List? ?? const [])
        .whereType<Map>()
        .map((item) => '${item['name'] ?? ''}')
        .where((name) => name.isNotEmpty)
        .join(', ');
    return names.isEmpty ? '—' : names;
  }

  @override
  Widget build(BuildContext context) {
    final content = RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: EdgeInsets.fromLTRB(16, widget.embedded ? 10 : 16, 16, 96),
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _projectMode ? 'Mon projet' : 'Projets',
                      style: const TextStyle(
                        fontSize: 23,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      _projectMode
                          ? 'Consultez les informations de votre projet.'
                          : 'Gérez les projets de votre coordination.',
                      style: TextStyle(color: AppTheme.muted),
                    ),
                  ],
                ),
              ),
              if (!_archived && _canManage) ...[
                const SizedBox(width: 10),
                AppButton.add(
                  label: 'Créer un projet',
                  compact: true,
                  onPressed: _loading ? null : _openForm,
                ),
              ],
            ],
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _search,
            textInputAction: TextInputAction.search,
            onSubmitted: (_) => _load(),
            decoration: InputDecoration(
              hintText: 'Rechercher un projet…',
              prefixIcon: const Icon(Icons.search_rounded),
              suffixIcon: IconButton(
                tooltip: 'Rechercher',
                onPressed: _load,
                icon: const Icon(Icons.arrow_forward_rounded),
              ),
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              ChoiceChip(
                label: const Text('Projets actifs'),
                selected: !_archived,
                onSelected: (_) {
                  setState(() => _archived = false);
                  _load();
                },
              ),
              ChoiceChip(
                label: const Text('Archives'),
                selected: _archived,
                onSelected: (_) {
                  setState(() => _archived = true);
                  _load();
                },
              ),
              if (!_archived)
                DropdownButton<String>(
                  value: _status,
                  items: const [
                    DropdownMenuItem(
                      value: '',
                      child: Text('Tous les statuts'),
                    ),
                    DropdownMenuItem(value: 'active', child: Text('Actifs')),
                    DropdownMenuItem(
                      value: 'inactive',
                      child: Text('Inactifs'),
                    ),
                  ],
                  onChanged: (value) {
                    setState(() => _status = value ?? '');
                    _load();
                  },
                ),
              if (_missions.isNotEmpty && !_coordinationMode)
                DropdownButton<String>(
                  value: _missionId,
                  items: [
                    const DropdownMenuItem(
                      value: '',
                      child: Text('Toutes les missions'),
                    ),
                    for (final item in _missions)
                      DropdownMenuItem(
                        value: '${item['id']}',
                        child: Text('${item['name']}'),
                      ),
                  ],
                  onChanged: (value) {
                    setState(() => _missionId = value ?? '');
                    _load();
                  },
                ),
            ],
          ),
          const SizedBox(height: 16),
          if (_loading)
            const Padding(
              padding: EdgeInsets.all(40),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_error != null)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    Text(_error!),
                    AppButton.text(label: 'Réessayer', onPressed: _load),
                  ],
                ),
              ),
            )
          else if (_projects.isEmpty)
            AppEmptyState(
              icon: Icons.account_tree_outlined,
              title: _archived ? 'Aucun projet archivé' : 'Aucun projet',
              description: _archived
                  ? 'Les projets archivés apparaîtront ici.'
                  : 'Créez le premier projet depuis une mission autorisée.',
              action: !_archived && _canManage
                  ? AppButton.add(
                      label: 'Créer un projet',
                      onPressed: _openForm,
                    )
                  : null,
            )
          else
            ..._projects.map(
              (project) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Card(
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: AppTheme.green.withValues(alpha: .1),
                      child: Icon(
                        Icons.account_tree_outlined,
                        color: AppTheme.green,
                      ),
                    ),
                    title: Text(
                      '${project['name']}',
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    subtitle: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${project['code']} · ${(project['mission'] as Map?)?['name'] ?? 'Mission'}',
                        ),
                        if (project['_sync_status'] == 'pending')
                          const Text(
                            'En attente de synchronisation',
                            style: TextStyle(
                              color: AppTheme.orange,
                              fontSize: 12,
                            ),
                          ),
                      ],
                    ),
                    trailing: PopupMenuButton<String>(
                      tooltip: 'Actions',
                      onSelected: (action) {
                        if (action == 'view') _showProject(project);
                        if (action == 'edit') _openForm(project);
                        if (action == 'medical') {
                          _openMedicalConfiguration(project);
                        }
                        if (action == 'archive') _changeArchiveState(project);
                      },
                      itemBuilder: (_) => [
                        const PopupMenuItem(value: 'view', child: Text('Voir')),
                        if (!_archived && _canManage)
                          const PopupMenuItem(
                            value: 'edit',
                            child: Text('Modifier'),
                          ),
                        if (!_archived && _canManage)
                          const PopupMenuItem(
                            value: 'medical',
                            child: Text('Configuration médicale'),
                          ),
                        if (_canManage)
                          PopupMenuItem(
                            value: 'archive',
                            child: Text(_archived ? 'Restaurer' : 'Archiver'),
                          ),
                      ],
                    ),
                    onTap: () => _showProject(project),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
    if (widget.embedded) return content;
    return Scaffold(
      appBar: AppBar(
        title: Text(_projectMode ? 'Mon projet' : 'Configuration des projets'),
      ),
      floatingActionButton: _archived || !_canManage
          ? null
          : AppFab(
              onPressed: _loading ? null : _openForm,
              tooltip: 'Ajouter un projet',
            ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              _projectMode ? 'Mon projet' : 'Configuration des projets',
              style: TextStyle(fontSize: 23, fontWeight: FontWeight.w800),
            ),
            Text(
              'Gérez les projets de votre coordination.',
              style: TextStyle(color: AppTheme.muted),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _load(),
              decoration: InputDecoration(
                hintText: 'Rechercher un projet…',
                prefixIcon: const Icon(Icons.search_rounded),
                suffixIcon: IconButton(
                  tooltip: 'Rechercher',
                  onPressed: _load,
                  icon: const Icon(Icons.arrow_forward_rounded),
                ),
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                ChoiceChip(
                  label: const Text('Projets actifs'),
                  selected: !_archived,
                  onSelected: (_) {
                    setState(() => _archived = false);
                    _load();
                  },
                ),
                ChoiceChip(
                  label: const Text('Archives'),
                  selected: _archived,
                  onSelected: (_) {
                    setState(() => _archived = true);
                    _load();
                  },
                ),
                if (!_archived)
                  DropdownButton<String>(
                    value: _status,
                    items: const [
                      DropdownMenuItem(
                        value: '',
                        child: Text('Tous les statuts'),
                      ),
                      DropdownMenuItem(value: 'active', child: Text('Actifs')),
                      DropdownMenuItem(
                        value: 'inactive',
                        child: Text('Inactifs'),
                      ),
                    ],
                    onChanged: (value) {
                      setState(() => _status = value ?? '');
                      _load();
                    },
                  ),
                if (_missions.isNotEmpty && !_coordinationMode)
                  DropdownButton<String>(
                    value: _missionId,
                    items: [
                      const DropdownMenuItem(
                        value: '',
                        child: Text('Toutes les missions'),
                      ),
                      for (final item in _missions)
                        DropdownMenuItem(
                          value: '${item['id']}',
                          child: Text('${item['name']}'),
                        ),
                    ],
                    onChanged: (value) {
                      setState(() => _missionId = value ?? '');
                      _load();
                    },
                  ),
              ],
            ),
            const SizedBox(height: 16),
            if (_loading)
              const Padding(
                padding: EdgeInsets.all(40),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      Text(_error!),
                      AppButton.text(label: 'Réessayer', onPressed: _load),
                    ],
                  ),
                ),
              )
            else if (_projects.isEmpty)
              AppEmptyState(
                icon: Icons.account_tree_outlined,
                title: _archived ? 'Aucun projet archivé' : 'Aucun projet',
                description: _archived
                    ? 'Les projets archivés apparaîtront ici.'
                    : 'Créez le premier projet depuis une mission autorisée.',
                action: !_archived && _canManage
                    ? AppButton.add(
                        label: 'Créer un projet',
                        onPressed: _openForm,
                      )
                    : null,
              )
            else
              ..._projects.map(
                (project) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Card(
                    child: ListTile(
                      leading: CircleAvatar(
                        backgroundColor: AppTheme.green.withValues(alpha: .1),
                        child: Icon(
                          Icons.account_tree_outlined,
                          color: AppTheme.green,
                        ),
                      ),
                      title: Text(
                        '${project['name']}',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      subtitle: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${project['code']} · ${(project['mission'] as Map?)?['name'] ?? 'Mission'}',
                          ),
                          if (project['_sync_status'] == 'pending')
                            const Text(
                              'En attente de synchronisation',
                              style: TextStyle(
                                color: AppTheme.orange,
                                fontSize: 12,
                              ),
                            ),
                        ],
                      ),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          AppIconAction(
                            icon: Icons.visibility_outlined,
                            tooltip: 'Voir le projet',
                            color: AppActionColor.green,
                            onPressed: () => _showProject(project),
                          ),
                          const SizedBox(width: 4),
                          if (!_archived && _canManage)
                            AppIconAction(
                              icon: Icons.edit_outlined,
                              tooltip: 'Modifier le projet',
                              color: AppActionColor.orange,
                              onPressed: () => _openForm(project),
                            ),
                          if (!_archived && _canManage) ...[
                            const SizedBox(width: 4),
                            AppIconAction(
                              icon: Icons.medical_services_outlined,
                              tooltip: 'Configuration médicale',
                              color: AppActionColor.green,
                              onPressed: () =>
                                  _openMedicalConfiguration(project),
                            ),
                          ],
                          const SizedBox(width: 4),
                          if (_canManage)
                            AppIconAction(
                              icon: _archived
                                  ? Icons.restore_rounded
                                  : Icons.archive_outlined,
                              tooltip: _archived
                                  ? 'Restaurer le projet'
                                  : 'Archiver le projet',
                              color: _archived
                                  ? AppActionColor.green
                                  : AppActionColor.red,
                              onPressed: () => _changeArchiveState(project),
                            ),
                        ],
                      ),
                      onTap: _archived
                          ? () => _showProject(project)
                          : () => context.push(
                              '/organizations/$_organizationId/projects/${project['id']}/funding',
                              extra: '${project['name']}',
                            ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _DateField extends StatelessWidget {
  const _DateField({
    required this.label,
    required this.value,
    required this.onChanged,
    this.firstDate,
  });

  final String label;
  final DateTime? value;
  final DateTime? firstDate;
  final ValueChanged<DateTime?> onChanged;

  @override
  Widget build(BuildContext context) => InkWell(
    borderRadius: BorderRadius.circular(12),
    onTap: () async {
      final selected = await showDatePicker(
        context: context,
        initialDate: value ?? firstDate ?? DateTime.now(),
        firstDate: firstDate ?? DateTime(2000),
        lastDate: DateTime(2100),
      );
      if (selected != null) onChanged(selected);
    },
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        suffixIcon: value == null
            ? const Icon(Icons.calendar_today_outlined)
            : IconButton(
                tooltip: 'Effacer',
                onPressed: () => onChanged(null),
                icon: const Icon(Icons.close_rounded),
              ),
      ),
      child: Text(
        value == null
            ? 'Non renseignée'
            : '${value!.day.toString().padLeft(2, '0')}/'
                  '${value!.month.toString().padLeft(2, '0')}/${value!.year}',
      ),
    ),
  );
}
