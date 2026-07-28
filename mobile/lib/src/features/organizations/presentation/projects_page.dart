import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../data/organization_service.dart';

class ProjectsPage extends StatefulWidget {
  const ProjectsPage({
    required this.organizationId,
    required this.missionId,
    required this.missionName,
    super.key,
  });

  final String organizationId;
  final String missionId;
  final String missionName;

  @override
  State<ProjectsPage> createState() => _ProjectsPageState();
}

class _ProjectsPageState extends State<ProjectsPage> {
  final _service = OrganizationService();
  List<Map<String, dynamic>> _projects = [];
  bool _loading = true;
  String? _error;

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
      final projects = await _service.projects(
        organizationId: widget.organizationId,
        missionId: widget.missionId,
      );
      if (mounted) setState(() => _projects = projects);
    } on DioException catch (error) {
      if (mounted) {
        setState(
          () => _error = error.response?.statusCode == 403
              ? 'Vous n’avez pas la permission de consulter les projets.'
              : 'Impossible de charger les projets.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _create() async {
    final code = TextEditingController();
    final name = TextEditingController();
    final description = TextEditingController();
    final key = GlobalKey<FormState>();
    final created = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Nouveau projet'),
        content: Form(
          key: key,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: code,
                decoration: const InputDecoration(labelText: 'Code projet'),
                validator: (value) =>
                    value?.trim().isEmpty == true ? 'Champ obligatoire' : null,
              ),
              TextFormField(
                controller: name,
                decoration: const InputDecoration(labelText: 'Nom'),
                validator: (value) =>
                    value?.trim().isEmpty == true ? 'Champ obligatoire' : null,
              ),
              TextFormField(
                controller: description,
                decoration: const InputDecoration(labelText: 'Description'),
                maxLines: 3,
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () async {
              if (!(key.currentState?.validate() ?? false)) return;
              try {
                await _service.createProject(
                  organizationId: widget.organizationId,
                  missionId: widget.missionId,
                  code: code.text.trim(),
                  name: name.text.trim(),
                  description: description.text.trim(),
                );
                if (context.mounted) Navigator.pop(context, true);
              } on DioException catch (error) {
                if (!context.mounted) return;
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      error.response?.statusCode == 403
                          ? 'Vous n’avez pas la permission de créer un projet.'
                          : 'Création impossible. Vérifiez les informations.',
                    ),
                  ),
                );
              }
            },
            child: const Text('Créer'),
          ),
        ],
      ),
    );
    code.dispose();
    name.dispose();
    description.dispose();
    if (created == true) await _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.missionName)),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _create,
        icon: const Icon(Icons.add),
        label: const Text('Projet'),
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              'Projets de la mission',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 16),
            if (_loading)
              const Center(child: CircularProgressIndicator())
            else if (_error != null)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text(_error!),
                ),
              )
            else if (_projects.isEmpty)
              const Card(
                child: Padding(
                  padding: EdgeInsets.all(20),
                  child: Text('Aucun projet enregistré.'),
                ),
              )
            else
              for (final project in _projects)
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.work_outline),
                    title: Text(project['name'] as String),
                    subtitle: Text(project['code'] as String),
                    trailing: Icon(
                      project['is_active'] == true
                          ? Icons.check_circle
                          : Icons.pause_circle,
                      color: project['is_active'] == true
                          ? Colors.green
                          : Colors.grey,
                    ),
                    onTap: () => context.push(
                      '/organizations/${widget.organizationId}/projects/${project['id']}/funding',
                      extra: project['name'] as String,
                    ),
                  ),
                ),
          ],
        ),
      ),
    );
  }
}
