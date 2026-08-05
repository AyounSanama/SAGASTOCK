import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/data/auth_service.dart';
import '../access/application_access.dart';
import '../theme/app_theme.dart';
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
        final compactItems = items.length <= 5
            ? items
            : items
                .where((item) => const {
                      'dashboard', 'stocks', 'receipts',
                      'dispensations', 'dispensing', 'profile',
                    }.contains(item.key))
                .take(5)
                .toList(growable: false);
        final wide = MediaQuery.sizeOf(context).width >= 1100;

        return Scaffold(
          body: Row(
            children: [
              if (wide)
                const SizedBox(width: 300, child: AppNavigationDrawer()),
              Expanded(child: child),
            ],
          ),
          bottomNavigationBar: wide || compactItems.isEmpty
              ? null
              : _BottomNavigation(
                  items: compactItems,
                  location: location,
                ),
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
        padding: const EdgeInsets.symmetric(horizontal: 3, vertical: 6),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: AppTheme.border),
          boxShadow: const [
            BoxShadow(color: Color(0x1A182033), blurRadius: 22, offset: Offset(0, 7)),
          ],
        ),
        child: Row(
          children: [
            for (final item in items)
              Expanded(
                child: _NavigationItem(
                  item: item,
                  selected: location == item.path || location.startsWith('${item.path}/'),
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
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: () => context.go(item.path),
        borderRadius: BorderRadius.circular(12),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(item.icon, size: 22, color: color),
            const SizedBox(height: 4),
            Text(
              item.label,
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
}

@Deprecated('Utiliser MainLayout.')
class MainNavigationShell extends MainLayout {
  const MainNavigationShell({
    required super.location,
    required super.child,
    super.key,
  });
}
