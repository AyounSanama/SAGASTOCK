import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:drift/drift.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

import '../database/app_database.dart';
import '../network/api_client.dart';
import 'sync_bootstrap.dart';

class OfflineOperationService {
  OfflineOperationService({
    ApiClient? client,
    AppDatabase? database,
    FlutterSecureStorage? storage,
  }) : _client = client ?? ApiClient(),
       _database = database ?? AppDatabase.shared,
       _storage = storage ?? const FlutterSecureStorage();

  final ApiClient _client;
  final AppDatabase _database;
  final FlutterSecureStorage _storage;

  Future<bool> execute({
    required String method,
    required String endpoint,
    required Map<String, dynamic> payload,
    required String entityType,
    String? organizationId,
  }) async {
    final operationId = const Uuid().v4();
    final token = await _storage.read(key: 'auth_token');
    try {
      await _client.dio.request<void>(
        endpoint,
        data: payload,
        options: Options(
          method: method.toUpperCase(),
          headers: {
            'Authorization': 'Bearer $token',
            'Idempotency-Key': operationId,
          },
        ),
      );
      return true;
    } on DioException catch (error) {
      if (error.response != null) rethrow;
    }

    final user = await _cachedUser();
    await _database.enqueueOperation(
      OfflineOperationsCompanion.insert(
        operationId: operationId,
        idempotencyKey: operationId,
        ownerUserId: '${user['id']}',
        organizationId: Value(organizationId),
        entityType: entityType,
        method: method.toUpperCase(),
        endpoint: endpoint,
        payloadJson: jsonEncode(payload),
        createdAt: DateTime.now().toUtc(),
        updatedAt: DateTime.now().toUtc(),
      ),
    );
    return false;
  }

  Future<int> pendingCount({String? entityType}) async {
    final user = await _cachedUser();
    return _database.pendingOperationCount(
      '${user['id']}',
      entityType: entityType,
    );
  }

  Future<void> synchronize() async {
    await SyncBootstrap.syncNow();
  }

  Future<Map<String, dynamic>> _cachedUser() async {
    final encoded = await _storage.read(key: 'cached_user');
    if (encoded == null) throw StateError('Aucune identité locale disponible.');
    return (jsonDecode(encoded) as Map).cast<String, dynamic>();
  }
}
