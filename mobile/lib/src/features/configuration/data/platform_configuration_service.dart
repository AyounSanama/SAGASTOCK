import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/network/api_client.dart';
import '../../../core/data/local_first_repository.dart';

class PlatformConfigurationService {
  PlatformConfigurationService({ApiClient? client})
    : _client = client ?? ApiClient(),
      _repository = LocalFirstRepository(client: client);
  final ApiClient _client;
  final LocalFirstRepository _repository;
  final _storage = const FlutterSecureStorage();
  Future<Options> _options() async => Options(
    headers: {
      'Authorization': 'Bearer ${await _storage.read(key: 'auth_token')}',
    },
  );
  Future<Map<String, dynamic>> home() => _repository.document(
    collection: 'platform_configuration_home',
    endpoint: '/platform-configuration',
  );
  Future<List<Map<String, dynamic>>> organizations({String search = ''}) async {
    return _repository.list(
      collection: 'platform_configuration_organizations',
      endpoint: '/platform-configuration/organizations',
      query: search.isEmpty ? const {} : {'search': search},
      responseItems: (body) =>
          ((body['data'] as Map<String, dynamic>?)?['data'] as List<dynamic>? ??
          const []),
    );
  }

  Future<Map<String, dynamic>> organization(String id) => _repository.document(
    collection: 'platform_configuration_organization',
    endpoint: '/platform-configuration/organizations/$id',
    organizationId: id,
  );
  Future<Map<String, dynamic>> preview(
    String id,
    String category,
    Map<String, dynamic> settings,
  ) async {
    try {
      return (await _client.dio.post<Map<String, dynamic>>(
            '/platform-configuration/organizations/$id/preview',
            data: {'category': category, 'settings': settings},
            options: await _options(),
          )).data ??
          {};
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      return {
        'preview': {
          'offline': true,
          'changed_fields': settings.keys.toList(),
          'message':
              'Cette modification sera synchronisée au retour du réseau.',
        },
      };
    }
  }

  Future<bool> apply(
    String id,
    String category,
    Map<String, dynamic> settings,
  ) => _repository.mutate(
    collection: 'platform_configuration_history',
    organizationId: id,
    endpoint: '/platform-configuration/organizations/$id/apply',
    method: 'POST',
    payload: {'category': category, 'settings': settings},
    optimisticPayload: {
      'organization': 'Organisation',
      'category': category,
      'category_label': category,
      'action': 'Modification de configuration',
      'version': 'En attente',
      'author': 'Utilisateur local',
      'status': 'pending',
      'created_at': DateTime.now().toIso8601String(),
    },
  );
  Future<List<Map<String, dynamic>>> history() => _repository.list(
    collection: 'platform_configuration_history',
    endpoint: '/platform-configuration/history',
    responseItems: (body) =>
        ((body['data'] as Map<String, dynamic>?)?['data'] as List<dynamic>? ??
        const []),
  );
}
