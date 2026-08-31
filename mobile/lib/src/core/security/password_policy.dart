class PasswordPolicy {
  const PasswordPolicy._();

  static const int minimumLength = 6;
  static const String helperText =
      '6 caractères minimum avec majuscule, minuscule, chiffre et symbole.';

  static String? validate(String? value) {
    if ((value ?? '').length < minimumLength) {
      return '$minimumLength caractères minimum';
    }
    return null;
  }
}
