import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../database/app_database.dart';
import 'sync_service.dart';
import 'legacy_outbox_migrator.dart';

abstract final class SyncBootstrap {
  static AppDatabase? _database;
  static SyncService? _service;
  static Timer? _periodicSync;

  static Future<void> startForCachedSession() async {
    if (kIsWeb) return;
    const storage = FlutterSecureStorage();
    final cached = await storage.read(key: 'cached_user');
    if (cached == null) return;
    final user = (jsonDecode(cached) as Map).cast<String, dynamic>();
    final ownerUserId = '${user['id'] ?? ''}';
    if (ownerUserId.isEmpty) return;

    _database ??= AppDatabase.shared;
    await LegacyOutboxMigrator(
      database: _database!,
      storage: storage,
    ).migrate();
    await _service?.stop();
    _service = SyncService(database: _database!, ownerUserId: ownerUserId);
    await _service!.start();
    _periodicSync?.cancel();
    _periodicSync = Timer.periodic(const Duration(minutes: 2), (_) {
      unawaited(_service?.syncNow());
    });
  }

  static Future<SyncReport> syncNow() async =>
      await _service?.syncNow() ?? const SyncReport.empty();

  static Future<void> stop() async {
    await _service?.stop();
    _periodicSync?.cancel();
    _periodicSync = null;
    _service = null;
  }
}
