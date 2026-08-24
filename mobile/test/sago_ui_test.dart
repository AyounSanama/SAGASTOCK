import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/features/configuration/presentation/configuration_page.dart';
import 'package:sagastock_mobile/src/features/home/presentation/home_page.dart';

void main() {
  test('les pages Admin Sago partagées restent compilables', () {
    expect(const HomePage(), isA<HomePage>());
    expect(const ConfigurationPage(), isA<ConfigurationPage>());
  });
}
