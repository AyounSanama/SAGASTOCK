import 'package:flutter/material.dart';

import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_badge.dart';
import '../data/project_admin_service.dart';
import 'project_admin_widgets.dart';

/// Niveau 5 — Liste Standard en consultation (maquette mobile AdminProjet 08) :
/// liste validée par la Coordination, pour le projet ou une FOSA.
class ProjectAdminStandardListPage extends StatefulWidget {
  const ProjectAdminStandardListPage({this.service, super.key});

  final ProjectAdminService? service;

  @override
  State<ProjectAdminStandardListPage> createState() => _ProjectAdminStandardListPageState();
}

class _ProjectAdminStandardListPageState extends State<ProjectAdminStandardListPage> {
  late final ProjectAdminService _service = widget.service ?? ProjectAdminService();
  Map<String, dynamic> _data = const {};
  bool _loading = true;
  String? _facilityId;
  String _search = '';
  String? _pathology;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await _service.standardList(facilityId: _facilityId);
      if (mounted) setState(() => _data = data);
    } catch (_) {
      // Message ci-dessous.
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final products = asMaps(_data['products']);
    final facilities = asMaps(_data['facilities']);
    final pathologies = [for (final item in (_data['pathologies'] as List? ?? const [])) '$item'];
    final term = _search.trim().toLowerCase();
    final visible = products
        .where((product) => term.isEmpty || '${product['code']} ${product['name']}'.toLowerCase().contains(term))
        .where((product) => _pathology == null || product['pathology'] == _pathology)
        .toList();

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Column(
        children: [
          Container(
            color: AppColors.surface,
            child: SafeArea(
              bottom: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'LISTE STANDARD',
                      style: TextStyle(color: AppColors.textMuted, fontSize: 12, fontWeight: FontWeight.w700, letterSpacing: .6),
                    ),
                    Text('${_data['title'] ?? 'Liste Standard'}', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 10),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      decoration: BoxDecoration(color: AppColors.infoSurface, borderRadius: BorderRadius.circular(AppRadius.md)),
                      child: const Row(
                        children: [
                          Icon(Icons.lock_outline, size: 16, color: AppColors.infoText),
                          SizedBox(width: 8),
                          Expanded(
                            child: Text('Consultation seule · gérée par la Coordination', style: TextStyle(color: AppColors.infoText)),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 10),
                    if (facilities.isNotEmpty) ...[
                      DropdownButtonFormField<String?>(
                        key: ValueKey('facility-$_facilityId'),
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
                      decoration: const InputDecoration(prefixIcon: Icon(Icons.search), hintText: 'Code ou désignation', isDense: true),
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
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                children: [
                  if (_loading && _data.isEmpty)
                    const Padding(padding: EdgeInsets.all(32), child: Center(child: CircularProgressIndicator()))
                  else if (_data.isEmpty)
                    const ProjectAdminMessage('Aucune donnée enregistrée sur ce téléphone. Connectez-vous à internet une première fois.')
                  else if (products.isEmpty)
                    const ProjectAdminMessage('La Coordination n’a pas encore validé de Liste Standard pour ce projet.')
                  else
                    for (final product in visible)
                      ProjectAdminCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(child: Text('${product['name']}', style: const TextStyle(fontWeight: FontWeight.w700))),
                                AppBadge(
                                  label: product['retained'] == true ? 'Retenu' : 'Non retenu',
                                  variant: product['retained'] == true ? AppBadgeVariant.success : AppBadgeVariant.neutral,
                                ),
                              ],
                            ),
                            if (product['packaging'] != null)
                              Text('${product['packaging']}', style: const TextStyle(color: AppColors.textMuted)),
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    '${product['code'] ?? ''}',
                                    style: const TextStyle(fontFamily: 'monospace', color: AppColors.textMuted, fontSize: 13),
                                  ),
                                ),
                                Text('${product['pathology'] ?? ''}', style: const TextStyle(color: AppColors.textMuted, fontSize: 13)),
                              ],
                            ),
                          ],
                        ),
                      ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
