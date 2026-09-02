import 'package:dio/dio.dart';
import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/network/api_client.dart';
import '../../configuration/data/effective_configuration_service.dart';

class DashboardService {
  DashboardService({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;
  final _storage = const FlutterSecureStorage();
  static const _cacheKey = 'offline_dashboard';

  Future<String> _scopedCacheKey() async {
    final raw = await _storage.read(key: 'cached_user');
    if (raw == null) return _cacheKey;
    try {
      final user = jsonDecode(raw) as Map<String, dynamic>;
      final id = '${user['id'] ?? ''}';
      return id.isEmpty ? _cacheKey : '${_cacheKey}_$id';
    } catch (_) {
      return _cacheKey;
    }
  }

  Future<Map<String, dynamic>> load() async {
    final token = await _storage.read(key: 'auth_token');
    final cacheKey = await _scopedCacheKey();
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/dashboard',
        options: Options(headers: {'Authorization': 'Bearer $token'}),
      );
      final data = response.data ?? <String, dynamic>{};
      await _storage.write(key: cacheKey, value: jsonEncode(data));
      try {
        await EffectiveConfigurationService(
          client: _client,
          storage: _storage,
        ).refresh();
      } catch (_) {
        // Le Dashboard reste accessible avec la dernière configuration valide.
      }
      data['offline'] = false;
      return data;
    } on DioException catch (error) {
      if (error.response != null && error.response?.statusCode != 401) rethrow;
      final cached = await _storage.read(key: cacheKey);
      final data = cached == null
          ? <String, dynamic>{'stats': <String, dynamic>{}}
          : jsonDecode(cached) as Map<String, dynamic>;
      data['offline'] = true;
      return data;
    }
  }
}
