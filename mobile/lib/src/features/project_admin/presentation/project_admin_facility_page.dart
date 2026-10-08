import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_badge.dart';
import '../data/project_admin_service.dart';
import 'project_admin_widgets.dart';

/// Niveau 5 — Configuration de la FOSA (maquette mobile AdminProjet 07) :
/// informations héritées de la Coordination, détails, paramètres
/// d'approvisionnement, nombre de produits de la Liste Standard.
class ProjectAdminFacilityPage extends StatefulWidget {
  const ProjectAdminFacilityPage({this.facilityId, this.service, super.key});

  /// Null pour déclarer une nouvelle FOSA.
  final String? facilityId;
  final ProjectAdminService? service;

  @override
  State<ProjectAdminFacilityPage> createState() => _ProjectAdminFacilityPageState();
}

class _ProjectAdminFacilityPageState extends State<ProjectAdminFacilityPage> {
  late final ProjectAdminService _service = widget.service ?? ProjectAdminService();
  final _name = TextEditingController();
  final _code = TextEditingController();
  Map<String, dynamic> _options = const {};
  Map<String, dynamic> _facility = const {};
  String? _careLevelId;
  String? _categoryId;
  final Set<String> _populations = {};
  final Set<String> _pathologies = {};
  int? _period;
  int? _lead;
  num? _safety;
  DateTime? _inventory;
  DateTime? _submission;
  DateTime? _receipt;
  bool _active = true;
  int? _count;
  bool _loading = true;
  bool _saving = false;
  String? _error;
  Map<String, String> _fieldErrors = const {};
  Timer? _debounce;

  bool get _editing => widget.facilityId != null;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _name.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final options = await _service.options();
      final facility = _editing ? asMap((await _service.facility(widget.facilityId!))['facility']) : const <String, dynamic>{};
      final source = _editing ? facility : asMap(options['supply_defaults']);
      if (!mounted) return;
      setState(() {
        _options = options;
        _facility = facility;
        _name.text = '${facility['name'] ?? ''}';
        _code.text = '${facility['code'] ?? ''}';
        _careLevelId = facility['care_level_id'] as String?;
        _categoryId = facility['facility_category_id'] as String?;
        _populations.addAll([for (final id in (facility['target_population_ids'] as List? ?? const [])) '$id']);
        _pathologies.addAll([for (final id in (facility['pathology_ids'] as List? ?? const [])) '$id']);
        _period = (source['order_period_months'] as num?)?.toInt();
        _lead = (source['delivery_lead_time_months'] as num?)?.toInt();
        _safety = source['safety_stock_months'] as num?;
        _inventory = DateTime.tryParse('${source['inventory_date'] ?? ''}');
        _submission = DateTime.tryParse('${source['order_submission_date'] ?? ''}');
        _receipt = DateTime.tryParse('${source['order_receipt_date'] ?? ''}');
        _active = facility['is_active'] != false;
        _count = (facility['standard_list_count'] as num?)?.toInt();
      });
    } on ProjectAdminException catch (error) {
      _error = error.message;
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _criteriaChanged() {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), () async {
      try {
        final count = await _service.preview(_criteria());
        if (mounted) setState(() => _count = count);
      } on ProjectAdminException {
        // Le nombre reste affiché tel quel hors ligne.
      }
    });
  }

  Map<String, dynamic> _criteria() => {
    'care_level_id': _careLevelId,
    'facility_category_id': _categoryId,
    'target_population_ids': _populations.toList(),
    'pathology_ids': _pathologies.toList(),
  };

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _error = null;
      _fieldErrors = const {};
    });
    try {
      await _service.saveFacility(widget.facilityId, {
        'name': _name.text.trim(),
        'code': _code.text.trim(),
        ..._criteria(),
        'order_period_months': _period,
        'delivery_lead_time_months': _lead,
        'safety_stock_months': _safety,
        'inventory_date': _iso(_inventory),
        'order_submission_date': _iso(_submission),
        'order_receipt_date': _iso(_receipt),
        if (_editing) 'is_active': _active,
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            _editing
                ? 'Configuration de la FOSA enregistrée.'
                : 'FOSA déclarée : elle attend la validation de la Coordination.',
          ),
        ),
      );
      // Liste déroulante encore ouverte pendant l'enregistrement : fermée d'abord.
      Navigator.of(context).popUntil((route) => route is! PopupRoute);
      context.pop(true);
    } on ProjectAdminException catch (error) {
      if (mounted) {
        setState(() {
          _fieldErrors = error.fieldErrors;
          _error = error.fieldErrors.isEmpty ? error.message : 'Vérifiez les champs signalés.';
        });
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final project = asMap(_options['project']);
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.text,
        elevation: 0,
        scrolledUnderElevation: 0,
        titleSpacing: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(project.isEmpty ? 'Projet' : 'Projet ${project['code']}', style: AppTypography.caption.copyWith(color: AppColors.textMuted)),
            const Text('Configuration de la FOSA', style: AppTypography.title),
          ],
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _options.isEmpty
          ? ProjectAdminMessage(_error ?? ProjectAdminService.offlineMessage)
          : ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                if (_error != null)
                  Container(
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(color: AppColors.dangerSurface, borderRadius: BorderRadius.circular(AppRadius.md)),
                    child: Text(_error!, style: TextStyle(color: AppColors.dangerText)),
                  ),
                _section(
                  title: 'Informations générales',
                  subtitle: 'Définies par la Coordination',
                  children: [
                    for (final (label, value) in [
                      ('Pays', project['country']),
                      ('ONG', project['organization']),
                      ('Bailleur / projet', [project['donor'], project['code']].whereType<String>().join(' · ')),
                      ('Titre', project['name']),
                    ])
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        child: Row(
                          children: [
                            Text(label, style: TextStyle(color: AppColors.textMuted)),
                            const SizedBox(width: 12),
                            Expanded(child: Text('${value ?? '—'}', textAlign: TextAlign.right, style: const TextStyle(fontWeight: FontWeight.w600))),
                          ],
                        ),
                      ),
                  ],
                ),
                _section(
                  title: 'Détails de la FOSA',
                  children: [
                    if (_editing && _facility['validation_status'] != 'validated') ...[
                      Align(
                        alignment: Alignment.centerLeft,
                        child: AppBadge(
                          label: '${_facility['validation_status_label'] ?? ''}',
                          variant: badgeVariant(asMap(_facility['status'])['tone']),
                        ),
                      ),
                      if (_facility['refusal_reason'] != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 6),
                          child: Text('Motif du refus : ${_facility['refusal_reason']}', style: TextStyle(color: AppColors.dangerText)),
                        ),
                      const SizedBox(height: 12),
                    ],
                    TextField(controller: _name, decoration: InputDecoration(labelText: 'Nom de la FOSA *', errorText: _fieldErrors['name'])),
                    const SizedBox(height: 12),
                    TextField(controller: _code, decoration: InputDecoration(labelText: 'Code FOSA *', errorText: _fieldErrors['code'])),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String>(
                      initialValue: _careLevelId,
                      isExpanded: true,
                      decoration: InputDecoration(labelText: 'Niveau de soins *', errorText: _fieldErrors['care_level_id']),
                      items: [
                        for (final level in asMaps(_options['care_levels']))
                          DropdownMenuItem(
                            value: '${level['id']}',
                            child: Text('${'— ' * (((level['depth'] as num?)?.toInt() ?? 1) - 1)}${level['name']}'),
                          ),
                      ],
                      onChanged: (value) {
                        setState(() => _careLevelId = value);
                        _criteriaChanged();
                      },
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String>(
                      initialValue: _categoryId,
                      isExpanded: true,
                      decoration: InputDecoration(labelText: 'Catégorie de FOSA *', errorText: _fieldErrors['facility_category_id']),
                      items: [
                        for (final category in asMaps(_options['facility_categories']))
                          DropdownMenuItem(value: '${category['id']}', child: Text('${category['name']}')),
                      ],
                      onChanged: (value) {
                        setState(() => _categoryId = value);
                        _criteriaChanged();
                      },
                    ),
                    const SizedBox(height: 16),
                    _chips('Population cible *', asMaps(_options['target_populations']), _populations, _fieldErrors['target_population_ids']),
                    const SizedBox(height: 12),
                    _chips('Pathologies / activités *', asMaps(_options['pathologies']), _pathologies, _fieldErrors['pathology_ids']),
                  ],
                ),
                _section(
                  title: 'Paramètres d’approvisionnement',
                  subtitle: 'Pharmacie du projet → FOSA',
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: DropdownButtonFormField<int>(
                            initialValue: _period,
                            decoration: InputDecoration(labelText: 'Périodicité (mois)', errorText: _fieldErrors['order_period_months']),
                            items: [for (var month = 1; month <= 12; month++) DropdownMenuItem(value: month, child: Text('$month'))],
                            onChanged: (value) => setState(() => _period = value),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: DropdownButtonFormField<int>(
                            initialValue: _lead,
                            decoration: InputDecoration(labelText: 'Délai DL (mois)', errorText: _fieldErrors['delivery_lead_time_months']),
                            items: [for (var month = 1; month <= 12; month++) DropdownMenuItem(value: month, child: Text('$month'))],
                            onChanged: (value) => setState(() => _lead = value),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<num>(
                      initialValue: [
                        for (final value in (_options['safety_stock_options'] as List? ?? const [])) if (value is num) value,
                      ].where((option) => option == _safety).firstOrNull,
                      decoration: InputDecoration(labelText: 'Stock de sécurité (mois)', errorText: _fieldErrors['safety_stock_months']),
                      items: [
                        for (final value in (_options['safety_stock_options'] as List? ?? const []))
                          if (value is num) DropdownMenuItem(value: value, child: Text('$value'.replaceAll('.', ','))),
                      ],
                      onChanged: (value) => setState(() => _safety = value),
                    ),
                    const SizedBox(height: 12),
                    _date('Date d’inventaire', _inventory, (value) => _inventory = value),
                    const SizedBox(height: 12),
                    _date('Soumission de commande', _submission, (value) => _submission = value),
                    const SizedBox(height: 12),
                    _date('Réception de commande', _receipt, (value) => _receipt = value, _fieldErrors['order_receipt_date']),
                  ],
                ),
                if (_editing)
                  ProjectAdminCard(
                    child: SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('FOSA active', style: TextStyle(fontWeight: FontWeight.w600)),
                      subtitle: const Text('Une FOSA inactive ne peut plus se connecter ; ses données restent conservées.'),
                      value: _active,
                      onChanged: (value) => setState(() => _active = value),
                    ),
                  ),
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(color: AppColors.infoSurface, borderRadius: BorderRadius.circular(AppRadius.md)),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Liste Standard générée : ${_count == null ? '—' : '$_count produits'}',
                        style: TextStyle(color: AppColors.infoText, fontWeight: FontWeight.w700),
                      ),
                      Text('Modifiable uniquement par la Coordination.', style: TextStyle(color: AppColors.infoText)),
                    ],
                  ),
                ),
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
                        style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                        onPressed: _saving ? null : () => context.pop(),
                        child: const Text('Annuler'),
                      ),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      flex: 2,
                      child: FilledButton(
                        style: FilledButton.styleFrom(
                          backgroundColor: AppColors.primaryStrong,
                          minimumSize: const Size.fromHeight(48),
                        ),
                        onPressed: _saving ? null : _save,
                        child: _saving
                            ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                            : const Text('Enregistrer'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _section({required String title, String? subtitle, required List<Widget> children}) => ProjectAdminCard(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
        if (subtitle != null) Text(subtitle, style: TextStyle(color: AppColors.textMuted)),
        const SizedBox(height: 12),
        ...children,
      ],
    ),
  );

  Widget _chips(String label, List<Map<String, dynamic>> items, Set<String> selected, String? error) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
      const SizedBox(height: 8),
      if (items.isEmpty)
        Text('Aucune valeur configurée par la Coordination pour ce projet.', style: TextStyle(color: AppColors.textMuted)),
      Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final item in items)
            FilterChip(
              label: Text('${item['name']}'),
              selected: selected.contains('${item['id']}'),
              checkmarkColor: AppColors.primaryStrong,
              selectedColor: AppColors.primarySoft,
              onSelected: (value) {
                setState(() => value ? selected.add('${item['id']}') : selected.remove('${item['id']}'));
                _criteriaChanged();
              },
            ),
        ],
      ),
      if (error != null) Padding(padding: const EdgeInsets.only(top: 4), child: Text(error, style: TextStyle(color: AppColors.dangerText, fontSize: 13))),
    ],
  );

  Widget _date(String label, DateTime? value, ValueChanged<DateTime?> assign, [String? error]) => InkWell(
    onTap: () async {
      final picked = await showDatePicker(
        context: context,
        initialDate: value ?? DateTime.now(),
        firstDate: DateTime(2000),
        lastDate: DateTime(2100),
      );
      if (picked != null) setState(() => assign(picked));
    },
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        errorText: error,
        suffixIcon: value == null
            ? const Icon(Icons.calendar_today_outlined)
            : IconButton(tooltip: 'Effacer', onPressed: () => setState(() => assign(null)), icon: const Icon(Icons.close_rounded)),
      ),
      child: Text(value == null ? 'Non renseignée' : '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year}'),
    ),
  );

  static String? _iso(DateTime? date) => date == null
      ? null
      : '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
}
