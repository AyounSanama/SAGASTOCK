import 'package:flutter/material.dart';

import '../../auth/data/auth_service.dart';
import 'scoped_projects_page.dart';
import 'my_project_page.dart';

/// Projets de l'Admin Coordination, ouverts depuis « Ma Coordination »
/// (détail, modification, configuration médicale).
///
/// Niveau 2 : l'entrée « Configuration des projets » a quitté le menu ; les
/// bailleurs sont dans « Référentiels » et l'onglet « Programmes » est masqué
/// (aucun doublon).
class ProjectConfigurationPage extends StatefulWidget {
  const ProjectConfigurationPage({
    super.key,
    this.openCreate = false,
    this.openProjectId,
  });

  final bool openCreate;

  /// Projet dont le détail s'ouvre à l'arrivée.
  final String? openProjectId;

  @override
  State<ProjectConfigurationPage> createState() =>
      _ProjectConfigurationPageState();
}

class _ProjectConfigurationPageState extends State<ProjectConfigurationPage> {
  String? _role;

  @override
  void initState() {
    super.initState();
    _loadRole();
  }

  Future<void> _loadRole() async {
    final user = await AuthService().cachedUser();
    if (mounted) {
      setState(() => _role = '${user?['role'] ?? ''}'.toLowerCase());
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_role == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (_role == 'project_admin') {
      return const MyProjectPage();
    }
    return Scaffold(
      appBar: AppBar(title: const Text('Projets')),
      body: ScopedProjectsPage(
        openCreate: widget.openCreate,
        openProjectId: widget.openProjectId,
        embedded: true,
      ),
    );
  }
}
