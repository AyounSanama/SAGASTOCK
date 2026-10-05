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

  test('barre basse Admin Projet : Accueil, FOSA, Liste, Profil (AM-161)', () {
    final items = ApplicationAccess.navigation({
      'role': 'project_admin',
      'permissions': [
        'project.view',
        'health_facilities.view',
        'users.view',
        'standard_lists.view',
      ],
      'navigation': [
        {'key': 'dashboard', 'label': 'Tableau de bord', 'path': '/home', 'permission': null},
        {'key': 'projects', 'label': 'Projet & FOSA', 'path': '/projects', 'permission': 'project.view'},
        {'key': 'facilities', 'label': 'FOSA', 'path': '/health-facilities', 'permission': 'health_facilities.view'},
        {'key': 'users', 'label': 'Équipe FOSA', 'path': '/users', 'permission': 'users.view'},
        {'key': 'standard-lists', 'label': 'Liste standard', 'path': '/standard-lists', 'permission': 'standard_lists.view'},
        {'key': 'profile', 'label': 'Mon profil', 'path': '/profile', 'permission': null},
      ],
    });
    expect(
      mobilePrimaryNavigation('project_admin', items).map((item) => item.key),
      ['dashboard', 'facilities', 'standard-lists', 'profile'],
    );
  });

  test('barre basse Coordination : Accueil, Coordination, Liste, Profil (M-03)', () {
    final items = ApplicationAccess.navigation({
      'role': 'coordination_admin',
      'permissions': ['missions.view', 'projects.view', 'standard_lists.view'],
      'navigation': [
        {'key': 'dashboard', 'label': 'Tableau de bord', 'path': '/home', 'permission': null},
        {'key': 'missions', 'label': 'Ma Coordination', 'path': '/missions', 'permission': 'missions.view'},
        {'key': 'projects', 'label': 'Projets', 'path': '/projects', 'permission': 'projects.view'},
        {'key': 'standard-lists', 'label': 'Liste standard', 'path': '/standard-lists', 'permission': 'standard_lists.view'},
        {'key': 'profile', 'label': 'Mon profil', 'path': '/profile', 'permission': null},
      ],
    });
    expect(
      mobilePrimaryNavigation('coordination_admin', items).map((item) => item.key),
      ['dashboard', 'missions', 'standard-lists', 'profile'],
    );
  });
}
