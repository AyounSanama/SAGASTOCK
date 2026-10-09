import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../database/app_database.dart';
import '../network/api_client.dart';
import 'sync_bootstrap.dart';

/// Envoi refusé par le serveur ou en conflit, conservé sur le téléphone.
class SyncIssue {
  const SyncIssue({
    required this.operationId,
    required this.module,
    required this.label,
    required this.reference,
    required this.reason,
    required this.occurredAt,
    required this.conflict,
    required this.reported,
    required this.payload,
  });

  final String operationId;

  /// Type de la file : receipts, clinical, inventories, orders, stocks.
  final String module;

  /// « Dispensation », « Commande »…
  final String label;
  final String? reference;
  final String? reason;
  final DateTime occurredAt;
  final bool conflict;

  /// Conflit signalé à l'Admin (visible dans la supervision).
  final bool reported;
  final Map<String, dynamic> payload;

  String get title => reference == null ? label : '$label $reference';
}

/// État de synchronisation du téléphone : dernière réussite, envois en
/// attente, refusés et conflits. Rien n'est jamais effacé : « Abandonner » et
/// « J'ai compris » changent seulement le statut de l'opération.
class SyncStatusService {
  SyncStatusService({
    required this.database,
    required this.ownerUserId,
    ApiClient? client,
    FlutterSecureStorage? storage,
  }) : _client = client ?? ApiClient(),
       _storage = storage ?? const FlutterSecureStorage();

  final AppDatabase database;
  final String ownerUserId;
  final ApiClient _client;
  final FlutterSecureStorage _storage;

  /// Modules suivis, dans l'ordre de l'écran.
  static const modules = <String, String>{
    'receipts': 'Réceptions',
    'clinical': 'Dispensations',
    'inventories': 'Inventaires',
    'orders': 'Commandes',
    'stocks': 'Mouvements de stock',
  };

  static String _successKey(String ownerUserId) =>
      'sync_last_success_$ownerUserId';

  static Future<void> recordSuccess(
    String ownerUserId, {
    FlutterSecureStorage storage = const FlutterSecureStorage(),
  }) async {
    try {
      await storage.write(
        key: _successKey(ownerUserId),
        value: DateTime.now().toUtc().toIso8601String(),
      );
    } catch (_) {
      // L'horodatage ne doit jamais interrompre la synchronisation.
    }
  }

  Future<DateTime?> lastSuccess() async =>
      DateTime.tryParse(await _storage.read(key: _successKey(ownerUserId)) ?? '')
          ?.toLocal();

  Future<Map<String, int>> pending() async {
    final counts = await database.pendingCountsByEntity(ownerUserId);
    return {for (final module in modules.keys) module: counts[module] ?? 0};
  }

  Future<List<SyncIssue>> issues() async {
    final operations = await database.issueOperations(ownerUserId);
    return [
      for (final operation in operations)
        if (modules.containsKey(operation.entityType)) _issue(operation),
    ];
  }

  /// Remet l'envoi dans la file puis relance la synchronisation.
  Future<void> retry(String operationId) async {
    await database.setOperationStatus(operationId, 'pending', retry: true);
    await SyncBootstrap.syncNow();
  }

  Future<void> abandon(String operationId) =>
      _setAndReport(operationId, 'abandoned');

  Future<void> reportToAdmin(String operationId) =>
      _setAndReport(operationId, 'conflict_reported');

  Future<void> acknowledge(String operationId) =>
      _setAndReport(operationId, 'archived');

  Future<void> _setAndReport(String operationId, String status) async {
    await database.setOperationStatus(operationId, status);
    await reportQuietly();
  }

  /// Déclare l'état du téléphone au serveur (supervision). Sans réseau ou en
  /// cas de refus, rien n'est perdu : la prochaine synchronisation le refera.
  Future<void> reportQuietly() async {
    try {
      final token = await _storage.read(key: 'auth_token');
      final deviceId = await _storage.read(key: 'device_id');
      if (token == null || token.isEmpty || deviceId == null) return;
      final last = await lastSuccess();
      final issueList = await issues();
      await _client.dio.post<void>(
        '/sync/report',
        data: {
          'device_id': deviceId,
          'last_success_at': last?.toUtc().toIso8601String(),
          'pending': await pending(),
          'issues': [
            for (final issue in issueList.take(100))
              {
                'id': issue.operationId,
                'kind': issue.conflict ? 'conflict' : 'refused',
                'module': issue.module,
                'reference': issue.reference,
                'reason': issue.reason,
                'occurred_at': issue.occurredAt.toUtc().toIso8601String(),
                'reported': issue.reported,
              },
          ],
        },
        options: Options(headers: {'Authorization': 'Bearer $token'}),
      );
    } catch (_) {
      // Déclaration de supervision : jamais bloquante.
    }
  }

  SyncIssue _issue(OfflineOperation operation) {
    final payload = _payload(operation.payloadJson);
    return SyncIssue(
      operationId: operation.operationId,
      module: operation.entityType,
      label: _label(operation),
      reference: _reference(payload),
      reason: operation.lastError,
      occurredAt: operation.updatedAt.toLocal(),
      conflict: operation.status != 'failed',
      reported: operation.status == 'conflict_reported',
      payload: payload,
    );
  }

  static Map<String, dynamic> _payload(String json) {
    try {
      return (jsonDecode(json) as Map).cast<String, dynamic>();
    } catch (_) {
      return const {};
    }
  }

  /// Référence ou code de la saisie, jamais un nom de patient.
  static String? _reference(Map<String, dynamic> payload) {
    for (final key in const ['reference', 'code', 'batch_number']) {
      final value = '${payload[key] ?? ''}'.trim();
      if (value.isNotEmpty) return value;
    }
    return null;
  }

  static String _label(OfflineOperation operation) {
    final endpoint = operation.endpoint;
    return switch (operation.entityType) {
      'receipts' => 'Réception',
      'clinical' when endpoint.contains('/dispensations') => 'Dispensation',
      'clinical' when endpoint.contains('/prescriptions') => 'Ordonnance',
      'clinical' when endpoint.contains('/patients') => 'Dossier patient',
      'clinical' => 'Saisie clinique',
      'inventories' when endpoint.endsWith('/count') => 'Comptage d’inventaire',
      'inventories' when endpoint.endsWith('/submit') => 'Soumission d’inventaire',
      'inventories' when endpoint.endsWith('/start') => 'Démarrage d’inventaire',
      'inventories' => 'Inventaire',
      'orders' when endpoint.endsWith('/submit') => 'Soumission de commande',
      'orders' => 'Commande',
      'stocks' => 'Mouvement de stock',
      _ => 'Opération',
    };
  }
}
