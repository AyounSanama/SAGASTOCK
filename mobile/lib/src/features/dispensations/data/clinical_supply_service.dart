import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';
import '../../../core/network/api_client.dart';
import '../../../core/sync/offline_operation_service.dart';
import '../../../core/data/local_first_repository.dart';

class ClinicalSupplyService {
  ClinicalSupplyService({ApiClient? client}) {
    _client = client ?? ApiClient();
    _operations = OfflineOperationService(client: _client);
    _repository = LocalFirstRepository(client: _client, storage: _storage);
  }
  late final ApiClient _client;
  late final OfflineOperationService _operations;
  late final LocalFirstRepository _repository;
  final _storage = const FlutterSecureStorage();
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
    final data = await _repository.document(
      collection: 'clinical.options',
      organizationId: id,
      endpoint: '/organizations/$id/dispensations/options',
    );
    return _optionMap(data);
  }

  Future<bool> createPatient(String id, Map<String, dynamic> data) {
    data['id'] ??= const Uuid().v4();
    data['client_reference'] ??= data['id'];
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
    return _operations.execute(
      method: 'POST',
      endpoint: path,
      payload: Map<String, dynamic>.from(data),
      entityType: 'clinical',
      organizationId: RegExp(
        r'/organizations/([^/]+)',
      ).firstMatch(path)?.group(1),
    );
  }

  Future<int> pendingCount() =>
      _operations.pendingCount(entityType: 'clinical');
  Future<void> sync() => _operations.synchronize();

  Future<List<Map<String, dynamic>>> _list(String path, String key) async {
    return _repository.list(
      collection: key,
      organizationId: RegExp(
        r'/organizations/([^/]+)',
      ).firstMatch(path)?.group(1),
      endpoint: path,
    );
  }

  Map<String, List<Map<String, dynamic>>> _optionMap(Map<String, dynamic> d) =>
      {
        for (final key in ['sites', 'patients', 'prescriptions', 'products'])
          key: (d[key] as List? ?? []).cast<Map<String, dynamic>>(),
      };
}
