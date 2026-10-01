import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/config/app_config.dart';
import 'package:sagastock_mobile/src/core/config/server_settings.dart';

void main() {
  tearDown(() => AppConfig.setRuntimeBaseUrl(null));

  test('une adresse saisie simplement est complétée', () {
    expect(ServerSettings.normalize('192.168.137.1:8000'), 'http://192.168.137.1:8000/api/v1');
    expect(ServerSettings.normalize(' http://192.168.137.1:8000/ '), 'http://192.168.137.1:8000/api/v1');
    expect(ServerSettings.normalize('https://pharmacare.example/api/v1'), 'https://pharmacare.example/api/v1');
  });

  test('une adresse invalide est refusée', () {
    expect(ServerSettings.normalize(''), isNull);
    expect(ServerSettings.normalize('ftp://serveur'), isNull);
  });

  test('l’adresse d’exécution remplace celle de compilation, puis se réinitialise', () {
    final build = AppConfig.buildBaseUrl;
    AppConfig.setRuntimeBaseUrl('http://10.0.0.5:8000/api/v1');
    expect(AppConfig.apiBaseUrl, 'http://10.0.0.5:8000/api/v1');
    expect(AppConfig.buildBaseUrl, build);

    AppConfig.setRuntimeBaseUrl(null);
    expect(AppConfig.apiBaseUrl, build);
  });
}
