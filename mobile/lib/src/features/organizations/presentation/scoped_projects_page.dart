import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/app_empty_state.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../auth/data/auth_service.dart';
import '../data/organization_service.dart';

class ScopedProjectsPage extends StatefulWidget {
  const ScopedProjectsPage({super.key});
  @override
  State<ScopedProjectsPage> createState() => _ScopedProjectsPageState();
}

class _ScopedProjectsPageState extends State<ScopedProjectsPage> {
  final _service = OrganizationService();
  String? organizationId;
  List<Map<String, dynamic>> missions = [];
  List<Map<String, dynamic>> projects = [];
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final user = await AuthService().cachedUser() ?? {};
      final organization = user['organization'] as Map?;
      organizationId =
          '${user['organization_id'] ?? organization?['id'] ?? ''}';
      if (organizationId!.isEmpty) throw StateError('missing scope');
      final results = await Future.wait([
        _service.missions(organizationId!),
        _service.projects(organizationId: organizationId!),
      ]);
      missions = results[0];
      projects = results[1];
    } catch (_) {
      error = 'Impossible de charger les projets de votre organisation.';
    }
    if (mounted) setState(() => loading = false);
  }

  Future<void> _create() async {
    if (missions.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Créez d’abord une mission.')),
      );
      return;
    }
    final form = GlobalKey<FormState>();
    final code = TextEditingController();
    final name = TextEditingController();
    final description = TextEditingController();
    String missionId = '${missions.first['id']}';
    final saved = await showAppFormSheet<bool>(
      context: context,
      title: 'Nouveau projet',
      description:
          'Le projet restera limité au périmètre de votre organisation.',
      builder: (sheetContext) => SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: form,
          child: Column(
            children: [
              DropdownButtonFormField<String>(
                initialValue: missionId,
                decoration: const InputDecoration(labelText: 'Mission *'),
                items: missions
                    .map(
                      (mission) => DropdownMenuItem(
                        value: '${mission['id']}',
                        child: Text('${mission['name']}'),
                      ),
                    )
                    .toList(),
                onChanged: (value) => missionId = value ?? missionId,
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: code,
                decoration: const InputDecoration(labelText: 'Code *'),
                validator: (value) =>
                    value?.trim().isEmpty == true ? 'Champ obligatoire' : null,
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: name,
                decoration: const InputDecoration(labelText: 'Nom *'),
                validator: (value) =>
                    value?.trim().isEmpty == true ? 'Champ obligatoire' : null,
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: description,
                decoration: const InputDecoration(labelText: 'Description'),
                maxLines: 3,
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.pop(sheetContext, false),
                      child: const Text('Annuler'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: () async {
                        if (!(form.currentState?.validate() ?? false)) return;
                        await _service.createProject(
                          organizationId: organizationId!,
                          missionId: missionId,
                          code: code.text.trim(),
                          name: name.text.trim(),
                          description: description.text.trim(),
                        );
                        if (sheetContext.mounted) {
                          Navigator.pop(sheetContext, true);
                        }
                      },
                      icon: const Icon(Icons.add),
                      label: const Text('Créer'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
    code.dispose();
    name.dispose();
    description.dispose();
    if (saved == true) {
      await _load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Projet créé avec succès.')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Projets')),
    floatingActionButton: FloatingActionButton(
      onPressed: loading ? null : _create,
      child: const Icon(Icons.add),
    ),
    body: RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'Projets',
            style: TextStyle(fontSize: 23, fontWeight: FontWeight.w800),
          ),
          const Text(
            'Gérez les projets de votre organisation.',
            style: TextStyle(color: AppTheme.muted),
          ),
          const SizedBox(height: 16),
          if (loading)
            const Padding(
              padding: EdgeInsets.all(40),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (error != null)
            AppCard(child: Text(error!))
          else if (projects.isEmpty)
            const AppEmptyState(
              icon: Icons.account_tree_outlined,
              title: 'Aucun projet',
              description:
                  'Créez le premier projet depuis une mission autorisée.',
            )
          else
            ...projects.map(
              (project) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: AppCard(
                  child: ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: CircleAvatar(
                      backgroundColor: AppTheme.green.withValues(alpha: .1),
                      child: const Icon(
                        Icons.account_tree_outlined,
                        color: AppTheme.green,
                      ),
                    ),
                    title: Text(
                      '${project['name']}',
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    subtitle: Text(
                      '${project['code']} · ${(project['mission'] as Map?)?['name'] ?? 'Mission'}',
                    ),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => context.push(
                      '/organizations/$organizationId/projects/${project['id']}/funding',
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
