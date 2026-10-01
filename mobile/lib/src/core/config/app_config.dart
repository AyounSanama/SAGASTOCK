abstract final class AppConfig {
  /// Environments are selected at build time. API_BASE_URL always has the
  /// highest priority, which keeps local builds usable when the LAN IP changes.
  static const environment = String.fromEnvironment(
    'APP_ENV',
    defaultValue: 'development',
  );
  static const _overrideBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: '',
  );
  static const _developmentBaseUrl = String.fromEnvironment(
    'DEVELOPMENT_API_BASE_URL',
    // Point d'accès Windows « PC-SERGE » : le PC y a toujours l'adresse 192.168.137.1.
    defaultValue: 'http://192.168.137.1:8000/api/v1',
  );
  static const _stagingBaseUrl = String.fromEnvironment(
    'STAGING_API_BASE_URL',
    defaultValue: '',
  );
  static const _productionBaseUrl = String.fromEnvironment(
    'PRODUCTION_API_BASE_URL',
    defaultValue: '',
  );

  /// Validation clinique des ordonnances : masquée en V1, prévue en V4 (C-07).
  static const clinicalValidation = bool.fromEnvironment(
    'FEATURE_CLINICAL_VALIDATION',
  );

  /// Adresse saisie sur l'écran « Paramètres serveur » (tests uniquement),
  /// chargée au démarrage par `ServerSettings.load()`.
  static String? _runtimeBaseUrl;

  /// L'adresse modifiable à l'exécution est interdite en production.
  static bool get runtimeOverrideAllowed => environment != 'production';

  static String? get runtimeBaseUrl => _runtimeBaseUrl;

  static void setRuntimeBaseUrl(String? url) {
    _runtimeBaseUrl = runtimeOverrideAllowed && (url?.isNotEmpty ?? false)
        ? url
        : null;
  }

  /// Adresse fixée à la compilation (--dart-define), sans réglage d'exécution.
  static String get buildBaseUrl => _resolve(null);

  static String get apiBaseUrl => _resolve(_runtimeBaseUrl);

  static String _resolve(String? runtime) {
    final configured =
        runtime ??
        (_overrideBaseUrl.isNotEmpty
            ? _overrideBaseUrl
            : switch (environment) {
                'development' => _developmentBaseUrl,
                'staging' => _stagingBaseUrl,
                'production' => _productionBaseUrl,
                _ => throw StateError(
                  'Environnement API inconnu : $environment',
                ),
              });
    if (configured.isEmpty) {
      throw StateError(
        'URL API absente pour l’environnement $environment. '
        'Fournissez API_BASE_URL avec --dart-define.',
      );
    }
    final uri = Uri.tryParse(configured);
    if (uri == null || !uri.hasScheme || !uri.hasAuthority) {
      throw StateError('URL API invalide : $configured');
    }
    return configured.endsWith('/')
        ? configured.substring(0, configured.length - 1)
        : configured;
  }
}
