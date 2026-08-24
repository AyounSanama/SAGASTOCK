import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../connectivity/connectivity_service.dart';
import '../database/app_database.dart';
import '../network/api_client.dart';

typedef OperationSender = Future<void> Function(OfflineOperation operation);

class SyncReport {
  const SyncReport({
    required this.processed,
    required this.succeeded,
    required this.failed,
    required this.conflicts,
  });

  const SyncReport.empty()
    : processed = 0,
      succeeded = 0,
      failed = 0,
      conflicts = 0;

  final int processed;
  final int succeeded;
  final int failed;
  final int conflicts;
}

class SyncService {
  SyncService({
    required this.database,
    required this.ownerUserId,
    ConnectivityService? connectivity,
    ApiClient? client,
    FlutterSecureStorage? storage,
    OperationSender? sender,
  }) : _connectivity = connectivity ?? ConnectivityService(),
       _client = client ?? ApiClient(),
       _storage = storage ?? const FlutterSecureStorage(),
       _sender = sender;

  final AppDatabase database;
  final String ownerUserId;
  final ConnectivityService _connectivity;
  final ApiClient _client;
  final FlutterSecureStorage _storage;
  final OperationSender? _sender;

  StreamSubscription<bool>? _networkSubscription;
  bool _isSynchronizing = false;

  Future<void> start() async {
    await _networkSubscription?.cancel();
    _networkSubscription = _connectivity.networkChanges.listen((online) {
      if (online) unawaited(syncNow());
    });
    if (await _connectivity.hasNetwork) unawaited(syncNow());
  }

  Future<void> stop() async {
    await _networkSubscription?.cancel();
    _networkSubscription = null;
  }

  Future<SyncReport> syncNow() async {
    if (_isSynchronizing || !await _connectivity.hasNetwork) {
      return const SyncReport.empty();
    }
    _isSynchronizing = true;
    var processed = 0;
    var succeeded = 0;
    var failed = 0;
    var conflicts = 0;
    try {
      final operations = await database.pendingOperations(
        ownerUserId: ownerUserId,
      );
      for (final operation in operations) {
        if (!await _dependencyIsResolved(operation)) continue;
        processed++;
        await database.markOperationSyncing(operation.operationId);
        try {
          await (_sender ?? _send)(operation);
          await database.updateEntitySyncStateForOperation(operation, 'synced');
          await database.completeOperation(operation.operationId);
          succeeded++;
        } on DioException catch (error) {
          final result = await _handleDioFailure(operation, error);
          failed++;
          if (result == 'conflict') conflicts++;
          if (error.response == null) break;
        } catch (error) {
          await _scheduleRetry(operation, error.toString());
          failed++;
        }
      }
      return SyncReport(
        processed: processed,
        succeeded: succeeded,
        failed: failed,
        conflicts: conflicts,
      );
    } finally {
      _isSynchronizing = false;
    }
  }

  Future<bool> _dependencyIsResolved(OfflineOperation operation) async {
    final dependencyId = operation.dependencyOperationId;
    if (dependencyId == null) return true;
    final dependency = await database.operationById(dependencyId);
    return dependency == null;
  }

  Future<void> _send(OfflineOperation operation) async {
    final token = await _storage.read(key: 'auth_token');
    if (token == null || token.isEmpty) {
      throw StateError('Session locale absente pour la synchronisation.');
    }
    await _client.dio.request<void>(
      operation.endpoint,
      data: jsonDecode(operation.payloadJson),
      options: Options(
        method: operation.method.toUpperCase(),
        headers: {
          'Authorization': 'Bearer $token',
          'Idempotency-Key': operation.idempotencyKey,
        },
      ),
    );
  }

  Future<String> _handleDioFailure(
    OfflineOperation operation,
    DioException error,
  ) async {
    final statusCode = error.response?.statusCode;
    final message =
        error.response?.data?.toString() ??
        error.message ??
        'Erreur de synchronisation inconnue';
    if (statusCode == 409) {
      await database.failOperation(
        operationId: operation.operationId,
        status: 'conflict',
        attemptCount: operation.attemptCount + 1,
        error: message,
      );
      await database.updateEntitySyncStateForOperation(operation, 'conflict');
      return 'conflict';
    }
    if (statusCode != null &&
        statusCode >= 400 &&
        statusCode < 500 &&
        statusCode != 408 &&
        statusCode != 429) {
      await database.failOperation(
        operationId: operation.operationId,
        status: 'failed',
        attemptCount: operation.attemptCount + 1,
        error: message,
      );
      await database.updateEntitySyncStateForOperation(operation, 'failed');
      return 'failed';
    }
    await _scheduleRetry(operation, message);
    return 'retry';
  }

  Future<void> _scheduleRetry(
    OfflineOperation operation,
    String message,
  ) async {
    final attempts = operation.attemptCount + 1;
    final exhausted = attempts >= operation.maxAttempts;
    final delaySeconds = min(300, pow(2, min(attempts, 8)).toInt());
    await database.failOperation(
      operationId: operation.operationId,
      status: exhausted ? 'failed' : 'retry',
      attemptCount: attempts,
      error: message,
      nextAttemptAt: exhausted
          ? null
          : DateTime.now().toUtc().add(Duration(seconds: delaySeconds)),
    );
    await database.updateEntitySyncStateForOperation(
      operation,
      exhausted ? 'failed' : 'pending',
    );
  }
}
