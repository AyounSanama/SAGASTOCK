import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'core/theme/app_theme.dart';

class SagaStockApp extends StatelessWidget {
  const SagaStockApp({required this.router, super.key});

  final GoRouter router;

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'PharmaCare',
      debugShowCheckedModeBanner: false,
      locale: const Locale('fr'),
      theme: AppTheme.light,
      routerConfig: router,
    );
  }
}
