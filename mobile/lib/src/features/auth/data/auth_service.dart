import 'dart:convert';
import 'dart:io';
import 'package:dio/dio.dart';
import 'package:crypto/crypto.dart';
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
      _authenticatedAtKey = 'authenticated_at',
      _offlineVerifierKey = 'offline_password_verifier';

  Future<bool> hasSession() async {
    final token = await _storage.read(key: _tokenKey);
    final user = await _storage.read(key: _userKey);
    return token != null && user != null;
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
    // Android peut signaler « hors ligne » alors que le serveur local est
    // joignable. On tente donc toujours l'API avant le cache hors connexion.
    Response<Map<String, dynamic>> response;
    try {
      response = await _client.dio.post<Map<String, dynamic>>(
        '/auth/login',
        data: {
          'login': email,
          'password': password,
          'device_name': Platform.isIOS
              ? 'iPhone PharmaCare'
              : 'Android PharmaCare',
          'device_id': deviceId,
          'platform': Platform.isIOS ? 'ios' : 'android',
        },
      );
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      if (await _hasOfflineCache()) {
        return _offlineLogin(
          login: email,
          password: password,
          deviceId: deviceId,
        );
      }
      throw const ServerUnavailableException();
    }
    final token = response.data?['token'] as String?;
    final user = response.data?['user'] as Map<String, dynamic>?;
    if (token == null || user == null) {
      throw StateError('R\u00e9ponse de connexion incompl\u00e8te.');
    }
    // A successful online authentication starts a completely fresh identity
    // context. No cache from the previous account may survive this point.
    await clearAuthenticatedSession(revokeRemoteToken: false);
    await _storage.write(key: _tokenKey, value: token);
    await _storage.write(key: _userKey, value: jsonEncode(user));
    await _storage.write(
      key: _authenticatedAtKey,
      value: DateTime.now().toIso8601String(),
    );
    await _storage.write(
      key: _offlineVerifierKey,
      value: _passwordVerifier(
        deviceId: deviceId,
        login: email,
        password: password,
      ),
    );
    user['session_mode'] = 'online';
    await _storage.write(key: _userKey, value: jsonEncode(user));
    return user;
  }

  Future<Map<String, dynamic>> _offlineLogin({
    required String login,
    required String password,
    required String deviceId,
  }) async {
    final user = await cachedUser();
    final token = await _storage.read(key: _tokenKey);
    final expected = await _storage.read(key: _offlineVerifierKey);
    final normalized = login.trim().toLowerCase();
    final cachedLogin = '${user?['email'] ?? ''}'.trim().toLowerCase();
    final cachedUsername = '${user?['username'] ?? ''}'.trim().toLowerCase();
    final actual = _passwordVerifier(
      deviceId: deviceId,
      login: login,
      password: password,
    );
    if (user == null ||
        token == null ||
        expected == null ||
        (normalized != cachedLogin && normalized != cachedUsername) ||
        actual != expected) {
      throw const OfflineAuthenticationException();
    }
    user['session_mode'] = 'offline';
    await _storage.write(key: _userKey, value: jsonEncode(user));
    return user;
  }

  String _passwordVerifier({
    required String deviceId,
    required String login,
    required String password,
  }) {
    final normalized = login.trim().toLowerCase();
    return sha256
        .convert(utf8.encode('$deviceId:$normalized:$password'))
        .toString();
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

  Future<bool> _hasOfflineCache() async {
    final user = await cachedUser();
    final token = await _storage.read(key: _tokenKey);
    final expected = await _storage.read(key: _offlineVerifierKey);
    return user != null && token != null && expected != null;
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
    await clearAuthenticatedSession(revokeRemoteToken: false);
  }

  /// Single logout/reset entry point for every authenticated feature.
  ///
  /// The device identifier is deliberately preserved because it identifies
  /// the physical installation, not the authenticated person. Every other
  /// secure value and offline cache is identity-bound and is removed.
  Future<void> clearAuthenticatedSession({
    bool revokeRemoteToken = false,
  }) async {
    if (revokeRemoteToken) {
      final token = await _storage.read(key: _tokenKey);
      if (token != null) {
        try {
          await _client.dio.post<void>(
            '/auth/logout',
            options: await _authorized(),
          );
        } catch (_) {}
      }
    }

    final values = await _storage.readAll();
    for (final key in values.keys) {
      if (key != _deviceKey) {
        await _storage.delete(key: key);
      }
    }
  }

  static String messageFor(Object error) {
    if (error is OfflineAuthenticationException) {
      return 'Connexion hors ligne indisponible pour ces identifiants. Connectez-vous une première fois au serveur sur cet appareil.';
    }
    if (error is ServerUnavailableException) {
      return 'Impossible de contacter le serveur. Vérifiez l’URL API et la connectivité de l’appareil. Sur appareil physique, utilisez l’IP de l’ordinateur et non 10.0.2.2.';
    }
    if (error is DioException && error.response?.statusCode == 422) {
      return 'Adresse e-mail ou mot de passe incorrect.';
    }
    return 'Connexion impossible. Vérifiez le serveur et réessayez.';
  }
}

class OfflineAuthenticationException implements Exception {
  const OfflineAuthenticationException();
}

class ServerUnavailableException implements Exception {
  const ServerUnavailableException();
}
