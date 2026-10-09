import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../connectivity/connectivity_service.dart';
import '../database/app_database.dart';
import '../network/api_client.dart';
import 'offline_request_data.dart';
import 'sync_status_service.dart';

typedef OperationSender =
    Future<Map<String, dynamic>?> Function(OfflineOperation operation);

class SyncReport {
  const SyncReport({
    required this.processed,
    required this.succeeded,
    required this.failed,
    required this.conflicts,
    this.authenticationRequired = false,
  });

  const SyncReport.empty()
    : processed = 0,
      succeeded = 0,
      failed = 0,
      conflicts = 0,
      authenticationRequired = false;

  final int processed;
  final int succeeded;
  final int failed;
  final int conflicts;

  /// Jeton expiré ou révoqué : les opérations restent en attente (S-03).
  final bool authenticationRequired;
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

  /// S-03 : passe à `true` quand le serveur refuse le jeton (expiré, révoqué)
  /// ou exige un nouveau mot de passe. L'application redemande la connexion ;
  /// les opérations en attente sont conservées et repartent ensuite.
  static final ValueNotifier<bool> authenticationRequired = ValueNotifier(
    false,
  );

  static bool requiresAuthentication(DioException error) {
    final status = error.response?.statusCode;
    if (status == 401) return true;
    final data = error.response?.data;
    return status == 403 &&
        data is Map &&
        data['code'] == 'password_change_required';
  }

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
    var authenticationRequired = false;
    var networkLost = false;
    try {
      final operations = await database.pendingOperations(
        ownerUserId: ownerUserId,
      );
      for (final operation in operations) {
        if (!await _dependencyIsResolved(operation)) continue;
        processed++;
        await database.markOperationSyncing(operation.operationId);
        try {
          final response = await (_sender ?? _send)(operation);
          await database.completeOperationWithResponse(operation, response);
          succeeded++;
        } on DioException catch (error) {
          if (requiresAuthentication(error)) {
            // L'opération repart telle quelle après reconnexion : aucune
            // tentative comptée, aucune perte.
            await database.failOperation(
              operationId: operation.operationId,
              status: 'pending',
              attemptCount: operation.attemptCount,
              error: 'Reconnexion nécessaire',
            );
            authenticationRequired = true;
            SyncService.authenticationRequired.value = true;
            break;
          }
          final result = await _handleDioFailure(operation, error);
          failed++;
          if (result == 'conflict') conflicts++;
          if (error.response == null) {
            networkLost = true;
            break;
          }
        } catch (error) {
          await _scheduleRetry(operation, error.toString());
          failed++;
        }
      }
      // Passage complet avec le serveur (même si des envois sont refusés) :
      // c'est la « dernière synchronisation réussie » de l'écran et de la
      // supervision.
      // Envoyeur injecté (tests) : ni horodatage ni déclaration au serveur.
      if (_sender == null && !networkLost && !authenticationRequired) {
        await SyncStatusService.recordSuccess(ownerUserId, storage: _storage);
      }
      if (_sender == null && !authenticationRequired) {
        unawaited(
          SyncStatusService(
            database: database,
            ownerUserId: ownerUserId,
            client: _client,
            storage: _storage,
          ).reportQuietly(),
        );
      }
      return SyncReport(
        processed: processed,
        succeeded: succeeded,
        failed: failed,
        conflicts: conflicts,
        authenticationRequired: authenticationRequired,
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

  Future<Map<String, dynamic>?> _send(OfflineOperation operation) async {
    final token = await _storage.read(key: 'auth_token');
    if (token == null || token.isEmpty) {
      throw StateError('Session locale absente pour la synchronisation.');
    }
    final mappings = await database.localToRemoteIds(ownerUserId);
    final endpoint = _resolveString(operation.endpoint, mappings);
    final payload = _resolveReferences(
      (jsonDecode(operation.payloadJson) as Map).cast<String, dynamic>(),
      mappings,
    );
    final response = await _client.dio.request<Map<String, dynamic>>(
      endpoint,
      data: await prepareOfflineRequestData(payload),
      options: Options(
        method: operation.method.toUpperCase(),
        headers: {
          'Authorization': 'Bearer $token',
          'Idempotency-Key': operation.idempotencyKey,
        },
      ),
    );
    return response.data;
  }

  String _resolveString(String value, Map<String, String> mappings) {
    var resolved = value;
    for (final entry in mappings.entries) {
      resolved = resolved.replaceAll(entry.key, entry.value);
    }
    return resolved;
  }

  Map<String, dynamic> _resolveReferences(
    Map<String, dynamic> payload,
    Map<String, String> mappings,
  ) => payload.map(
    (key, value) => MapEntry(key, _resolveValue(value, mappings)),
  );

  dynamic _resolveValue(dynamic value, Map<String, String> mappings) {
    if (value is String) return mappings[value] ?? value;
    if (value is List) {
      return value.map((item) => _resolveValue(item, mappings)).toList();
    }
    if (value is Map) {
      return value.map(
        (key, item) => MapEntry('$key', _resolveValue(item, mappings)),
      );
    }
    return value;
  }

  /// Première erreur de validation, sinon message du serveur (300 caractères).
  static String? serverReason(Object? data) {
    String? reason;
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        reason = first is List && first.isNotEmpty ? '${first.first}' : '$first';
      } else if (data['message'] != null) {
        reason = '${data['message']}';
      }
    }
    if (reason == null || reason.trim().isEmpty) return null;
    reason = reason.trim();
    return reason.length > 300 ? '${reason.substring(0, 297)}…' : reason;
  }

  Future<String> _handleDioFailure(
    OfflineOperation operation,
    DioException error,
  ) async {
    final statusCode = error.response?.statusCode;
    // Motif lisible du serveur (affiché dans Synchronisation), pas la réponse
    // brute.
    final message =
        serverReason(error.response?.data) ??
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
