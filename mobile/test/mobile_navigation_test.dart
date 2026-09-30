import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/access/application_access.dart';
import 'package:sagastock_mobile/src/core/widgets/main_navigation_shell.dart';

void main() {
  test('Sago destinations do not depend on a legacy standards entry', () {
    final authorized = ApplicationAccess.navigation({
      'role': 'sago_admin',
      'permissions': [
        'dashboard.view',
        'organizations.view',
        'platform_standards.view',
      ],
    }).where((item) => item.key != 'standards').toList();
    expect(
      mobilePrimaryNavigation('sago_admin', authorized).map((item) => item.key),
      ['dashboard', 'organizations', 'history', 'profile'],
    );
  });

  test(
    'primary navigation never restores a destination denied by the manifest',
    () {
      final authorized = ApplicationAccess.navigation({
        'role': 'sago_admin',
        'permissions': ['dashboard.view'],
      });
      expect(
        mobilePrimaryNavigation(
          'sago_admin',
          authorized,
        ).map((item) => item.key),
        ['dashboard', 'profile'],
      );
    },
  );
}
