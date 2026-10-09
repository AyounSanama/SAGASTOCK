import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/data/local_first_repository.dart';
import '../../../core/network/api_client.dart';

class UserService {
  UserService({ApiClient? client, FlutterSecureStorage? storage})
    : _client = client ?? ApiClient(),
      _storage = storage ?? const FlutterSecureStorage(),
      _repository = LocalFirstRepository(
        client: client ?? ApiClient(),
        storage: storage ?? const FlutterSecureStorage(),
      );

  final ApiClient _client;
  final FlutterSecureStorage _storage;
  final LocalFirstRepository _repository;

  Future<List<Map<String, dynamic>>> users({
    String search = '',
    bool archived = false,
  }) => _repository.list(
    collection: archived ? 'users.archived' : 'users',
    endpoint: archived ? '/users/archived' : '/users',
    query: {if (search.isNotEmpty) 'search': search},
  );

  Future<List<Map<String, dynamic>>> assignableRoles() async {
    final response = await _client.dio.get<Map<String, dynamic>>(
      '/assignable-roles',
      options: await _authorized(),
    );
    return (response.data?['roles'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList(growable: false);
  }

  Future<Map<String, dynamic>> create(Map<String, dynamic> data) async {
    final response = await _client.dio.post<Map<String, dynamic>>(
      '/users',
      data: data,
      options: await _authorized(),
    );
    return response.data ?? const {};
  }

  Future<Map<String, dynamic>> update(
    String userId,
    Map<String, dynamic> data,
  ) async {
    final response = await _client.dio.put<Map<String, dynamic>>(
      '/users/$userId',
      data: data,
      options: await _authorized(),
    );
    return response.data ?? const {};
  }

  Future<void> archive(String userId) async {
    await _client.dio.delete<void>(
      '/users/$userId',
      options: await _authorized(),
    );
  }

  Future<void> restore(String userId) async {
    await _client.dio.post<void>(
      '/users/archived/$userId/restore',
      options: await _authorized(),
    );
  }

  /// Renvoie le mot de passe temporaire généré (à montrer une seule fois).
  Future<String?> resetPassword(String userId) async {
    final response = await _client.dio.post<Map<String, dynamic>>(
      '/users/$userId/reset-password',
      options: await _authorized(),
    );
    return response.data?['temporary_password'] as String?;
  }

  Future<Options> _authorized() async => Options(
    headers: {
      'Authorization': 'Bearer ${await _storage.read(key: 'auth_token')}',
    },
  );
}
