import 'package:flutter/material.dart';

import '../../auth/data/auth_service.dart';
import '../../coordination/presentation/coordination_dashboard_page.dart';
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
      return role == 'coordination_admin'
          ? const CoordinationDashboardPage()
          : const HomePage();
    },
  );
}
