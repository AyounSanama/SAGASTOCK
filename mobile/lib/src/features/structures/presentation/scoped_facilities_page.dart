import 'package:flutter/material.dart';

import '../../auth/data/auth_service.dart';
import '../../../core/access/session_scope.dart';
import '../../organizations/data/organization_service.dart';
import 'facilities_page.dart';

class ScopedFacilitiesPage extends StatelessWidget {
  const ScopedFacilitiesPage({super.key});

  Future<Map<String, dynamic>> _context() async {
    final user = await AuthService().cachedUser() ?? <String, dynamic>{};
    final organizationId = SessionScope.organizationId(user);
    final projectId = SessionScope.projectId(user);
    Map<String, dynamic>? project = user['project'] is Map
        ? Map<String, dynamic>.from(user['project'] as Map)
        : null;
    if (projectId.isNotEmpty && organizationId.isNotEmpty) {
      try {
        final response = await OrganizationService().project(
          projectId: projectId,
          organizationId: organizationId,
        );
        project = response['project'] is Map
            ? Map<String, dynamic>.from(response['project'] as Map)
            : response;
      } catch (_) {
        // The authenticated project id remains authoritative offline.
      }
    }
    return {'user': user, 'project': ?project};
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<Map<String, dynamic>>(
    future: _context(),
    builder: (context, snapshot) {
      if (snapshot.connectionState != ConnectionState.done) {
        return const Scaffold(body: Center(child: CircularProgressIndicator()));
      }
      final user =
          snapshot.data?['user'] as Map<String, dynamic>? ??
          const <String, dynamic>{};
      final project = snapshot.data?['project'] as Map<String, dynamic>?;
      final organization = user['organization'] as Map?;
      final organizationId =
          '${user['organization_id'] ?? organization?['id'] ?? ''}';
      if (organizationId.isEmpty) {
        return const Scaffold(
          body: Center(
            child: Text('Aucune organisation n’est rattachée à ce compte.'),
          ),
        );
      }
      return FacilitiesPage(
        organizationId: organizationId,
        organizationName: '${organization?['name'] ?? 'Mon organisation'}',
        scopedProject: project,
      );
    },
  );
}
