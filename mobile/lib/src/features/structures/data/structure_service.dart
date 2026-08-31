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
    final pagination = data['facilities'] as Map<String, dynamic>? ?? const {};
    await _repository.cacheList(
      collection: 'structures.facilities',
      organizationId: organizationId,
      values: (pagination['data'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList(growable: false),
    );
    await _repository.cacheList(
      collection: 'structures.facilities.archived',
      organizationId: organizationId,
      values: (data['archived_facilities'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList(growable: false),
    );
    final remoteFacilities = (pagination['data'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList(growable: false);
    final remoteSites = <Map<String, dynamic>>[];
    final remoteArchivedSites = <Map<String, dynamic>>[];
    for (final facility in remoteFacilities) {
      final facilitySummary = {
        'id': facility['id'],
        'name': facility['name'],
        'code': facility['code'],
      };
      for (final raw in facility['sites'] as List<dynamic>? ?? const []) {
        remoteSites.add({
          ...Map<String, dynamic>.from(raw as Map),
          'health_facility_id': facility['id'],
          'health_facility': facilitySummary,
        });
      }
      for (final raw
          in facility['archived_sites'] as List<dynamic>? ?? const []) {
        remoteArchivedSites.add({
          ...Map<String, dynamic>.from(raw as Map),
          'health_facility_id': facility['id'],
          'health_facility': facilitySummary,
        });
      }
    }
    await _repository.cacheList(
      collection: 'structures.sites',
      organizationId: organizationId,
      values: remoteSites,
    );
    await _repository.cacheList(
      collection: 'structures.sites.archived',
      organizationId: organizationId,
      values: remoteArchivedSites,
    );
    final active = await _repository.localList(
      collection: 'structures.facilities',
      organizationId: organizationId,
    );
    final archived = await _repository.localList(
      collection: 'structures.facilities.archived',
      organizationId: organizationId,
    );
    final sites = await _repository.localList(
      collection: 'structures.sites',
      organizationId: organizationId,
    );
    final archivedSites = await _repository.localList(
      collection: 'structures.sites.archived',
      organizationId: organizationId,
    );
    final enrichedFacilities = active
        .map(
          (facility) => {
            ...facility,
            'sites': sites
                .where((site) => site['health_facility_id'] == facility['id'])
                .toList(growable: false),
            'archived_sites': archivedSites
                .where((site) => site['health_facility_id'] == facility['id'])
                .toList(growable: false),
          },
        )
        .toList(growable: false);
    data['facilities'] = {...pagination, 'data': enrichedFacilities};
    data['archived_facilities'] = archived;
    data['sites'] = sites;
    data['archived_sites'] = archivedSites;
    return data;
  }

  Future<bool> saveFacility({
    required String organizationId,
    String? facilityId,
    required Map<String, dynamic> data,
  }) async {
    final path = facilityId == null
        ? '/organizations/$organizationId/facilities'
        : '/organizations/$organizationId/facilities/$facilityId';
    return _repository.mutate(
      collection: 'structures.facilities',
      organizationId: organizationId,
      endpoint: path,
      method: facilityId == null ? 'POST' : 'PUT',
      remoteId: facilityId,
      payload: data,
    );
  }

  Future<bool> archiveFacility(String organizationId, String facilityId) {
    return _repository.mutateEntityState(
      collection: 'structures.facilities',
      organizationId: organizationId,
      remoteId: facilityId,
      endpoint: '/organizations/$organizationId/facilities/$facilityId',
      deleted: true,
      method: 'DELETE',
    );
  }

  Future<bool> restoreFacility(String organizationId, String facilityId) {
    return _repository.mutateEntityState(
      collection: 'structures.facilities',
      organizationId: organizationId,
      remoteId: facilityId,
      endpoint:
          '/organizations/$organizationId/facilities/archived/$facilityId/restore',
      deleted: false,
    );
  }

  Future<bool> saveChild({
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
    return _repository.mutate(
      collection: 'structures.$collection',
      organizationId: organizationId,
      endpoint: path,
      method: childId == null ? 'POST' : 'PUT',
      remoteId: childId,
      payload: {...data, if (kind == 'site') 'health_facility_id': facilityId},
    );
  }

  Future<bool> archiveChild({
    required String organizationId,
    required String facilityId,
    required String kind,
    required String childId,
  }) async {
    return _repository.mutateEntityState(
      collection: 'structures.${_collection(kind)}',
      organizationId: organizationId,
      remoteId: childId,
      endpoint:
          '/organizations/$organizationId/facilities/$facilityId/${_collection(kind)}/$childId',
      deleted: true,
      method: 'DELETE',
    );
  }

  Future<bool> restoreChild({
    required String organizationId,
    required String facilityId,
    required String kind,
    required String childId,
  }) async {
    final collection = _collection(kind);
    return _repository.mutateEntityState(
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
