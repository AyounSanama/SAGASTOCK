import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'app_tokens.dart';

/// Niveau 4 — Mode d'affichage choisi par l'utilisateur : `light`, `dark` ou
/// `system` (suit le réglage du téléphone). Mémorisé sur l'appareil et dans le
/// profil (PUT /auth/theme), comme la langue.
class AppThemeMode {
  static const values = <String>['light', 'dark', 'system'];
  static const labels = {
    'light': 'Clair',
    'dark': 'Sombre',
    'system': 'Système',
  };

  /// Préférence en cours ; l'application se reconstruit à chaque changement.
  static final preference = ValueNotifier<String>('light');
  static const _storageKey = 'pharmacare.theme_preference';
  static const _storage = FlutterSecureStorage();

  static String normalize(Object? value) =>
      values.contains(value) ? value! as String : 'light';

  static void apply(Object? value) {
    preference.value = normalize(value);
    unawaited(
      _storage
          .write(key: _storageKey, value: preference.value)
          .catchError((_) {}),
    );
  }

  /// Relit le dernier choix (appelée au démarrage, avant le premier affichage).
  static Future<void> restore() async {
    try {
      final stored = await _storage.read(key: _storageKey);
      if (stored != null) preference.value = normalize(stored);
    } catch (_) {
      // Stockage indisponible : le mode clair reste le mode par défaut.
    }
  }

  /// Vrai si la palette sombre doit s'appliquer pour cette préférence.
  static bool isDark(String preference, Brightness platform) =>
      preference == 'dark' ||
      (preference == 'system' && platform == Brightness.dark);

  /// Applique la palette correspondante ; retourne vrai si elle est sombre.
  static bool activate(Brightness platform) {
    final dark = isDark(preference.value, platform);
    AppColors.useDark(dark);
    return dark;
  }
}
