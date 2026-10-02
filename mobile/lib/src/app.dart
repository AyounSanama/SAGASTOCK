import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:go_router/go_router.dart';

import 'core/theme/app_theme.dart';
import 'core/localization/app_locale.dart';
import 'core/security/app_lock_gate.dart';

class SagaStockApp extends StatelessWidget {
  const SagaStockApp({required this.router, super.key});

  final GoRouter router;

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<Locale>(
      valueListenable: AppLocale.current,
      builder: (context, locale, _) => MaterialApp.router(
        title: 'PharmaCare',
        debugShowCheckedModeBanner: false,
        locale: locale,
        supportedLocales: AppLocale.supported.map(Locale.new),
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        theme: AppTheme.light,
        routerConfig: router,
        // S-03 : verrouillage par PIN et reconnexion sans perte de données.
        builder: (context, child) =>
            AppLockGate(router: router, child: child ?? const SizedBox()),
      ),
    );
  }
}
