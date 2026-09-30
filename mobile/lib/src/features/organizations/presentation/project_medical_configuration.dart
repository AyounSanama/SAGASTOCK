import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../data/organization_service.dart';

/// AM-112 — Configuration médicale du projet :
/// Niveaux de soins → Populations cibles → Pathologies par population.

List<Map<String, dynamic>> _maps(dynamic value) => value is List
    ? value.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
    : const [];

const _levelLabels = {1: 'Niveau', 2: 'Catégorie', 3: 'Programme'};

/// Section en lecture seule (Admin Projet, Coordination).
class MedicalConfigurationSection extends StatelessWidget {
  const MedicalConfigurationSection({
    super.key,
    required this.configuration,
    this.onEdit,
  });

  /// Contenu de la clé `configuration` renvoyée par l'API.
  final Map<String, dynamic> configuration;
  final VoidCallback? onEdit;

  @override
  Widget build(BuildContext context) {
    final levels = _maps(configuration['care_levels']);
    final populations = _maps(configuration['target_populations']);
    final names = {
      for (final population in populations)
        '${population['id']}': '${population['name']}',
    };
    final pathologies = _maps(configuration['pathologies']);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'Configuration médicale',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                  ),
                ),
                if (onEdit != null)
                  TextButton.icon(
                    onPressed: onEdit,
                    icon: const Icon(Icons.edit_outlined, size: 18),
                    label: const Text('Modifier'),
                  ),
              ],
            ),
            if (configuration['is_configured'] != true)
              const Padding(
                padding: EdgeInsets.only(top: 6),
                child: Text(
                  'Configuration incomplète : niveau de soins et population cible requis.',
                  style: TextStyle(color: AppTheme.orange),
                ),
              ),
            const SizedBox(height: 10),
            const _Label('Niveaux de soins'),
            _Chips(
              values: levels
                  .map(
                    (level) =>
                        '${level['name']} · ${_levelLabels[level['depth']] ?? ''}',
                  )
                  .toList(),
            ),
            const _Label('Populations cibles'),
            _Chips(values: populations.map((p) => '${p['name']}').toList()),
            const _Label('Pathologies'),
            if (pathologies.isEmpty)
              const Text('—', style: TextStyle(color: AppTheme.muted))
            else
              ...pathologies.map(
                (pathology) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Text.rich(
                    TextSpan(
                      children: [
                        TextSpan(
                          text: '${pathology['name']} ',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        TextSpan(
                          text:
                              ((pathology['target_population_ids'] as List?) ??
                                      const [])
                                  .map((id) => names['$id'] ?? '')
                                  .where((name) => name.isNotEmpty)
                                  .join(', '),
                          style: const TextStyle(color: AppTheme.muted),
                        ),
                      ],
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

class _Label extends StatelessWidget {
  const _Label(this.text);
  final String text;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 10, bottom: 6),
    child: Text(
      text,
      style: const TextStyle(color: AppTheme.muted, fontSize: 12),
    ),
  );
}

class _Chips extends StatelessWidget {
  const _Chips({required this.values});
  final List<String> values;
  @override
  Widget build(BuildContext context) => values.isEmpty
      ? const Text('—', style: TextStyle(color: AppTheme.muted))
      : Wrap(
          spacing: 6,
          runSpacing: 6,
          children: values
              .map(
                (value) => Chip(
                  label: Text(value, style: const TextStyle(fontSize: 12)),
                  visualDensity: VisualDensity.compact,
                ),
              )
              .toList(),
        );
}

/// Éditeur de la Coordination. Renvoie `true` après enregistrement.
Future<bool?> showMedicalConfigurationEditor(
  BuildContext context, {
  required OrganizationService service,
  required String projectId,
  required String organizationId,
  required String projectName,
}) async {
  Map<String, dynamic> data;
  try {
    data = await service.medicalConfiguration(
      projectId: projectId,
      organizationId: organizationId,
    );
  } catch (_) {
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Configuration indisponible. Vérifiez la connexion.'),
        ),
      );
    }
    return null;
  }
  if (!context.mounted) return null;
  if (data['can_manage'] != true || data['options'] is! Map) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Action réservée à la Coordination.')),
    );
    return null;
  }
  final configuration = Map<String, dynamic>.from(data['configuration'] as Map);
  final levels = <String>{
    for (final item in _maps(configuration['care_levels'])) '${item['id']}',
  };
  final populations = <String>{
    for (final item in _maps(configuration['target_populations']))
      '${item['id']}',
  };
  final links = <String, Set<String>>{
    for (final item in _maps(configuration['pathologies']))
      '${item['id']}': {
        for (final id in (item['target_population_ids'] as List? ?? const []))
          '$id',
      },
  };
  var options = Map<String, dynamic>.from(data['options'] as Map);
  var saving = false;

  List<Map<String, dynamic>> flatten(dynamic nodes) => [
    for (final node in _maps(nodes)) ...[node, ...flatten(node['children'])],
  ];

  return showAppFormSheet<bool>(
    context: context,
    title: 'Configuration médicale',
    description: projectName,
    builder: (sheetContext) => StatefulBuilder(
      builder: (_, setSheetState) {
        Future<void> addReference(String type, String title) async {
          final added = await _promptReference(
            sheetContext,
            service: service,
            type: type,
            title: title,
            parents: type == 'care_level'
                ? flatten(
                    options['care_level_tree'],
                  ).where((node) => (node['depth'] as int? ?? 1) < 3).toList()
                : const [],
          );
          if (added != true) return;
          final refreshed = await service.medicalConfiguration(
            projectId: projectId,
            organizationId: organizationId,
          );
          if (refreshed['options'] is Map) {
            setSheetState(
              () => options = Map<String, dynamic>.from(
                refreshed['options'] as Map,
              ),
            );
          }
        }

        final treeNodes = flatten(options['care_level_tree']);
        final populationOptions = _maps(options['target_populations']);
        final pathologyOptions = _maps(options['pathologies']);
        final selectedPopulations = populationOptions
            .where((p) => populations.contains('${p['id']}'))
            .toList();

        return SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _EditorTitle(
                '1. Niveaux de soins',
                onAdd: saving
                    ? null
                    : () => addReference('care_level', 'Niveau de soins'),
              ),
              ...treeNodes.map(
                (node) => CheckboxListTile(
                  dense: true,
                  contentPadding: EdgeInsets.only(
                    left: ((node['depth'] as int? ?? 1) - 1) * 20.0,
                  ),
                  title: Text('${node['name']}'),
                  subtitle: Text('${node['level_label'] ?? ''}'),
                  value: levels.contains('${node['id']}'),
                  onChanged: saving
                      ? null
                      : (checked) => setSheetState(
                          () => checked == true
                              ? levels.add('${node['id']}')
                              : levels.remove('${node['id']}'),
                        ),
                ),
              ),
              const Divider(height: 24),
              _EditorTitle(
                '2. Populations cibles',
                onAdd: saving
                    ? null
                    : () =>
                          addReference('target_population', 'Population cible'),
              ),
              ...populationOptions.map(
                (population) => CheckboxListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  title: Text('${population['name']}'),
                  value: populations.contains('${population['id']}'),
                  onChanged: saving
                      ? null
                      : (checked) => setSheetState(() {
                          final id = '${population['id']}';
                          if (checked == true) {
                            populations.add(id);
                          } else {
                            populations.remove(id);
                            for (final set in links.values) {
                              set.remove(id);
                            }
                          }
                        }),
                ),
              ),
              const Divider(height: 24),
              _EditorTitle(
                '3. Pathologies par population',
                onAdd: saving
                    ? null
                    : () => addReference('pathology', 'Pathologie'),
              ),
              if (selectedPopulations.isEmpty)
                const Text(
                  'Sélectionnez d’abord les populations cibles.',
                  style: TextStyle(color: AppTheme.muted),
                )
              else
                ...pathologyOptions.map(
                  (pathology) => Padding(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${pathology['name']}',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        Wrap(
                          spacing: 6,
                          children: selectedPopulations.map((population) {
                            final pathologyId = '${pathology['id']}';
                            final populationId = '${population['id']}';
                            return FilterChip(
                              label: Text('${population['name']}'),
                              selected:
                                  links[pathologyId]?.contains(populationId) ??
                                  false,
                              onSelected: saving
                                  ? null
                                  : (selected) => setSheetState(() {
                                      final set = links.putIfAbsent(
                                        pathologyId,
                                        () => <String>{},
                                      );
                                      selected
                                          ? set.add(populationId)
                                          : set.remove(populationId);
                                    }),
                            );
                          }).toList(),
                        ),
                      ],
                    ),
                  ),
                ),
              const SizedBox(height: 20),
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
                      label: 'Enregistrer',
                      loading: saving,
                      onPressed: saving
                          ? null
                          : () async {
                              setSheetState(() => saving = true);
                              try {
                                await service.saveMedicalConfiguration(
                                  projectId: projectId,
                                  careLevelIds: levels.toList(),
                                  targetPopulationIds: populations.toList(),
                                  pathologies: [
                                    for (final entry in links.entries)
                                      if (entry.value.isNotEmpty)
                                        {
                                          'pathology_id': entry.key,
                                          'target_population_ids': entry.value
                                              .toList(),
                                        },
                                  ],
                                );
                                if (sheetContext.mounted) {
                                  Navigator.pop(sheetContext, true);
                                }
                              } on DioException catch (error) {
                                setSheetState(() => saving = false);
                                if (!sheetContext.mounted) return;
                                ScaffoldMessenger.of(sheetContext).showSnackBar(
                                  SnackBar(content: Text(_errorMessage(error))),
                                );
                              }
                            },
                    ),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    ),
  );
}

String _errorMessage(DioException error) {
  final data = error.response?.data;
  if (data is Map && data['errors'] is Map) {
    for (final value in (data['errors'] as Map).values) {
      if (value is List && value.isNotEmpty) return '${value.first}';
    }
  }
  if (error.response == null) {
    return 'Connexion requise pour enregistrer la configuration médicale.';
  }
  return error.response?.statusCode == 403
      ? 'Action réservée à la Coordination.'
      : 'Enregistrement impossible. Vérifiez les informations.';
}

class _EditorTitle extends StatelessWidget {
  const _EditorTitle(this.title, {this.onAdd});
  final String title;
  final VoidCallback? onAdd;
  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Text(
          title,
          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
        ),
      ),
      IconButton(
        tooltip: 'Ajouter au référentiel',
        onPressed: onAdd,
        icon: const Icon(Icons.add_circle_outline),
      ),
    ],
  );
}

Future<bool?> _promptReference(
  BuildContext context, {
  required OrganizationService service,
  required String type,
  required String title,
  required List<Map<String, dynamic>> parents,
}) {
  final code = TextEditingController();
  final name = TextEditingController();
  String? parentId;
  final key = GlobalKey<FormState>();
  var saving = false;
  return showDialog<bool>(
    context: context,
    builder: (dialogContext) => StatefulBuilder(
      builder: (_, setState) => AlertDialog(
        title: Text('Ajouter : $title'),
        content: Form(
          key: key,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (type == 'care_level')
                DropdownButtonFormField<String>(
                  initialValue: parentId,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Rattacher à'),
                  items: [
                    const DropdownMenuItem(
                      value: '',
                      child: Text('Nouveau niveau (racine)'),
                    ),
                    ...parents.map(
                      (node) => DropdownMenuItem(
                        value: '${node['id']}',
                        child: Text(
                          '${'— ' * ((node['depth'] as int? ?? 1) - 1)}${node['name']}',
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ),
                  ],
                  onChanged: (value) => parentId = value,
                ),
              TextFormField(
                controller: code,
                decoration: const InputDecoration(labelText: 'Code *'),
                validator: (v) =>
                    (v ?? '').trim().isEmpty ? 'Champ obligatoire' : null,
              ),
              TextFormField(
                controller: name,
                decoration: const InputDecoration(labelText: 'Nom *'),
                validator: (v) =>
                    (v ?? '').trim().isEmpty ? 'Champ obligatoire' : null,
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: saving ? null : () => Navigator.pop(dialogContext),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: saving
                ? null
                : () async {
                    if (!(key.currentState?.validate() ?? false)) return;
                    setState(() => saving = true);
                    try {
                      await service.addMedicalReference(
                        type: type,
                        code: code.text.trim(),
                        name: name.text.trim(),
                        parentId: parentId,
                      );
                      if (dialogContext.mounted) {
                        Navigator.pop(dialogContext, true);
                      }
                    } on DioException catch (error) {
                      setState(() => saving = false);
                      if (!dialogContext.mounted) return;
                      ScaffoldMessenger.of(dialogContext).showSnackBar(
                        SnackBar(content: Text(_errorMessage(error))),
                      );
                    }
                  },
            child: const Text('Ajouter'),
          ),
        ],
      ),
    ),
  );
}
