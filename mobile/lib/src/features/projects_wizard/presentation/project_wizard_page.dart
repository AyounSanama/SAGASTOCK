import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_tokens.dart';
import '../data/project_wizard_service.dart';

/// Niveau 2 — Assistant « Créer un projet / programme » (mobile, maquette
/// Coordination étape 3). Mêmes étapes et mêmes règles serveur que le Web :
/// 1. Informations générales, 2. Bailleur et projet (brouillon enregistré),
/// 3. Configuration de la Liste Standard, 4. Paramètres d'approvisionnement.
class ProjectWizardPage extends StatefulWidget {
  const ProjectWizardPage({
    this.projectId,
    this.initialStep,
    this.service,
    super.key,
  });

  /// Brouillon ou projet existant ; null pour un nouveau projet.
  final String? projectId;

  /// `identity`, `standard-list` ou `supply` ; à défaut, première étape à compléter.
  final String? initialStep;
  final ProjectWizardService? service;

  @override
  State<ProjectWizardPage> createState() => _ProjectWizardPageState();
}

class _ProjectWizardPageState extends State<ProjectWizardPage> {
  static const _titles = [
    'Informations générales',
    'Bailleur et projet',
    'Configuration de la Liste Standard',
    'Paramètres d’approvisionnement',
  ];
  static const _identityFields = {'type', 'mission_id', 'implementing_partner'};

  late final ProjectWizardService _service =
      widget.service ?? ProjectWizardService();

  int _step = 0;
  bool _loading = true;
  bool _busy = false;
  bool _refreshing = false;
  String? _error;
  Map<String, String> _fieldErrors = const {};
  Map<String, dynamic> _options = const {};

  String? _projectId;
  String? _status;
  String _type = 'donor_project';
  String? _missionId;
  String? _donorId;
  final _organization = TextEditingController();
  final _code = TextEditingController();
  final _name = TextEditingController();
  DateTime? _startsOn;
  DateTime? _endsOn;

  Map<String, dynamic> _list = const {};
  final Set<String> _levels = {};
  final Set<String> _populations = {};
  final Set<String> _pathologies = {};
  final Set<String> _excluded = {};
  final Set<String> _added = {};
  Timer? _debounce;

  int? _period;
  int? _lead;
  num? _safety;
  DateTime? _inventory;
  DateTime? _submission;
  DateTime? _receipt;

  bool get _editing => _status != null && _status != 'draft';

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _organization.dispose();
    _code.dispose();
    _name.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final options = await _service.options();
      final payload = widget.projectId == null
          ? null
          : await _service.project(widget.projectId!);
      _options = options;
      final missions = _maps(options['missions']);
      if (missions.isNotEmpty) {
        _missionId = '${missions.first['id']}';
        _organization.text = '${missions.first['organization_name'] ?? ''}';
      }
      if (payload != null) {
        _applyProject(_map(payload['project']));
        _step = switch (widget.initialStep ?? payload['resume_step']) {
          'supply' => 3,
          'standard-list' => 2,
          _ => 0,
        };
        if (_step >= 2) await _loadList();
      }
    } on ProjectWizardException catch (error) {
      _error = error.message;
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _applyProject(Map<String, dynamic> project) {
    _projectId = '${project['id']}';
    _status = project['status'] as String?;
    _type = '${project['type'] ?? 'donor_project'}';
    _missionId = project['mission_id'] as String? ?? _missionId;
    _organization.text = '${project['implementing_partner'] ?? ''}';
    _donorId = project['donor_id'] as String?;
    _code.text = '${project['code'] ?? ''}';
    _name.text = '${project['name'] ?? ''}';
    _startsOn = DateTime.tryParse('${project['starts_on'] ?? ''}');
    _endsOn = DateTime.tryParse('${project['ends_on'] ?? ''}');
    _period = (project['order_period_months'] as num?)?.toInt();
    _lead = (project['delivery_lead_time_months'] as num?)?.toInt();
    _safety = project['safety_stock_months'] as num?;
    _inventory = DateTime.tryParse('${project['inventory_date'] ?? ''}');
    _submission = DateTime.tryParse('${project['order_submission_date'] ?? ''}');
    _receipt = DateTime.tryParse('${project['order_receipt_date'] ?? ''}');
  }

  /// Étape 3 : liste enregistrée, ou recalculée pour les choix en cours.
  Future<void> _loadList({bool refresh = false}) async {
    final products = _maps(_list['products']);
    final data = await _service.standardList(
      _projectId!,
      selection: refresh
          ? {
              'care_level_ids': _levels.toList(),
              'target_population_ids': _populations.toList(),
              'pathology_ids': _pathologies.toList(),
              'listed': [for (final product in products) '${product['id']}'],
              'retained': [
                for (final product in products)
                  if (!_excluded.contains('${product['id']}')) '${product['id']}',
              ],
              'added': _added.toList(),
            }
          : null,
    );
    if (!mounted) return;
    setState(() {
      _list = data;
      if (!refresh) {
        _levels
          ..clear()
          ..addAll(_ids(data['care_level_ids']));
        _populations
          ..clear()
          ..addAll(_ids(data['target_population_ids']));
        _pathologies
          ..clear()
          ..addAll(_ids(data['pathology_ids']));
      }
      final rows = _maps(data['products']);
      _excluded
        ..clear()
        ..addAll([
          for (final row in rows)
            if (row['retained'] != true) '${row['id']}',
        ]);
      _added
        ..clear()
        ..addAll([
          for (final row in rows)
            if (row['added'] == true) '${row['id']}',
        ]);
    });
  }

  void _scheduleRefresh() {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), () async {
      setState(() => _refreshing = true);
      try {
        await _loadList(refresh: true);
      } on ProjectWizardException catch (error) {
        if (mounted) setState(() => _error = error.message);
      } finally {
        if (mounted) setState(() => _refreshing = false);
      }
    });
  }

  Map<String, dynamic> _identityData() => {
    'type': _type,
    'mission_id': _missionId,
    'implementing_partner': _organization.text.trim(),
    'donor_id': _donorId,
    'code': _code.text.trim(),
    'name': _name.text.trim(),
    'starts_on': _iso(_startsOn),
    'ends_on': _iso(_endsOn),
  };

  Map<String, dynamic> _listData() => {
    'care_level_ids': _levels.toList(),
    'target_population_ids': _populations.toList(),
    'pathology_ids': _pathologies.toList(),
    'retained': [
      for (final product in _maps(_list['products']))
        if (!_excluded.contains('${product['id']}')) '${product['id']}',
    ],
    'added': _added.toList(),
  };

  Map<String, dynamic> _supplyData() => {
    'order_period_months': _period,
    'delivery_lead_time_months': _lead,
    'safety_stock_months': _safety,
    'inventory_date': _iso(_inventory),
    'order_submission_date': _iso(_submission),
    'order_receipt_date': _iso(_receipt),
  };

  Future<void> _next() async {
    final errors = _localErrors();
    if (errors.isNotEmpty) {
      setState(() => _fieldErrors = errors);
      return;
    }
    if (_step == 0) {
      setState(() {
        _fieldErrors = const {};
        _step = 1;
      });
      return;
    }
    await _run(() async {
      switch (_step) {
        case 1:
          final identity = await _service.saveIdentity(_projectId, _identityData());
          _applyProject(_map(identity['project']));
          await _loadList();
          _goTo(2);
        case 2:
          await _service.saveStandardList(_projectId!, _listData());
          _goTo(3);
        default:
          final wasActive = _editing;
          final supply = await _service.saveSupply(_projectId!, _supplyData());
          final code = _map(supply['project'])['code'];
          _close(
            wasActive
                ? 'Projet $code mis à jour.'
                : 'Projet $code créé : il est « Actif ». Créez maintenant le compte de son Admin Projet dans « Comptes ».',
          );
      }
    });
  }

  Future<void> _saveDraft() => _run(() async {
    switch (_step) {
      case 0:
      case 1:
        await _service.saveIdentity(_projectId, _identityData(), draft: true);
      case 2:
        await _service.saveStandardList(_projectId!, _listData(), draft: true);
      default:
        await _service.saveSupply(_projectId!, _supplyData(), draft: true);
    }
    _close('Brouillon du projet ${_code.text.trim()} enregistré.');
  });

  Future<void> _run(Future<void> Function() action) async {
    setState(() {
      _busy = true;
      _error = null;
      _fieldErrors = const {};
    });
    try {
      await action();
    } on ProjectWizardException catch (error) {
      if (!mounted) return;
      setState(() {
        _fieldErrors = error.fieldErrors;
        _error = error.fieldErrors.isEmpty ? error.message : null;
        // Erreur sur un champ d'une étape précédente : y revenir.
        if (_step <= 1 && error.fieldErrors.isNotEmpty) {
          _step = error.fieldErrors.keys.every(_identityFields.contains) ? 0 : 1;
        }
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _goTo(int step) {
    if (mounted) setState(() => _step = step);
  }

  void _close(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    context.pop(true);
  }

  void _previous() {
    if (_step == 0) {
      context.pop();
    } else {
      setState(() {
        _fieldErrors = const {};
        _error = null;
        _step -= 1;
      });
    }
  }

  /// Contrôles immédiats ; le serveur reste la référence.
  Map<String, String> _localErrors() {
    const missing = 'Champ obligatoire.';
    return switch (_step) {
      0 => {
        if (_missionId == null) 'mission_id': missing,
        if (_organization.text.trim().isEmpty) 'implementing_partner': missing,
      },
      1 => {
        if (_type == 'donor_project' && _donorId == null)
          'donor_id': 'Sélectionnez le bailleur du projet.',
        if (_code.text.trim().isEmpty) 'code': missing,
        if (_name.text.trim().isEmpty) 'name': missing,
      },
      2 => {
        if (_levels.isEmpty) 'care_level_ids': 'Sélectionnez au moins un niveau de soins.',
        if (_populations.isEmpty)
          'target_population_ids': 'Sélectionnez au moins une population cible.',
      },
      _ => {
        if (_period == null) 'order_period_months': missing,
        if (_lead == null) 'delivery_lead_time_months': missing,
        if (_safety == null) 'safety_stock_months': missing,
      },
    };
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.text,
        elevation: 0,
        scrolledUnderElevation: 0,
        leading: IconButton(
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back),
          onPressed: _busy ? null : _previous,
        ),
        titleSpacing: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Étape ${_step + 1} sur 4',
              style: AppTypography.caption.copyWith(color: AppColors.textMuted),
            ),
            Text(
              _titles[_step],
              style: AppTypography.title,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
        actions: [
          if (!_editing && !_loading && _options.isNotEmpty)
            TextButton(
              onPressed: _busy ? null : _saveDraft,
              child: const Text('Brouillon'),
            ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(3),
          child: LinearProgressIndicator(
            value: (_step + 1) / 4,
            minHeight: 3,
            backgroundColor: AppColors.border,
            color: AppColors.primary,
          ),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _options.isEmpty
          ? _Failure(message: _error ?? ProjectWizardService.offlineMessage, onRetry: _load)
          : ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                if (_error != null) _Notice(text: _error!, danger: true),
                ...switch (_step) {
                  0 => _identityStep(),
                  1 => _donorStep(),
                  2 => _standardListStep(),
                  _ => _supplyStep(),
                },
              ],
            ),
      bottomNavigationBar: _loading || _options.isEmpty
          ? null
          : SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.all(AppSpacing.lg),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  border: Border(top: BorderSide(color: AppColors.border)),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        style: OutlinedButton.styleFrom(
                          minimumSize: const Size.fromHeight(48),
                        ),
                        onPressed: _busy ? null : _previous,
                        child: Text(_step == 0 ? 'Annuler' : 'Précédent'),
                      ),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      flex: 2,
                      child: FilledButton(
                        style: FilledButton.styleFrom(
                          backgroundColor: AppColors.primaryStrong,
                          foregroundColor: Colors.white,
                          minimumSize: const Size.fromHeight(48),
                        ),
                        onPressed: _busy || _refreshing ? null : _next,
                        child: _busy
                            ? const SizedBox.square(
                                dimension: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : Text(
                                _step < 3
                                    ? 'Suivant'
                                    : (_editing ? 'Enregistrer' : 'Créer le projet'),
                              ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }

  // Étape 1 ---------------------------------------------------------------

  List<Widget> _identityStep() {
    final missions = _maps(_options['missions']);
    final mission = missions.where((item) => item['id'] == _missionId).firstOrNull;
    return [
      _Section(
        title: 'Type',
        subtitle: 'Un programme national du Ministère de la Santé se crée comme un projet.',
        children: [
          _TypeCard(
            selected: _type == 'donor_project',
            title: 'Projet bailleur',
            subtitle: 'Financé par un bailleur (ex. : GFFO5, FH4). Un bailleur par projet.',
            onTap: () => setState(() => _type = 'donor_project'),
          ),
          const SizedBox(height: AppSpacing.sm),
          _TypeCard(
            selected: _type == 'national_program',
            title: 'Programme national',
            subtitle: 'Programme du Ministère de la Santé (ex. : PNLT, PNLP). Bailleur facultatif.',
            onTap: () => setState(() => _type = 'national_program'),
          ),
          const SizedBox(height: AppSpacing.lg),
          DropdownButtonFormField<String>(
            key: ValueKey('mission-$_missionId'),
            initialValue: _missionId,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: 'Mission (pays) *',
              errorText: _fieldErrors['mission_id'],
            ),
            items: [
              for (final item in missions)
                DropdownMenuItem(
                  value: '${item['id']}',
                  child: Text(
                    missions.where((other) => other['country'] == item['country']).length > 1
                        ? '${item['country']} · ${item['name']}'
                        : '${item['country'] ?? item['name']}',
                  ),
                ),
            ],
            onChanged: (value) => setState(() {
              _missionId = value;
              if (!_donors().any((donor) => donor['id'] == _donorId)) _donorId = null;
            }),
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: _organization,
            maxLength: 190,
            decoration: InputDecoration(
              labelText: 'Organisation ou programme *',
              counterText: '',
              errorText: _fieldErrors['implementing_partner'],
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          InputDecorator(
            decoration: InputDecoration(
              labelText: 'Coordination',
              filled: true,
              fillColor: AppColors.disabledSurface,
            ),
            child: Text(
              '${mission?['name'] ?? '—'} (votre coordination)',
              style: TextStyle(color: AppColors.textMuted),
            ),
          ),
        ],
      ),
    ];
  }

  // Étape 2 ---------------------------------------------------------------

  List<Map<String, dynamic>> _donors() {
    final mission = _maps(_options['missions']).where((item) => item['id'] == _missionId).firstOrNull;
    return _maps(_options['donors'])
        .where((donor) => donor['organization_id'] == mission?['organization_id'])
        .toList();
  }

  List<Widget> _donorStep() {
    final national = _type == 'national_program';
    final donors = _donors();
    return [
      _Section(
        title: 'Bailleur et projet',
        subtitle: 'Un projet est rattaché à un seul bailleur.',
        children: [
          DropdownButtonFormField<String?>(
            key: ValueKey('donor-$_donorId-${donors.length}-$national'),
            initialValue: _donorId,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: national ? 'Bailleur (facultatif)' : 'Bailleur *',
              errorText: _fieldErrors['donor_id'],
            ),
            items: [
              DropdownMenuItem<String?>(
                value: null,
                child: Text(
                  national
                      ? 'Aucun (${_options['national_program_default_donor'] ?? 'Ministère de la Santé'})'
                      : 'Sélectionner un bailleur',
                ),
              ),
              for (final donor in donors)
                DropdownMenuItem<String?>(
                  value: '${donor['id']}',
                  child: Text('${donor['name']}'),
                ),
            ],
            onChanged: (value) => setState(() => _donorId = value),
          ),
          if (_options['can_add_donor'] == true)
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton(
                onPressed: _busy ? null : _addDonor,
                child: const Text('+ Ajouter un bailleur'),
              ),
            ),
          const SizedBox(height: AppSpacing.sm),
          TextField(
            controller: _code,
            maxLength: 50,
            textCapitalization: TextCapitalization.characters,
            decoration: InputDecoration(
              labelText: 'Code bailleur / programme MoH *',
              hintText: 'ex. GFFO5, PNLT',
              counterText: '',
              errorText: _fieldErrors['code'],
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: _name,
            maxLength: 180,
            decoration: InputDecoration(
              labelText: 'Intitulé du projet *',
              counterText: '',
              errorText: _fieldErrors['name'],
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          _DateTile(
            label: 'Date de début',
            value: _startsOn,
            onChanged: (value) => setState(() => _startsOn = value),
          ),
          const SizedBox(height: AppSpacing.md),
          _DateTile(
            label: 'Date de fin',
            value: _endsOn,
            errorText: _fieldErrors['ends_on'],
            onChanged: (value) => setState(() => _endsOn = value),
          ),
        ],
      ),
    ];
  }

  Future<void> _addDonor() async {
    final name = TextEditingController();
    final code = TextEditingController();
    String? error;
    var saving = false;
    final donor = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogContext, setDialogState) => AlertDialog(
          title: const Text('Ajouter un bailleur'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Le bailleur est ajouté au référentiel et sélectionné pour ce projet.',
                style: TextStyle(color: AppColors.textMuted),
              ),
              const SizedBox(height: AppSpacing.md),
              TextField(
                controller: name,
                decoration: const InputDecoration(labelText: 'Nom du bailleur *'),
              ),
              const SizedBox(height: AppSpacing.md),
              TextField(
                controller: code,
                textCapitalization: TextCapitalization.characters,
                decoration: InputDecoration(labelText: 'Sigle *', errorText: error),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: saving ? null : () => Navigator.pop(dialogContext),
              child: const Text('Annuler'),
            ),
            FilledButton(
              style: FilledButton.styleFrom(backgroundColor: AppColors.primaryStrong),
              onPressed: saving
                  ? null
                  : () async {
                      setDialogState(() {
                        saving = true;
                        error = null;
                      });
                      try {
                        final created = await _service.addDonor(
                          missionId: _missionId!,
                          name: name.text.trim(),
                          code: code.text.trim(),
                        );
                        if (dialogContext.mounted) Navigator.pop(dialogContext, created);
                      } on ProjectWizardException catch (exception) {
                        setDialogState(() {
                          saving = false;
                          error = exception.message;
                        });
                      }
                    },
              child: const Text('Ajouter'),
            ),
          ],
        ),
      ),
    );
    name.dispose();
    code.dispose();
    if (donor == null || !mounted) return;
    setState(() {
      _options = {
        ..._options,
        'donors': [..._maps(_options['donors']), donor],
      };
      _donorId = '${donor['id']}';
    });
  }

  // Étape 3 ---------------------------------------------------------------

  List<Widget> _standardListStep() {
    final options = _map(_list['options']);
    final products = _maps(_list['products']);
    final retained = products.where((product) => !_excluded.contains('${product['id']}')).length;
    void toggle(Set<String> values, String id) {
      setState(() => values.contains(id) ? values.remove(id) : values.add(id));
      _scheduleRefresh();
    }

    return [
      _ChoiceGroup(
        title: 'Niveaux de soins *',
        items: _maps(options['care_levels']),
        selected: _levels,
        errorText: _fieldErrors['care_level_ids'],
        onToggle: (id) => toggle(_levels, id),
      ),
      _ChoiceGroup(
        title: 'Population cible *',
        items: _maps(options['target_populations']),
        selected: _populations,
        errorText: _fieldErrors['target_population_ids'],
        onToggle: (id) => toggle(_populations, id),
      ),
      _ChoiceGroup(
        title: 'Pathologies et activités *',
        items: _maps(options['pathologies']),
        selected: _pathologies,
        onToggle: (id) => toggle(_pathologies, id),
      ),
      const SizedBox(height: AppSpacing.sm),
      Container(
        padding: const EdgeInsets.all(AppSpacing.lg),
        decoration: BoxDecoration(
          color: AppColors.surface,
          border: Border.all(color: AppColors.border),
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Liste Standard : $retained / ${products.length}',
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
                      ),
                      Text(
                        'produits retenus',
                        style: TextStyle(color: AppColors.textMuted),
                      ),
                    ],
                  ),
                ),
                OutlinedButton(
                  onPressed: products.isEmpty && _maps(_list['addable']).isEmpty
                      ? null
                      : _openProducts,
                  child: const Text('Voir et ajuster'),
                ),
              ],
            ),
            if (_refreshing) ...[
              const SizedBox(height: AppSpacing.md),
              const LinearProgressIndicator(color: AppColors.primary),
            ],
            if (_fieldErrors['retained'] != null) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(_fieldErrors['retained']!, style: TextStyle(color: AppColors.dangerText)),
            ],
            if (products.isEmpty && !_refreshing) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                _levels.isEmpty || _populations.isEmpty
                    ? 'Sélectionnez au moins un niveau de soins et une population cible pour générer la liste.'
                    : 'Aucun produit du catalogue ne correspond à ces choix.',
                style: TextStyle(color: AppColors.textMuted),
              ),
            ],
          ],
        ),
      ),
    ];
  }

  Future<void> _openProducts() async {
    var search = '';
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: AppColors.surface,
      builder: (sheetContext) => StatefulBuilder(
        builder: (sheetContext, setSheetState) {
          final products = _maps(_list['products']);
          final addable = _maps(_list['addable']);
          final visible = products.where((product) {
            final term = search.trim().toLowerCase();
            return term.isEmpty ||
                '${product['code']} ${product['name']}'.toLowerCase().contains(term);
          }).toList();
          final retained = products.where((product) => !_excluded.contains('${product['id']}')).length;
          return SizedBox(
            height: MediaQuery.sizeOf(sheetContext).height * 0.85,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Liste Standard : $retained / ${products.length} produits retenus',
                        style: AppTypography.title,
                      ),
                      Text(
                        'Cochez ou décochez selon les protocoles de votre organisation.',
                        style: TextStyle(color: AppColors.textMuted),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      TextField(
                        decoration: const InputDecoration(
                          prefixIcon: Icon(Icons.search),
                          hintText: 'Code ou désignation',
                        ),
                        onChanged: (value) => setSheetState(() => search = value),
                      ),
                      if (addable.isNotEmpty)
                        TextButton(
                          onPressed: () async {
                            final id = await _pickProduct(sheetContext, addable);
                            if (id == null) return;
                            _added.add(id);
                            if (sheetContext.mounted) Navigator.pop(sheetContext);
                            _scheduleRefresh();
                          },
                          child: const Text('+ Ajouter un produit'),
                        ),
                    ],
                  ),
                ),
                const Divider(height: 1),
                Expanded(
                  child: ListView.separated(
                    itemCount: visible.length,
                    separatorBuilder: (_, _) => const Divider(height: 1),
                    itemBuilder: (_, index) {
                      final product = visible[index];
                      final id = '${product['id']}';
                      return CheckboxListTile(
                        value: !_excluded.contains(id),
                        activeColor: AppColors.primaryStrong,
                        controlAffinity: ListTileControlAffinity.leading,
                        title: Text('${product['name']}'),
                        subtitle: Text(
                          [
                            product['code'],
                            product['packaging'],
                            product['pathology'],
                            if (product['added'] == true) 'Ajouté',
                          ].where((value) => value != null && '$value'.isNotEmpty).join(' · '),
                        ),
                        onChanged: (value) {
                          setState(() => value == true ? _excluded.remove(id) : _excluded.add(id));
                          setSheetState(() {});
                        },
                      );
                    },
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Future<String?> _pickProduct(BuildContext context, List<Map<String, dynamic>> addable) {
    String? selected;
    return showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Ajouter un produit'),
        content: DropdownButtonFormField<String>(
          isExpanded: true,
          decoration: const InputDecoration(labelText: 'Produit *'),
          items: [
            for (final product in addable)
              DropdownMenuItem(
                value: '${product['id']}',
                child: Text('${product['name']}', overflow: TextOverflow.ellipsis),
              ),
          ],
          onChanged: (value) => selected = value,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Annuler'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AppColors.primaryStrong),
            onPressed: () => Navigator.pop(dialogContext, selected),
            child: const Text('Ajouter'),
          ),
        ],
      ),
    );
  }

  // Étape 4 ---------------------------------------------------------------

  List<Widget> _supplyStep() {
    final safetyOptions = [
      for (final value in (_options['safety_stock_options'] as List? ?? const []))
        if (value is num) value,
    ];
    return [
      _Section(
        title: 'Paramètres d’approvisionnement',
        subtitle: 'Ils seront proposés par défaut à chaque FOSA du projet, puis modifiables par FOSA.',
        children: [
          DropdownButtonFormField<int>(
            initialValue: _period,
            decoration: InputDecoration(
              labelText: 'Périodicité de commande *',
              helperText: 'de 1 à 12 mois',
              errorText: _fieldErrors['order_period_months'],
            ),
            items: [
              for (var month = 1; month <= 12; month++)
                DropdownMenuItem(value: month, child: Text('$month mois')),
            ],
            onChanged: (value) => setState(() => _period = value),
          ),
          const SizedBox(height: AppSpacing.md),
          DropdownButtonFormField<int>(
            initialValue: _lead,
            decoration: InputDecoration(
              labelText: 'Délai de livraison *',
              helperText: 'en mois',
              errorText: _fieldErrors['delivery_lead_time_months'],
            ),
            items: [
              for (var month = 1; month <= 12; month++)
                DropdownMenuItem(value: month, child: Text('$month mois')),
            ],
            onChanged: (value) => setState(() => _lead = value),
          ),
          const SizedBox(height: AppSpacing.md),
          DropdownButtonFormField<num>(
            initialValue: safetyOptions.where((option) => option == _safety).firstOrNull,
            decoration: InputDecoration(
              labelText: 'Stock de sécurité *',
              helperText: '0,25 / 0,5 / 0,75 / 1 / 1,5 / 2 mois',
              errorText: _fieldErrors['safety_stock_months'],
            ),
            items: [
              for (final months in safetyOptions)
                DropdownMenuItem(value: months, child: Text('${_decimal(months)} mois')),
            ],
            onChanged: (value) => setState(() => _safety = value),
          ),
          const SizedBox(height: AppSpacing.md),
          _DateTile(
            label: 'Date d’inventaire',
            value: _inventory,
            onChanged: (value) => setState(() => _inventory = value),
          ),
          const SizedBox(height: AppSpacing.md),
          _DateTile(
            label: 'Date de soumission de commande',
            value: _submission,
            onChanged: (value) => setState(() => _submission = value),
          ),
          const SizedBox(height: AppSpacing.md),
          _DateTile(
            label: 'Date de réception de commande',
            value: _receipt,
            errorText: _fieldErrors['order_receipt_date'],
            onChanged: (value) => setState(() => _receipt = value),
          ),
        ],
      ),
      _Notice(
        text: _editing
            ? 'Les nouvelles valeurs sont proposées aux FOSA déclarées ensuite ; chaque modification est historisée.'
            : 'Après création, le projet passe « Actif ». Vous pourrez ensuite créer le compte de l’Admin Projet dans Ma Coordination > Comptes.',
      ),
    ];
  }

  static String _decimal(num value) => '$value'.replaceAll('.', ',');

  static String? _iso(DateTime? date) => date == null
      ? null
      : '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

  static Map<String, dynamic> _map(Object? value) =>
      value is Map ? Map<String, dynamic>.from(value) : const {};

  static List<Map<String, dynamic>> _maps(Object? value) => value is List
      ? [for (final item in value) if (item is Map) Map<String, dynamic>.from(item)]
      : const [];

  static Iterable<String> _ids(Object? value) =>
      value is List ? value.map((item) => '$item') : const [];
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.children, this.subtitle});

  final String title;
  final String? subtitle;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: AppSpacing.lg),
    padding: const EdgeInsets.all(AppSpacing.lg),
    decoration: BoxDecoration(
      color: AppColors.surface,
      border: Border.all(color: AppColors.border),
      borderRadius: BorderRadius.circular(AppRadius.lg),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
        if (subtitle != null) ...[
          const SizedBox(height: AppSpacing.xs),
          Text(subtitle!, style: TextStyle(color: AppColors.textMuted)),
        ],
        const SizedBox(height: AppSpacing.lg),
        ...children,
      ],
    ),
  );
}

class _TypeCard extends StatelessWidget {
  const _TypeCard({
    required this.selected,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final bool selected;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    selected: selected,
    inMutuallyExclusiveGroup: true,
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.md),
        decoration: BoxDecoration(
          color: selected ? AppColors.primarySoft : AppColors.surface,
          border: Border.all(color: selected ? AppColors.primaryStrong : AppColors.border),
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              selected ? Icons.radio_button_checked : Icons.radio_button_unchecked,
              color: selected ? AppColors.primaryStrong : AppColors.controlBorder,
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 2),
                  Text(subtitle, style: TextStyle(color: AppColors.textMuted, fontSize: 13)),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

/// Puces à cocher ; au-delà de 4 choix, « + N autres » déplie la liste.
class _ChoiceGroup extends StatefulWidget {
  const _ChoiceGroup({
    required this.title,
    required this.items,
    required this.selected,
    required this.onToggle,
    this.errorText,
  });

  final String title;
  final List<Map<String, dynamic>> items;
  final Set<String> selected;
  final ValueChanged<String> onToggle;
  final String? errorText;

  @override
  State<_ChoiceGroup> createState() => _ChoiceGroupState();
}

class _ChoiceGroupState extends State<_ChoiceGroup> {
  static const _collapsedCount = 4;
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    final items = widget.items;
    final visible = [
      for (var index = 0; index < items.length; index++)
        if (_expanded ||
            index < _collapsedCount ||
            widget.selected.contains('${items[index]['id']}'))
          items[index],
    ];
    final hidden = items.length - visible.length;
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(widget.title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
          const SizedBox(height: AppSpacing.sm),
          if (items.isEmpty)
            Text('Aucune valeur dans le référentiel médical.', style: TextStyle(color: AppColors.textMuted)),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              for (final item in visible)
                _choiceChip(
                  label: '${item['name']}',
                  selected: widget.selected.contains('${item['id']}'),
                  onSelected: () => widget.onToggle('${item['id']}'),
                ),
              if (hidden > 0)
                ActionChip(
                  label: Text('+ $hidden autres'),
                  shape: StadiumBorder(side: BorderSide(color: AppColors.border)),
                  backgroundColor: AppColors.surface,
                  onPressed: () => setState(() => _expanded = true),
                ),
            ],
          ),
          if (widget.errorText != null) ...[
            const SizedBox(height: AppSpacing.xs),
            Text(widget.errorText!, style: TextStyle(color: AppColors.dangerText, fontSize: 13)),
          ],
        ],
      ),
    );
  }

  Widget _choiceChip({
    required String label,
    required bool selected,
    required VoidCallback onSelected,
  }) => FilterChip(
    label: Text(label),
    selected: selected,
    onSelected: (_) => onSelected(),
    showCheckmark: true,
    checkmarkColor: AppColors.primaryStrong,
    selectedColor: AppColors.primarySoft,
    backgroundColor: AppColors.surface,
    labelStyle: TextStyle(color: selected ? AppColors.primaryStrong : AppColors.text),
    shape: StadiumBorder(
      side: BorderSide(color: selected ? AppColors.primary : AppColors.border),
    ),
  );
}

class _DateTile extends StatelessWidget {
  const _DateTile({
    required this.label,
    required this.value,
    required this.onChanged,
    this.errorText,
  });

  final String label;
  final DateTime? value;
  final String? errorText;
  final ValueChanged<DateTime?> onChanged;

  @override
  Widget build(BuildContext context) => InkWell(
    borderRadius: BorderRadius.circular(AppRadius.md),
    onTap: () async {
      final selected = await showDatePicker(
        context: context,
        initialDate: value ?? DateTime.now(),
        firstDate: DateTime(2000),
        lastDate: DateTime(2100),
      );
      if (selected != null) onChanged(selected);
    },
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        errorText: errorText,
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
            : '${value!.day.toString().padLeft(2, '0')}/${value!.month.toString().padLeft(2, '0')}/${value!.year}',
      ),
    ),
  );
}

class _Notice extends StatelessWidget {
  const _Notice({required this.text, this.danger = false});

  final String text;
  final bool danger;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: AppSpacing.lg),
    padding: const EdgeInsets.all(AppSpacing.md),
    decoration: BoxDecoration(
      color: danger ? AppColors.dangerSurface : AppColors.infoSurface,
      borderRadius: BorderRadius.circular(AppRadius.md),
    ),
    child: Text(
      text,
      style: TextStyle(color: danger ? AppColors.dangerText : AppColors.infoText),
    ),
  );
}

class _Failure extends StatelessWidget {
  const _Failure({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(AppSpacing.xl),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.cloud_off_outlined, size: 40, color: AppColors.textMuted),
          const SizedBox(height: AppSpacing.md),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: AppSpacing.md),
          OutlinedButton(onPressed: onRetry, child: const Text('Réessayer')),
        ],
      ),
    ),
  );
}
