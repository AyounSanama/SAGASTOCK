import 'dart:convert';

import 'package:drift/drift.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

import '../database/app_database.dart';

class LegacyOutboxMigrator {
  LegacyOutboxMigrator({
    required this.database,
    FlutterSecureStorage? storage,
    Future<String?> Function(String key)? readSecureValue,
    Future<void> Function(String key)? deleteSecureValue,
  }) : _storage = storage ?? const FlutterSecureStorage(),
       _readSecureValue = readSecureValue,
       _deleteSecureValue = deleteSecureValue;

  final AppDatabase database;
  final FlutterSecureStorage _storage;
  final Future<String?> Function(String key)? _readSecureValue;
  final Future<void> Function(String key)? _deleteSecureValue;

  static const _sources = <String, String>{
    'offline_stock_outbox': 'stocks',
    'offline_clinical_outbox_v2': 'clinical',
    'offline_inventory_outbox': 'inventories',
    'offline_order_outbox': 'orders',
    // Ancienne file propre aux réceptions, reprise par la file commune.
    'offline_receipt_outbox': 'receipts',
  };

  Future<int> migrate() async {
    final cachedUser = await _read('cached_user');
    if (cachedUser == null) return 0;
    final user = (jsonDecode(cachedUser) as Map).cast<String, dynamic>();
    final ownerUserId = '${user['id'] ?? ''}';
    if (ownerUserId.isEmpty) return 0;

    var migrated = 0;
    for (final source in _sources.entries) {
      final encoded = await _read(source.key);
      if (encoded == null || encoded.isEmpty) continue;
      final values = (jsonDecode(encoded) as List)
          .map((item) => (item as Map).cast<String, dynamic>())
          .toList();
      var sourceIsValid = true;
      await database.transaction(() async {
        for (final item in values) {
          final operationId = _validUuid('${item['operation_id'] ?? ''}')
              ? '${item['operation_id']}'
              : const Uuid().v4();
          final endpoint = '${item['path'] ?? ''}';
          if (endpoint.isEmpty) {
            sourceIsValid = false;
            continue;
          }
          await database.enqueueOperation(
            OfflineOperationsCompanion.insert(
              operationId: operationId,
              idempotencyKey: operationId,
              ownerUserId: ownerUserId,
              organizationId: Value(_organizationFrom(endpoint)),
              entityType: source.value,
              method: '${item['method'] ?? 'POST'}'.toUpperCase(),
              endpoint: endpoint,
              payloadJson: jsonEncode(item['data'] ?? const {}),
              attemptCount: Value((item['attempts'] as num?)?.toInt() ?? 0),
              lastError: Value(item['last_error']?.toString()),
              createdAt:
                  DateTime.tryParse('${item['queued_at'] ?? ''}')?.toUtc() ??
                  DateTime.now().toUtc(),
              updatedAt: DateTime.now().toUtc(),
            ),
          );
          migrated++;
        }
      });
      if (sourceIsValid) await _delete(source.key);
    }
    return migrated;
  }

  String? _organizationFrom(String endpoint) =>
      RegExp(r'/organizations/([^/]+)').firstMatch(endpoint)?.group(1);

  bool _validUuid(String value) => RegExp(
    r'^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$',
  ).hasMatch(value);

  Future<String?> _read(String key) =>
      _readSecureValue?.call(key) ?? _storage.read(key: key);

  Future<void> _delete(String key) =>
      _deleteSecureValue?.call(key) ?? _storage.delete(key: key);
}
