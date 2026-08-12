import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/config/app_config.dart';

void main() {
  test('la configuration de développement expose une URL API valide', () {
    final uri = Uri.parse(AppConfig.apiBaseUrl);

    expect(uri.scheme, anyOf('http', 'https'));
    expect(uri.host, isNotEmpty);
    expect(uri.path, endsWith('/api/v1'));
  });
}
