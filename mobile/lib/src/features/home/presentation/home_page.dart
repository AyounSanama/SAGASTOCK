import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/access/application_access.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/theme/module_icon_registry.dart';
import '../../../core/widgets/app_dashboard_panel.dart';
import '../../../core/widgets/app_navigation_drawer.dart';
import '../../auth/data/auth_service.dart';
import '../data/dashboard_service.dart';

class HomePage extends StatelessWidget {
  const HomePage({super.key, this.loadFuture});

  @visibleForTesting
  final Future<List<dynamic>>? loadFuture;

  @override
  Widget build(BuildContext context) => Scaffold(
    drawer: const AppNavigationDrawer(),
    body: SafeArea(
      child: FutureBuilder<List<dynamic>>(
        future:
            loadFuture ??
            Future.wait<dynamic>([
              DashboardService().load(),
              AuthService().cachedUser(),
            ]),
        builder: (context, snapshot) {
          final data =
              snapshot.data?.first as Map<String, dynamic>? ?? const {};
          final user = snapshot.data != null && snapshot.data!.length > 1
              ? snapshot.data![1] as Map<String, dynamic>?
              : null;
          final stats = data['stats'] as Map<String, dynamic>? ?? const {};
          final widgets = _maps(data['widgets']);
          final navigation = _maps(data['navigation']);
          final activities = _maps(data['activities']);
          final projects = _maps(data['recent_projects']);
          final offline = data['offline'] == true;
          final isProjectAdmin =
              '${user?['role'] ?? ''}'.toLowerCase() == 'project_admin';

          return RefreshIndicator(
            onRefresh: () => DashboardService().load(),
            child: CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                const SliverToBoxAdapter(child: _DashboardHeader()),
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 28),
                  sliver: SliverList.list(
                    children: [
                      _Welcome(user: user, offline: offline),
                      const SizedBox(height: AppSpacing.xl),
                      _SummaryCards(
                        role: '${user?['role'] ?? ''}',
                        stats: stats,
                        widgets: widgets,
                      ),
                      const SizedBox(height: AppSpacing.xl),
                      const _SectionTitle('Actions rapides'),
                      const SizedBox(height: AppSpacing.md),
                      _QuickActions(user: user, navigation: navigation),
                      if (projects.isNotEmpty && !isProjectAdmin) ...[
                        const SizedBox(height: AppSpacing.xl),
                        _RecentProjects(projects: projects),
                      ],
                      const SizedBox(height: AppSpacing.xl),
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

  static List<Map<String, dynamic>> _maps(dynamic value) {
    if (value is! List) return const [];
    return value
        .whereType<Map>()
        .map((item) {
          return item.map((key, value) => MapEntry('$key', value));
        })
        .toList(growable: false);
  }
}

class _DashboardHeader extends StatelessWidget {
  const _DashboardHeader();

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(10, 8, 12, 8),
    child: Row(
      children: [
        Builder(
          builder: (context) => _HeaderButton(
            icon: Icons.menu_rounded,
            tooltip: 'Menu principal',
            onPressed: Scaffold.of(context).openDrawer,
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        const Expanded(
          child: Text(
            'Tableau de bord',
            maxLines: 2,
            style: TextStyle(
              color: AppTheme.ink,
              fontSize: 19,
              fontWeight: FontWeight.w900,
              letterSpacing: -.35,
            ),
          ),
        ),
        _HeaderButton(
          icon: Icons.notifications_none_rounded,
          onPressed: () => context.push('/notifications'),
          tooltip: 'Notifications',
        ),
        const SizedBox(width: AppSpacing.xs),
        _HeaderButton(
          icon: Icons.person_outline_rounded,
          tooltip: 'Mon profil',
          color: AppTheme.orange,
          onPressed: () => context.go('/profile'),
        ),
      ],
    ),
  );
}

class _HeaderButton extends StatelessWidget {
  const _HeaderButton({
    required this.icon,
    required this.tooltip,
    this.onPressed,
    this.color = AppTheme.ink,
  });
  final IconData icon;
  final String tooltip;
  final VoidCallback? onPressed;
  final Color color;

  @override
  Widget build(BuildContext context) => IconButton(
    tooltip: tooltip,
    onPressed: onPressed,
    style: IconButton.styleFrom(
      minimumSize: const Size.square(44),
      fixedSize: const Size.square(44),
      foregroundColor: color,
      backgroundColor: color.withValues(alpha: .08),
      disabledForegroundColor: AppTheme.muted,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(13)),
    ),
    icon: Icon(icon, size: 24),
  );
}

class _Welcome extends StatelessWidget {
  const _Welcome({required this.user, required this.offline});
  final Map<String, dynamic>? user;
  final bool offline;

  @override
  Widget build(BuildContext context) {
    final name = '${user?['name'] ?? ''}'.trim();
    final message = switch ('${user?['role'] ?? ''}'.toLowerCase()) {
      'coordination_admin' => 'Voici l’essentiel de votre coordination.',
      'project_admin' => 'Voici l’essentiel de votre projet.',
      _ => 'Voici l’essentiel de votre activité.',
    };
    final statusColor = offline ? AppTheme.gray : AppTheme.green;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                name.isEmpty ? 'Bonjour 👋' : 'Bonjour, $name 👋',
                style: const TextStyle(
                  color: AppTheme.ink,
                  fontSize: 19,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                message,
                style: const TextStyle(color: AppTheme.muted, fontSize: 13),
              ),
            ],
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
          decoration: BoxDecoration(
            color: statusColor.withValues(alpha: .10),
            borderRadius: BorderRadius.circular(AppRadius.pill),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                offline ? Icons.cloud_off_outlined : Icons.circle,
                size: offline ? 15 : 8,
                color: statusColor,
              ),
              const SizedBox(width: 6),
              Text(
                offline ? 'Hors connexion' : 'En ligne',
                style: TextStyle(
                  color: statusColor,
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

class _SummaryCards extends StatelessWidget {
  const _SummaryCards({
    required this.role,
    required this.stats,
    required this.widgets,
  });
  final String role;
  final Map<String, dynamic> stats;
  final List<Map<String, dynamic>> widgets;

  @override
  Widget build(BuildContext context) {
    final items = role.toLowerCase() == 'coordination_admin'
        ? const <_SummaryData>[
            _SummaryData(
              'missions',
              'Ma coordination',
              'Mission pays',
              Icons.public_outlined,
              '/missions',
            ),
            _SummaryData(
              'projects',
              'Projets',
              'Projets actifs',
              Icons.business_center_outlined,
              '/projects',
            ),
            _SummaryData(
              'donors',
              'Bailleurs',
              'Bailleurs actifs',
              Icons.account_balance_outlined,
              '/funding',
            ),
            _SummaryData(
              'programs',
              'Programmes',
              'Programmes actifs',
              Icons.folder_outlined,
              '/funding',
            ),
          ]
        : widgets
              .map(
                (item) => _SummaryData(
                  '${item['key'] ?? ''}',
                  '${item['label'] ?? ''}',
                  '${item['caption'] ?? ''}',
                  ModuleIconRegistry.resolve(item['icon']?.toString()),
                  '${item['route'] ?? '/home'}',
                  value: item['value'],
                ),
              )
              .toList(growable: false);

    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 680 ? 4 : 2;
        final width =
            (constraints.maxWidth - AppSpacing.md * (columns - 1)) / columns;
        return Wrap(
          spacing: AppSpacing.md,
          runSpacing: AppSpacing.md,
          children: [
            for (final item in items)
              SizedBox(
                width: width,
                child: _SummaryCard(
                  data: item,
                  value: '${item.value ?? stats[item.key] ?? 0}',
                ),
              ),
          ],
        );
      },
    );
  }
}

class _SummaryData {
  const _SummaryData(
    this.key,
    this.label,
    this.caption,
    this.icon,
    this.route, {
    this.value,
  });
  final String key;
  final String label;
  final String caption;
  final IconData icon;
  final String route;
  final dynamic value;
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.data, required this.value});
  final _SummaryData data;
  final String value;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: InkWell(
      onTap: () => context.go(data.route),
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: AppTheme.orangeSoft,
                borderRadius: BorderRadius.circular(AppRadius.md),
              ),
              child: Icon(data.icon, color: AppTheme.orange, size: 23),
            ),
            const SizedBox(height: 10),
            Text(
              data.label,
              maxLines: 2,
              style: const TextStyle(
                color: AppTheme.ink,
                fontSize: 13,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              value,
              style: const TextStyle(
                color: AppTheme.orange,
                fontSize: 25,
                fontWeight: FontWeight.w900,
              ),
            ),
            Text(
              data.caption,
              maxLines: 2,
              style: const TextStyle(color: AppTheme.muted, fontSize: 11),
            ),
          ],
        ),
      ),
    ),
  );
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.title);
  final String title;
  @override
  Widget build(BuildContext context) => Text(
    title,
    style: const TextStyle(
      color: AppTheme.ink,
      fontSize: 18,
      fontWeight: FontWeight.w800,
    ),
  );
}

class _QuickActions extends StatelessWidget {
  const _QuickActions({required this.user, required this.navigation});
  final Map<String, dynamic>? user;
  final List<Map<String, dynamic>> navigation;

  @override
  Widget build(BuildContext context) {
    final role = '${user?['role'] ?? ''}'.toLowerCase();
    final permissions = ApplicationAccess.permissions(user);
    final actions = role == 'coordination_admin'
        ? <_ActionData>[
            if (permissions.contains('projects.manage'))
              const _ActionData(
                'Créer un projet',
                Icons.add_rounded,
                '/projects?create=1',
              ),
            if (permissions.contains('funding.view'))
              const _ActionData(
                'Bailleurs & Programmes',
                Icons.account_balance_outlined,
                '/funding',
              ),
            if (permissions.contains('standard_lists.view'))
              const _ActionData(
                'Liste standard du projet',
                Icons.format_list_bulleted_rounded,
                '/standard-lists',
              ),
          ]
        : navigation
              .where(
                (item) => !{'dashboard', 'profile'}.contains('${item['key']}'),
              )
              .take(4)
              .map(
                (item) => _ActionData(
                  '${item['label'] ?? ''}',
                  ModuleIconRegistry.resolve(item['icon']?.toString()),
                  '${item['path'] ?? '/home'}',
                ),
              )
              .toList(growable: false);

    if (actions.isEmpty) return const SizedBox.shrink();
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm,
          vertical: AppSpacing.lg,
        ),
        child: LayoutBuilder(
          builder: (context, constraints) {
            final width = constraints.maxWidth / actions.length;
            return Wrap(
              children: [
                for (final action in actions)
                  SizedBox(
                    width: width,
                    child: _QuickAction(data: action),
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _ActionData {
  const _ActionData(this.label, this.icon, this.route);
  final String label;
  final IconData icon;
  final String route;
}

class _QuickAction extends StatelessWidget {
  const _QuickAction({required this.data});
  final _ActionData data;
  @override
  Widget build(BuildContext context) => InkWell(
    onTap: () => context.go(data.route),
    borderRadius: BorderRadius.circular(AppRadius.md),
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              color: AppTheme.orangeSoft,
              borderRadius: BorderRadius.circular(18),
            ),
            child: Icon(data.icon, color: AppTheme.orange, size: 27),
          ),
          const SizedBox(height: 8),
          Text(
            data.label,
            maxLines: 2,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: AppTheme.ink,
              fontSize: 11,
              fontWeight: FontWeight.w700,
              height: 1.2,
            ),
          ),
        ],
      ),
    ),
  );
}

class _RecentProjects extends StatelessWidget {
  const _RecentProjects({required this.projects});
  final List<Map<String, dynamic>> projects;
  @override
  Widget build(BuildContext context) => AppDashboardPanel(
    title: 'Mes projets récents',
    icon: Icons.business_center_outlined,
    color: AppTheme.orange,
    action: TextButton(
      onPressed: () => context.go('/projects'),
      child: const Text('Voir tout'),
    ),
    child: Column(
      children: [
        for (var index = 0; index < projects.length; index++) ...[
          _ProjectRow(project: projects[index]),
          if (index < projects.length - 1) const Divider(height: 1),
        ],
      ],
    ),
  );
}

class _ProjectRow extends StatelessWidget {
  const _ProjectRow({required this.project});
  final Map<String, dynamic> project;
  @override
  Widget build(BuildContext context) {
    final donors = (project['donors'] as List? ?? const []).length;
    final programs = (project['programs'] as List? ?? const []).length;
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(vertical: 4),
      leading: Container(
        width: 46,
        height: 46,
        decoration: BoxDecoration(
          color: AppTheme.orangeSoft,
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        child: const Icon(
          Icons.business_center_outlined,
          color: AppTheme.orange,
        ),
      ),
      title: Text(
        '${project['name'] ?? 'Projet'}',
        maxLines: 2,
        style: const TextStyle(fontWeight: FontWeight.w700),
      ),
      subtitle: Text(
        'Code : ${project['code'] ?? '—'} · Bailleurs : $donors · Programmes : $programs',
        maxLines: 2,
      ),
      trailing: const Icon(Icons.chevron_right_rounded),
      onTap: () => context.go('/projects'),
    );
  }
}

class _RecentActivities extends StatelessWidget {
  const _RecentActivities({required this.activities});
  final List<Map<String, dynamic>> activities;
  @override
  Widget build(BuildContext context) => AppDashboardPanel(
    title: 'Activités récentes',
    icon: Icons.history_rounded,
    color: AppTheme.orange,
    child: activities.isEmpty
        ? const ListTile(
            contentPadding: EdgeInsets.zero,
            leading: Icon(Icons.info_outline_rounded, color: AppTheme.muted),
            title: Text('Aucune activité récente'),
          )
        : Column(
            children: [
              for (
                var index = 0;
                index < activities.length && index < 5;
                index++
              ) ...[
                _ActivityRow(item: activities[index]),
                if (index < activities.length - 1 && index < 4)
                  const Divider(height: 1),
              ],
            ],
          ),
  );
}

class _ActivityRow extends StatelessWidget {
  const _ActivityRow({required this.item});
  final Map<String, dynamic> item;
  @override
  Widget build(BuildContext context) {
    final date = '${item['created_at'] ?? ''}';
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: AppTheme.orangeSoft,
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        child: const Icon(Icons.check_rounded, color: AppTheme.orange),
      ),
      title: Text(
        _humanEvent('${item['event'] ?? ''}'),
        style: const TextStyle(fontWeight: FontWeight.w700),
      ),
      subtitle: date.isEmpty ? null : Text(_readableDate(date)),
    );
  }

  static String _humanEvent(String event) => switch (event.toLowerCase()) {
    'project.created' => 'Projet créé',
    'project.updated' => 'Projet modifié',
    'project.archived' => 'Projet archivé',
    'project.restored' => 'Projet restauré',
    'donor.created' => 'Bailleur ajouté',
    'donor.updated' => 'Bailleur modifié',
    'program.created' => 'Programme ajouté',
    'program.updated' => 'Programme modifié',
    'user.password_changed' => 'Mot de passe modifié',
    'user.created' => 'Utilisateur ajouté',
    _ => 'Activité enregistrée',
  };

  static String _readableDate(String value) {
    final parsed = DateTime.tryParse(value)?.toLocal();
    if (parsed == null) return '';
    final day = parsed.day.toString().padLeft(2, '0');
    final month = parsed.month.toString().padLeft(2, '0');
    final hour = parsed.hour.toString().padLeft(2, '0');
    final minute = parsed.minute.toString().padLeft(2, '0');
    return '$day/$month/${parsed.year} à $hour:$minute';
  }
}
