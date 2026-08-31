import 'package:flutter/material.dart';

import '../../auth/data/auth_service.dart';
import 'scoped_funding_page.dart';
import 'scoped_projects_page.dart';

/// Conteneur V1 de l'Admin Coordination : les trois sections sœurs utilisent
/// les écrans, repositories et formulaires existants.
class ProjectConfigurationPage extends StatefulWidget {
  const ProjectConfigurationPage({super.key, this.openCreate = false});

  final bool openCreate;

  @override
  State<ProjectConfigurationPage> createState() =>
      _ProjectConfigurationPageState();
}

class _ProjectConfigurationPageState extends State<ProjectConfigurationPage>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs;
  String? _role;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 3, vsync: this);
    _loadRole();
  }

  Future<void> _loadRole() async {
    final user = await AuthService().cachedUser();
    if (mounted) {
      setState(() => _role = '${user?['role'] ?? ''}'.toLowerCase());
    }
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_role == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (_role == 'project_admin') {
      return const ScopedProjectsPage();
    }
    return Scaffold(
      appBar: AppBar(
        title: const Text('Configuration des projets'),
        bottom: TabBar(
          controller: _tabs,
          tabs: const [
            Tab(text: 'Projets'),
            Tab(text: 'Bailleurs'),
            Tab(text: 'Programmes'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: [
          ScopedProjectsPage(openCreate: widget.openCreate, embedded: true),
          const ScopedFundingPage(
            embedded: true,
            section: FundingSection.donors,
          ),
          const ScopedFundingPage(
            embedded: true,
            section: FundingSection.programs,
          ),
        ],
      ),
    );
  }
}
