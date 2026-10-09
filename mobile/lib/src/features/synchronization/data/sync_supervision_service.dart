import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/network/api_client.dart';

/// Supervision de la synchronisation : même calcul que la page Web
/// (indicateurs, filtres, recherche et détail faits par le serveur).
class SyncSupervisionService {
  SyncSupervisionService({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;
  final _storage = const FlutterSecureStorage();

  Future<Map<String, dynamic>> overview({
    String? projectId,
    String filter = 'all',
    String search = '',
    String? deviceId,
  }) async {
    final response = await _client.dio.get<Map<String, dynamic>>(
      '/sync/supervision',
      queryParameters: {
        'project_id': ?projectId,
        if (filter != 'all') 'filter': filter,
        if (search.trim().isNotEmpty) 'search': search.trim(),
        'device': ?deviceId,
      },
      options: Options(
        headers: {
          'Authorization': 'Bearer ${await _storage.read(key: 'auth_token')}',
        },
      ),
    );
    return response.data ?? const {};
  }
}
