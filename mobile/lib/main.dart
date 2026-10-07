import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'src/app.dart';
import 'src/core/routing/app_router.dart';
import 'src/core/access/application_access.dart';
import 'src/features/auth/data/auth_service.dart';
import 'src/core/sync/sync_bootstrap.dart';
import 'src/core/localization/app_locale.dart';
import 'src/core/theme/app_theme_mode.dart';
import 'src/core/config/server_settings.dart';

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
  await AppLocale.restore();
  await AppThemeMode.restore();
  await ServerSettings.load();
  try {
    final service = AuthService();
    final hasSession = await service.hasSession();
    if (!hasSession) return '/login';
    final user = await service.cachedUser();
    AppLocale.apply('${user?['preferred_locale'] ?? 'fr'}');
    // Niveau 4 : mode d'affichage du profil (Clair / Sombre / Système).
    if (user?['theme_preference'] != null) {
      AppThemeMode.apply(user!['theme_preference']);
    }
    return ApplicationAccess.landingPath(user);
  } catch (_) {
    return '/login';
  }
}
