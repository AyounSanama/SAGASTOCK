import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/network/api_client.dart';

class StructureService {
  StructureService({ApiClient? client, FlutterSecureStorage? storage})
    : _client = client ?? ApiClient(),
      _storage = storage ?? const FlutterSecureStorage();

  final ApiClient _client;
  final FlutterSecureStorage _storage;

  Future<Options> _authorized() async {
    final token = await _storage.read(key: 'auth_token');
    return Options(headers: {'Authorization': 'Bearer $token'});
  }

  Future<Map<String, dynamic>> list(
    String organizationId, {
    String search = '',
  }) async {
    final cacheKey = 'offline_structures_$organizationId';
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/organizations/$organizationId/structures',
        queryParameters: search.isEmpty ? null : {'search': search},
        options: await _authorized(),
      );
      final data = response.data ?? <String, dynamic>{};
      if (search.isEmpty) {
        await _storage.write(key: cacheKey, value: jsonEncode(data));
      }
      return data;
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      final cached = await _storage.read(key: cacheKey);
      if (cached == null) {
        return {
          'facilities': {'data': <dynamic>[]},
          'archived_facilities': <dynamic>[],
          'offline': true,
        };
      }
      final data = jsonDecode(cached) as Map<String, dynamic>;
      data['offline'] = true;
      if (search.isNotEmpty) {
        final query = search.toLowerCase();
        final facilities =
            ((data['facilities'] as Map<String, dynamic>?)?['data']
                        as List<dynamic>? ??
                    [])
                .where(
                  (item) =>
                      '${item['name']}'.toLowerCase().contains(query) ||
                      '${item['code']}'.toLowerCase().contains(query),
                )
                .toList();
        (data['facilities'] as Map<String, dynamic>)['data'] = facilities;
      }
      return data;
    }
  }

  Future<void> saveFacility({
    required String organizationId,
    String? facilityId,
    required Map<String, dynamic> data,
  }) async {
    final path = facilityId == null
        ? '/organizations/$organizationId/facilities'
        : '/organizations/$organizationId/facilities/$facilityId';
    if (facilityId == null) {
      await _client.dio.post<void>(
        path,
        data: data,
        options: await _authorized(),
      );
    } else {
      await _client.dio.put<void>(
        path,
        data: data,
        options: await _authorized(),
      );
    }
  }

  Future<void> archiveFacility(
    String organizationId,
    String facilityId,
  ) async {
    await _client.dio.delete<void>(
      '/organizations/$organizationId/facilities/$facilityId',
      options: await _authorized(),
    );
  }

  Future<void> restoreFacility(
    String organizationId,
    String facilityId,
  ) async {
    await _client.dio.post<void>(
      '/organizations/$organizationId/facilities/archived/$facilityId/restore',
      options: await _authorized(),
    );
  }

  Future<void> saveChild({
    required String organizationId,
    required String facilityId,
    required String kind,
    String? childId,
    required Map<String, dynamic> data,
  }) async {
    final collection = _collection(kind);
    final path =
        '/organizations/$organizationId/facilities/$facilityId/$collection'
        '${childId == null ? '' : '/$childId'}';
    if (childId == null) {
      await _client.dio.post<void>(
        path,
        data: data,
        options: await _authorized(),
      );
    } else {
      await _client.dio.put<void>(
        path,
        data: data,
        options: await _authorized(),
      );
    }
  }

  Future<void> archiveChild({
    required String organizationId,
    required String facilityId,
    required String kind,
    required String childId,
  }) async {
    await _client.dio.delete<void>(
      '/organizations/$organizationId/facilities/$facilityId/${_collection(kind)}/$childId',
      options: await _authorized(),
    );
  }

  Future<void> restoreChild({
    required String organizationId,
    required String facilityId,
    required String kind,
    required String childId,
  }) async {
    final collection = _collection(kind);
    await _client.dio.post<void>(
      '/organizations/$organizationId/facilities/$facilityId/$collection/archived/$childId/restore',
      options: await _authorized(),
    );
  }

  String _collection(String kind) => switch (kind) {
    'department' => 'departments',
    'pharmacy' => 'pharmacies',
    'site' => 'sites',
    _ => throw ArgumentError.value(kind),
  };
}
