import 'dart:convert';
import 'dart:io';
import 'package:dio/dio.dart';
import 'package:crypto/crypto.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';
import '../../../core/network/api_client.dart';
import '../../../core/database/app_database.dart';
import '../../../core/sync/sync_bootstrap.dart';
import '../../configuration/data/effective_configuration_service.dart';

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
  static const _pendingLocaleKey = 'pending_preferred_locale';

  /// Clé de chiffrement de la base locale (DEC-10, lot d) : liée à
  /// l'installation, jamais au compte ni au PIN.
  static const localDatabaseKeyName = 'local_database_key';

  /// Valeurs conservées quand la session est fermée (déconnexion, PIN oublié,
  /// 5 codes faux, jeton expiré) : sans elles, les opérations en attente
  /// deviendraient illisibles ou orphelines.
  static const preservedStorageKeys = {_deviceKey, localDatabaseKeyName};
  static const _maximumOfflineSession = Duration(days: 30);

  Future<bool> hasSession() async {
    final token = await _storage.read(key: _tokenKey);
    final user = await _storage.read(key: _userKey);
    return token != null && user != null;
  }

  Future<Map<String, dynamic>?> cachedUser() async {
    final value = await _storage.read(key: _userKey);
    return value == null ? null : jsonDecode(value) as Map<String, dynamic>;
  }

  /// Refreshes the authorization context when the API is reachable while
  /// preserving the last valid identity for offline-first operation.
  Future<Map<String, dynamic>?> refreshedUser() async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/auth/me',
        options: await _authorized(),
      );
      final user = response.data?['user'] as Map<String, dynamic>?;
      if (user != null) {
        await _storage.write(key: _userKey, value: jsonEncode(user));
        return user;
      }
    } catch (_) {
      // The cached authorization context remains the source of truth offline.
    }
    return cachedUser();
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
      throw ServerUnavailableException.fromDio(error);
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
    final pendingLocale = await _storage.read(key: _pendingLocaleKey);
    if (pendingLocale != null) {
      await updatePreferredLocale(pendingLocale);
    }
    try {
      await EffectiveConfigurationService(
        client: _client,
        storage: _storage,
      ).refresh();
    } catch (_) {
      // Une configuration distante indisponible ne doit jamais empêcher la
      // première ouverture ni le fonctionnement Offline-First.
    }
    await SyncBootstrap.startForCachedSession();
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
        !await _offlineSessionIsValid() ||
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
    return user != null &&
        token != null &&
        expected != null &&
        await _offlineSessionIsValid();
  }

  Future<bool> _offlineSessionIsValid() async {
    final value = await _storage.read(key: _authenticatedAtKey);
    final authenticatedAt = value == null ? null : DateTime.tryParse(value);
    if (authenticatedAt == null) return false;
    return DateTime.now().toUtc().difference(authenticatedAt.toUtc()) <=
        _maximumOfflineSession;
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

  Future<void> updatePreferredLocale(String locale) async {
    if (!const ['fr'].contains(locale)) {
      throw ArgumentError.value(locale, 'locale', 'Langue non prise en charge');
    }
    final user = await cachedUser();
    if (user == null) throw StateError('Session locale absente.');
    user['preferred_locale'] = locale;
    await _storage.write(key: _userKey, value: jsonEncode(user));
    await _storage.write(key: _pendingLocaleKey, value: locale);
    try {
      await _client.dio.put<void>(
        '/auth/locale',
        data: {'preferred_locale': locale},
        options: await _authorized(),
      );
      await _storage.delete(key: _pendingLocaleKey);
    } catch (_) {
      // La préférence locale reste active hors connexion. Elle sera
      // renvoyée au serveur lors d'une prochaine modification en ligne.
    }
  }

  /// Mon profil : prénom, nom, identifiant, e-mail, téléphone (en ligne).
  /// Les erreurs de validation du serveur sont renvoyées par champ.
  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> data) async {
    final response = await _client.dio.put<Map<String, dynamic>>(
      '/auth/profile',
      data: data,
      options: await _authorized(),
    );
    final user = Map<String, dynamic>.from(response.data!['user'] as Map);
    final cached = await cachedUser() ?? {};
    // Le menu et les droits du compte sont conservés tels quels.
    final merged = {...cached, ...user};
    await _storage.write(key: _userKey, value: jsonEncode(merged));
    return merged;
  }

  /// Niveau 4 — Mode d'affichage (Clair / Sombre / Système) : appliqué tout de
  /// suite sur le téléphone, envoyé au serveur dès que possible.
  Future<void> updateThemePreference(String preference) async {
    final user = await cachedUser();
    if (user != null) {
      user['theme_preference'] = preference;
      await _storage.write(key: _userKey, value: jsonEncode(user));
    }
    try {
      await _client.dio.put<void>(
        '/auth/theme',
        data: {'theme_preference': preference},
        options: await _authorized(),
      );
    } catch (_) {
      // Hors connexion : le choix reste actif sur le téléphone.
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
        // Termine d'abord les écritures autorisées encore en attente. En cas
        // d'absence réseau, elles restent dans Drift pour la prochaine
        // authentification du même utilisateur.
        await SyncBootstrap.syncNow();
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
    bool clearLocalData = false,
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

    final previousUser = await cachedUser();
    await SyncBootstrap.stop();
    if (clearLocalData && !kIsWeb && previousUser?['id'] != null) {
      await AppDatabase.shared.clearIdentityData('${previousUser!['id']}');
    }
    final values = await _storage.readAll();
    for (final key in values.keys.toList()) {
      if (!preservedStorageKeys.contains(key)) {
        await _storage.delete(key: key);
      }
    }
  }

  static String messageFor(Object error) {
    if (error is OfflineAuthenticationException) {
      return 'Connexion hors ligne indisponible pour ces identifiants. Connectez-vous une première fois au serveur sur cet appareil.';
    }
    if (error is ServerUnavailableException) {
      return switch (error.type) {
        DioExceptionType.connectionTimeout =>
          'Le serveur ne répond pas dans le délai prévu. Vérifiez que Laravel est démarré et que le port 8000 est autorisé par le pare-feu.',
        DioExceptionType.receiveTimeout || DioExceptionType.sendTimeout =>
          'La communication avec le serveur a expiré. Vérifiez la qualité du réseau puis réessayez.',
        DioExceptionType.badCertificate =>
          'Le certificat de sécurité du serveur n’est pas valide.',
        DioExceptionType.connectionError =>
          'Connexion au serveur impossible (${error.host}). Vérifiez que le téléphone et l’ordinateur utilisent le même réseau et que le port 8000 est ouvert.',
        _ =>
          'Impossible de contacter le serveur (${error.host}). Vérifiez l’URL API et la connectivité de l’appareil.',
      };
    }
    if (error is DioException && error.response?.statusCode == 422) {
      final data = error.response?.data;
      if (data is Map) {
        final errors = data['errors'];
        if (errors is Map) {
          for (final value in errors.values) {
            if (value is List && value.isNotEmpty && value.first is String) {
              return value.first as String;
            }
            if (value is String && value.trim().isNotEmpty) {
              return value;
            }
          }
        }
        final message = data['message'];
        if (message is String && message.trim().isNotEmpty) {
          return message;
        }
      }
      return 'Adresse e-mail ou mot de passe incorrect.';
    }
    return 'Connexion impossible. Vérifiez le serveur et réessayez.';
  }
}

class OfflineAuthenticationException implements Exception {
  const OfflineAuthenticationException();
}

class ServerUnavailableException implements Exception {
  const ServerUnavailableException({
    required this.type,
    required this.host,
    this.cause,
  });

  factory ServerUnavailableException.fromDio(DioException error) {
    return ServerUnavailableException(
      type: error.type,
      host: error.requestOptions.uri.authority,
      cause: error.error,
    );
  }

  final DioExceptionType type;
  final String host;
  final Object? cause;
}
