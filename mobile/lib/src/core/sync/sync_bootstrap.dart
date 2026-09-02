import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../database/app_database.dart';
import '../access/session_scope.dart';
import '../data/local_first_repository.dart';
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
    await _primeProjectContext(user, storage);
    _periodicSync?.cancel();
    _periodicSync = Timer.periodic(const Duration(minutes: 2), (_) {
      unawaited(_service?.syncNow());
    });
  }

  static Future<void> _primeProjectContext(
    Map<String, dynamic> user,
    FlutterSecureStorage storage,
  ) async {
    final projectId = SessionScope.projectId(user);
    final organizationId = SessionScope.organizationId(user);
    if (projectId.isEmpty || organizationId.isEmpty) return;
    final repository = LocalFirstRepository(
      database: _database!,
      storage: storage,
    );
    try {
      await repository.document(
        collection: 'project.current',
        endpoint: '/projects/$projectId',
        organizationId: organizationId,
        query: {'project_id': projectId},
      );
      await repository.document(
        collection: 'project.standard-list',
        endpoint: '/projects/$projectId/standard-list',
        organizationId: organizationId,
        query: {'project_id': projectId},
      );
    } catch (_) {
      // La synchronisation de fond ne doit jamais bloquer l'ouverture de l'app.
    }
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
