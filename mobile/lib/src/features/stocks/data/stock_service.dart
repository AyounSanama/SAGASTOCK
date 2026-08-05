import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

import '../../../core/network/api_client.dart';

class StockService {
  StockService({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;
  final _storage = const FlutterSecureStorage();
  static const _outboxKey = 'offline_stock_outbox';

  Future<Options> _authorized() async => Options(
    headers: {
      'Authorization': 'Bearer ${await _storage.read(key: 'auth_token')}',
    },
  );

  Future<List<Map<String, dynamic>>> organizations() async {
    return _cachedList('/organizations', 'offline_organizations');
  }

  Future<List<Map<String, dynamic>>> balances(String organizationId) async {
    await _syncOutbox();
    return _cachedList(
      '/organizations/$organizationId/stocks/balances',
      'offline_stock_balances_$organizationId',
    );
  }

  Future<List<Map<String, dynamic>>> movements(String organizationId) async {
    return _cachedList(
      '/organizations/$organizationId/stocks/movements',
      'offline_stock_movements_$organizationId',
    );
  }

  Future<List<Map<String, dynamic>>> batches(
    String organizationId, {
    String search = '',
    String status = '',
  }) async {
    final query = <String, dynamic>{
      if (search.trim().isNotEmpty) 'search': search.trim(),
      if (status.isNotEmpty) 'status': status,
    };
    final suffix = '${status}_${search.trim()}';
    return _cachedList(
      '/organizations/$organizationId/catalog/batches',
      'offline_batches_${organizationId}_$suffix',
      queryParameters: query,
    );
  }

  Future<Map<String, List<Map<String, dynamic>>>> batchOptions(
    String organizationId,
  ) async {
    final results = await Future.wait([
      _cachedList(
        '/organizations/$organizationId/catalog/products',
        'offline_batch_products_$organizationId',
        queryParameters: const {'status': 'active'},
      ),
      _cachedList(
        '/organizations/$organizationId/catalog/suppliers',
        'offline_batch_suppliers_$organizationId',
      ),
    ]);
    return {'products': results[0], 'suppliers': results[1]};
  }

  Future<void> saveBatch(
    String organizationId, {
    String? id,
    required String productId,
    String? supplierId,
    required String batchNumber,
    DateTime? manufacturedOn,
    required DateTime expiresOn,
    double? unitCost,
    String? currency,
    String? origin,
    required String status,
  }) async {
    final path = '/organizations/$organizationId/catalog/batches';
    final data = <String, dynamic>{
      'client_reference': const Uuid().v4(),
      'product_id': productId,
      'supplier_id': supplierId,
      'batch_number': batchNumber,
      'manufactured_on': manufacturedOn?.toIso8601String().split('T').first,
      'expires_on': expiresOn.toIso8601String().split('T').first,
      'unit_cost': unitCost,
      'currency': currency?.trim().toUpperCase(),
      'origin': origin?.trim(),
      'status': status,
    };
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

  Future<void> archiveBatch(String organizationId, String id) async {
    await _client.dio.delete<void>(
      '/organizations/$organizationId/catalog/batches/$id',
      options: await _authorized(),
    );
  }

  Future<void> restoreBatch(String organizationId, String id) async {
    await _client.dio.post<void>(
      '/organizations/$organizationId/catalog/batches/archived/$id/restore',
      options: await _authorized(),
    );
  }

  Future<void> compensate(
    String organizationId,
    String movementId,
    String reason,
  ) async {
    await _client.dio.post<void>(
      '/organizations/$organizationId/stocks/movements/$movementId/compensate',
      data: {'reason': reason.trim()},
      options: await _authorized(),
    );
  }

  Future<List<Map<String, dynamic>>> fefo(
    String organizationId, {
    required String siteId,
    required String productId,
    required double quantity,
  }) async {
    final response = await _client.dio.get<Map<String, dynamic>>(
      '/organizations/$organizationId/stocks/fefo',
      queryParameters: {
        'site_id': siteId,
        'product_id': productId,
        'quantity': quantity,
      },
      options: await _authorized(),
    );
    return (response.data?['allocations'] as List<dynamic>? ?? const [])
        .cast<Map<String, dynamic>>();
  }

  Future<Map<String, List<Map<String, dynamic>>>> movementOptions(
    String organizationId,
  ) async {
    final cacheKey = 'offline_stock_options_$organizationId';
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/organizations/$organizationId/stocks/options',
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

  Future<void> createMovement(
    String organizationId, {
    required String siteId,
    required String batchId,
    required String movementType,
    required double quantity,
    String? reason,
  }) async {
    final path = '/organizations/$organizationId/stocks/movements';
    final data = <String, dynamic>{
      'site_id': siteId,
      'batch_id': batchId,
      'movement_type': movementType,
      'quantity': quantity,
      if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
    };
    try {
      await _client.dio.post<Map<String, dynamic>>(
        path,
        data: data,
        options: await _authorized(),
      );
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      final pending = await _readOutbox();
      pending.add({
        'path': path,
        'data': data,
        'queued_at': DateTime.now().toIso8601String(),
      });
      await _storage.write(key: _outboxKey, value: jsonEncode(pending));
    }
  }

  Future<List<Map<String, dynamic>>> _cachedList(
    String path,
    String cacheKey, {
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        path,
        queryParameters: queryParameters,
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
    'batches': (data['batches'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>(),
  };

  Future<List<Map<String, dynamic>>> _readOutbox() async {
    final value = await _storage.read(key: _outboxKey);
    return (value == null ? <dynamic>[] : jsonDecode(value) as List)
        .cast<Map<String, dynamic>>();
  }

  Future<void> _syncOutbox() async {
    final pending = await _readOutbox();
    if (pending.isEmpty) return;
    final remaining = <Map<String, dynamic>>[];
    for (final item in pending) {
      try {
        await _client.dio.post<void>(
          item['path'] as String,
          data: item['data'],
          options: await _authorized(),
        );
      } on DioException {
        remaining.add(item);
      }
    }
    await _storage.write(key: _outboxKey, value: jsonEncode(remaining));
  }
}
