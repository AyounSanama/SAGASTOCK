import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';
import '../../../core/network/api_client.dart';

class ClinicalSupplyService {
  ClinicalSupplyService({ApiClient? client}) : _client = client ?? ApiClient();
  final ApiClient _client;
  final _storage = const FlutterSecureStorage();
  static const _outboxKey = 'offline_clinical_outbox_v2';

  Future<Options> _auth() async => Options(
    headers: {
      'Authorization': 'Bearer ${await _storage.read(key: 'auth_token')}',
    },
  );
  Future<List<Map<String, dynamic>>> organizations() =>
      _list('/organizations', 'offline_organizations');
  Future<List<Map<String, dynamic>>> patients(String id) =>
      _list('/organizations/$id/patients', 'offline_patients_$id');
  Future<List<Map<String, dynamic>>> prescriptions(String id) =>
      _list('/organizations/$id/prescriptions', 'offline_prescriptions_$id');
  Future<List<Map<String, dynamic>>> dispensations(String id) async {
    await sync();
    return _list(
      '/organizations/$id/dispensations',
      'offline_dispensations_$id',
    );
  }

  Future<Map<String, List<Map<String, dynamic>>>> options(String id) async {
    final key = 'offline_clinical_options_$id';
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/organizations/$id/dispensations/options',
        options: await _auth(),
      );
      final data = response.data ?? <String, dynamic>{};
      await _storage.write(key: key, value: jsonEncode(data));
      return _optionMap(data);
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      final cached = await _storage.read(key: key);
      if (cached == null) rethrow;
      return _optionMap(jsonDecode(cached) as Map<String, dynamic>);
    }
  }

  Future<bool> createPatient(String id, Map<String, dynamic> data) {
    data['client_reference'] ??= const Uuid().v4();
    return _sendOrQueue('/organizations/$id/patients', data);
  }
  Future<bool> createPrescription(String id, Map<String, dynamic> data) {
    data['client_reference'] ??= const Uuid().v4();
    return _sendOrQueue('/organizations/$id/prescriptions', data);
  }
  Future<bool> validatePrescription(
    String id,
    String prescriptionId,
    Map<String, dynamic> clinicalValidation,
  ) => _sendOrQueue(
    '/organizations/$id/prescriptions/$prescriptionId/validate',
    clinicalValidation,
  );
  Future<bool> returnDispensation(
    String id,
    String dispensationId,
    Map<String, dynamic> data,
  ) => _sendOrQueue(
    '/organizations/$id/dispensations/$dispensationId/return',
    data,
  );

  Future<bool> dispense(String id, Map<String, dynamic> data) async {
    data['offline_uuid'] ??= const Uuid().v4();
    return _sendOrQueue('/organizations/$id/dispensations', data);
  }

  Future<bool> _sendOrQueue(String path, Map<String, dynamic> data) async {
    try {
      await _client.dio.post(path, data: data, options: await _auth());
      return true;
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      final items = await _outbox();
      items.add({
        'operation_id': const Uuid().v4(),
        'path': path,
        'data': Map<String, dynamic>.from(data),
        'queued_at': DateTime.now().toIso8601String(),
        'attempts': 0,
      });
      await _storage.write(key: _outboxKey, value: jsonEncode(items));
      return false;
    }
  }

  Future<int> pendingCount() async => (await _outbox()).length;
  Future<void> sync() async {
    final pending = await _outbox();
    if (pending.isEmpty) return;
    final remaining = <Map<String, dynamic>>[];
    for (final item in pending) {
      try {
        await _client.dio.post(
          item['path'],
          data: item['data'],
          options: await _auth(),
        );
      } on DioException catch (error) {
        // Une erreur réseau ou serveur reste rejouable. Une erreur métier 4xx
        // est conservée avec son diagnostic afin de ne perdre aucune saisie.
        remaining.add({
          ...item,
          'attempts': ((item['attempts'] as num?)?.toInt() ?? 0) + 1,
          'last_error': error.response?.data?.toString() ?? error.message,
        });
      }
    }
    await _storage.write(key: _outboxKey, value: jsonEncode(remaining));
  }

  Future<List<Map<String, dynamic>>> _list(String path, String key) async {
    try {
      final r = await _client.dio.get<Map<String, dynamic>>(
        path,
        options: await _auth(),
      );
      final data = (r.data?['data'] as List? ?? [])
          .cast<Map<String, dynamic>>();
      await _storage.write(key: key, value: jsonEncode(data));
      return data;
    } on DioException catch (e) {
      if (e.response != null) rethrow;
      final cached = await _storage.read(key: key);
      return cached == null
          ? []
          : (jsonDecode(cached) as List).cast<Map<String, dynamic>>();
    }
  }

  Map<String, List<Map<String, dynamic>>> _optionMap(Map<String, dynamic> d) =>
      {
        for (final key in ['sites', 'patients', 'prescriptions', 'products'])
          key: (d[key] as List? ?? []).cast<Map<String, dynamic>>(),
      };
  Future<List<Map<String, dynamic>>> _outbox() async {
    final value = await _storage.read(key: _outboxKey);
    return (value == null ? [] : jsonDecode(value) as List)
        .cast<Map<String, dynamic>>();
  }
}
