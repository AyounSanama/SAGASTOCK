import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/localization/app_locale.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('le slogan de connexion suit la langue choisie', () {
    AppLocale.apply('en');
    expect(AppLocale.tagline, 'Putting Patients at the Heart of Every Supply.');

    AppLocale.apply('fr');
    expect(AppLocale.tagline, 'Placer le patient au cœur de chaque approvisionnement.');

    // Langue non traduite : repli sur le français.
    AppLocale.apply('de');
    expect(AppLocale.preferredCode.value, 'fr');
  });
}
