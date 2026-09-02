import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/data/local_first_repository.dart';
import '../../../core/database/app_database.dart';
import '../../../core/network/api_client.dart';

/// Encode les listes multipart selon la convention comprise par PHP/Laravel.
FormData buildOrganizationCreationFormData(
  Map<String, dynamic> payload, {
  MultipartFile? logo,
}) {
  final data = FormData();
  for (final entry in payload.entries) {
    final value = entry.value;
    if (value == null) continue;
    if (value is Iterable) {
      for (final item in value) {
        data.fields.add(MapEntry('${entry.key}[]', '$item'));
      }
    } else {
      data.fields.add(MapEntry(entry.key, '$value'));
    }
  }
  if (logo != null) data.files.add(MapEntry('logo', logo));
  return data;
}

class OrganizationService {
  OrganizationService({
    ApiClient? client,
    FlutterSecureStorage? storage,
    AppDatabase? database,
  }) {
    _client = client ?? ApiClient();
    _storage = storage ?? const FlutterSecureStorage();
    _repository = LocalFirstRepository(
      client: _client,
      storage: _storage,
      database: database,
    );
  }

  late final ApiClient _client;
  late final FlutterSecureStorage _storage;
  late final LocalFirstRepository _repository;

  Future<Options> _authorized() async {
    final token = await _storage.read(key: 'auth_token');
    return Options(headers: {'Authorization': 'Bearer $token'});
  }

  Future<List<Map<String, dynamic>>> list({String search = ''}) async {
    return _repository.list(
      collection: 'organizations',
      endpoint: '/organizations',
      query: {if (search.isNotEmpty) 'search': search},
    );
  }

  Future<Map<String, dynamic>> create(
    Map<String, dynamic> payload, {
    XFile? logo,
  }) async {
    final data = buildOrganizationCreationFormData(
      payload,
      logo: logo == null
          ? null
          : MultipartFile.fromBytes(
              await logo.readAsBytes(),
              filename: logo.name,
            ),
    );
    final response = await _client.dio.post<Map<String, dynamic>>(
      '/organizations',
      data: data,
      options: await _authorized(),
    );
    return response.data ?? const {};
  }

  Future<List<Map<String, dynamic>>> countries() async {
    return _repository.list(
      collection: 'countries',
      endpoint: '/countries',
      responseKey: 'countries',
    );
  }

  Future<Map<String, dynamic>> project({
    required String projectId,
    required String organizationId,
  }) => _repository.document(
    collection: 'project.current',
    endpoint: '/projects/$projectId',
    organizationId: organizationId,
    query: {'project_id': projectId},
  );

  Future<List<Map<String, dynamic>>> organizationCountries(
    String organizationId,
  ) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/organizations/$organizationId',
        options: await _authorized(),
      );
      final organization = response.data?['organization'] as Map?;
      return (organization?['countries'] as List? ?? const [])
          .whereType<Map>()
          .map((country) => Map<String, dynamic>.from(country))
          .toList(growable: false);
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      final cached = await _repository.list(
        collection: 'countries',
        endpoint: '/countries',
        responseKey: 'countries',
      );
      final userJson = await _storage.read(key: 'cached_user');
      if (userJson == null) return const [];
      // The cached login payload remains the authority for the offline scope.
      final user = jsonDecode(userJson) as Map<String, dynamic>;
      final allowed =
          ((user['organization'] as Map?)?['countries'] as List? ?? const [])
              .whereType<Map>()
              .map((country) => '${country['id']}')
              .toSet();
      return cached
          .where((country) => allowed.contains('${country['id']}'))
          .toList();
    }
  }

  Future<List<Map<String, dynamic>>> missions(
    String organizationId, {
    String search = '',
    String status = '',
    String countryId = '',
    bool archived = false,
  }) async {
    final values = await _repository.list(
      collection: archived ? 'missions_archived' : 'missions',
      organizationId: organizationId,
      endpoint: archived
          ? '/organizations/$organizationId/missions-archived'
          : '/organizations/$organizationId/missions',
    );
    final normalized = search.trim().toLowerCase();
    return values
        .where((mission) {
          final matchesSearch =
              normalized.isEmpty ||
              '${mission['name'] ?? ''}'.toLowerCase().contains(normalized) ||
              '${mission['code'] ?? ''}'.toLowerCase().contains(normalized);
          final matchesStatus =
              archived ||
              status.isEmpty ||
              (status == 'active' && mission['is_active'] == true) ||
              (status == 'inactive' && mission['is_active'] != true);
          final matchesCountry =
              archived ||
              countryId.isEmpty ||
              '${mission['country_id'] ?? (mission['country'] as Map?)?['id'] ?? ''}' ==
                  countryId;
          return matchesSearch && matchesStatus && matchesCountry;
        })
        .toList(growable: false);
  }

  Future<bool> createMission({
    required String organizationId,
    required String countryId,
    required String code,
    required String name,
    DateTime? startsOn,
    DateTime? endsOn,
    String? address,
    String? managerName,
    String? phone,
    String? email,
    String? description,
    bool isActive = true,
  }) async {
    return _repository.mutate(
      collection: 'missions',
      organizationId: organizationId,
      endpoint: '/organizations/$organizationId/missions',
      method: 'POST',
      payload: {
        'country_id': countryId,
        'code': code,
        'name': name,
        'starts_on': _date(startsOn),
        'ends_on': _date(endsOn),
        'address': address,
        'manager_name': managerName,
        'phone': phone,
        'email': email,
        'description': description,
        'is_active': isActive,
      },
    );
  }

  Future<bool> updateMission({
    required String organizationId,
    required String missionId,
    required String countryId,
    required String code,
    required String name,
    DateTime? startsOn,
    DateTime? endsOn,
    String? address,
    String? managerName,
    String? phone,
    String? email,
    String? description,
    required bool isActive,
  }) async {
    return _repository.mutate(
      collection: 'missions',
      organizationId: organizationId,
      endpoint: '/organizations/$organizationId/missions/$missionId',
      method: 'PUT',
      remoteId: missionId,
      payload: {
        'country_id': countryId,
        'code': code,
        'name': name,
        'starts_on': _date(startsOn),
        'ends_on': _date(endsOn),
        'address': address,
        'manager_name': managerName,
        'phone': phone,
        'email': email,
        'description': description,
        'is_active': isActive,
      },
    );
  }

  Future<bool> archiveMission({
    required String organizationId,
    required String missionId,
  }) => _repository.mutateEntityState(
    collection: 'missions',
    organizationId: organizationId,
    remoteId: missionId,
    endpoint: '/organizations/$organizationId/missions/$missionId',
    deleted: true,
    method: 'DELETE',
  );

  Future<bool> restoreMission({
    required String organizationId,
    required String missionId,
  }) => _repository.mutateEntityState(
    collection: 'missions',
    organizationId: organizationId,
    remoteId: missionId,
    endpoint:
        '/organizations/$organizationId/missions/archived/$missionId/restore',
    deleted: false,
  );

  String? _date(DateTime? value) => value?.toIso8601String().split('T').first;

  Future<List<Map<String, dynamic>>> projects({
    required String organizationId,
    String? missionId,
    String search = '',
    String status = '',
    bool archived = false,
  }) async {
    return _repository.list(
      collection: archived ? 'projects.archived' : 'projects',
      organizationId: organizationId,
      endpoint:
          '/organizations/$organizationId/projects${archived ? '/archived' : ''}',
      query: {
        'mission_id': ?missionId,
        if (search.isNotEmpty) 'search': search,
        if (!archived && status.isNotEmpty) 'status': status,
      },
    );
  }

  Future<Map<String, dynamic>> projectSetup(String organizationId) =>
      _repository.document(
        collection: 'projects.setup',
        organizationId: organizationId,
        endpoint: '/organizations/$organizationId/projects-setup',
      );

  Future<Map<String, dynamic>> createDonor({
    required String organizationId,
    required String code,
    required String name,
  }) async {
    final response = await _client.dio.post<Map<String, dynamic>>(
      '/organizations/$organizationId/donors',
      data: {'code': code, 'name': name, 'is_active': true},
      options: await _authorized(),
    );
    return Map<String, dynamic>.from(response.data?['donor'] as Map);
  }

  Future<Map<String, dynamic>> createProgram({
    required String organizationId,
    required String code,
    required String name,
  }) async {
    final response = await _client.dio.post<Map<String, dynamic>>(
      '/organizations/$organizationId/programs',
      data: {'code': code, 'name': name, 'is_active': true},
      options: await _authorized(),
    );
    return Map<String, dynamic>.from(response.data?['program'] as Map);
  }

  Future<void> updateDonor({
    required String organizationId,
    required String donorId,
    required String code,
    required String name,
  }) async {
    await _client.dio.put<void>(
      '/organizations/$organizationId/donors/$donorId',
      data: {'code': code, 'name': name, 'is_active': true},
      options: await _authorized(),
    );
  }

  Future<void> updateProgram({
    required String organizationId,
    required String programId,
    required String code,
    required String name,
  }) async {
    await _client.dio.put<void>(
      '/organizations/$organizationId/programs/$programId',
      data: {'code': code, 'name': name, 'is_active': true},
      options: await _authorized(),
    );
  }

  Future<void> archiveFundingReference({
    required String organizationId,
    required String referenceId,
    required bool donor,
  }) async {
    await _client.dio.delete<void>(
      '/organizations/$organizationId/${donor ? 'donors' : 'programs'}/$referenceId',
      options: await _authorized(),
    );
  }

  Future<void> restoreFundingReference({
    required String organizationId,
    required String referenceId,
    required bool donor,
  }) async {
    await _client.dio.post<void>(
      '/organizations/$organizationId/${donor ? 'donors' : 'programs'}/archived/$referenceId/restore',
      options: await _authorized(),
    );
  }

  Future<void> createConfiguredProject({
    required String organizationId,
    required String missionId,
    required String code,
    required String name,
    String? description,
    DateTime? startsOn,
    DateTime? endsOn,
    required List<String> donorIds,
    required List<String> programIds,
    required String adminFirstName,
    required String adminLastName,
    required String adminEmail,
    String? adminPhone,
    String? adminUsername,
    required String adminPassword,
    int? orderPeriodMonths,
    int? deliveryLeadTimeMonths,
    int? safetyStockMonths,
  }) async {
    // Le mot de passe ne doit jamais être persisté dans l'outbox locale.
    // Cette opération atomique est donc envoyée uniquement en ligne.
    await _client.dio.post<void>(
      '/organizations/$organizationId/projects',
      options: await _authorized(),
      data: {
        'mission_id': missionId,
        'code': code,
        'name': name,
        if (description?.isNotEmpty == true) 'description': description,
        if (startsOn != null) 'starts_on': _date(startsOn),
        if (endsOn != null) 'ends_on': _date(endsOn),
        'is_active': true,
        'donor_ids': donorIds,
        'program_ids': programIds,
        'order_period_months': ?orderPeriodMonths,
        'delivery_lead_time_months': ?deliveryLeadTimeMonths,
        'safety_stock_months': ?safetyStockMonths,
        'admin': {
          'first_name': adminFirstName,
          'last_name': adminLastName,
          'email': adminEmail,
          if (adminPhone?.isNotEmpty == true) 'phone': adminPhone,
          if (adminUsername?.isNotEmpty == true) 'username': adminUsername,
          'password': adminPassword,
          'password_confirmation': adminPassword,
        },
      },
    );
  }

  Future<bool> createProject({
    required String organizationId,
    required String missionId,
    required String code,
    required String name,
    String? description,
    DateTime? startsOn,
    DateTime? endsOn,
    int? orderPeriodMonths,
    int? deliveryLeadTimeMonths,
    int? safetyStockMonths,
    bool isActive = true,
  }) async {
    return _repository.mutate(
      collection: 'projects',
      organizationId: organizationId,
      endpoint: '/organizations/$organizationId/projects',
      method: 'POST',
      payload: {
        'mission_id': missionId,
        'code': code,
        'name': name,
        if (description?.isNotEmpty == true) 'description': description,
        if (startsOn != null) 'starts_on': _date(startsOn),
        if (endsOn != null) 'ends_on': _date(endsOn),
        'order_period_months': orderPeriodMonths,
        'delivery_lead_time_months': deliveryLeadTimeMonths,
        'safety_stock_months': safetyStockMonths,
        'is_active': isActive,
      },
    );
  }

  Future<bool> updateProject({
    required String organizationId,
    required String projectId,
    required String missionId,
    required String code,
    required String name,
    String? description,
    DateTime? startsOn,
    DateTime? endsOn,
    int? orderPeriodMonths,
    int? deliveryLeadTimeMonths,
    int? safetyStockMonths,
    List<String> donorIds = const [],
    List<String> programIds = const [],
    List<Map<String, dynamic>> donorRecords = const [],
    List<Map<String, dynamic>> programRecords = const [],
    bool isActive = true,
  }) {
    final payload = <String, dynamic>{
      'mission_id': missionId,
      'code': code,
      'name': name,
      'description': description ?? '',
      'starts_on': _date(startsOn),
      'ends_on': _date(endsOn),
      'order_period_months': orderPeriodMonths,
      'delivery_lead_time_months': deliveryLeadTimeMonths,
      'safety_stock_months': safetyStockMonths,
      'donor_ids': donorIds,
      'program_ids': programIds,
      'is_active': isActive,
    };
    return _repository.mutate(
      collection: 'projects',
      organizationId: organizationId,
      endpoint: '/organizations/$organizationId/projects/$projectId',
      method: 'PUT',
      remoteId: projectId,
      payload: payload,
      optimisticPayload: {
        ...payload,
        'donors': donorRecords,
        'programs': programRecords,
      },
    );
  }

  Future<bool> archiveProject({
    required String organizationId,
    required String projectId,
  }) => _repository.mutateEntityState(
    collection: 'projects',
    organizationId: organizationId,
    remoteId: projectId,
    endpoint: '/organizations/$organizationId/projects/$projectId',
    deleted: true,
    method: 'DELETE',
  );

  Future<bool> restoreProject({
    required String organizationId,
    required String projectId,
  }) => _repository.mutateEntityState(
    collection: 'projects',
    organizationId: organizationId,
    remoteId: projectId,
    endpoint:
        '/organizations/$organizationId/projects/archived/$projectId/restore',
    deleted: false,
  );

  Future<Map<String, dynamic>> funding({
    required String organizationId,
    required String projectId,
  }) async {
    final response = await _client.dio.get<Map<String, dynamic>>(
      '/organizations/$organizationId/projects/$projectId/funding',
      options: await _authorized(),
    );
    return response.data ?? {};
  }

  Future<void> createAndAttachDonor({
    required String organizationId,
    required String projectId,
    required String code,
    required String name,
  }) async {
    final created = await _client.dio.post<Map<String, dynamic>>(
      '/organizations/$organizationId/donors',
      data: {'code': code, 'name': name, 'is_active': true},
      options: await _authorized(),
    );
    final donor = created.data?['donor'] as Map<String, dynamic>;
    await _client.dio.post<void>(
      '/organizations/$organizationId/projects/$projectId/donors',
      data: {'donor_id': donor['id']},
      options: await _authorized(),
    );
  }

  Future<void> createAndAttachProgram({
    required String organizationId,
    required String projectId,
    required String code,
    required String name,
  }) async {
    final created = await _client.dio.post<Map<String, dynamic>>(
      '/organizations/$organizationId/programs',
      data: {'code': code, 'name': name, 'is_active': true},
      options: await _authorized(),
    );
    final program = created.data?['program'] as Map<String, dynamic>;
    await _client.dio.post<void>(
      '/organizations/$organizationId/projects/$projectId/programs',
      data: {'program_id': program['id']},
      options: await _authorized(),
    );
  }
}
