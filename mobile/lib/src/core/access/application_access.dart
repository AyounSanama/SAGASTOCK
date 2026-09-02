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
      'key': 'standards',
      'label': 'Standards & Référentiels',
      'path': '/standards',
      'icon': 'standard_lists',
      'permission': 'platform_standards.view',
    },
    {
      'key': 'assistance',
      'label': 'Assistance',
      'path': '/standards/assistance',
      'icon': 'organizations',
      'permission': 'platform_standards.view',
    },
    {
      'key': 'history',
      'label': 'Historique',
      'path': '/standards/history',
      'icon': 'activity_logs',
      'permission': 'platform_standards.view',
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
      'label': 'Listes standards de médicaments',
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
      'label': 'Entrées en stock',
      'path': '/receipts',
      'icon': 'receipts',
      'permission': 'receipts.view',
    },
    {
      'key': 'dispensing',
      'label': 'Dispensation de médicaments',
      'path': '/dispensations',
      'icon': 'dispensing',
      'permission': 'dispensing.view',
    },
    {
      'key': 'inventory-orders',
      'label': 'Inventaires & Commandes',
      'path': '/inventories',
      'icon': 'inventories',
      'permission': 'inventories.view',
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

  /// Availability gate for the staged V1. It complements RBAC: a permission
  /// alone must not expose a module hidden for the current role.
  static bool moduleAvailable(Map<String, dynamic>? user, String path) {
    final role = '${user?['role'] ?? ''}'.toLowerCase();
    final roots = switch (role) {
      'coordination_admin' => const {
        '/home',
        '/missions',
        '/projects',
        '/funding',
        '/standard-lists',
        '/profile',
      },
      'project_admin' => const {
        '/home',
        '/projects',
        '/health-facilities',
        '/users',
        '/standard-lists',
        '/profile',
      },
      _ => null,
    };
    if (roots == null) return true;
    return roots.any((root) => path == root || path.startsWith('$root/'));
  }

  /// Route d'accueil unique, calculée depuis l'identité authentifiée.
  static String landingPath(Map<String, dynamic>? user) {
    if (user?['must_change_password'] == true) return '/change-password';
    final role = '${user?['role'] ?? ''}'.toLowerCase();
    return role == 'sago_admin' ? '/sago/dashboard' : '/home';
  }

  static List<ApplicationNavigationItem> navigation(
    Map<String, dynamic>? user,
  ) {
    final remote = user?['navigation'] as List<dynamic>?;
    final role = '${user?['role'] ?? ''}'.toLowerCase();
    final source = role == 'sago_admin' || remote == null || remote.isEmpty
        ? _fallbackManifest
        : remote.cast<Map<String, dynamic>>();
    final allowedKeys = switch (role) {
      'sago_admin' => const {
        'dashboard',
        'configuration',
        'organizations',
        'standards',
        'assistance',
        'history',
        'profile',
      },
      'coordination_admin' => const {
        'dashboard',
        'missions',
        'projects',
        'standard-lists',
        'profile',
      },
      'project_admin' => const {
        'dashboard',
        'projects',
        'facilities',
        'users',
        'standard-lists',
        'profile',
      },
      _ => null,
    };
    final filteredSource = allowedKeys == null
        ? source
        : source.where((raw) => allowedKeys.contains(raw['key']));

    return filteredSource
        .where((raw) => allows(user, raw['permission']?.toString()))
        .map(
          (raw) => ApplicationNavigationItem(
            key: raw['key']?.toString() ?? '',
            label: role == 'coordination_admin'
                ? switch (raw['key']) {
                    'missions' => 'Ma Coordination',
                    'projects' => 'Configuration des projets',
                    'standard-lists' => 'Liste standard du projet',
                    _ => raw['label']?.toString() ?? '',
                  }
                : role == 'project_admin'
                ? switch (raw['key']) {
                    'projects' => 'Mon projet',
                    'standard-lists' => 'Liste standard',
                    'users' => 'Équipe FOSA',
                    _ => raw['label']?.toString() ?? '',
                  }
                : raw['label']?.toString() ?? '',
            path: role == 'sago_admin' && raw['key'] == 'dashboard'
                ? '/sago/dashboard'
                : raw['path']?.toString() ?? '/home',
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
      '/standards': 'platform_standards.view',
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
      '/prescriptions': 'prescriptions.view',
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
