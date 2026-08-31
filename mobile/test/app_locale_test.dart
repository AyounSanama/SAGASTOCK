import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/localization/app_locale.dart';

void main() {
  test('la langue embarquee reste francaise et utilise le fallback', () {
    AppLocale.apply('fr');
    expect(AppLocale.current.value.languageCode, 'fr');

    AppLocale.apply('en');
    expect(AppLocale.current.value.languageCode, 'fr');
  });
}
