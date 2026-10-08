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

  test('Admin Coordination conserve les modules autorisés par le backend', () {
    final user = <String, dynamic>{
      'role': 'coordination_admin',
      'permissions': <String>['dashboard.view', 'missions.view'],
      'navigation': <Map<String, dynamic>>[
        <String, dynamic>{
          'key': 'dashboard',
          'label': 'Tableau de bord',
          'path': '/home',
          'icon': 'dashboard',
          'permission': 'dashboard.view',
        },
        <String, dynamic>{
          'key': 'missions',
          'label': 'Missions',
          'path': '/missions',
          'icon': 'missions',
          'permission': 'missions.view',
        },
        <String, dynamic>{
          'key': 'users',
          'label': 'Utilisateurs',
          'path': '/users',
          'icon': 'users',
          'permission': 'users.view',
        },
      ],
    };

    final navigation = ApplicationAccess.navigation(user);
    final keys = navigation.map((item) => item.key).toList(growable: false);

    // « Utilisateurs » hors menu Coordination ; « Mon profil » toujours présent (niveau 2).
    expect(keys, <String>['dashboard', 'missions', 'profile']);
    expect(navigation[1].label, 'Ma Coordination');
  });

  test('la V1 filtre aussi un ancien manifeste conservé hors connexion', () {
    final coordination = <String, dynamic>{
      'role': 'coordination_admin',
      'permissions': <String>[
        'dashboard.view',
        'missions.view',
        'projects.view',
        'standard_lists.view',
        'stocks.view',
      ],
      'navigation': <Map<String, dynamic>>[
        for (final item in <(String, String, String, String)>[
          ('dashboard', 'Tableau de bord', '/home', 'dashboard.view'),
          ('missions', 'Missions', '/missions', 'missions.view'),
          ('projects', 'Projets', '/projects', 'projects.view'),
          (
            'standard-lists',
            'Listes',
            '/standard-lists',
            'standard_lists.view',
          ),
          ('stocks', 'Stocks', '/stocks', 'stocks.view'),
        ])
          <String, dynamic>{
            'key': item.$1,
            'label': item.$2,
            'path': item.$3,
            'icon': item.$1,
            'permission': item.$4,
          },
      ],
    };

    final items = ApplicationAccess.navigation(coordination);
    // Niveau 2 : « Configuration des projets » quitte le menu (projets
    // ouverts depuis Ma Coordination) ; Liste Standard de la Coordination.
    expect(items.map((item) => item.label), <String>[
      'Tableau de bord',
      'Ma Coordination',
      'Liste Standard',
      'Mon profil',
    ]);
    expect(items[2].path, '/coordination/standard-list');
    // Avec le droit de voir les bailleurs : « Référentiels ».
    final withFunding = ApplicationAccess.navigation({
      ...coordination,
      'permissions': [...coordination['permissions'] as List, 'funding.view'],
    });
    expect(withFunding.map((item) => item.label), <String>[
      'Tableau de bord',
      'Ma Coordination',
      'Référentiels',
      'Liste Standard',
      'Mon profil',
    ]);
    expect(ApplicationAccess.moduleAvailable(coordination, '/stocks'), isFalse);
    expect(
      ApplicationAccess.moduleAvailable(coordination, '/projects/42'),
      isTrue,
    );

    final project = <String, dynamic>{...coordination, 'role': 'project_admin'};
    expect(ApplicationAccess.moduleAvailable(project, '/missions'), isFalse);
    expect(ApplicationAccess.moduleAvailable(project, '/projects/42'), isTrue);
    expect(
      ApplicationAccess.moduleAvailable(project, '/health-facilities'),
      isTrue,
    );
    expect(ApplicationAccess.moduleAvailable(project, '/users'), isTrue);
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

  test('Admin Sago : pas d’ancien Centre de contrôle dans le menu', () {
    final keys = ApplicationAccess.navigation(<String, dynamic>{
      'role': 'sago_admin',
      'permissions': <String>[
        'configuration.view',
        'organizations.view',
        'platform_standards.view',
      ],
    }).map((item) => item.key);

    expect(keys, isNot(contains('configuration')));
    expect(keys, containsAll(<String>['organizations', 'standards', 'history']));
  });

  test('le rôle est affiché par son libellé, jamais par son code', () {
    expect(ApplicationAccess.roleLabel({'role': 'sago_admin'}), 'Admin Sago');
    expect(ApplicationAccess.roleLabel({'role': 'site_user'}), 'Utilisateur du Site');
    expect(
      ApplicationAccess.roleLabel({'role': 'coordination_admin', 'read_only': true}),
      'Coordination (lecture seule)',
    );
  });
}
