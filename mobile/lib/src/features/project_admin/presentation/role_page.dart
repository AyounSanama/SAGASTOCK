import 'package:flutter/material.dart';

import '../../auth/data/auth_service.dart';

/// Affiche l'écran de l'Admin Projet (niveau 5) ou l'écran habituel des
/// autres rôles, selon le rôle du compte enregistré sur le téléphone.
class ProjectAdminOr extends StatelessWidget {
  const ProjectAdminOr({required this.projectAdmin, required this.other, super.key});

  final Widget projectAdmin;
  final Widget other;

  @override
  Widget build(BuildContext context) => FutureBuilder<Map<String, dynamic>?>(
    future: AuthService().cachedUser(),
    builder: (context, snapshot) {
      if (snapshot.connectionState != ConnectionState.done) {
        return const Scaffold(body: Center(child: CircularProgressIndicator()));
      }
      return '${snapshot.data?['role'] ?? ''}'.toLowerCase() == 'project_admin' ? projectAdmin : other;
    },
  );
}
