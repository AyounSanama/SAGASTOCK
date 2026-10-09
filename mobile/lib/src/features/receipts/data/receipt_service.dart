import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/network/api_client.dart';
import '../../../core/sync/offline_operation_service.dart';

class ReceiptService {
  ReceiptService({ApiClient? client}) : _client = client ?? ApiClient() {
    _operations = OfflineOperationService(client: _client);
  }

  final ApiClient _client;
  late final OfflineOperationService _operations;
  final _storage = const FlutterSecureStorage();

  Future<Options> _authorized() async => Options(
    headers: {
      'Authorization': 'Bearer ${await _storage.read(key: 'auth_token')}',
    },
  );

  Future<List<Map<String, dynamic>>> organizations() async {
    return _cachedList('/organizations', 'offline_organizations');
  }

  Future<List<Map<String, dynamic>>> list(String organizationId) async {
    await _operations.synchronize();
    return _cachedList(
      '/organizations/$organizationId/receipts',
      'offline_receipts_$organizationId',
    );
  }

  Future<Map<String, List<Map<String, dynamic>>>> options(
    String organizationId,
  ) async {
    final cacheKey = 'offline_receipt_options_$organizationId';
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/organizations/$organizationId/receipts/options',
        options: await _authorized(),
      );
      final data = response.data ?? <String, dynamic>{};
      await _storage.write(key: cacheKey, value: jsonEncode(data));
      return _optionsFrom(data);
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      final cached = await _storage.read(key: cacheKey);
      if (cached == null) rethrow;
      return _optionsFrom(jsonDecode(cached) as Map<String, dynamic>);
    }
  }

  /// Envoi immédiat, sinon file hors connexion commune (visible dans
  /// Synchronisation, où un refus du serveur peut être réessayé ou abandonné).
  Future<bool> create(
    String organizationId, {
    required Map<String, dynamic> data,
  }) => _operations.execute(
    method: 'post',
    endpoint: '/organizations/$organizationId/receipts',
    payload: data,
    entityType: 'receipts',
    organizationId: organizationId,
  );

  Future<void> validate(String organizationId, String receiptId) async {
    await _client.dio.post<void>(
      '/organizations/$organizationId/receipts/$receiptId/validate',
      options: await _authorized(),
    );
  }

  Future<int> pendingCount() => _operations.pendingCount(entityType: 'receipts');

  Future<List<Map<String, dynamic>>> _cachedList(
    String path,
    String cacheKey,
  ) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        path,
        options: await _authorized(),
      );
      final values = (response.data?['data'] as List<dynamic>? ?? [])
          .cast<Map<String, dynamic>>();
      await _storage.write(key: cacheKey, value: jsonEncode(values));
      return values;
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      final cached = await _storage.read(key: cacheKey);
      if (cached == null) return [];
      return (jsonDecode(cached) as List).cast<Map<String, dynamic>>();
    }
  }

  Map<String, List<Map<String, dynamic>>> _optionsFrom(
    Map<String, dynamic> data,
  ) => {
    'sites': (data['sites'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>(),
    'suppliers': (data['suppliers'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>(),
    'products': (data['products'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>(),
  };
}
