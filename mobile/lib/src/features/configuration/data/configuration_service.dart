import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/network/api_client.dart';

class ConfigurationService {
  ConfigurationService({ApiClient? client, FlutterSecureStorage? storage})
    : _client = client ?? ApiClient(),
      _storage = storage ?? const FlutterSecureStorage();

  final ApiClient _client;
  final FlutterSecureStorage _storage;

  Future<Options> _authorized() async {
    final token = await _storage.read(key: 'auth_token');
    return Options(headers: {'Authorization': 'Bearer $token'});
  }

  Future<Map<String, dynamic>> load() async {
    final response = await _client.dio.get<Map<String, dynamic>>(
      '/configuration/workflows',
      options: await _authorized(),
    );
    return response.data ?? const <String, dynamic>{};
  }

  Future<Map<String, dynamic>> start(String flowType) async {
    final response = await _client.dio.post<Map<String, dynamic>>(
      '/configuration/workflows',
      data: {'flow_type': flowType},
      options: await _authorized(),
    );
    return response.data ?? const <String, dynamic>{};
  }
}
