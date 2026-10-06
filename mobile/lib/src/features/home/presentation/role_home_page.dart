import 'package:flutter/material.dart';

import '../../auth/data/auth_service.dart';
import '../../coordination/presentation/coordination_dashboard_page.dart';
import '../../project_admin/presentation/project_admin_home_page.dart';
import 'home_page.dart';

/// Accueil selon le rôle : la Coordination a son tableau de bord (maquette 08).
class RoleHomePage extends StatelessWidget {
  const RoleHomePage({super.key});

  @override
  Widget build(BuildContext context) => FutureBuilder<Map<String, dynamic>?>(
    future: AuthService().cachedUser(),
    builder: (context, snapshot) {
      if (snapshot.connectionState != ConnectionState.done) {
        return const Scaffold(body: Center(child: CircularProgressIndicator()));
      }
      final role = '${snapshot.data?['role'] ?? ''}'.toLowerCase();
      return switch (role) {
        'coordination_admin' => const CoordinationDashboardPage(),
        // Niveau 5 : accueil de l'Admin Projet (maquette mobile 05).
        'project_admin' => const ProjectAdminHomePage(),
        _ => const HomePage(),
      };
    },
  );
}
