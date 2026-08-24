import 'dart:convert';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/network/api_client.dart';

class EffectiveConfigurationService {
  EffectiveConfigurationService({ApiClient? client, FlutterSecureStorage? storage})
    : _client = client ?? ApiClient(),
      _storage = storage ?? const FlutterSecureStorage();

  final ApiClient _client;
  final FlutterSecureStorage _storage;
  static const _cachePrefix = 'effective_configuration_';

  Future<Map<String, dynamic>?> refresh({bool allowCached = true}) async {
    final token = await _storage.read(key: 'auth_token');
    final userRaw = await _storage.read(key: 'cached_user');
    if (token == null || userRaw == null) return null;
    final user = jsonDecode(userRaw) as Map<String, dynamic>;
    final organizationId = user['organization_id']?.toString();
    if (organizationId == null || organizationId.isEmpty) return null;
    final key = '$_cachePrefix$organizationId';

    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/configuration/effective',
        options: Options(headers: {'Authorization': 'Bearer $token'}),
      );
      final payload = response.data ?? <String, dynamic>{};
      _verify(payload);
      await _storage.write(key: key, value: jsonEncode(payload));
      await _acknowledge(payload, token);
      payload['offline'] = false;
      return payload;
    } on DioException {
      if (!allowCached) rethrow;
      final cached = await _storage.read(key: key);
      if (cached == null) rethrow;
      final payload = jsonDecode(cached) as Map<String, dynamic>;
      _verify(payload);
      payload['offline'] = true;
      return payload;
    }
  }

  Future<Map<String, dynamic>?> cached(String organizationId) async {
    final value = await _storage.read(key: '$_cachePrefix$organizationId');
    if (value == null) return null;
    final payload = jsonDecode(value) as Map<String, dynamic>;
    _verify(payload);
    payload['offline'] = true;
    return payload;
  }

  void _verify(Map<String, dynamic> payload) {
    for (final item in (payload['configurations'] as List<dynamic>? ?? const [])) {
      final configuration = Map<String, dynamic>.from(item as Map);
      final content = configuration['configuration'];
      final expected = configuration['checksum']?.toString();
      final actual = sha256.convert(utf8.encode(jsonEncode(content))).toString();
      if (expected == null || expected != actual) {
        throw const FormatException('Configuration PharmaCare invalide.');
      }
    }
  }

  Future<void> _acknowledge(Map<String, dynamic> payload, String token) async {
    final deviceId = await _storage.read(key: 'device_id');
    final organizationId = payload['organization_id']?.toString();
    if (deviceId == null || organizationId == null) return;
    final configurations = (payload['configurations'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .map((item) => {'id': item['id'], 'checksum': item['checksum']})
        .toList();
    try {
      await _client.dio.post<void>(
        '/configuration/effective/acknowledge',
        data: {
          'organization_id': organizationId,
          'device_id': deviceId,
          'configurations': configurations,
        },
        options: Options(headers: {'Authorization': 'Bearer $token'}),
      );
    } on DioException {
      // La configuration vérifiée reste valide localement. L’acquittement sera
      // retenté au prochain rafraîchissement sans bloquer l’application.
    }
  }
}
