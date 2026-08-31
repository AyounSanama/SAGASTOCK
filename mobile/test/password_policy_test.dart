import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/security/password_policy.dart';

void main() {
  test('la politique commune refuse 5 et accepte 6 caracteres securises', () {
    expect(PasswordPolicy.validate('Aa1!a'), isNotNull);
    expect(PasswordPolicy.validate('Aa1!aa'), isNull);
  });
}
