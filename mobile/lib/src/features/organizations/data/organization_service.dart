import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/data/local_first_repository.dart';
import '../../../core/database/app_database.dart';
import '../../../core/network/api_client.dart';

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
    final data = FormData.fromMap({
      ...payload,
      if (logo != null)
        'logo': MultipartFile.fromBytes(
          await logo.readAsBytes(),
          filename: logo.name,
        ),
    });
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
  }) async {
    return _repository.list(
      collection: 'projects',
      organizationId: organizationId,
      endpoint: '/organizations/$organizationId/projects',
      query: {'mission_id': ?missionId},
    );
  }

  Future<void> createProject({
    required String organizationId,
    required String missionId,
    required String code,
    required String name,
    String? description,
  }) async {
    await _repository.mutate(
      collection: 'projects',
      organizationId: organizationId,
      endpoint: '/organizations/$organizationId/projects',
      method: 'POST',
      payload: {
        'mission_id': missionId,
        'code': code,
        'name': name,
        if (description?.isNotEmpty == true) 'description': description,
        'is_active': true,
      },
    );
  }

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
