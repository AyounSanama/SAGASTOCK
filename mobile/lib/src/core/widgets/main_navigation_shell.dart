import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/data/auth_service.dart';
import '../access/application_access.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import 'app_navigation_drawer.dart';

/// Layout authentifié unique. Son contenu varie uniquement avec les permissions.
class MainLayout extends StatelessWidget {
  const MainLayout({required this.location, required this.child, super.key});

  final String location;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>?>(
      future: AuthService().cachedUser(),
      builder: (context, snapshot) {
        final items = ApplicationAccess.navigation(snapshot.data);
        final role = '${snapshot.data?['role'] ?? ''}'.toLowerCase();
        final isSago = items.any((item) => item.key == 'standards');
        final compactKeys = role == 'coordination_admin'
            ? const {'dashboard', 'projects', 'standard-lists', 'profile'}
            : role == 'project_admin'
            ? const {'dashboard', 'projects', 'standard-lists', 'profile'}
            : isSago
            ? const {
                'dashboard',
                'organizations',
                'assistance',
                'history',
                'profile',
              }
            : const {
                'dashboard',
                'stocks',
                'receipts',
                'dispensations',
                'dispensing',
                'inventory-orders',
                'profile',
              };
        final compactItems = items
            .where((item) => compactKeys.contains(item.key))
            .take(5)
            .toList(growable: false);
        final wide = MediaQuery.sizeOf(context).width >= AppBreakpoints.desktop;

        return Scaffold(
          body: Row(
            children: [
              if (wide)
                const SizedBox(
                  width: AppSizes.sidebarWidth,
                  child: AppNavigationDrawer(),
                ),
              Expanded(child: child),
            ],
          ),
          bottomNavigationBar: wide || compactItems.isEmpty
              ? null
              : _BottomNavigation(items: compactItems, location: location),
        );
      },
    );
  }
}

class _BottomNavigation extends StatelessWidget {
  const _BottomNavigation({required this.items, required this.location});

  final List<ApplicationNavigationItem> items;
  final String location;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      minimum: const EdgeInsets.fromLTRB(10, 0, 10, 8),
      child: Container(
        height: 68,
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.xs,
          vertical: AppSpacing.sm,
        ),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(AppRadius.lg),
          border: Border.all(color: AppTheme.border),
          boxShadow: const [
            BoxShadow(
              color: Color(0x1A182033),
              blurRadius: 22,
              offset: Offset(0, 7),
            ),
          ],
        ),
        child: Row(
          children: [
            for (final item in items)
              Expanded(
                child: _NavigationItem(
                  item: item,
                  selected:
                      location == item.path ||
                      location.startsWith('${item.path}/'),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _NavigationItem extends StatelessWidget {
  const _NavigationItem({required this.item, required this.selected});

  final ApplicationNavigationItem item;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppTheme.orange : AppTheme.gray;
    return Material(
      color: selected ? AppTheme.orangeSoft : Colors.transparent,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: InkWell(
        onTap: () => context.go(item.path),
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(item.icon, size: 22, color: color),
            const SizedBox(height: 4),
            Text(
              _compactLabel(item),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: color,
                fontSize: 10,
                fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _compactLabel(ApplicationNavigationItem item) => switch (item.key) {
    'dashboard' => 'Accueil',
    'projects' => 'Projets',
    'standard-lists' => 'Liste standard',
    'profile' => 'Mon profil',
    _ => item.label,
  };
}

@Deprecated('Utiliser MainLayout.')
class MainNavigationShell extends MainLayout {
  const MainNavigationShell({
    required super.location,
    required super.child,
    super.key,
  });
}
