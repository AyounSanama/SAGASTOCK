import 'package:flutter/material.dart';

import 'core/routing/app_router.dart';
import 'core/theme/app_theme.dart';

class SagaStockApp extends StatelessWidget {
  const SagaStockApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'PharmaCare',
      debugShowCheckedModeBanner: false,
      locale: const Locale('fr'),
      theme: AppTheme.light,
      routerConfig: appRouter,
    );
  }
}
