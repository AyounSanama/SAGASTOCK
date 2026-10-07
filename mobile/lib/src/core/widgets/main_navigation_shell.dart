import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/data/auth_service.dart';
import '../access/application_access.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import 'app_navigation_drawer.dart';

/// Primary destinations are selected from the already authorized manifest.
List<ApplicationNavigationItem> mobilePrimaryNavigation(
  String role,
  List<ApplicationNavigationItem> items,
) {
  final keys = switch (role) {
    'sago_admin' => const ['dashboard', 'organizations', 'history', 'profile'],
    // M-03 : Accueil, Coordination, Liste, Profil (« Analyses » au niveau 9).
    'coordination_admin' => const [
      'dashboard',
      'missions',
      'standard-lists',
      'profile',
    ],
    // AM-161 — maquette 05 : Accueil, FOSA, Liste, Profil.
    'project_admin' => const [
      'dashboard',
      'facilities',
      'standard-lists',
      'profile',
    ],
    _ => const ['dashboard', 'stocks', 'receipts', 'dispensing', 'profile'],
  };
  return [
    for (final key in keys)
      for (final item in items)
        if (item.key == key) item,
  ];
}

/// Layout authentifié unique. Son contenu varie uniquement avec les permissions.
class MainLayout extends StatefulWidget {
  const MainLayout({required this.location, required this.child, super.key});

  final String location;
  final Widget child;

  @override
  State<MainLayout> createState() => _MainLayoutState();
}

class _MainLayoutState extends State<MainLayout> {
  bool _collapsed = false;

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>?>(
      future: AuthService().cachedUser(),
      builder: (context, snapshot) {
        final items = ApplicationAccess.navigation(snapshot.data);
        final role = '${snapshot.data?['role'] ?? ''}'.toLowerCase();
        final isSago =
            role == 'sago_admin' ||
            items.any((item) => item.key == 'standards');
        final operational =
            !isSago &&
            role != 'coordination_admin' &&
            role != 'project_admin' &&
            items.any((item) => item.key == 'stocks');
        final compactItems = mobilePrimaryNavigation(role, items);
        final width = MediaQuery.sizeOf(context).width;
        final wide = width >= AppBreakpoints.tablet;
        final compact = _collapsed || width < AppBreakpoints.desktop;

        return Scaffold(
          drawer: const AppNavigationDrawer(),
          body: Row(
            children: [
              if (wide)
                SizedBox(
                  width: compact
                      ? AppSizes.sidebarCollapsedWidth
                      : AppSizes.sidebarWidth,
                  child: AppNavigationDrawer(
                    compact: compact,
                    onToggle: width < AppBreakpoints.desktop
                        ? null
                        : () => setState(() => _collapsed = !_collapsed),
                    persistent: true,
                  ),
                ),
              Expanded(child: widget.child),
            ],
          ),
          bottomNavigationBar: wide || compactItems.isEmpty
              ? null
              : operational
              ? _OperationalNavigation(items: items, location: widget.location)
              : _BottomNavigation(
                  items: compactItems,
                  location: widget.location,
                ),
        );
      },
    );
  }
}

class _OperationalNavigation extends StatelessWidget {
  const _OperationalNavigation({required this.items, required this.location});
  final List<ApplicationNavigationItem> items;
  final String location;

  @override
  Widget build(BuildContext context) {
    final primary = items
        .where((item) => {'dashboard', 'stocks', 'reports'}.contains(item.key))
        .toList();
    final actions = items
        .where(
          (item) => {
            'receipts',
            'dispensing',
            'dispensations',
            'inventory-orders',
            'orders',
          }.contains(item.key),
        )
        .toList();
    return SafeArea(
      child: Material(
        color: AppColors.surface,
        child: SizedBox(
          height: 72,
          child: Row(
            children: [
              for (final item in primary.where((item) => item.key != 'reports'))
                Expanded(
                  child: _NavigationItem(
                    item: item,
                    selected: location == item.path,
                  ),
                ),
              if (actions.isNotEmpty)
                Expanded(
                  child: IconButton.filled(
                    tooltip: 'Actions disponibles',
                    icon: const Icon(Icons.add),
                    onPressed: () => showModalBottomSheet<void>(
                      context: context,
                      useSafeArea: true,
                      builder: (sheetContext) => ListView(
                        shrinkWrap: true,
                        children: [
                          const ListTile(title: Text('Actions disponibles')),
                          for (final item in actions)
                            ListTile(
                              leading: Icon(item.icon),
                              title: Text(item.label),
                              onTap: () {
                                Navigator.pop(sheetContext);
                                context.go(item.path);
                              },
                            ),
                        ],
                      ),
                    ),
                  ),
                ),
              for (final item in primary.where((item) => item.key == 'reports'))
                Expanded(
                  child: _NavigationItem(
                    item: item,
                    selected: location == item.path,
                  ),
                ),
              Expanded(
                child: Builder(
                  builder: (context) => InkWell(
                    onTap: () => Scaffold.of(context).openDrawer(),
                    child: const Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.menu),
                        SizedBox(height: 4),
                        Text('Menu', style: TextStyle(fontSize: 11)),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
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
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(AppRadius.lg),
          border: Border.all(color: AppTheme.border),
          boxShadow: const [
            BoxShadow(
              color: Color(0x1A1C1F23),
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
    'projects' => item.label == 'Mon projet' ? 'Mon projet' : 'Projets',
    // Libellés courts des maquettes (barre basse).
    'missions' => 'Coordination',
    'standard-lists' => 'Liste',
    'facilities' => 'FOSA',
    'profile' => 'Profil',
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
