import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/theme/module_icon_registry.dart';
import '../../../core/widgets/app_dashboard_panel.dart';
import '../../../core/widgets/app_kpi_card.dart';
import '../../../core/widgets/app_navigation_drawer.dart';
import '../../auth/data/auth_service.dart';
import '../data/dashboard_service.dart';

class HomePage extends StatelessWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      drawer: const AppNavigationDrawer(),
      body: SafeArea(
        child: FutureBuilder<Map<String, dynamic>>(
          future: DashboardService().load(),
          builder: (context, snapshot) {
            final data = snapshot.data ?? const <String, dynamic>{};
            final stats = data['stats'] as Map<String, dynamic>? ??
                const <String, dynamic>{};
            final activities = _asMaps(data['activities']);
            final movements = _asMaps(data['recent_movements']);
            final widgets = _asMaps(data['widgets']);
            final navigation = _asMaps(data['navigation']);
            final offline = data['offline'] == true;

            return RefreshIndicator(
              onRefresh: () async => DashboardService().load(),
              child: CustomScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                slivers: [
                  SliverToBoxAdapter(
                    child: _DashboardHeader(
                      offline: offline,
                      onLogout: () async {
                        await AuthService().logout();
                        if (context.mounted) context.go('/login');
                      },
                    ),
                  ),
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(18, 8, 18, 30),
                    sliver: SliverList.list(
                      children: [
                        _DashboardIntro(offline: offline),
                        const SizedBox(height: 18),
                        _Statistics(stats: stats, widgets: widgets),
                        const SizedBox(height: 24),
                        const _SectionTitle(
                          title: 'Actions rapides',
                          subtitle: 'Accédez à vos opérations courantes',
                        ),
                        const SizedBox(height: 12),
                        _QuickActions(navigation: navigation),
                        const SizedBox(height: 24),
                        _StockAlerts(movements: movements),
                        const SizedBox(height: 18),
                        _RecentActivities(activities: activities),
                      ],
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  static List<Map<String, dynamic>> _asMaps(dynamic value) {
    if (value is! List) return const [];
    return value.whereType<Map>().map((item) {
      return item.map((key, value) => MapEntry(key.toString(), value));
    }).toList();
  }
}

class _DashboardHeader extends StatelessWidget {
  const _DashboardHeader({
    required this.offline,
    required this.onLogout,
  });

  final bool offline;
  final VoidCallback onLogout;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 8),
      child: Row(
        children: [
          Builder(
            builder: (context) => _HeaderAction(
              icon: Icons.menu_rounded,
              tooltip: 'Menu principal',
              onTap: Scaffold.of(context).openDrawer,
            ),
          ),
          const SizedBox(width: 10),
          Image.asset(
            'assets/images/pharmacare-logo.png',
            width: 38,
            height: 38,
            fit: BoxFit.contain,
            semanticLabel: 'Logo PharmaCare',
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Tableau de bord',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: AppTheme.ink,
                    fontSize: 21,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -.4,
                  ),
                ),
                SizedBox(height: 2),
                Text(
                  'Vue d’ensemble de PharmaCare',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: AppTheme.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
          _HeaderAction(
            icon: offline ? Icons.cloud_off_rounded : Icons.cloud_done_rounded,
            tooltip: offline ? 'Mode hors connexion' : 'Données synchronisées',
            color: offline ? AppTheme.gray : AppTheme.green,
            onTap: () {},
          ),
          const SizedBox(width: 8),
          Stack(
            clipBehavior: Clip.none,
            children: [
              _HeaderAction(
                icon: Icons.notifications_none_rounded,
                tooltip: 'Notifications',
                color: AppTheme.blue,
                onTap: () {},
              ),
              Positioned(
                top: -3,
                right: -2,
                child: Container(
                  width: 19,
                  height: 19,
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(
                    color: AppTheme.red,
                    shape: BoxShape.circle,
                  ),
                  child: const Text(
                    '3',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(width: 8),
          Semantics(
            button: true,
            label: 'Ouvrir mon profil',
            child: InkWell(
              onTap: () => context.go('/profile'),
              onLongPress: onLogout,
              borderRadius: BorderRadius.circular(22),
              child: Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppTheme.orange.withValues(alpha: .12),
                  border: Border.all(
                    color: AppTheme.orange.withValues(alpha: .32),
                  ),
                ),
                child: const Icon(Icons.person_rounded, color: AppTheme.orange),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _HeaderAction extends StatelessWidget {
  const _HeaderAction({
    required this.icon,
    required this.tooltip,
    required this.onTap,
    this.color = AppTheme.ink,
  });

  final IconData icon;
  final String tooltip;
  final VoidCallback onTap;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      tooltip: tooltip,
      onPressed: onTap,
      style: IconButton.styleFrom(
        minimumSize: const Size.square(44),
        fixedSize: const Size.square(44),
        foregroundColor: color,
        backgroundColor: color.withValues(alpha: .08),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(13)),
      ),
      icon: Icon(icon, size: 23),
    );
  }
}

class _DashboardIntro extends StatelessWidget {
  const _DashboardIntro({required this.offline});

  final bool offline;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Bonjour 👋',
                style: TextStyle(
                  color: AppTheme.ink,
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                ),
              ),
              SizedBox(height: 3),
              Text(
                'Voici les informations essentielles de votre activité.',
                style: TextStyle(
                  color: AppTheme.muted,
                  fontSize: 12,
                  height: 1.35,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 10),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
          decoration: BoxDecoration(
            color: (offline ? AppTheme.gray : AppTheme.green)
                .withValues(alpha: .10),
            borderRadius: BorderRadius.circular(999),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                offline ? Icons.cloud_off_rounded : Icons.circle,
                color: offline ? AppTheme.gray : AppTheme.green,
                size: offline ? 15 : 8,
              ),
              const SizedBox(width: 6),
              Text(
                offline ? 'Hors connexion' : 'À jour',
                style: TextStyle(
                  color: offline ? AppTheme.gray : AppTheme.green,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _Statistics extends StatelessWidget {
  const _Statistics({required this.stats, required this.widgets});

  final Map<String, dynamic> stats;
  final List<Map<String, dynamic>> widgets;

  @override
  Widget build(BuildContext context) {
    final items = widgets.map((widget) {
      final key = widget['key']?.toString() ?? '';
      final rawValue = widget['value'] ?? stats[key] ?? 0;
      return _StatData(
        key: key,
        value: key == 'stock_quantity' ? _quantity(rawValue) : '$rawValue',
        label: widget['label']?.toString() ?? key,
        caption: widget['caption']?.toString() ?? '',
        icon: ModuleIconRegistry.resolve(widget['icon']?.toString()),
        color: _widgetColor(widget['color']?.toString()),
        route: widget['route']?.toString() ?? '/home',
      );
    }).toList(growable: false);

    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= AppBreakpoints.desktop ? 4 : constraints.maxWidth >= AppBreakpoints.tablet ? 3 : constraints.maxWidth >= 520 ? 2 : 1;
        final cardWidth = (constraints.maxWidth - (AppSpacing.md * (columns - 1))) / columns;
        return Wrap(
          spacing: 12,
          runSpacing: 12,
          children: [
            for (final item in items)
              SizedBox(width: cardWidth, child: _StatCard(data: item)),
          ],
        );
      },
    );
  }

  static String _quantity(dynamic value) {
    final number = num.tryParse('$value') ?? 0;
    return number == number.roundToDouble()
        ? number.toInt().toString()
        : number.toStringAsFixed(1);
  }

  static Color _widgetColor(String? color) => switch (color) {
    'red' => AppTheme.red,
    'orange' => AppTheme.orange,
    'cyan' => const Color(0xFF16B8B4),
    _ => AppTheme.blue,
  };
}

class _StatData {
  const _StatData({
    required this.value,
    required this.label,
    required this.caption,
    required this.icon,
    required this.color,
    required this.route,
    required this.key,
  });

  final String value;
  final String label;
  final String caption;
  final IconData icon;
  final Color color;
  final String route;
  final String key;
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.data});

  final _StatData data;

  @override
  Widget build(BuildContext context) => AppKpiCard(
    label: data.label,
    value: data.value,
    caption: data.caption,
    icon: data.icon,
    tone: data.color == AppTheme.red ? AppKpiTone.red : data.color == AppTheme.orange ? AppKpiTone.orange : AppKpiTone.blue,
    onTap: () => context.go(data.route),
  );
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, this.subtitle});

  final String title;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            color: AppTheme.ink,
            fontSize: 17,
            fontWeight: FontWeight.w800,
          ),
        ),
        if (subtitle != null) ...[
          const SizedBox(height: 2),
          Text(
            subtitle!,
            style: const TextStyle(color: AppTheme.muted, fontSize: 11),
          ),
        ],
      ],
    );
  }
}

class _QuickActions extends StatelessWidget {
  const _QuickActions({required this.navigation});

  final List<Map<String, dynamic>> navigation;

  @override
  Widget build(BuildContext context) {
    const colors = [AppTheme.orange, AppTheme.blue, AppTheme.green, AppTheme.purple, AppTheme.gray];
    final modules = navigation
        .where((item) => !{'dashboard', 'configuration', 'profile'}.contains(item['key']?.toString()))
        .take(5)
        .toList(growable: false);
    return SizedBox(
      height: 98,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: modules.length,
        separatorBuilder: (_, _) => const SizedBox(width: 10),
        itemBuilder: (context, index) => _QuickAction(
          label: modules[index]['label']?.toString() ?? '',
          icon: ModuleIconRegistry.resolve(modules[index]['icon']?.toString()),
          color: colors[index % colors.length],
          route: modules[index]['path']?.toString(),
        ),
      ),
    );
  }
}

class _QuickAction extends StatelessWidget {
  const _QuickAction({
    required this.label,
    required this.icon,
    required this.color,
    this.route,
  });

  final String label;
  final IconData icon;
  final Color color;
  final String? route;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 82,
      child: InkWell(
        onTap: route == null ? null : () => context.go(route!),
        borderRadius: BorderRadius.circular(16),
        child: Column(
          children: [
            Ink(
              width: 58,
              height: 58,
              decoration: BoxDecoration(
                color: color.withValues(alpha: .10),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: color.withValues(alpha: .20)),
              ),
              child: Icon(icon, color: color, size: 26),
            ),
            const SizedBox(height: 7),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: AppTheme.ink,
                fontSize: 10.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StockAlerts extends StatelessWidget {
  const _StockAlerts({required this.movements});

  final List<Map<String, dynamic>> movements;

  @override
  Widget build(BuildContext context) {
    return _DashboardPanel(
      title: 'Derniers mouvements de stock',
      subtitle: 'Suivi des opérations récentes',
      icon: Icons.inventory_2_outlined,
      color: AppTheme.orange,
      onViewAll: () => context.go('/stocks'),
      child: movements.isEmpty
          ? const _EmptyRow(
              icon: Icons.check_circle_outline_rounded,
              color: AppTheme.green,
              title: 'Aucun mouvement récent',
              subtitle: 'Le stock est prêt à être utilisé.',
            )
          : Column(
              children: [
                for (var index = 0;
                    index < movements.length && index < 4;
                    index++) ...[
                  _MovementRow(item: movements[index]),
                  if (index < movements.length - 1 && index < 3)
                    const Divider(height: 1),
                ],
              ],
            ),
    );
  }
}

class _MovementRow extends StatelessWidget {
  const _MovementRow({required this.item});

  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final product = item['product'] as Map?;
    final site = item['site'] as Map?;
    final type = '${item['type'] ?? item['movement_type'] ?? 'Mouvement'}';
    final isOut = type.toLowerCase().contains('out') ||
        type.toLowerCase().contains('sort');
    final color = isOut ? AppTheme.red : AppTheme.green;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 11),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: color.withValues(alpha: .10),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              isOut ? Icons.north_east_rounded : Icons.south_west_rounded,
              color: color,
              size: 20,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${product?['name'] ?? 'Produit pharmaceutique'}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AppTheme.ink,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '${site?['name'] ?? 'Site de stockage'}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AppTheme.muted,
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
            decoration: BoxDecoration(
              color: color.withValues(alpha: .09),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              type,
              style: TextStyle(
                color: color,
                fontSize: 10,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _RecentActivities extends StatelessWidget {
  const _RecentActivities({required this.activities});

  final List<Map<String, dynamic>> activities;

  @override
  Widget build(BuildContext context) {
    return _DashboardPanel(
      title: 'Activités récentes',
      subtitle: 'Historique des dernières actions',
      icon: Icons.history_rounded,
      color: AppTheme.blue,
      child: activities.isEmpty
          ? const _EmptyRow(
              icon: Icons.info_outline_rounded,
              color: AppTheme.blue,
              title: 'Aucune activité récente',
              subtitle: 'Les prochaines opérations apparaîtront ici.',
            )
          : Column(
              children: [
                for (var index = 0;
                    index < activities.length && index < 5;
                    index++) ...[
                  _ActivityRow(item: activities[index], index: index),
                  if (index < activities.length - 1 && index < 4)
                    const Divider(height: 1),
                ],
              ],
            ),
    );
  }
}

class _ActivityRow extends StatelessWidget {
  const _ActivityRow({required this.item, required this.index});

  final Map<String, dynamic> item;
  final int index;

  @override
  Widget build(BuildContext context) {
    final colors = [AppTheme.blue, AppTheme.green, AppTheme.orange, AppTheme.purple];
    final color = colors[index % colors.length];
    final event = '${item['event'] ?? 'Activité enregistrée'}'
        .replaceAll('_', ' ')
        .trim();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 11),
      child: Row(
        children: [
          Container(
            width: 9,
            height: 9,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 13),
          Expanded(
            child: Text(
              event.isEmpty ? 'Activité enregistrée' : event,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: AppTheme.ink,
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          const SizedBox(width: 8),
          const Icon(Icons.chevron_right_rounded, color: AppTheme.muted, size: 19),
        ],
      ),
    );
  }
}

class _EmptyRow extends StatelessWidget {
  const _EmptyRow({
    required this.icon,
    required this.color,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final Color color;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 14),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: color.withValues(alpha: .10),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: color, size: 21),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: AppTheme.ink,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: AppTheme.muted,
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DashboardPanel extends StatelessWidget {
  const _DashboardPanel({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.color,
    required this.child,
    this.onViewAll,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final Color color;
  final Widget child;
  final VoidCallback? onViewAll;

  @override
  Widget build(BuildContext context) {
    return AppDashboardPanel(
      title: title,
      description: subtitle,
      icon: icon,
      color: color,
      action: onViewAll == null ? null : TextButton(onPressed: onViewAll, child: const Text('Voir tout')),
      child: child,
    );
  }
}
