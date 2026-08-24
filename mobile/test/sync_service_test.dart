import 'dart:convert';

import 'package:dio/dio.dart';
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
      sender: (_) async {},
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
}
