class PasswordPolicy {
  const PasswordPolicy._();

  static const int minimumLength = 6;
  static const String helperText =
      '6 caractères minimum avec majuscule, minuscule, chiffre et symbole.';

  static String? validate(String? value) {
    final password = value ?? '';
    if (password.length < minimumLength) {
      return '$minimumLength caractères minimum';
    }
    if (!RegExp(r'[A-Z]').hasMatch(password)) {
      return 'Ajoutez au moins une lettre majuscule';
    }
    if (!RegExp(r'[a-z]').hasMatch(password)) {
      return 'Ajoutez au moins une lettre minuscule';
    }
    if (!RegExp(r'[0-9]').hasMatch(password)) {
      return 'Ajoutez au moins un chiffre';
    }
    if (!RegExp(r'[^A-Za-z0-9]').hasMatch(password)) {
      return 'Ajoutez au moins un symbole';
    }
    return null;
  }
}
