import 'dart:convert';

import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/database/app_database.dart';
import 'package:sagastock_mobile/src/core/sync/legacy_outbox_migrator.dart';

void main() {
  test('migre sans doublon une ancienne outbox vers Drift', () async {
    final database = AppDatabase.forTesting(NativeDatabase.memory());
    addTearDown(database.close);
    final values = <String, String>{
      'cached_user': jsonEncode({'id': 'user-a'}),
      'offline_inventory_outbox': jsonEncode([
        {
          'operation_id': '3d6f0a66-7a62-4cb1-999c-81435c806997',
          'method': 'post',
          'path': '/organizations/org-a/inventories',
          'data': {'reference': 'INV-1'},
          'attempts': 2,
          'queued_at': '2026-08-20T08:00:00Z',
        },
      ]),
    };
    final migrator = LegacyOutboxMigrator(
      database: database,
      readSecureValue: (key) async => values[key],
      deleteSecureValue: (key) async => values.remove(key),
    );

    expect(await migrator.migrate(), 1);
    expect(await migrator.migrate(), 0);
    final pending = await database.pendingOperations(ownerUserId: 'user-a');
    expect(pending, hasLength(1));
    expect(pending.single.organizationId, 'org-a');
    expect(pending.single.attemptCount, 2);
    expect(values.containsKey('offline_inventory_outbox'), isFalse);
  });
}
