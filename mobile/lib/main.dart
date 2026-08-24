import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'src/app.dart';
import 'src/core/routing/app_router.dart';
import 'src/core/access/application_access.dart';
import 'src/features/auth/data/auth_service.dart';
import 'src/core/sync/sync_bootstrap.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // Android conserve l'écran natif tant que le premier frame Flutter n'est
  // pas rendu. Une lecture du stockage sécurisé ne doit donc jamais pouvoir
  // bloquer indéfiniment le lancement de l'application.
  final initialLocation = await _resolveInitialLocation().timeout(
    const Duration(seconds: 3),
    onTimeout: () => '/login',
  );
  final router = createAppRouter(initialLocation: initialLocation);
  runApp(ProviderScope(child: SagaStockApp(router: router)));
  unawaited(SyncBootstrap.startForCachedSession());
}

Future<String> _resolveInitialLocation() async {
  try {
    final service = AuthService();
    final hasSession = await service.hasSession();
    if (!hasSession) return '/login';
    final user = await service.cachedUser();
    return ApplicationAccess.landingPath(user);
  } catch (_) {
    return '/login';
  }
}
