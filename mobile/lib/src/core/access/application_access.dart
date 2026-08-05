import 'package:flutter/material.dart';

import '../theme/module_icon_registry.dart';

class ApplicationNavigationItem {
  const ApplicationNavigationItem({
    required this.key,
    required this.label,
    required this.path,
    required this.icon,
    required this.permission,
  });

  final String key;
  final String label;
  final String path;
  final IconData icon;
  final String? permission;
}

abstract final class ApplicationAccess {
  static const coordinationAdmin = 'coordination_admin';
  static const _fallbackManifest = <Map<String, String?>>[
    {
      'key': 'dashboard',
      'label': 'Tableau de bord',
      'path': '/home',
      'icon': 'dashboard',
      'permission': null,
    },
    {
      'key': 'configuration',
      'label': 'Configuration',
      'path': '/configuration',
      'icon': 'configuration',
      'permission': 'configuration.view',
    },
    {
      'key': 'organizations',
      'label': 'Organisations',
      'path': '/organizations',
      'icon': 'organizations',
      'permission': 'organizations.view',
    },
    {
      'key': 'missions',
      'label': 'Missions',
      'path': '/missions',
      'icon': 'missions',
      'permission': 'missions.view',
    },
    {
      'key': 'projects',
      'label': 'Projets',
      'path': '/projects',
      'icon': 'projects',
      'permission': 'projects.view',
    },
    {
      'key': 'funding',
      'label': 'Bailleurs et programmes',
      'path': '/funding',
      'icon': 'funding',
      'permission': 'funding.view',
    },
    {
      'key': 'facilities',
      'label': 'Formations sanitaires',
      'path': '/health-facilities',
      'icon': 'facilities',
      'permission': 'health_facilities.view',
    },
    {
      'key': 'sites',
      'label': 'Sites de dispensation',
      'path': '/dispensing-sites',
      'icon': 'sites',
      'permission': 'dispensing_sites.view',
    },
    {
      'key': 'users',
      'label': 'Utilisateurs',
      'path': '/users',
      'icon': 'users',
      'permission': 'users.view',
    },
    {
      'key': 'standard-lists',
      'label': 'Listes standards',
      'path': '/standard-lists',
      'icon': 'standard_lists',
      'permission': 'standard_lists.view',
    },
    {
      'key': 'products',
      'label': 'Produits',
      'path': '/products',
      'icon': 'products',
      'permission': 'products.view',
    },
    {
      'key': 'stocks',
      'label': 'Stocks',
      'path': '/stocks',
      'icon': 'stocks',
      'permission': 'stocks.view',
    },
    {
      'key': 'receipts',
      'label': 'Réceptions',
      'path': '/receipts',
      'icon': 'receipts',
      'permission': 'receipts.view',
    },
    {
      'key': 'dispensing',
      'label': 'Dispensation',
      'path': '/dispensations',
      'icon': 'dispensing',
      'permission': 'dispensing.view',
    },
    {
      'key': 'inventories',
      'label': 'Inventaires',
      'path': '/inventories',
      'icon': 'inventories',
      'permission': 'inventories.view',
    },
    {
      'key': 'orders',
      'label': 'Commandes',
      'path': '/orders',
      'icon': 'orders',
      'permission': 'orders.view',
    },
    {
      'key': 'reports',
      'label': 'Rapports',
      'path': '/reports',
      'icon': 'reports',
      'permission': 'reports.view',
    },
    {
      'key': 'synchronization',
      'label': 'Synchronisation',
      'path': '/synchronization',
      'icon': 'synchronization',
      'permission': 'synchronization.view',
    },
    {
      'key': 'settings',
      'label': 'Paramètres organisation',
      'path': '/settings',
      'icon': 'settings',
      'permission': 'settings.view',
    },
    {
      'key': 'project_settings',
      'label': 'Paramètres du projet',
      'path': '/project-settings',
      'icon': 'settings',
      'permission': 'project_settings.view',
    },
    {
      'key': 'site_settings',
      'label': 'Paramètres du site',
      'path': '/site-settings',
      'icon': 'settings',
      'permission': 'site_settings.view',
    },
    {
      'key': 'activity_logs',
      'label': 'Journal des activités',
      'path': '/activity-log',
      'icon': 'activity_logs',
      'permission': 'activity_logs.view',
    },
    {
      'key': 'local_activity_logs',
      'label': 'Journal local',
      'path': '/activity-log-local',
      'icon': 'activity_logs',
      'permission': 'activity_logs.view_local',
    },
    {
      'key': 'profile',
      'label': 'Mon profil',
      'path': '/profile',
      'icon': 'profile',
      'permission': null,
    },
  ];

  static Set<String> permissions(Map<String, dynamic>? user) =>
      (user?['permissions'] as List<dynamic>? ?? const [])
          .map((value) => value.toString())
          .toSet();

  static bool allows(Map<String, dynamic>? user, String? permission) =>
      permission == null || permissions(user).contains(permission);

  /// Route d'accueil unique, calculée depuis l'identité authentifiée.
  static String landingPath(Map<String, dynamic>? user) {
    if (user?['must_change_password'] == true) return '/change-password';

    final role = user?['role']?.toString();
    final roles = (user?['roles'] as List<dynamic>? ?? const <dynamic>[])
        .map((value) => value.toString())
        .toSet();
    final isCoordination =
        role == coordinationAdmin ||
        role == 'organization_admin' ||
        roles.contains(coordinationAdmin) ||
        roles.contains('organization_admin');

    return isCoordination && allows(user, 'configuration.view')
        ? '/configuration'
        : '/home';
  }

  static List<ApplicationNavigationItem> navigation(
    Map<String, dynamic>? user,
  ) {
    final remote = user?['navigation'] as List<dynamic>?;
    final source = remote == null || remote.isEmpty
        ? _fallbackManifest
        : remote.cast<Map<String, dynamic>>();

    return source
        .where((raw) => allows(user, raw['permission']?.toString()))
        .map(
          (raw) => ApplicationNavigationItem(
            key: raw['key']?.toString() ?? '',
            label: raw['label']?.toString() ?? '',
            path: raw['path']?.toString() ?? '/home',
            icon: ModuleIconRegistry.resolve(raw['icon']?.toString()),
            permission: raw['permission']?.toString(),
          ),
        )
        .toList(growable: false);
  }

  static String? requiredPermission(String path) {
    const routes = <String, String>{
      '/configuration': 'configuration.view',
      '/organizations': 'organizations.view',
      '/missions': 'missions.view',
      '/projects': 'projects.view',
      '/funding': 'funding.view',
      '/health-facilities': 'health_facilities.view',
      '/dispensing-sites': 'dispensing_sites.view',
      '/users': 'users.view',
      '/standard-lists': 'standard_lists.view',
      '/products': 'products.view',
      '/stocks': 'stocks.view',
      '/receipts': 'receipts.view',
      '/dispensations': 'dispensing.view',
      '/inventories': 'inventories.view',
      '/orders': 'orders.view',
      '/reports': 'reports.view',
      '/synchronization': 'synchronization.view',
      '/project-settings': 'project_settings.view',
      '/site-settings': 'site_settings.view',
      '/settings': 'settings.view',
      '/activity-log-local': 'activity_logs.view_local',
      '/activity-log': 'activity_logs.view',
    };
    for (final entry in routes.entries) {
      if (path.startsWith(entry.key)) return entry.value;
    }
    return null;
  }
}
