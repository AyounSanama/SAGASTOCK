import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/data/local_first_repository.dart';
import '../../../core/database/app_database.dart';
import '../../../core/network/api_client.dart';

class StructureService {
  StructureService({
    ApiClient? client,
    FlutterSecureStorage? storage,
    AppDatabase? database,
  }) : _repository = LocalFirstRepository(
         client: client ?? ApiClient(),
         storage: storage ?? const FlutterSecureStorage(),
         database: database,
       );

  final LocalFirstRepository _repository;

  Future<Map<String, dynamic>> list(
    String organizationId, {
    String search = '',
  }) async {
    final data = await _repository.document(
      collection: 'structures',
      organizationId: organizationId,
      endpoint: '/organizations/$organizationId/structures',
      query: {if (search.isNotEmpty) 'search': search},
    );
    if (data.isEmpty) {
      return {
        'facilities': {'data': <dynamic>[]},
        'archived_facilities': <dynamic>[],
        'offline': true,
      };
    }
    return data;
  }

  Future<void> saveFacility({
    required String organizationId,
    String? facilityId,
    required Map<String, dynamic> data,
  }) async {
    final path = facilityId == null
        ? '/organizations/$organizationId/facilities'
        : '/organizations/$organizationId/facilities/$facilityId';
    await _repository.mutate(
      collection: 'structures.facilities',
      organizationId: organizationId,
      endpoint: path,
      method: facilityId == null ? 'POST' : 'PUT',
      remoteId: facilityId,
      payload: data,
    );
  }

  Future<void> archiveFacility(String organizationId, String facilityId) async {
    await _repository.mutateEntityState(
      collection: 'structures.facilities',
      organizationId: organizationId,
      remoteId: facilityId,
      endpoint: '/organizations/$organizationId/facilities/$facilityId',
      deleted: true,
      method: 'DELETE',
    );
  }

  Future<void> restoreFacility(String organizationId, String facilityId) async {
    await _repository.mutateEntityState(
      collection: 'structures.facilities',
      organizationId: organizationId,
      remoteId: facilityId,
      endpoint:
          '/organizations/$organizationId/facilities/archived/$facilityId/restore',
      deleted: false,
    );
  }

  Future<void> saveChild({
    required String organizationId,
    required String facilityId,
    required String kind,
    String? childId,
    required Map<String, dynamic> data,
  }) async {
    final collection = _collection(kind);
    final path =
        '/organizations/$organizationId/facilities/$facilityId/$collection'
        '${childId == null ? '' : '/$childId'}';
    await _repository.mutate(
      collection: 'structures.$collection',
      organizationId: organizationId,
      endpoint: path,
      method: childId == null ? 'POST' : 'PUT',
      remoteId: childId,
      payload: data,
    );
  }

  Future<void> archiveChild({
    required String organizationId,
    required String facilityId,
    required String kind,
    required String childId,
  }) async {
    await _repository.mutateEntityState(
      collection: 'structures.${_collection(kind)}',
      organizationId: organizationId,
      remoteId: childId,
      endpoint:
          '/organizations/$organizationId/facilities/$facilityId/${_collection(kind)}/$childId',
      deleted: true,
      method: 'DELETE',
    );
  }

  Future<void> restoreChild({
    required String organizationId,
    required String facilityId,
    required String kind,
    required String childId,
  }) async {
    final collection = _collection(kind);
    await _repository.mutateEntityState(
      collection: 'structures.$collection',
      organizationId: organizationId,
      remoteId: childId,
      endpoint:
          '/organizations/$organizationId/facilities/$facilityId/$collection/archived/$childId/restore',
      deleted: false,
    );
  }

  String _collection(String kind) => switch (kind) {
    'department' => 'departments',
    'pharmacy' => 'pharmacies',
    'site' => 'sites',
    _ => throw ArgumentError.value(kind),
  };
}
