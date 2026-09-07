import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/connectivity/connectivity_service.dart';
import 'package:sagastock_mobile/src/core/database/app_database.dart';
import 'package:sagastock_mobile/src/core/sync/sync_service.dart';

class _OnlineConnectivity extends ConnectivityService {
  @override
  Future<bool> get hasNetwork async => true;

  @override
  Stream<bool> get networkChanges => const Stream.empty();
}

void main() {
  late AppDatabase database;

  setUp(() => database = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => database.close());

  Future<void> enqueue(String id) => database.enqueueOperation(
    OfflineOperationsCompanion.insert(
      operationId: id,
      idempotencyKey: 'idem-$id',
      ownerUserId: 'user-a',
      entityType: 'orders',
      method: 'POST',
      endpoint: '/orders',
      payloadJson: jsonEncode({'reference': id}),
      createdAt: DateTime.now().toUtc(),
      updatedAt: DateTime.now().toUtc(),
    ),
  );

  test('supprime de la file une opération synchronisée', () async {
    await enqueue('operation-1');
    final service = SyncService(
      database: database,
      ownerUserId: 'user-a',
      connectivity: _OnlineConnectivity(),
      sender: (_) async => null,
    );

    final report = await service.syncNow();

    expect(report.succeeded, 1);
    expect(await database.operationById('operation-1'), isNull);
  });

  test('conserve et marque un conflit serveur', () async {
    await enqueue('operation-2');
    final service = SyncService(
      database: database,
      ownerUserId: 'user-a',
      connectivity: _OnlineConnectivity(),
      sender: (operation) async {
        throw DioException(
          requestOptions: RequestOptions(path: operation.endpoint),
          response: Response(
            requestOptions: RequestOptions(path: operation.endpoint),
            statusCode: 409,
            data: {'message': 'Conflit'},
          ),
          type: DioExceptionType.badResponse,
        );
      },
    );

    final report = await service.syncNow();
    final operation = await database.operationById('operation-2');

    expect(report.conflicts, 1);
    expect(operation?.status, 'conflict');
  });

  test(
    'remplace un identifiant local par UUID serveur après création',
    () async {
      const localId = 'local-operation-site';
      const databaseId = 'db-site';
      const serverId = '01991e3e-1480-7f94-8f91-9e944f72fd5b';
      await database.putEntity(
        localId: databaseId,
        entityType: 'structures.sites',
        ownerUserId: 'user-a',
        payload: const {'id': localId, 'name': 'Pharmacie principale'},
        syncState: 'pending',
      );
      await database.enqueueOperation(
        OfflineOperationsCompanion.insert(
          operationId: 'operation-site',
          idempotencyKey: 'idem-site',
          ownerUserId: 'user-a',
          entityType: 'structures.sites',
          localEntityId: const Value(databaseId),
          method: 'POST',
          endpoint: '/sites',
          payloadJson: jsonEncode({'name': 'Pharmacie principale'}),
          createdAt: DateTime.now().toUtc(),
          updatedAt: DateTime.now().toUtc(),
        ),
      );
      final service = SyncService(
        database: database,
        ownerUserId: 'user-a',
        connectivity: _OnlineConnectivity(),
        sender: (_) async => const {
          'site': {'id': serverId, 'name': 'Pharmacie principale'},
        },
      );

      expect((await service.syncNow()).succeeded, 1);
      final site = (await database.entitiesFor(
        entityType: 'structures.sites',
        ownerUserId: 'user-a',
      )).single;
      expect(site.remoteId, serverId);
      expect(site.payload['id'], serverId);
      expect(site.payload['_local_id'], localId);
      expect(site.syncState, 'synced');
    },
  );

  test('attend la FOSA parente avant de synchroniser son site', () async {
    final now = DateTime.now().toUtc();
    await database.enqueueOperation(
      OfflineOperationsCompanion.insert(
        operationId: 'facility-operation',
        idempotencyKey: 'facility-idem',
        ownerUserId: 'user-a',
        entityType: 'structures.facilities',
        method: 'POST',
        endpoint: '/facilities',
        payloadJson: '{}',
        createdAt: now,
        updatedAt: now,
      ),
    );
    await database.enqueueOperation(
      OfflineOperationsCompanion.insert(
        operationId: 'site-operation',
        idempotencyKey: 'site-idem',
        ownerUserId: 'user-a',
        entityType: 'structures.sites',
        method: 'POST',
        endpoint: '/facilities/local-facility/sites',
        payloadJson: '{}',
        dependencyOperationId: const Value('facility-operation'),
        createdAt: now.add(const Duration(milliseconds: 1)),
        updatedAt: now,
      ),
    );
    final sent = <String>[];
    final service = SyncService(
      database: database,
      ownerUserId: 'user-a',
      connectivity: _OnlineConnectivity(),
      sender: (operation) async {
        sent.add(operation.operationId);
        return null;
      },
    );

    final report = await service.syncNow();
    expect(report.succeeded, 2);
    expect(sent, ['facility-operation', 'site-operation']);
  });
}
