import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/access/application_access.dart';

void main() {
  test('chaque route protégée exige la permission attendue', () {
    expect(
      ApplicationAccess.requiredPermission('/configuration'),
      'configuration.view',
    );
    expect(ApplicationAccess.requiredPermission('/receipts'), 'receipts.view');
    expect(
      ApplicationAccess.requiredPermission('/prescriptions'),
      'prescriptions.view',
    );
    expect(
      ApplicationAccess.requiredPermission('/dispensations'),
      'dispensing.view',
    );
    expect(
      ApplicationAccess.requiredPermission('/project-settings'),
      'project_settings.view',
    );
    expect(
      ApplicationAccess.requiredPermission('/site-settings'),
      'site_settings.view',
    );
    expect(
      ApplicationAccess.requiredPermission('/activity-log-local'),
      'activity_logs.view_local',
    );
  });

  test('le manifeste local masque les modules sans permission', () {
    final user = <String, dynamic>{
      'permissions': <String>[
        'dashboard.view',
        'stocks.view',
        'receipts.view',
        'site_settings.view',
      ],
    };

    final keys = ApplicationAccess.navigation(
      user,
    ).map((item) => item.key).toSet();
    expect(
      keys,
      containsAll(<String>[
        'dashboard',
        'stocks',
        'receipts',
        'site_settings',
        'profile',
      ]),
    );
    expect(keys, isNot(contains('configuration')));
    expect(keys, isNot(contains('organizations')));
    expect(keys, isNot(contains('users')));
  });

  test(
    'la navigation conserve le même ordre et le tableau de bord en premier',
    () {
      final user = <String, dynamic>{
        'permissions': <String>[
          'configuration.view',
          'organizations.view',
          'products.view',
          'stocks.view',
        ],
      };

      final keys = ApplicationAccess.navigation(
        user,
      ).map((item) => item.key).toList(growable: false);

      expect(keys.first, 'dashboard');
      expect(keys, <String>[
        'dashboard',
        'configuration',
        'organizations',
        'products',
        'stocks',
        'profile',
      ]);
    },
  );

  test('une permission retirée supprime immédiatement le module concerné', () {
    final withUsers = ApplicationAccess.navigation(<String, dynamic>{
      'permissions': <String>['users.view', 'stocks.view'],
    }).map((item) => item.key);
    final withoutUsers = ApplicationAccess.navigation(<String, dynamic>{
      'permissions': <String>['stocks.view'],
    }).map((item) => item.key);

    expect(withUsers, contains('users'));
    expect(withoutUsers, isNot(contains('users')));
    expect(withoutUsers, contains('stocks'));
  });

  test('Admin Coordination entre directement sur le tableau de bord', () {
    final user = <String, dynamic>{
      'role': 'coordination_admin',
      'roles': <String>['coordination_admin'],
      'permissions': <String>['configuration.view', 'dashboard.view'],
    };

    expect(ApplicationAccess.landingPath(user), '/home');
  });

  test('les autres rôles entrent sur le tableau de bord', () {
    for (final role in <String>[
      'owner',
      'sago_admin',
      'project_admin',
      'site_admin',
      'site_user',
    ]) {
      expect(
        ApplicationAccess.landingPath(<String, dynamic>{
          'role': role,
          'roles': <String>[role],
          'permissions': <String>['dashboard.view'],
        }),
        role == 'sago_admin' ? '/sago/dashboard' : '/home',
      );
    }
  });

  test('le changement obligatoire du mot de passe reste prioritaire', () {
    expect(
      ApplicationAccess.landingPath(<String, dynamic>{
        'role': 'coordination_admin',
        'permissions': <String>['configuration.view'],
        'must_change_password': true,
      }),
      '/change-password',
    );
  });
}
