import 'dart:convert';

import 'package:drift/drift.dart';

import 'database_connection.dart';

part 'app_database.g.dart';

class LocalEntities extends Table {
  TextColumn get localId => text()();
  TextColumn get entityType => text()();
  TextColumn get ownerUserId => text()();
  TextColumn get organizationId => text().nullable()();
  TextColumn get remoteId => text().nullable()();
  TextColumn get payloadJson => text()();
  TextColumn get syncState => text().withDefault(const Constant('synced'))();
  TextColumn get serverVersion => text().nullable()();
  BoolColumn get isDeleted => boolean().withDefault(const Constant(false))();
  DateTimeColumn get createdAt => dateTime()();
  DateTimeColumn get updatedAt => dateTime()();
  DateTimeColumn get lastSyncedAt => dateTime().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {localId};
}

class OfflineOperations extends Table {
  TextColumn get operationId => text()();
  TextColumn get idempotencyKey => text().unique()();
  TextColumn get ownerUserId => text()();
  TextColumn get organizationId => text().nullable()();
  TextColumn get entityType => text()();
  TextColumn get localEntityId => text().nullable()();
  TextColumn get method => text()();
  TextColumn get endpoint => text()();
  TextColumn get payloadJson => text()();
  TextColumn get dependencyOperationId => text().nullable()();
  TextColumn get status => text().withDefault(const Constant('pending'))();
  IntColumn get attemptCount => integer().withDefault(const Constant(0))();
  IntColumn get maxAttempts => integer().withDefault(const Constant(10))();
  DateTimeColumn get nextAttemptAt => dateTime().nullable()();
  TextColumn get lastError => text().nullable()();
  DateTimeColumn get createdAt => dateTime()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {operationId};
}

class SynchronizationStates extends Table {
  TextColumn get scopeKey => text()();
  TextColumn get ownerUserId => text()();
  TextColumn get organizationId => text().nullable()();
  TextColumn get entityType => text()();
  TextColumn get cursor => text().nullable()();
  TextColumn get serverVersion => text().nullable()();
  DateTimeColumn get lastPulledAt => dateTime().nullable()();
  DateTimeColumn get lastPushedAt => dateTime().nullable()();
  TextColumn get lastError => text().nullable()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {scopeKey};
}

@DriftDatabase(
  tables: [LocalEntities, OfflineOperations, SynchronizationStates],
)
class AppDatabase extends _$AppDatabase {
  AppDatabase() : super(openPharmaCareDatabase());

  AppDatabase.forTesting(super.executor);

  static final AppDatabase shared = AppDatabase();

  @override
  int get schemaVersion => 1;

  Future<void> putEntity({
    required String localId,
    required String entityType,
    required String ownerUserId,
    required Map<String, dynamic> payload,
    String? organizationId,
    String? remoteId,
    String syncState = 'synced',
    String? serverVersion,
    bool isDeleted = false,
    DateTime? lastSyncedAt,
  }) async {
    final now = DateTime.now().toUtc();
    await into(localEntities).insertOnConflictUpdate(
      LocalEntitiesCompanion.insert(
        localId: localId,
        entityType: entityType,
        ownerUserId: ownerUserId,
        organizationId: Value(organizationId),
        remoteId: Value(remoteId),
        payloadJson: jsonEncode(payload),
        syncState: Value(syncState),
        serverVersion: Value(serverVersion),
        isDeleted: Value(isDeleted),
        createdAt: now,
        updatedAt: now,
        lastSyncedAt: Value(lastSyncedAt?.toUtc()),
      ),
    );
  }

  Future<List<LocalEntity>> entitiesFor({
    required String entityType,
    required String ownerUserId,
    String? organizationId,
    bool includeDeleted = false,
  }) {
    final query = select(localEntities)
      ..where(
        (row) =>
            row.entityType.equals(entityType) &
            row.ownerUserId.equals(ownerUserId),
      );
    if (organizationId != null) {
      query.where((row) => row.organizationId.equals(organizationId));
    }
    if (!includeDeleted) {
      query.where((row) => row.isDeleted.equals(false));
    }
    query.orderBy([(row) => OrderingTerm.desc(row.updatedAt)]);
    return query.get();
  }

  Future<void> replaceEntities({
    required String entityType,
    required String ownerUserId,
    required List<Map<String, dynamic>> payloads,
    String? organizationId,
    required String Function(Map<String, dynamic>) localIdFor,
  }) async {
    await transaction(() async {
      final removal = delete(localEntities)
        ..where(
          (row) =>
              row.entityType.equals(entityType) &
              row.ownerUserId.equals(ownerUserId) &
              row.syncState.equals('synced'),
        );
      if (organizationId == null) {
        removal.where((row) => row.organizationId.isNull());
      } else {
        removal.where((row) => row.organizationId.equals(organizationId));
      }
      await removal.go();
      for (final payload in payloads) {
        await putEntity(
          localId: localIdFor(payload),
          entityType: entityType,
          ownerUserId: ownerUserId,
          organizationId: organizationId,
          remoteId: payload['id']?.toString(),
          payload: payload,
          lastSyncedAt: DateTime.now().toUtc(),
        );
      }
    });
  }

  Future<void> markRemoteEntity({
    required String ownerUserId,
    required String organizationId,
    required String remoteId,
    required bool deleted,
    required String syncState,
  }) =>
      (update(localEntities)..where(
            (row) =>
                row.ownerUserId.equals(ownerUserId) &
                row.organizationId.equals(organizationId) &
                row.remoteId.equals(remoteId),
          ))
          .write(
            LocalEntitiesCompanion(
              isDeleted: Value(deleted),
              syncState: Value(syncState),
              updatedAt: Value(DateTime.now().toUtc()),
            ),
          );

  Future<void> updateEntitySyncStateForOperation(
    OfflineOperation operation,
    String state,
  ) async {
    final reference = operation.localEntityId;
    if (reference == null) return;
    final rows =
        await (select(localEntities)..where(
              (row) =>
                  row.ownerUserId.equals(operation.ownerUserId) &
                  (row.localId.equals(reference) |
                      row.remoteId.equals(reference)),
            ))
            .get();
    for (final row in rows) {
      final payload = row.payload;
      if (state == 'synced') {
        payload.remove('_operation_id');
      }
      payload['_sync_status'] = state;
      await (update(
        localEntities,
      )..where((item) => item.localId.equals(row.localId))).write(
        LocalEntitiesCompanion(
          payloadJson: Value(jsonEncode(payload)),
          syncState: Value(state),
          updatedAt: Value(DateTime.now().toUtc()),
          lastSyncedAt: state == 'synced'
              ? Value(DateTime.now().toUtc())
              : const Value.absent(),
        ),
      );
    }
  }

  Future<void> enqueueOperation(OfflineOperationsCompanion operation) => into(
    offlineOperations,
  ).insert(operation, mode: InsertMode.insertOrIgnore);

  Future<List<OfflineOperation>> pendingOperations({
    required String ownerUserId,
    int limit = 100,
  }) {
    final now = DateTime.now().toUtc();
    final query = select(offlineOperations)
      ..where(
        (row) =>
            row.ownerUserId.equals(ownerUserId) &
            row.status.isIn(const ['pending', 'retry']) &
            (row.nextAttemptAt.isNull() |
                row.nextAttemptAt.isSmallerOrEqualValue(now)),
      )
      ..orderBy([(row) => OrderingTerm.asc(row.createdAt)])
      ..limit(limit);
    return query.get();
  }

  Future<OfflineOperation?> operationById(String operationId) => (select(
    offlineOperations,
  )..where((row) => row.operationId.equals(operationId))).getSingleOrNull();

  Future<String?> dependencyForLocalReference(
    String ownerUserId,
    String localReference,
  ) async {
    final entities = await (select(
      localEntities,
    )..where((row) => row.ownerUserId.equals(ownerUserId))).get();
    for (final entity in entities) {
      if ('${entity.payload['id']}' != localReference) continue;
      final operation =
          await (select(offlineOperations)..where(
                (row) =>
                    row.ownerUserId.equals(ownerUserId) &
                    row.localEntityId.equals(entity.localId),
              ))
              .getSingleOrNull();
      return operation?.operationId;
    }
    return null;
  }

  Future<Map<String, String>> localToRemoteIds(String ownerUserId) async {
    final entities =
        await (select(localEntities)..where(
              (row) =>
                  row.ownerUserId.equals(ownerUserId) &
                  row.remoteId.isNotNull(),
            ))
            .get();
    return {
      for (final entity in entities)
        if (entity.payload['_local_id'] is String)
          entity.payload['_local_id'] as String: entity.remoteId!,
    };
  }

  Future<void> completeOperationWithResponse(
    OfflineOperation operation,
    Map<String, dynamic>? response,
  ) async {
    await transaction(() async {
      final reference = operation.localEntityId;
      if (reference != null && response != null) {
        final entity =
            await (select(localEntities)..where(
                  (row) =>
                      row.ownerUserId.equals(operation.ownerUserId) &
                      row.localId.equals(reference),
                ))
                .getSingleOrNull();
        final serverPayload = _serverEntity(response);
        final serverId = serverPayload?['id']?.toString();
        if (entity != null && serverPayload != null && serverId != null) {
          final previous = entity.payload;
          final localId = '${previous['id']}';
          final payload = <String, dynamic>{
            ...previous,
            ...serverPayload,
            '_local_id': localId,
            '_sync_status': 'synced',
          }..remove('_operation_id');
          await (update(
            localEntities,
          )..where((row) => row.localId.equals(entity.localId))).write(
            LocalEntitiesCompanion(
              remoteId: Value(serverId),
              payloadJson: Value(jsonEncode(payload)),
              syncState: const Value('synced'),
              updatedAt: Value(DateTime.now().toUtc()),
              lastSyncedAt: Value(DateTime.now().toUtc()),
            ),
          );
        }
      }
      await completeOperation(operation.operationId);
    });
  }

  Map<String, dynamic>? _serverEntity(Map<String, dynamic> response) {
    if (response['id'] != null) return response;
    for (final value in response.values) {
      if (value is Map && value['id'] != null) {
        return Map<String, dynamic>.from(value);
      }
    }
    return null;
  }

  Future<void> markOperationSyncing(String operationId) =>
      (update(
        offlineOperations,
      )..where((row) => row.operationId.equals(operationId))).write(
        OfflineOperationsCompanion(
          status: const Value('syncing'),
          updatedAt: Value(DateTime.now().toUtc()),
        ),
      );

  Future<void> completeOperation(String operationId) => (delete(
    offlineOperations,
  )..where((row) => row.operationId.equals(operationId))).go();

  Future<void> failOperation({
    required String operationId,
    required String status,
    required int attemptCount,
    required String error,
    DateTime? nextAttemptAt,
  }) =>
      (update(
        offlineOperations,
      )..where((row) => row.operationId.equals(operationId))).write(
        OfflineOperationsCompanion(
          status: Value(status),
          attemptCount: Value(attemptCount),
          lastError: Value(error),
          nextAttemptAt: Value(nextAttemptAt?.toUtc()),
          updatedAt: Value(DateTime.now().toUtc()),
        ),
      );

  Stream<int> watchPendingOperationCount(
    String ownerUserId, {
    String? entityType,
  }) {
    final count = offlineOperations.operationId.count();
    final query = selectOnly(offlineOperations)..addColumns([count]);
    var predicate =
        offlineOperations.ownerUserId.equals(ownerUserId) &
        offlineOperations.status.isIn(const ['pending', 'retry', 'syncing']);
    if (entityType != null) {
      predicate = predicate & offlineOperations.entityType.equals(entityType);
    }
    query.where(predicate);
    return query.watchSingle().map((row) => row.read(count) ?? 0);
  }

  Future<int> pendingOperationCount(String ownerUserId, {String? entityType}) =>
      watchPendingOperationCount(ownerUserId, entityType: entityType).first;

  /// Envois refusés par le serveur ou en conflit : conservés sur le téléphone
  /// jusqu'à ce que l'utilisateur réessaie, abandonne ou prenne acte.
  static const issueStatuses = ['failed', 'conflict', 'conflict_reported'];

  Future<List<OfflineOperation>> issueOperations(String ownerUserId) =>
      (select(offlineOperations)
            ..where(
              (row) =>
                  row.ownerUserId.equals(ownerUserId) &
                  row.status.isIn(issueStatuses),
            )
            ..orderBy([(row) => OrderingTerm.desc(row.updatedAt)]))
          .get();

  /// Opérations en attente d'envoi, par type (réceptions, inventaires…).
  Future<Map<String, int>> pendingCountsByEntity(String ownerUserId) async {
    final rows =
        await (select(offlineOperations)..where(
              (row) =>
                  row.ownerUserId.equals(ownerUserId) &
                  row.status.isIn(const ['pending', 'retry', 'syncing']),
            ))
            .get();
    final counts = <String, int>{};
    for (final row in rows) {
      counts[row.entityType] = (counts[row.entityType] ?? 0) + 1;
    }
    return counts;
  }

  /// Change le statut d'une opération sans jamais effacer sa saisie :
  /// `pending` (réessayer), `abandoned`, `conflict_reported`, `archived`.
  Future<void> setOperationStatus(
    String operationId,
    String status, {
    bool retry = false,
  }) =>
      (update(
        offlineOperations,
      )..where((row) => row.operationId.equals(operationId))).write(
        OfflineOperationsCompanion(
          status: Value(status),
          attemptCount: retry ? const Value(0) : const Value.absent(),
          nextAttemptAt: retry ? const Value(null) : const Value.absent(),
          updatedAt: Value(DateTime.now().toUtc()),
        ),
      );

  Future<void> clearIdentityData(String ownerUserId) async {
    await transaction(() async {
      await (delete(
        localEntities,
      )..where((row) => row.ownerUserId.equals(ownerUserId))).go();
      await (delete(
        offlineOperations,
      )..where((row) => row.ownerUserId.equals(ownerUserId))).go();
      await (delete(
        synchronizationStates,
      )..where((row) => row.ownerUserId.equals(ownerUserId))).go();
    });
  }
}

extension LocalEntityPayload on LocalEntity {
  Map<String, dynamic> get payload =>
      (jsonDecode(payloadJson) as Map).cast<String, dynamic>();
}
