import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/network/api_client.dart';

class ReceiptService {
  ReceiptService({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;
  final _storage = const FlutterSecureStorage();
  static const _outboxKey = 'offline_receipt_outbox';

  Future<Options> _authorized() async => Options(
    headers: {
      'Authorization': 'Bearer ${await _storage.read(key: 'auth_token')}',
    },
  );

  Future<List<Map<String, dynamic>>> organizations() async {
    return _cachedList('/organizations', 'offline_organizations');
  }

  Future<List<Map<String, dynamic>>> list(String organizationId) async {
    await syncOutbox();
    return _cachedList(
      '/organizations/$organizationId/receipts',
      'offline_receipts_$organizationId',
    );
  }

  Future<Map<String, List<Map<String, dynamic>>>> options(
    String organizationId,
  ) async {
    final cacheKey = 'offline_receipt_options_$organizationId';
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/organizations/$organizationId/receipts/options',
        options: await _authorized(),
      );
      final data = response.data ?? <String, dynamic>{};
      await _storage.write(key: cacheKey, value: jsonEncode(data));
      return _optionsFrom(data);
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      final cached = await _storage.read(key: cacheKey);
      if (cached == null) rethrow;
      return _optionsFrom(jsonDecode(cached) as Map<String, dynamic>);
    }
  }

  Future<bool> create(
    String organizationId, {
    required Map<String, dynamic> data,
  }) async {
    final path = '/organizations/$organizationId/receipts';
    try {
      await _client.dio.post<Map<String, dynamic>>(
        path,
        data: data,
        options: await _authorized(),
      );
      return true;
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      final pending = await _readOutbox();
      pending.add({
        'path': path,
        'data': data,
        'queued_at': DateTime.now().toIso8601String(),
      });
      await _storage.write(key: _outboxKey, value: jsonEncode(pending));
      return false;
    }
  }

  Future<void> validate(String organizationId, String receiptId) async {
    await _client.dio.post<void>(
      '/organizations/$organizationId/receipts/$receiptId/validate',
      options: await _authorized(),
    );
  }

  Future<int> pendingCount() async => (await _readOutbox()).length;

  Future<void> syncOutbox() async {
    final pending = await _readOutbox();
    if (pending.isEmpty) return;
    final remaining = <Map<String, dynamic>>[];
    for (final item in pending) {
      try {
        await _client.dio.post<void>(
          item['path'] as String,
          data: item['data'],
          options: await _authorized(),
        );
      } on DioException {
        remaining.add(item);
      }
    }
    await _storage.write(key: _outboxKey, value: jsonEncode(remaining));
  }

  Future<List<Map<String, dynamic>>> _cachedList(
    String path,
    String cacheKey,
  ) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        path,
        options: await _authorized(),
      );
      final values = (response.data?['data'] as List<dynamic>? ?? [])
          .cast<Map<String, dynamic>>();
      await _storage.write(key: cacheKey, value: jsonEncode(values));
      return values;
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      final cached = await _storage.read(key: cacheKey);
      if (cached == null) return [];
      return (jsonDecode(cached) as List).cast<Map<String, dynamic>>();
    }
  }

  Map<String, List<Map<String, dynamic>>> _optionsFrom(
    Map<String, dynamic> data,
  ) => {
    'sites': (data['sites'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>(),
    'suppliers': (data['suppliers'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>(),
    'products': (data['products'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>(),
  };

  Future<List<Map<String, dynamic>>> _readOutbox() async {
    final value = await _storage.read(key: _outboxKey);
    return (value == null ? <dynamic>[] : jsonDecode(value) as List)
        .cast<Map<String, dynamic>>();
  }
}
