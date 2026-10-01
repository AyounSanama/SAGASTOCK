import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class AppLocale {
  static const supported = <String>['fr'];
  static final current = ValueNotifier<Locale>(const Locale('fr'));

  /// Langue choisie par l'utilisateur (FR/EN), mémorisée sur l'appareil pour
  /// que l'écran de connexion la suive avant toute session.
  static final preferredCode = ValueNotifier<String>('fr');
  static const _storageKey = 'pharmacare.preferred_locale';
  static const _storage = FlutterSecureStorage();

  static void apply(String? code) {
    current.value = Locale(supported.contains(code) ? code! : 'fr');
    preferredCode.value = code == 'en' ? 'en' : 'fr';
    unawaited(
      _storage
          .write(key: _storageKey, value: preferredCode.value)
          .catchError((_) {}),
    );
  }

  /// Relit la dernière langue choisie (appelée au démarrage).
  static Future<void> restore() async {
    try {
      final code = await _storage.read(key: _storageKey);
      if (code != null) preferredCode.value = code == 'en' ? 'en' : 'fr';
    } catch (_) {
      // Stockage indisponible : le français reste la langue par défaut.
    }
  }

  static String get tagline => preferredCode.value == 'en'
      ? 'Putting Patients at the Heart of Every Supply.'
      : 'Placer le patient au cœur de chaque approvisionnement.';
}
