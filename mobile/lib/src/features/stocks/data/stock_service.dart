import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

import '../../../core/network/api_client.dart';
import '../../../core/sync/offline_operation_service.dart';
import '../../../core/data/local_first_repository.dart';

class StockService {
  StockService({ApiClient? client}) {
    _client = client ?? ApiClient();
    _operations = OfflineOperationService(client: _client);
    _repository = LocalFirstRepository(client: _client, storage: _storage);
  }

  late final ApiClient _client;
  late final OfflineOperationService _operations;
  late final LocalFirstRepository _repository;
  final _storage = const FlutterSecureStorage();

  Future<Options> _authorized() async => Options(
    headers: {
      'Authorization': 'Bearer ${await _storage.read(key: 'auth_token')}',
    },
  );

  Future<List<Map<String, dynamic>>> organizations() async {
    return _cachedList('/organizations', 'offline_organizations');
  }

  Future<List<Map<String, dynamic>>> balances(String organizationId) async {
    await _operations.synchronize();
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
    final data = await _repository.document(
      collection: 'stock.options',
      organizationId: organizationId,
      endpoint: '/organizations/$organizationId/stocks/options',
    );
    return _optionsFrom(data);
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
    final clientReference = const Uuid().v4();
    final data = <String, dynamic>{
      'client_reference': clientReference,
      'site_id': siteId,
      'batch_id': batchId,
      'movement_type': movementType,
      'quantity': quantity,
      if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
    };
    await _operations.execute(
      method: 'POST',
      endpoint: path,
      payload: data,
      entityType: 'stocks',
      organizationId: organizationId,
    );
  }

  Future<List<Map<String, dynamic>>> _cachedList(
    String path,
    String cacheKey, {
    Map<String, dynamic>? queryParameters,
  }) async {
    return _repository.list(
      collection: cacheKey,
      organizationId: RegExp(
        r'/organizations/([^/]+)',
      ).firstMatch(path)?.group(1),
      endpoint: path,
      query: queryParameters ?? const {},
    );
  }

  Map<String, List<Map<String, dynamic>>> _optionsFrom(
    Map<String, dynamic> data,
  ) => {
    'sites': (data['sites'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>(),
    'batches': (data['batches'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>(),
  };
}
