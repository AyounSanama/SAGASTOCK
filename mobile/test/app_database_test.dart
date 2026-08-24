import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/database/app_database.dart';

void main() {
  late AppDatabase database;

  setUp(() => database = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => database.close());

  test(
    'cloisonne les données locales par utilisateur et organisation',
    () async {
      await database.putEntity(
        localId: 'local-1',
        entityType: 'products',
        ownerUserId: 'user-a',
        organizationId: 'org-a',
        payload: const {'name': 'Paracétamol'},
      );
      await database.putEntity(
        localId: 'local-2',
        entityType: 'products',
        ownerUserId: 'user-b',
        organizationId: 'org-b',
        payload: const {'name': 'Amoxicilline'},
      );

      final values = await database.entitiesFor(
        entityType: 'products',
        ownerUserId: 'user-a',
        organizationId: 'org-a',
      );

      expect(values, hasLength(1));
      expect(values.single.payload['name'], 'Paracétamol');
    },
  );

  test('conserve une opération offline idempotente dans la file', () async {
    final now = DateTime.now().toUtc();
    final operation = OfflineOperationsCompanion.insert(
      operationId: 'operation-1',
      idempotencyKey: 'idem-1',
      ownerUserId: 'user-a',
      entityType: 'dispensations',
      method: 'POST',
      endpoint: '/organizations/org-a/dispensations',
      payloadJson: '{}',
      createdAt: now,
      updatedAt: now,
    );

    await database.enqueueOperation(operation);
    await database.enqueueOperation(operation);

    final pending = await database.pendingOperations(ownerUserId: 'user-a');
    expect(pending, hasLength(1));
    expect(pending.single.idempotencyKey, 'idem-1');
  });
}
