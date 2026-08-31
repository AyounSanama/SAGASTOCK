import 'package:flutter/material.dart';

class AppLocale {
  static const supported = <String>['fr'];
  static final current = ValueNotifier<Locale>(const Locale('fr'));

  static void apply(String? code) {
    current.value = Locale(supported.contains(code) ? code! : 'fr');
  }
}
