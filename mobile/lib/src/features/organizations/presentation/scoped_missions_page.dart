import 'package:flutter/material.dart';

import '../../auth/data/auth_service.dart';
import '../../../core/access/application_access.dart';
import '../../coordination/presentation/coordination_page.dart';
import 'missions_page.dart';

class ScopedMissionsPage extends StatelessWidget {
  const ScopedMissionsPage({super.key});

  @override
  Widget build(BuildContext context) => FutureBuilder<Map<String, dynamic>?>(
    future: AuthService().cachedUser(),
    builder: (context, snapshot) {
      if (snapshot.connectionState != ConnectionState.done) {
        return const Scaffold(body: Center(child: CircularProgressIndicator()));
      }
      final user = snapshot.data ?? const <String, dynamic>{};
      final organization = user['organization'] as Map?;
      final organizationId =
          '${user['organization_id'] ?? organization?['id'] ?? ''}';
      final organizationName = '${organization?['name'] ?? 'Mon organisation'}';
      final allowedCountries = (organization?['countries'] as List? ?? const [])
          .whereType<Map>()
          .map((country) => Map<String, dynamic>.from(country))
          .toList(growable: false);
      final coordination = user['coordination'] is Map
          ? Map<String, dynamic>.from(user['coordination'] as Map)
          : null;
      final role = '${user['role'] ?? ''}'.toLowerCase();
      // AM-172 — « Ma Coordination » selon les maquettes (onglets Projets,
      // À valider, FOSA, Comptes).
      if (role == 'coordination_admin') {
        return CoordinationPage(
          organizationId: organizationId.isEmpty ? null : organizationId,
        );
      }
      if (organizationId.isEmpty) {
        return const Scaffold(
          body: Center(
            child: Text('Aucune organisation n’est rattachée à ce compte.'),
          ),
        );
      }
      return MissionsPage(
        organizationId: organizationId,
        organizationName: organizationName,
        allowedCountries: allowedCountries,
        readOnly: role == 'coordination_admin',
        canCreateProject: ApplicationAccess.allows(user, 'projects.manage'),
        canCreateAccount: ApplicationAccess.allows(user, 'users.manage'),
        accessReadOnly: ApplicationAccess.isReadOnly(user),
        initialCoordination: coordination,
      );
    },
  );
}
