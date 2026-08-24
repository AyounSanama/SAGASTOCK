import 'dart:convert';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:drift/drift.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

import '../database/app_database.dart';
import '../network/api_client.dart';

class LocalFirstRepository {
  LocalFirstRepository({
    AppDatabase? database,
    ApiClient? client,
    FlutterSecureStorage? storage,
    Future<String?> Function(String key)? readSecureValue,
  }) : database = database ?? AppDatabase.shared,
       _client = client ?? ApiClient(),
       _storage = storage ?? const FlutterSecureStorage(),
       _readSecureValue = readSecureValue;

  final AppDatabase database;
  final ApiClient _client;
  final FlutterSecureStorage _storage;
  final Future<String?> Function(String key)? _readSecureValue;

  Future<List<Map<String, dynamic>>> list({
    required String collection,
    required String endpoint,
    String? organizationId,
    Map<String, dynamic> query = const {},
    String responseKey = 'data',
    List<dynamic> Function(Map<String, dynamic>)? responseItems,
  }) async {
    final identity = await _identity();
    final entityType = _collectionKey(collection, query);
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        endpoint,
        queryParameters: query,
        options: await _authorized(),
      );
      final body = response.data ?? <String, dynamic>{};
      final values =
          ((responseItems?.call(body) ?? body[responseKey] as List<dynamic>?) ??
                  const [])
              .map((item) => (item as Map).cast<String, dynamic>())
              .toList(growable: false);
      await database.replaceEntities(
        entityType: entityType,
        ownerUserId: identity.userId,
        organizationId: organizationId,
        payloads: values,
        localIdFor: (payload) => _localId(
          ownerUserId: identity.userId,
          organizationId: organizationId,
          entityType: entityType,
          remoteId: '${payload['id'] ?? jsonEncode(payload)}',
        ),
      );
    } on DioException catch (error) {
      if (error.response != null) rethrow;
    }
    return localList(
      collection: collection,
      organizationId: organizationId,
      query: query,
    );
  }

  Future<List<Map<String, dynamic>>> localList({
    required String collection,
    String? organizationId,
    Map<String, dynamic> query = const {},
  }) async {
    final identity = await _identity();
    final rows = await database.entitiesFor(
      entityType: _collectionKey(collection, query),
      ownerUserId: identity.userId,
      organizationId: organizationId,
    );
    return rows.map((row) => row.payload).toList(growable: false);
  }

  Future<Map<String, dynamic>> document({
    required String collection,
    required String endpoint,
    String? organizationId,
    Map<String, dynamic> query = const {},
  }) async {
    final identity = await _identity();
    final entityType = _collectionKey(collection, query);
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        endpoint,
        queryParameters: query,
        options: await _authorized(),
      );
      final payload = response.data ?? <String, dynamic>{};
      await database.replaceEntities(
        entityType: entityType,
        ownerUserId: identity.userId,
        organizationId: organizationId,
        payloads: [payload],
        localIdFor: (_) => _localId(
          ownerUserId: identity.userId,
          organizationId: organizationId,
          entityType: entityType,
          remoteId: 'document',
        ),
      );
    } on DioException catch (error) {
      if (error.response != null) rethrow;
    }
    final local = await database.entitiesFor(
      entityType: entityType,
      ownerUserId: identity.userId,
      organizationId: organizationId,
    );
    return local.isEmpty ? <String, dynamic>{} : local.single.payload;
  }

  Future<bool> mutate({
    required String collection,
    String? organizationId,
    required String endpoint,
    required String method,
    required Map<String, dynamic> payload,
    Map<String, dynamic>? optimisticPayload,
    String? remoteId,
    Map<String, dynamic> collectionQuery = const {},
  }) async {
    final identity = await _identity();
    final operationId = const Uuid().v4();
    try {
      final authorized = await _authorized();
      await _client.dio.request<void>(
        endpoint,
        data: payload,
        options: authorized.copyWith(
          method: method.toUpperCase(),
          headers: {...?authorized.headers, 'Idempotency-Key': operationId},
        ),
      );
      return true;
    } on DioException catch (error) {
      if (error.response != null) rethrow;
    }

    final localId = remoteId ?? 'local-$operationId';
    final databaseLocalId = _localId(
      ownerUserId: identity.userId,
      organizationId: organizationId,
      entityType: _collectionKey(collection, collectionQuery),
      remoteId: localId,
    );
    final localPayload = <String, dynamic>{
      ...?optimisticPayload,
      if (optimisticPayload == null) ...payload,
      'id': localId,
      '_sync_status': 'pending',
      '_operation_id': operationId,
    };
    await database.transaction(() async {
      await database.putEntity(
        localId: databaseLocalId,
        entityType: _collectionKey(collection, collectionQuery),
        ownerUserId: identity.userId,
        organizationId: organizationId,
        remoteId: remoteId,
        payload: localPayload,
        syncState: 'pending',
      );
      await database.enqueueOperation(
        OfflineOperationsCompanion.insert(
          operationId: operationId,
          idempotencyKey: operationId,
          ownerUserId: identity.userId,
          organizationId: Value(organizationId),
          entityType: collection,
          localEntityId: Value(databaseLocalId),
          method: method.toUpperCase(),
          endpoint: endpoint,
          payloadJson: jsonEncode(payload),
          createdAt: DateTime.now().toUtc(),
          updatedAt: DateTime.now().toUtc(),
        ),
      );
    });
    return false;
  }

  Future<bool> mutateEntityState({
    required String collection,
    required String organizationId,
    required String remoteId,
    required String endpoint,
    required bool deleted,
    String method = 'POST',
  }) async {
    final identity = await _identity();
    final operationId = const Uuid().v4();
    try {
      final authorized = await _authorized();
      await _client.dio.request<void>(
        endpoint,
        options: authorized.copyWith(
          method: method.toUpperCase(),
          headers: {...?authorized.headers, 'Idempotency-Key': operationId},
        ),
      );
      return true;
    } on DioException catch (error) {
      if (error.response != null) rethrow;
    }
    await database.transaction(() async {
      await database.markRemoteEntity(
        ownerUserId: identity.userId,
        organizationId: organizationId,
        remoteId: remoteId,
        deleted: deleted,
        syncState: 'pending',
      );
      await database.enqueueOperation(
        OfflineOperationsCompanion.insert(
          operationId: operationId,
          idempotencyKey: operationId,
          ownerUserId: identity.userId,
          organizationId: Value(organizationId),
          entityType: collection,
          localEntityId: Value(remoteId),
          method: method.toUpperCase(),
          endpoint: endpoint,
          payloadJson: '{}',
          createdAt: DateTime.now().toUtc(),
          updatedAt: DateTime.now().toUtc(),
        ),
      );
    });
    return false;
  }

  Stream<List<Map<String, dynamic>>> watch({
    required String collection,
    required String ownerUserId,
    String? organizationId,
    Map<String, dynamic> query = const {},
  }) {
    final entityType = _collectionKey(collection, query);
    final selection = database.select(database.localEntities)
      ..where(
        (row) =>
            row.entityType.equals(entityType) &
            row.ownerUserId.equals(ownerUserId) &
            row.isDeleted.equals(false),
      );
    if (organizationId == null) {
      selection.where((row) => row.organizationId.isNull());
    } else {
      selection.where((row) => row.organizationId.equals(organizationId));
    }
    selection.orderBy([(row) => OrderingTerm.desc(row.updatedAt)]);
    return selection.watch().map(
      (rows) => rows.map((row) => row.payload).toList(growable: false),
    );
  }

  Future<Options> _authorized() async => Options(
    headers: {'Authorization': 'Bearer ${await _read('auth_token')}'},
  );

  Future<_LocalIdentity> _identity() async {
    final value = await _read('cached_user');
    if (value == null) {
      throw StateError('Aucune identité locale disponible.');
    }
    final user = (jsonDecode(value) as Map).cast<String, dynamic>();
    final id = '${user['id'] ?? ''}';
    if (id.isEmpty) throw StateError('Identité locale invalide.');
    return _LocalIdentity(id);
  }

  Future<String?> _read(String key) =>
      _readSecureValue?.call(key) ?? _storage.read(key: key);

  String _collectionKey(String collection, Map<String, dynamic> query) {
    final sorted = Map.fromEntries(
      query.entries.toList()..sort((a, b) => a.key.compareTo(b.key)),
    );
    final signature = sha256
        .convert(utf8.encode(jsonEncode(sorted)))
        .toString();
    return '$collection:$signature';
  }

  String _localId({
    required String ownerUserId,
    required String? organizationId,
    required String entityType,
    required String remoteId,
  }) => sha256
      .convert(
        utf8.encode(
          '$ownerUserId:${organizationId ?? 'global'}:$entityType:$remoteId',
        ),
      )
      .toString();
}

class _LocalIdentity {
  const _LocalIdentity(this.userId);

  final String userId;
}
