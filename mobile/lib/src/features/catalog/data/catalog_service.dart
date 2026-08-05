import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/network/api_client.dart';

class CatalogService {
  CatalogService({ApiClient? client, FlutterSecureStorage? storage})
    : _client = client ?? ApiClient(),
      _storage = storage ?? const FlutterSecureStorage();

  final ApiClient _client;
  final FlutterSecureStorage _storage;

  Future<Options> _authorized() async => Options(
    headers: {
      'Authorization': 'Bearer ${await _storage.read(key: 'auth_token')}',
    },
  );

  Future<List<Map<String, dynamic>>> organizations() async {
    const key = 'offline_catalog_organizations';
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/organizations',
        options: await _authorized(),
      );
      final values = _data(response.data);
      await _storage.write(key: key, value: jsonEncode(values));
      return values;
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      return _cached(key);
    }
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
    final key =
        'offline_catalog_${organizationId}_${resource}_${jsonEncode(filtered)}';
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/organizations/$organizationId/catalog/$resource',
        queryParameters: filtered,
        options: await _authorized(),
      );
      final values = _data(response.data);
      await _storage.write(key: key, value: jsonEncode(values));
      return values;
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      return _cached(key);
    }
  }

  List<Map<String, dynamic>> _data(Map<String, dynamic>? payload) =>
      (payload?['data'] as List<dynamic>? ?? const <dynamic>[])
          .cast<Map<String, dynamic>>();

  Future<List<Map<String, dynamic>>> _cached(String key) async {
    final value = await _storage.read(key: key);
    if (value == null) return <Map<String, dynamic>>[];
    return (jsonDecode(value) as List<dynamic>).cast<Map<String, dynamic>>();
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
    id == null
        ? await _client.dio.post<void>(
            path,
            data: data,
            options: await _authorized(),
          )
        : await _client.dio.put<void>(
            '$path/$id',
            data: data,
            options: await _authorized(),
          );
  }

  Future<void> saveProduct({
    required String organizationId,
    String? id,
    required Map<String, dynamic> data,
  }) async {
    final path = '/organizations/$organizationId/catalog/products';
    id == null
        ? await _client.dio.post<void>(
            path,
            data: data,
            options: await _authorized(),
          )
        : await _client.dio.put<void>(
            '$path/$id',
            data: data,
            options: await _authorized(),
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
    await _client.dio.delete<void>(
      '/organizations/$organizationId/catalog/$resource/$id',
      options: await _authorized(),
    );
  }

  Future<void> restore(
    String organizationId,
    String resource,
    String id,
  ) async {
    await _client.dio.post<void>(
      '/organizations/$organizationId/catalog/$resource/archived/$id/restore',
      options: await _authorized(),
    );
  }
}
