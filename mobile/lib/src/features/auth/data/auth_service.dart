import 'dart:convert';
import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';
import '../../../core/network/api_client.dart';

class AuthService {
  AuthService({ApiClient? client, FlutterSecureStorage? storage})
    : _client = client ?? ApiClient(),
      _storage = storage ?? const FlutterSecureStorage();
  final ApiClient _client;
  final FlutterSecureStorage _storage;
  static const _tokenKey = 'auth_token',
      _deviceKey = 'device_id',
      _userKey = 'cached_user',
      _authenticatedAtKey = 'authenticated_at';
  Future<bool> hasSession() async {
    final token = await _storage.read(key: _tokenKey);
    final authenticatedAt = DateTime.tryParse(
      await _storage.read(key: _authenticatedAtKey) ?? '',
    );
    if (token == null ||
        authenticatedAt == null ||
        DateTime.now().difference(authenticatedAt).inDays >= 7) {
      await logout();
      return false;
    }
    return true;
  }

  Future<Map<String, dynamic>?> cachedUser() async {
    final value = await _storage.read(key: _userKey);
    return value == null ? null : jsonDecode(value) as Map<String, dynamic>;
  }

  Future<Options> _authorized() async {
    final token = await _storage.read(key: _tokenKey);
    return Options(headers: {'Authorization': 'Bearer $token'});
  }

  Future<Map<String, dynamic>> login({
    required String email,
    required String password,
  }) async {
    var deviceId = await _storage.read(key: _deviceKey);
    deviceId ??= const Uuid().v4();
    await _storage.write(key: _deviceKey, value: deviceId);
    final response = await _client.dio.post<Map<String, dynamic>>(
      '/auth/login',
      data: {
        'email': email,
        'password': password,
        'device_name': Platform.isIOS
            ? 'iPhone SAGASTOCK'
            : 'Android SAGASTOCK',
        'device_id': deviceId,
        'platform': Platform.isIOS ? 'ios' : 'android',
      },
    );
    final token = response.data?['token'] as String?;
    final user = response.data?['user'] as Map<String, dynamic>?;
    if (token == null || user == null) {
      throw StateError('R\u00e9ponse de connexion incompl\u00e8te.');
    }
    await _storage.write(key: _tokenKey, value: token);
    await _storage.write(key: _userKey, value: jsonEncode(user));
    return user;
  }

  Future<String> forgotPassword(String email) async {
    final response = await _client.dio.post<Map<String, dynamic>>(
      '/auth/forgot-password',
      data: {'email': email},
    );
    return response.data?['message'] as String? ??
        'Si ce compte existe, un lien a \u00e9t\u00e9 envoy\u00e9.';
  }

  Future<void> resetPassword({
    required String email,
    required String token,
    required String password,
  }) async {
    await _client.dio.post<void>(
      '/auth/reset-password',
      data: {
        'email': email,
        'token': token,
        'password': password,
        'password_confirmation': password,
      },
    );
  }

  Future<void> changePassword({
    required String currentPassword,
    required String password,
  }) async {
    await _client.dio.put<void>(
      '/auth/password',
      data: {
        'current_password': currentPassword,
        'password': password,
        'password_confirmation': password,
      },
      options: await _authorized(),
    );
    final user = await cachedUser();
    if (user != null) {
      user['must_change_password'] = false;
      await _storage.write(key: _userKey, value: jsonEncode(user));
    }
  }

  Future<List<Map<String, dynamic>>> devices() async {
    final response = await _client.dio.get<Map<String, dynamic>>(
      '/auth/devices',
      options: await _authorized(),
    );
    return (response.data?['devices'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>();
  }

  Future<void> revokeDevice(String id) async {
    await _client.dio.delete<void>(
      '/auth/devices/$id',
      options: await _authorized(),
    );
  }

  Future<void> logout() async {
    final token = await _storage.read(key: _tokenKey);
    if (token != null) {
      try {
        await _client.dio.post<void>(
          '/auth/logout',
          options: await _authorized(),
        );
      } catch (_) {}
    }
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _userKey);
    await _storage.delete(key: _authenticatedAtKey);
  }

  static String messageFor(Object error) {
    if (error is DioException && error.response?.statusCode == 422) {
      return 'Adresse e-mail ou mot de passe incorrect.';
    }
    return 'Connexion impossible. V\u00e9rifiez le serveur et r\u00e9essayez.';
  }
}
