import 'package:flutter/material.dart';

import '../../auth/data/auth_service.dart';
import 'sync_supervision_page.dart';
import 'synchronization_page.dart';

/// Comptes Site : la synchronisation de leur téléphone. Coordination et
/// Admin Projet : la supervision, identique à la page Web.
class SynchronizationEntry extends StatelessWidget {
  const SynchronizationEntry({super.key});

  static bool usesLocalScreen(Map<String, dynamic>? user) =>
      const {'site_admin', 'site_user'}.contains(
        '${user?['role'] ?? ''}'.toLowerCase(),
      );

  @override
  Widget build(BuildContext context) => FutureBuilder<Map<String, dynamic>?>(
    future: AuthService().cachedUser(),
    builder: (context, snapshot) {
      if (snapshot.connectionState != ConnectionState.done) {
        return const Scaffold(body: Center(child: CircularProgressIndicator()));
      }
      return usesLocalScreen(snapshot.data)
          ? const SynchronizationPage()
          : const SyncSupervisionPage();
    },
  );
}
