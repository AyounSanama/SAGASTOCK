import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/access/application_access.dart';
import 'package:sagastock_mobile/src/features/users/presentation/coordination_account_sheet.dart';

void main() {
  test('la Coordination propose Admin Projet et Admin Coordination en lecture seule uniquement', () {
    expect(coordinationAccountRoleLabel('project_admin'), 'Admin Projet');
    expect(coordinationAccountRoleLabel('coordination_admin'), 'Admin Coordination (lecture seule)');
    // « Formation sanitaire » (Admin Site) n'est pas proposé.
    expect(coordinationAccountRoleLabel('site_admin'), isNull);
    expect(coordinationAccountRoleLabel('site_user'), isNull);
  });

  test('un compte en lecture seule n’obtient aucune permission d’écriture', () {
    final reader = {
      'role': 'coordination_admin',
      'read_only': true,
      'permissions': ['projects.view', 'projects.manage', 'users.manage'],
    };
    expect(ApplicationAccess.isReadOnly(reader), isTrue);
    expect(ApplicationAccess.allows(reader, 'projects.view'), isTrue);
    expect(ApplicationAccess.allows(reader, 'projects.manage'), isFalse);
    expect(ApplicationAccess.allows(reader, 'users.manage'), isFalse);

    final coordination = {...reader, 'read_only': false};
    expect(ApplicationAccess.allows(coordination, 'projects.manage'), isTrue);
  });
}
