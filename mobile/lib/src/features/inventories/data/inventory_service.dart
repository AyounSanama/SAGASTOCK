import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';
import '../../../core/network/api_client.dart';

class InventoryService {
  InventoryService({ApiClient? client}) : _client = client ?? ApiClient();
  final ApiClient _client;
  final _storage = const FlutterSecureStorage();
  static const _outboxKey = 'offline_inventory_outbox';
  Future<Options> _auth() async => Options(
    headers: {
      'Authorization': 'Bearer ${await _storage.read(key: 'auth_token')}',
    },
  );
  Future<List<Map<String, dynamic>>> organizations() =>
      _list('/organizations', 'offline_organizations');
  Future<List<Map<String, dynamic>>> inventories(String id) async {
    await sync();
    return _list('/organizations/$id/inventories', 'offline_inventories_$id');
  }

  Future<List<Map<String, dynamic>>> sites(String id) async {
    final key = 'offline_inventory_sites_$id';
    try {
      final r = await _client.dio.get<Map<String, dynamic>>(
        '/organizations/$id/inventories/options',
        options: await _auth(),
      );
      final data = (r.data?['sites'] as List? ?? [])
          .cast<Map<String, dynamic>>();
      await _storage.write(key: key, value: jsonEncode(data));
      return data;
    } on DioException catch (e) {
      if (e.response != null) rethrow;
      final cached = await _storage.read(key: key);
      return cached == null
          ? []
          : (jsonDecode(cached) as List).cast<Map<String, dynamic>>();
    }
  }

  Future<bool> create(String id, Map<String, dynamic> data) {
    data['offline_uuid'] ??= const Uuid().v4();
    return _send('post', '/organizations/$id/inventories', data);
  }

  Future<bool> start(String id, String inventory) =>
      _send('post', '/organizations/$id/inventories/$inventory/start', {});
  Future<bool> count(
    String id,
    String inventory,
    List<Map<String, dynamic>> lines,
  ) => _send('put', '/organizations/$id/inventories/$inventory/count', {
    'lines': lines,
  });
  Future<bool> submit(String id, String inventory) =>
      _send('post', '/organizations/$id/inventories/$inventory/submit', {});
  Future<int> pendingCount() async => (await _outbox()).length;
  Future<bool> _send(
    String method,
    String path,
    Map<String, dynamic> data,
  ) async {
    try {
      await _client.dio.request(
        path,
        data: data,
        options: (await _auth()).copyWith(method: method.toUpperCase()),
      );
      return true;
    } on DioException catch (e) {
      if (e.response != null) rethrow;
      final q = await _outbox();
      q.add({
        'operation_id': const Uuid().v4(),
        'method': method,
        'path': path,
        'data': data,
        'queued_at': DateTime.now().toIso8601String(),
        'attempts': 0,
      });
      await _storage.write(key: _outboxKey, value: jsonEncode(q));
      return false;
    }
  }

  Future<void> sync() async {
    final q = await _outbox();
    if (q.isEmpty) return;
    final remaining = <Map<String, dynamic>>[];
    for (final item in q) {
      try {
        await _client.dio.request(
          item['path'],
          data: item['data'],
          options: (await _auth()).copyWith(
            method: '${item['method']}'.toUpperCase(),
          ),
        );
      } on DioException catch (e) {
        remaining.add({
          ...item,
          'attempts': ((item['attempts'] as num?)?.toInt() ?? 0) + 1,
          'last_error': e.response?.data?.toString() ?? e.message,
        });
      }
    }
    await _storage.write(key: _outboxKey, value: jsonEncode(remaining));
  }

  Future<List<Map<String, dynamic>>> _list(String path, String key) async {
    try {
      final r = await _client.dio.get<Map<String, dynamic>>(
        path,
        options: await _auth(),
      );
      final data = (r.data?['data'] as List? ?? [])
          .cast<Map<String, dynamic>>();
      await _storage.write(key: key, value: jsonEncode(data));
      return data;
    } on DioException catch (e) {
      if (e.response != null) rethrow;
      final cached = await _storage.read(key: key);
      return cached == null
          ? []
          : (jsonDecode(cached) as List).cast<Map<String, dynamic>>();
    }
  }

  Future<List<Map<String, dynamic>>> _outbox() async {
    final v = await _storage.read(key: _outboxKey);
    return (v == null ? [] : jsonDecode(v) as List)
        .cast<Map<String, dynamic>>();
  }
}
