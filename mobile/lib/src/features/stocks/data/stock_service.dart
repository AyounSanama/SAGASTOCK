import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/network/api_client.dart';

class StockService {
  StockService({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;
  final _storage = const FlutterSecureStorage();

  Future<Options> _authorized() async => Options(
    headers: {
      'Authorization': 'Bearer ${await _storage.read(key: 'auth_token')}',
    },
  );

  Future<List<Map<String, dynamic>>> organizations() async {
    final response = await _client.dio.get<Map<String, dynamic>>(
      '/organizations',
      options: await _authorized(),
    );
    return (response.data?['data'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>();
  }

  Future<List<Map<String, dynamic>>> balances(String organizationId) async {
    final response = await _client.dio.get<Map<String, dynamic>>(
      '/organizations/$organizationId/stocks/balances',
      options: await _authorized(),
    );
    return (response.data?['data'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>();
  }

  Future<List<Map<String, dynamic>>> movements(String organizationId) async {
    final response = await _client.dio.get<Map<String, dynamic>>(
      '/organizations/$organizationId/stocks/movements',
      options: await _authorized(),
    );
    return (response.data?['data'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>();
  }
}
