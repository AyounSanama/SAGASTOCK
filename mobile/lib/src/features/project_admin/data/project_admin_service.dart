import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/data/local_first_repository.dart';
import '../../../core/database/app_database.dart';
import '../../../core/network/api_client.dart';

/// Erreur affichable, avec les erreurs par champ renvoyées par le serveur.
class ProjectAdminException implements Exception {
  const ProjectAdminException(this.message, [this.fieldErrors = const {}]);

  final String message;
  final Map<String, String> fieldErrors;

  @override
  String toString() => message;
}

/// Niveau 5 — Espace Admin Projet sur le téléphone (maquettes AdminProjet 05
/// à 08). Les lectures restent disponibles hors ligne (dernière
/// synchronisation) ; déclarer ou modifier une FOSA exige le réseau, car la
/// FOSA passe ensuite par la validation de la Coordination.
class ProjectAdminService {
  ProjectAdminService({
    ApiClient? client,
    FlutterSecureStorage? storage,
    AppDatabase? database,
  }) : _client = client ?? ApiClient(),
       _storage = storage ?? const FlutterSecureStorage() {
    _repository = LocalFirstRepository(
      client: _client,
      storage: _storage,
      database: database,
    );
  }

  final ApiClient _client;
  final FlutterSecureStorage _storage;
  late final LocalFirstRepository _repository;

  static const offlineMessage =
      'Déclarer ou modifier une FOSA nécessite une connexion internet. Hors ligne, les informations restent consultables.';

  Future<Map<String, dynamic>> dashboard() => _repository.document(
    collection: 'project_admin_dashboard',
    endpoint: '/project-admin/dashboard',
  );

  Future<Map<String, dynamic>> facilities() => _repository.document(
    collection: 'project_admin_facilities',
    endpoint: '/project-admin/facilities',
  );

  Future<Map<String, dynamic>> standardList({String? facilityId}) =>
      _repository.document(
        collection: 'project_admin_standard_list',
        endpoint: '/project-admin/standard-list',
        query: {if (facilityId != null) 'facility': facilityId},
      );

  Future<Map<String, dynamic>> options() =>
      _send('GET', '/project-admin/facilities/options');

  Future<Map<String, dynamic>> facility(String id) =>
      _send('GET', '/project-admin/facilities/$id');

  /// Nombre de produits de la Liste Standard pour les choix en cours.
  Future<int?> preview(Map<String, dynamic> criteria) async {
    final data = await _send('GET', '/project-admin/facilities/preview', null, {
      if (criteria['care_level_id'] != null) 'care_level_id': criteria['care_level_id'],
      if (criteria['facility_category_id'] != null)
        'facility_category_id': criteria['facility_category_id'],
      'target_population_ids[]': criteria['target_population_ids'] ?? const [],
      'pathology_ids[]': criteria['pathology_ids'] ?? const [],
    });
    return (data['count'] as num?)?.toInt();
  }

  Future<Map<String, dynamic>> saveFacility(String? id, Map<String, dynamic> data) =>
      id == null
      ? _send('POST', '/project-admin/facilities', data)
      : _send('PUT', '/project-admin/facilities/$id', data);

  Future<Map<String, dynamic>> _send(
    String method,
    String path, [
    Map<String, dynamic>? data,
    Map<String, dynamic>? query,
  ]) async {
    try {
      final response = await _client.dio.request<Map<String, dynamic>>(
        path,
        data: data,
        queryParameters: query,
        options: Options(
          method: method,
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer ${await _storage.read(key: 'auth_token')}',
          },
        ),
      );
      return response.data ?? const {};
    } on DioException catch (error) {
      final response = error.response;
      if (response == null) throw const ProjectAdminException(offlineMessage);
      final body = response.data;
      final errors = body is Map ? body['errors'] : null;
      final fields = <String, String>{
        if (errors is Map)
          for (final entry in errors.entries)
            if (entry.value is List && (entry.value as List).isNotEmpty)
              '${entry.key}'.split('.').first: '${(entry.value as List).first}',
      };
      throw ProjectAdminException(
        fields.values.firstOrNull ??
            '${(body is Map ? body['message'] : null) ?? 'Action impossible (${response.statusCode}).'}',
        fields,
      );
    }
  }
}
