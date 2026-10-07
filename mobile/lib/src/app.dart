import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:go_router/go_router.dart';

import 'core/theme/app_theme.dart';
import 'core/theme/app_theme_mode.dart';
import 'core/localization/app_locale.dart';
import 'core/security/app_lock_gate.dart';

class SagaStockApp extends StatefulWidget {
  const SagaStockApp({required this.router, super.key});

  final GoRouter router;

  @override
  State<SagaStockApp> createState() => _SagaStockAppState();
}

/// Niveau 4 : l'application suit le mode choisi (Clair / Sombre / Système) et,
/// en mode « Système », le réglage du téléphone.
class _SagaStockAppState extends State<SagaStockApp>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangePlatformBrightness() {
    if (AppThemeMode.preference.value == 'system') setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<String>(
      valueListenable: AppThemeMode.preference,
      builder: (context, _, _) {
        final dark = AppThemeMode.activate(
          WidgetsBinding.instance.platformDispatcher.platformBrightness,
        );
        return ValueListenableBuilder<Locale>(
          valueListenable: AppLocale.current,
          builder: (context, locale, _) => MaterialApp.router(
            // Changer de palette reconstruit tous les écrans (couleurs lues à la construction).
            key: ValueKey(dark),
            title: 'PharmaCare',
            debugShowCheckedModeBanner: false,
            locale: locale,
            supportedLocales: AppLocale.supported.map(Locale.new),
            localizationsDelegates: const [
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            theme: AppTheme.current,
            routerConfig: widget.router,
            // S-03 : verrouillage par PIN et reconnexion sans perte de données.
            builder: (context, child) => AppLockGate(
              router: widget.router,
              child: child ?? const SizedBox(),
            ),
          ),
        );
      },
    );
  }
}
