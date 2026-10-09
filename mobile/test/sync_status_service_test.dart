import 'dart:convert';

import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/database/app_database.dart';
import 'package:sagastock_mobile/src/core/sync/sync_service.dart';
import 'package:sagastock_mobile/src/core/sync/sync_status_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  late AppDatabase database;
  late SyncStatusService service;

  setUp(() {
    database = AppDatabase.forTesting(NativeDatabase.memory());
    service = SyncStatusService(database: database, ownerUserId: 'user-a');
  });
  tearDown(() => database.close());

  Future<void> enqueue(
    String id, {
    required String entityType,
    required String endpoint,
    String status = 'pending',
    String? error,
    Map<String, dynamic> payload = const {},
  }) => database.enqueueOperation(
    OfflineOperationsCompanion.insert(
      operationId: id,
      idempotencyKey: 'idem-$id',
      ownerUserId: 'user-a',
      entityType: entityType,
      method: 'POST',
      endpoint: endpoint,
      payloadJson: jsonEncode(payload),
      status: Value(status),
      lastError: Value(error),
      createdAt: DateTime.now().toUtc(),
      updatedAt: DateTime.now().toUtc(),
    ),
  );

  test('compte les envois en attente par module et liste les refus et conflits', () async {
    await enqueue('r1', entityType: 'receipts', endpoint: '/organizations/o/receipts');
    await enqueue('r2', entityType: 'receipts', endpoint: '/organizations/o/receipts', status: 'retry');
    await enqueue(
      'd1',
      entityType: 'clinical',
      endpoint: '/organizations/o/dispensations',
      status: 'failed',
      error: 'Le stock de ce site est gelé par un inventaire en cours.',
      payload: {'reference': 'DIS-0317', 'patient_id': 'p-1'},
    );
    await enqueue('c1', entityType: 'receipts', endpoint: '/organizations/o/receipts', status: 'conflict', payload: {'reference': 'REC-249'});
    await enqueue('n1', entityType: 'notifications', endpoint: '/notifications/1/read', status: 'failed');

    final pending = await service.pending();
    expect(pending['receipts'], 2);
    expect(pending['clinical'], 0);

    final issues = await service.issues();
    expect(issues, hasLength(2), reason: 'Notifications hors du suivi');
    final refused = issues.firstWhere((issue) => !issue.conflict);
    expect(refused.title, 'Dispensation DIS-0317');
    expect(refused.reason, contains('gelé'));
    expect(issues.firstWhere((issue) => issue.conflict).title, 'Réception REC-249');
  });

  test('abandonner, signaler ou classer ne supprime jamais la saisie', () async {
    await enqueue('d1', entityType: 'clinical', endpoint: '/organizations/o/dispensations', status: 'failed');
    await enqueue('c1', entityType: 'receipts', endpoint: '/organizations/o/receipts', status: 'conflict');

    await service.abandon('d1');
    await service.reportToAdmin('c1');
    expect((await database.operationById('d1'))?.status, 'abandoned');
    final reported = (await service.issues()).single;
    expect(reported.reported, isTrue);

    await service.acknowledge('c1');
    expect(await service.issues(), isEmpty);
    expect((await database.operationById('c1'))?.status, 'archived');
  });

  test('garde le motif lisible du serveur', () {
    expect(
      SyncService.serverReason({
        'message': 'The given data was invalid.',
        'errors': {
          'reference': ['La référence est déjà utilisée.'],
        },
      }),
      'La référence est déjà utilisée.',
    );
    expect(SyncService.serverReason({'message': 'Stock gelé.'}), 'Stock gelé.');
    expect(SyncService.serverReason('<html>'), isNull);
  });
}
