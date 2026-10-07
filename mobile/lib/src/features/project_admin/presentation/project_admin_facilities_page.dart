import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_badge.dart';
import '../data/project_admin_service.dart';
import 'project_admin_widgets.dart';

/// Niveau 5 — Formations sanitaires du projet (maquette mobile AdminProjet 06) :
/// recherche, filtres Toutes / Actives / Inactives, déclaration d'une FOSA.
class ProjectAdminFacilitiesPage extends StatefulWidget {
  const ProjectAdminFacilitiesPage({this.service, super.key});

  final ProjectAdminService? service;

  @override
  State<ProjectAdminFacilitiesPage> createState() => _ProjectAdminFacilitiesPageState();
}

class _ProjectAdminFacilitiesPageState extends State<ProjectAdminFacilitiesPage> {
  late final ProjectAdminService _service = widget.service ?? ProjectAdminService();
  Map<String, dynamic> _data = const {};
  bool _loading = true;
  String _search = '';
  String _filter = 'all';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await _service.facilities();
      if (mounted) setState(() => _data = data);
    } catch (_) {
      // Message ci-dessous.
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _open(String location) async {
    final saved = await context.push<bool>(location);
    if (saved == true && mounted) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final project = asMap(_data['project']);
    final all = asMaps(_data['facilities']);
    final active = all.where((facility) => asMap(facility['status'])['key'] == 'active').toList();
    final term = _search.trim().toLowerCase();
    final visible = (switch (_filter) {
      'active' => active,
      'inactive' => all.where((facility) => asMap(facility['status'])['key'] != 'active').toList(),
      _ => all,
    }).where((facility) => term.isEmpty || '${facility['name']} ${facility['code']}'.toLowerCase().contains(term)).toList();

    return Scaffold(
      backgroundColor: AppColors.background,
      floatingActionButton: FloatingActionButton(
        tooltip: 'Ajouter une FOSA',
        backgroundColor: AppColors.primaryStrong,
        foregroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.lg)),
        onPressed: () => _open('/health-facilities/new'),
        child: const Icon(Icons.add),
      ),
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
                    Text(project.isEmpty ? 'Mon projet' : 'Projet ${project['code']}', style: TextStyle(color: AppColors.textMuted, fontSize: 13)),
                    const Text('Formations sanitaires', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 12),
                    TextField(
                      decoration: const InputDecoration(prefixIcon: Icon(Icons.search), hintText: 'Rechercher une FOSA', isDense: true),
                      onChanged: (value) => setState(() => _search = value),
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      children: [
                        for (final (key, label) in [('all', 'Toutes (${all.length})'), ('active', 'Actives'), ('inactive', 'Inactives')])
                          ChoiceChip(
                            label: Text(label),
                            selected: _filter == key,
                            showCheckmark: false,
                            selectedColor: AppColors.primaryStrong,
                            labelStyle: TextStyle(color: _filter == key ? Colors.white : AppColors.text),
                            onSelected: (_) => setState(() => _filter = key),
                          ),
                      ],
                    ),
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
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
                children: [
                  if (_loading && _data.isEmpty)
                    const Padding(padding: EdgeInsets.all(32), child: Center(child: CircularProgressIndicator()))
                  else if (_data.isEmpty)
                    const ProjectAdminMessage('Aucune donnée enregistrée sur ce téléphone. Connectez-vous à internet une première fois.')
                  else if (visible.isEmpty)
                    ProjectAdminMessage(all.isEmpty ? 'Aucune formation sanitaire. Déclarez la première avec « + ».' : 'Aucune FOSA ne correspond.')
                  else
                    for (final facility in visible)
                      InkWell(
                        borderRadius: BorderRadius.circular(AppRadius.lg),
                        onTap: () => _open('/health-facilities/${facility['id']}/configure'),
                        child: ProjectAdminCard(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(child: Text('${facility['name']}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700))),
                                  AppBadge(
                                    label: '${asMap(facility['status'])['label'] ?? ''}',
                                    variant: badgeVariant(asMap(facility['status'])['tone']),
                                  ),
                                ],
                              ),
                              Text(
                                [facility['code'], facility['category_code']?.toString().replaceFirst('CAT-', '') ?? facility['category']].whereType<String>().join(' · '),
                                style: TextStyle(color: AppColors.textMuted),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                '${facility['care_level'] ?? '—'}     ${facility['accounts_count'] ?? 0} ${(facility['accounts_count'] ?? 0) == 1 ? 'compte' : 'comptes'}',
                                style: TextStyle(color: AppColors.textMuted),
                              ),
                            ],
                          ),
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
