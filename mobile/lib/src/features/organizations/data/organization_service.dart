import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/network/api_client.dart';

class OrganizationService {
  OrganizationService({ApiClient? client, FlutterSecureStorage? storage})
    : _client = client ?? ApiClient(),
      _storage = storage ?? const FlutterSecureStorage();

  final ApiClient _client;
  final FlutterSecureStorage _storage;

  Future<Options> _authorized() async {
    final token = await _storage.read(key: 'auth_token');
    return Options(headers: {'Authorization': 'Bearer $token'});
  }

  Future<List<Map<String, dynamic>>> list({String search = ''}) async {
    const cacheKey = 'offline_organizations';
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/organizations',
        queryParameters: search.isEmpty ? null : {'search': search},
        options: await _authorized(),
      );
      final values = (response.data?['data'] as List<dynamic>? ?? [])
          .cast<Map<String, dynamic>>();
      if (search.isEmpty) {
        await _storage.write(key: cacheKey, value: jsonEncode(values));
      }
      return values;
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      final cached = await _storage.read(key: cacheKey);
      final values = (cached == null ? <dynamic>[] : jsonDecode(cached) as List)
          .cast<Map<String, dynamic>>();
      final query = search.trim().toLowerCase();
      if (query.isEmpty) return values;
      return values
          .where(
            (item) =>
                '${item['name']}'.toLowerCase().contains(query) ||
                '${item['code']}'.toLowerCase().contains(query),
          )
          .toList();
    }
  }

  Future<void> create({
    required String code,
    required String name,
    String? countryCode,
  }) async {
    await _client.dio.post<void>(
      '/organizations',
      data: {
        'code': code,
        'name': name,
        if (countryCode?.isNotEmpty == true)
          'country_code': countryCode!.toUpperCase(),
        'is_active': true,
      },
      options: await _authorized(),
    );
  }

  Future<List<Map<String, dynamic>>> countries() async {
    final response = await _client.dio.get<Map<String, dynamic>>(
      '/countries',
      options: await _authorized(),
    );
    return (response.data?['countries'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>();
  }

  Future<List<Map<String, dynamic>>> missions(String organizationId) async {
    final response = await _client.dio.get<Map<String, dynamic>>(
      '/organizations/$organizationId/missions',
      options: await _authorized(),
    );
    return (response.data?['data'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>();
  }

  Future<void> createMission({
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
    await _client.dio.post<void>(
      '/organizations/$organizationId/missions',
      data: {
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
      options: await _authorized(),
    );
  }

  Future<void> updateMission({
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
    await _client.dio.put<void>(
      '/organizations/$organizationId/missions/$missionId',
      data: {
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
      options: await _authorized(),
    );
  }

  String? _date(DateTime? value) =>
      value?.toIso8601String().split('T').first;

  Future<List<Map<String, dynamic>>> projects({
    required String organizationId,
    required String missionId,
  }) async {
    final response = await _client.dio.get<Map<String, dynamic>>(
      '/organizations/$organizationId/projects',
      queryParameters: {'mission_id': missionId},
      options: await _authorized(),
    );
    return (response.data?['data'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>();
  }

  Future<void> createProject({
    required String organizationId,
    required String missionId,
    required String code,
    required String name,
    String? description,
  }) async {
    await _client.dio.post<void>(
      '/organizations/$organizationId/projects',
      data: {
        'mission_id': missionId,
        'code': code,
        'name': name,
        if (description?.isNotEmpty == true) 'description': description,
        'is_active': true,
      },
      options: await _authorized(),
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
