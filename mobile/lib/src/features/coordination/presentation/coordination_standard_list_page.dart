import 'package:flutter/material.dart';

import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_badge.dart';
import '../../project_admin/presentation/project_admin_widgets.dart';
import '../data/coordination_service.dart';

/// Niveau 6 — « Liste Standard » de la Coordination (mobile) : liste de chaque
/// projet, décochage d'articles par FOSA et lien code-barres ↔ produit.
/// Consultable hors ligne ; les modifications exigent le réseau.
class CoordinationStandardListPage extends StatefulWidget {
  const CoordinationStandardListPage({this.service, super.key});

  final CoordinationService? service;

  @override
  State<CoordinationStandardListPage> createState() => _CoordinationStandardListPageState();
}

class _CoordinationStandardListPageState extends State<CoordinationStandardListPage> {
  late final CoordinationService _service = widget.service ?? CoordinationService();
  Map<String, dynamic> _data = const {};
  bool _loading = true;
  bool _saving = false;
  String? _error;
  String? _projectId;
  String? _facilityId;
  String _search = '';
  String? _pathology;
  final Set<String> _retained = {};

  bool get _editable => _facilityId != null && _data['can_manage'] == true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await _service.standardList(projectId: _projectId, facilityId: _facilityId);
      if (!mounted) return;
      setState(() {
        _data = data;
        _projectId ??= _map(data['project'])['id'] as String?;
        _retained
          ..clear()
          ..addAll([
            for (final product in asMaps(data['products']))
              if (product['retained'] == true) '${product['id']}',
          ]);
      });
    } catch (_) {
      if (mounted) setState(() => _error = 'Liste indisponible. Vérifiez la connexion puis réessayez.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _saveFacility() async {
    setState(() => _saving = true);
    try {
      await _service.saveFacilityList(_projectId!, _facilityId!, _retained.toList());
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Liste Standard de la FOSA enregistrée.')));
      await _load();
    } on CoordinationActionException catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _editBarcode(Map<String, dynamic> product) async {
    final controller = TextEditingController(text: '${product['barcode'] ?? ''}');
    final value = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Code-barres'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('${product['name']}', style: TextStyle(color: AppColors.textMuted)),
            TextField(
              controller: controller,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Code-barres', helperText: 'Vide : lien retiré'),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Annuler')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AppColors.primaryStrong),
            onPressed: () => Navigator.pop(dialogContext, controller.text.trim()),
            child: const Text('Enregistrer'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (value == null || !mounted) return;
    try {
      await _service.saveBarcode(_projectId!, '${product['id']}', value);
      await _load();
    } on CoordinationActionException catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final products = asMaps(_data['products']);
    final projects = asMaps(_data['projects']);
    final facilities = asMaps(_data['facilities']);
    final pathologies = [for (final item in (_data['pathologies'] as List? ?? const [])) '$item'];
    final term = _search.trim().toLowerCase();
    final visible = products
        .where((product) => term.isEmpty ||
            '${product['code']} ${product['name']} ${product['barcode'] ?? ''}'.toLowerCase().contains(term))
        .where((product) => _pathology == null || product['pathology'] == _pathology)
        .toList();

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Liste Standard')),
      bottomNavigationBar: _editable && products.isNotEmpty
          ? SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                child: FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.primaryStrong,
                    minimumSize: const Size.fromHeight(48),
                  ),
                  onPressed: _saving ? null : _saveFacility,
                  child: Text('Enregistrer pour cette FOSA (${_retained.length} / ${products.length})'),
                ),
              ),
            )
          : null,
      body: Column(
        children: [
          Container(
            color: AppColors.surface,
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (_data['title'] != null)
                  Text('${_data['title']}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(color: AppColors.infoSurface, borderRadius: BorderRadius.circular(AppRadius.md)),
                  child: Text(
                    'Choisissez une FOSA pour décocher les articles qu’elle ne doit pas recevoir.',
                    style: TextStyle(color: AppColors.infoText),
                  ),
                ),
                const SizedBox(height: 10),
                if (projects.length > 1) ...[
                  DropdownButtonFormField<String>(
                    key: ValueKey('project-$_projectId'),
                    initialValue: _projectId,
                    isExpanded: true,
                    decoration: const InputDecoration(labelText: 'Projet', isDense: true),
                    items: [
                      for (final project in projects)
                        DropdownMenuItem(value: '${project['id']}', child: Text('${project['code']} · ${project['name']}')),
                    ],
                    onChanged: (value) {
                      setState(() {
                        _projectId = value;
                        _facilityId = null;
                      });
                      _load();
                    },
                  ),
                  const SizedBox(height: 10),
                ],
                if (facilities.isNotEmpty) ...[
                  DropdownButtonFormField<String?>(
                    key: ValueKey('facility-$_projectId-$_facilityId'),
                    initialValue: _facilityId,
                    isExpanded: true,
                    decoration: const InputDecoration(labelText: 'FOSA', isDense: true),
                    items: [
                      const DropdownMenuItem<String?>(value: null, child: Text('Toutes les FOSA du projet')),
                      for (final facility in facilities)
                        DropdownMenuItem<String?>(value: '${facility['id']}', child: Text('${facility['name']}')),
                    ],
                    onChanged: (value) {
                      setState(() => _facilityId = value);
                      _load();
                    },
                  ),
                  const SizedBox(height: 10),
                ],
                TextField(
                  decoration: const InputDecoration(
                    prefixIcon: Icon(Icons.search),
                    hintText: 'Code, désignation ou code-barres',
                    isDense: true,
                  ),
                  onChanged: (value) => setState(() => _search = value),
                ),
                if (pathologies.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        for (final (value, label) in <(String?, String)>[
                          (null, 'Toutes'),
                          for (final pathology in pathologies) (pathology, pathology),
                        ])
                          Padding(
                            padding: const EdgeInsets.only(right: 8),
                            child: ChoiceChip(
                              label: Text(label),
                              selected: _pathology == value,
                              showCheckmark: false,
                              selectedColor: AppColors.primaryStrong,
                              labelStyle: TextStyle(color: _pathology == value ? Colors.white : AppColors.text),
                              onSelected: (_) => setState(() => _pathology = value),
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
          const Divider(height: 1),
          if (_loading && _data.isNotEmpty) const LinearProgressIndicator(minHeight: 2),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                children: [
                  if (_loading && _data.isEmpty)
                    const Padding(padding: EdgeInsets.all(32), child: Center(child: CircularProgressIndicator()))
                  else if (_error != null && _data.isEmpty)
                    ProjectAdminMessage(_error!)
                  else if (projects.isEmpty)
                    const ProjectAdminMessage('Aucun projet dans votre coordination.')
                  else if (products.isEmpty)
                    const ProjectAdminMessage('Aucune Liste Standard validée pour ce projet. Configurez-la dans l’assistant du projet.')
                  else
                    for (final product in visible) _productCard(product),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _productCard(Map<String, dynamic> product) {
    final id = '${product['id']}';
    final retained = _editable ? _retained.contains(id) : product['retained'] == true;
    return ProjectAdminCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text('${product['name']}', style: const TextStyle(fontWeight: FontWeight.w700))),
              if (_editable)
                Switch(
                  value: retained,
                  activeThumbColor: AppColors.primaryStrong,
                  onChanged: (value) => setState(() => value ? _retained.add(id) : _retained.remove(id)),
                )
              else
                AppBadge(
                  label: retained ? 'Retenu' : 'Non retenu',
                  variant: retained ? AppBadgeVariant.success : AppBadgeVariant.neutral,
                ),
            ],
          ),
          if (product['packaging'] != null) Text('${product['packaging']}', style: TextStyle(color: AppColors.textMuted)),
          Row(
            children: [
              Expanded(
                child: Text(
                  '${product['code'] ?? ''}',
                  style: TextStyle(fontFamily: 'monospace', color: AppColors.textMuted, fontSize: 13),
                ),
              ),
              Text('${product['pathology'] ?? ''}', style: TextStyle(color: AppColors.textMuted, fontSize: 13)),
            ],
          ),
          Row(
            children: [
              Icon(Icons.qr_code_2, size: 16, color: AppColors.textMuted),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  product['barcode'] == null ? 'Aucun code-barres' : '${product['barcode']}',
                  style: TextStyle(color: AppColors.textMuted, fontSize: 13),
                ),
              ),
              if (_data['can_manage'] == true)
                TextButton(
                  onPressed: () => _editBarcode(product),
                  child: Text(product['barcode'] == null ? 'Associer' : 'Modifier'),
                ),
            ],
          ),
        ],
      ),
    );
  }

  Map<String, dynamic> _map(Object? value) =>
      value is Map ? value.map((key, item) => MapEntry('$key', item)) : const {};
}
