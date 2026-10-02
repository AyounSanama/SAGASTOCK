import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/config/app_config.dart';

void main() {
  test('la configuration de développement expose une URL API valide', () {
    final uri = Uri.parse(AppConfig.apiBaseUrl);

    expect(uri.scheme, anyOf('http', 'https'));
    expect(uri.host, isNotEmpty);
    expect(uri.path, endsWith('/api/v1'));
  });

  test('S-06 : HTTPS obligatoire en production, HTTP permis en test', () {
    expect(
      () => AppConfig.validateBaseUrl(
        'http://192.168.137.1:8000/api/v1',
        environment: 'production',
      ),
      throwsStateError,
    );
    expect(
      AppConfig.validateBaseUrl(
        'https://pharmacare.example.org/api/v1/',
        environment: 'production',
      ),
      'https://pharmacare.example.org/api/v1',
    );
    expect(
      AppConfig.validateBaseUrl(
        'http://192.168.137.1:8000/api/v1',
        environment: 'development',
      ),
      'http://192.168.137.1:8000/api/v1',
    );
  });

  test('délais réseau allongés en version de test uniquement', () {
    expect(
      AppConfig.connectTimeoutFor('production'),
      const Duration(seconds: 5),
    );
    expect(
      AppConfig.receiveTimeoutFor('production'),
      const Duration(seconds: 12),
    );
    for (final environment in ['development', 'staging']) {
      expect(
        AppConfig.connectTimeoutFor(environment),
        const Duration(seconds: 10),
      );
      expect(
        AppConfig.receiveTimeoutFor(environment),
        const Duration(seconds: 60),
      );
    }
  });
}
