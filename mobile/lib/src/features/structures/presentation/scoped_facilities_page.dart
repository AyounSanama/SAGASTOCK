import 'package:flutter/material.dart';

import '../../auth/data/auth_service.dart';
import 'facilities_page.dart';

class ScopedFacilitiesPage extends StatelessWidget {
  const ScopedFacilitiesPage({super.key});

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
      );
    },
  );
}
