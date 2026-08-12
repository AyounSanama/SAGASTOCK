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
    defaultValue: 'http://192.168.64.98:8000/api/v1',
  );
  static const _stagingBaseUrl = String.fromEnvironment(
    'STAGING_API_BASE_URL',
    defaultValue: '',
  );
  static const _productionBaseUrl = String.fromEnvironment(
    'PRODUCTION_API_BASE_URL',
    defaultValue: '',
  );

  static String get apiBaseUrl {
    final configured = _overrideBaseUrl.isNotEmpty
        ? _overrideBaseUrl
        : switch (environment) {
            'development' => _developmentBaseUrl,
            'staging' => _stagingBaseUrl,
            'production' => _productionBaseUrl,
            _ => throw StateError('Environnement API inconnu : $environment'),
          };
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
