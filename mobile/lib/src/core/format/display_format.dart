/// Quantité lisible : « 20 » plutôt que « 20.0000 », deux décimales au plus.
String formatQuantity(Object? value) {
  final number = value is num ? value : double.tryParse('$value');
  if (number == null) return '${value ?? '—'}';
  if (number == number.roundToDouble()) return number.toStringAsFixed(0);
  return number.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '');
}

/// Date du jour seule : le serveur renvoie les dates avec une heure à minuit.
String formatDay(Object? value) =>
    value == null || '$value'.isEmpty ? '—' : '$value'.split('T').first;
