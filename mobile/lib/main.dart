import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'src/app.dart';
import 'src/core/routing/app_router.dart';
import 'src/core/access/application_access.dart';
import 'src/features/auth/data/auth_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final initialLocation = await _resolveInitialLocation();
  final router = createAppRouter(initialLocation: initialLocation);
  runApp(ProviderScope(child: SagaStockApp(router: router)));
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
