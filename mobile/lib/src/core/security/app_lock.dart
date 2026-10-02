import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:crypto/crypto.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

enum AppLockState { unlocked, locked, setupRequired }

/// S-03 — Verrouillage de l'application par code PIN.
///
/// * PIN de 4 à 6 chiffres, créé après la connexion, propre au compte (effacé
///   avec la session : un autre utilisateur crée le sien).
/// * Seule une empreinte salée est conservée, dans le stockage sécurisé.
/// * Verrouillage après 5 minutes d'inactivité, au retour en avant-plan
///   après 5 minutes, et à chaque démarrage à froid.
/// * 5 codes faux : la session est fermée ; les opérations en attente restent
///   sur le téléphone et repartent à la prochaine connexion.
class AppLock {
  AppLock({
    FlutterSecureStorage? storage,
    DateTime Function()? clock,
    this.inactivityLimit = const Duration(minutes: 5),
  }) : _storage = storage ?? const FlutterSecureStorage(),
       _clock = clock ?? DateTime.now;

  static final AppLock instance = AppLock();

  static const maxAttempts = 5;
  static const _hashKey = 'app_lock_pin_hash';
  static const _saltKey = 'app_lock_pin_salt';

  final FlutterSecureStorage _storage;
  final DateTime Function() _clock;
  final Duration inactivityLimit;

  final ValueNotifier<AppLockState> state = ValueNotifier(
    AppLockState.unlocked,
  );
  DateTime? _lastActivity;
  int _failedAttempts = 0;

  int get remainingAttempts => maxAttempts - _failedAttempts;

  static bool isValidPin(String pin) => RegExp(r'^\d{4,6}$').hasMatch(pin);

  Future<bool> hasPin() async => (await _storage.read(key: _hashKey)) != null;

  /// À appeler à chaque interaction de l'utilisateur.
  void touch() {
    if (state.value == AppLockState.unlocked) _lastActivity = _clock();
  }

  /// Réévalue l'état : session ouverte sans PIN → création obligatoire ;
  /// inactivité dépassée (ou démarrage à froid) → verrouillé.
  Future<void> evaluate({required bool hasSession}) async {
    if (!hasSession) {
      _lastActivity = null;
      _failedAttempts = 0;
      state.value = AppLockState.unlocked;
      return;
    }
    if (!await hasPin()) {
      state.value = AppLockState.setupRequired;
      return;
    }
    if (state.value != AppLockState.unlocked) return;
    final last = _lastActivity;
    if (last == null || _clock().difference(last) >= inactivityLimit) {
      state.value = AppLockState.locked;
    }
  }

  Future<void> setPin(String pin) async {
    if (!isValidPin(pin)) {
      throw ArgumentError('Le code PIN doit comporter 4 à 6 chiffres.');
    }
    final salt = base64Encode(
      List<int>.generate(16, (_) => Random.secure().nextInt(256)),
    );
    await _storage.write(key: _saltKey, value: salt);
    await _storage.write(key: _hashKey, value: _hash(pin, salt));
    _unlock();
  }

  /// Vérifie le code. Renvoie `false` si faux ; au 5e échec, [onLockedOut]
  /// doit fermer la session (les données en attente sont conservées).
  Future<bool> unlock(String pin) async {
    final salt = await _storage.read(key: _saltKey);
    final expected = await _storage.read(key: _hashKey);
    if (salt != null && expected != null && _hash(pin, salt) == expected) {
      _unlock();
      return true;
    }
    _failedAttempts++;
    return false;
  }

  bool get lockedOut => _failedAttempts >= maxAttempts;

  void _unlock() {
    _failedAttempts = 0;
    _lastActivity = _clock();
    state.value = AppLockState.unlocked;
  }

  /// Empreinte salée et étirée (20 000 passes) : un PIN court ne doit pas
  /// pouvoir être retrouvé rapidement même si l'empreinte était extraite.
  static String _hash(String pin, String salt) {
    var digest = sha256.convert(utf8.encode('$salt:$pin')).bytes;
    for (var i = 0; i < 20000; i++) {
      digest = sha256.convert([...digest, ...utf8.encode(salt)]).bytes;
    }
    return base64Encode(digest);
  }
}
