import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'app_config.dart';

/// Adresse du serveur modifiable sans recompiler (builds de test uniquement).
///
/// L'adresse de compilation (`--dart-define=API_BASE_URL=...`) reste la valeur
/// par défaut ; celle saisie ici la remplace jusqu'à « Revenir à l'adresse de
/// compilation ». Refusée en production (voir [AppConfig.runtimeOverrideAllowed]).
abstract final class ServerSettings {
  static const _key = 'pharmacare.api_base_url';
  static const _storage = FlutterSecureStorage();

  /// À appeler au démarrage, avant la création des clients réseau.
  static Future<void> load() async {
    if (!AppConfig.runtimeOverrideAllowed) return;
    try {
      AppConfig.setRuntimeBaseUrl(await _storage.read(key: _key));
    } catch (_) {
      // Stockage indisponible : l'adresse de compilation s'applique.
    }
  }

  /// Normalise une saisie : « 192.168.137.1:8000 » devient
  /// « http://192.168.137.1:8000/api/v1 ». Renvoie null si invalide.
  static String? normalize(String input) {
    var value = input.trim();
    if (value.isEmpty) return null;
    if (!value.contains('://')) value = 'http://$value';
    final uri = Uri.tryParse(value);
    if (uri == null ||
        !(uri.scheme == 'http' || uri.scheme == 'https') ||
        uri.host.isEmpty) {
      return null;
    }
    var path = uri.path.replaceAll(RegExp(r'/+$'), '');
    if (!path.endsWith('/api/v1')) path = '$path/api/v1';
    return uri.replace(path: path, query: null, fragment: null).toString();
  }

  static Future<void> save(String normalizedUrl) async {
    AppConfig.setRuntimeBaseUrl(normalizedUrl);
    await _storage.write(key: _key, value: normalizedUrl);
  }

  static Future<void> reset() async {
    AppConfig.setRuntimeBaseUrl(null);
    await _storage.delete(key: _key);
  }

  /// Vérifie que le serveur répond (`GET /health`).
  static Future<bool> ping(String normalizedUrl, {Dio? dio}) async {
    final client =
        dio ??
        Dio(
          BaseOptions(
            connectTimeout: AppConfig.connectTimeout,
            receiveTimeout: AppConfig.receiveTimeout,
          ),
        );
    try {
      final response = await client.get<dynamic>('$normalizedUrl/health');
      final body = response.data;
      return response.statusCode == 200 &&
          body is Map &&
          body['status'] == 'ok';
    } catch (_) {
      return false;
    }
  }
}
