import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/data/local_first_repository.dart';
import '../../../core/database/app_database.dart';
import '../../../core/network/api_client.dart';

class CatalogService {
  CatalogService({
    ApiClient? client,
    FlutterSecureStorage? storage,
    AppDatabase? database,
  }) {
    _client = client ?? ApiClient();
    _storage = storage ?? const FlutterSecureStorage();
    _repository = LocalFirstRepository(
      client: _client,
      storage: _storage,
      database: database,
    );
  }

  late final ApiClient _client;
  late final FlutterSecureStorage _storage;
  late final LocalFirstRepository _repository;

  Future<Options> _authorized() async => Options(
    headers: {
      'Authorization': 'Bearer ${await _storage.read(key: 'auth_token')}',
    },
  );

  Future<List<Map<String, dynamic>>> organizations() async {
    return _repository.list(
      collection: 'catalog.organizations',
      endpoint: '/organizations',
    );
  }

  Future<List<Map<String, dynamic>>> products(
    String organizationId, {
    String search = '',
    String type = '',
    String status = 'active',
  }) => _list(
    organizationId,
    'products',
    query: {'search': search, 'type': type, 'status': status},
  );

  Future<List<Map<String, dynamic>>> references(
    String organizationId, {
    String search = '',
    String type = '',
    String status = 'active',
  }) => _list(
    organizationId,
    'references',
    query: {'search': search, 'type': type, 'status': status},
  );

  Future<List<Map<String, dynamic>>> lists(
    String organizationId, {
    String search = '',
    String status = 'active',
  }) => _list(
    organizationId,
    'lists',
    query: {'search': search, 'status': status},
  );

  Future<List<Map<String, dynamic>>> _list(
    String organizationId,
    String resource, {
    required Map<String, String> query,
  }) async {
    final filtered = Map<String, String>.from(query)
      ..removeWhere((_, value) => value.isEmpty);
    return _repository.list(
      collection: 'catalog.$resource',
      endpoint: '/organizations/$organizationId/catalog/$resource',
      organizationId: organizationId,
      query: filtered,
    );
  }

  Future<void> saveReference({
    required String organizationId,
    String? id,
    required String type,
    required String code,
    required String name,
    String? description,
    bool active = true,
  }) async {
    final data = {
      'reference_type': type,
      'code': code,
      'name': name,
      'description': description,
      'is_active': active,
    };
    final path = '/organizations/$organizationId/catalog/references';
    await _repository.mutate(
      collection: 'catalog.references',
      organizationId: organizationId,
      endpoint: id == null ? path : '$path/$id',
      method: id == null ? 'POST' : 'PUT',
      payload: data,
      remoteId: id,
      collectionQuery: const {'status': 'active'},
    );
  }

  Future<void> saveProduct({
    required String organizationId,
    String? id,
    required Map<String, dynamic> data,
  }) async {
    final path = '/organizations/$organizationId/catalog/products';
    await _repository.mutate(
      collection: 'catalog.products',
      organizationId: organizationId,
      endpoint: id == null ? path : '$path/$id',
      method: id == null ? 'POST' : 'PUT',
      payload: data,
      remoteId: id,
      collectionQuery: const {'status': 'active'},
    );
  }

  Future<void> saveList({
    required String organizationId,
    required String code,
    required String name,
    String? description,
    required List<String> productIds,
  }) async {
    await _client.dio.post<void>(
      '/organizations/$organizationId/catalog/lists',
      data: {
        'code': code,
        'name': name,
        'description': description,
        'scope_type': 'organization',
        'scope_id': organizationId,
        'allow_outside_list': false,
        'is_active': true,
        'items': productIds.map((id) => {'product_id': id}).toList(),
      },
      options: await _authorized(),
    );
  }

  Future<void> updateList({
    required String organizationId,
    required String listId,
    required String code,
    required String name,
    String? description,
    required List<String> productIds,
  }) async {
    final path = '/organizations/$organizationId/catalog/lists/$listId';
    await _client.dio.put<void>(
      path,
      data: {
        'code': code,
        'name': name,
        'description': description,
        'allow_outside_list': false,
        'is_active': true,
      },
      options: await _authorized(),
    );
    await _client.dio.post<void>(
      '$path/versions',
      data: {
        'change_notes': 'Mise à jour depuis l’application mobile',
        'items': productIds.map((id) => {'product_id': id}).toList(),
      },
      options: await _authorized(),
    );
  }

  Future<void> publishListVersion({
    required String organizationId,
    required String listId,
    required String versionId,
  }) async {
    await _client.dio.post<void>(
      '/organizations/$organizationId/catalog/lists/$listId/versions/$versionId/publish',
      data: {
        'effective_from': DateTime.now().toIso8601String().split('T').first,
      },
      options: await _authorized(),
    );
  }

  Future<void> archive(
    String organizationId,
    String resource,
    String id,
  ) async {
    await _repository.mutateEntityState(
      collection: 'catalog.$resource',
      organizationId: organizationId,
      remoteId: id,
      endpoint: '/organizations/$organizationId/catalog/$resource/$id',
      deleted: true,
      method: 'DELETE',
    );
  }

  Future<void> restore(
    String organizationId,
    String resource,
    String id,
  ) async {
    await _repository.mutateEntityState(
      collection: 'catalog.$resource',
      organizationId: organizationId,
      remoteId: id,
      endpoint:
          '/organizations/$organizationId/catalog/$resource/archived/$id/restore',
      deleted: false,
    );
  }
}
