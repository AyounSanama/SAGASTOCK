import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/data/auth_service.dart';
import '../access/application_access.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import 'pharmacare_wordmark.dart';

/// Sidebar unique, alimentée exclusivement par le manifeste de permissions.
class AppNavigationDrawer extends StatelessWidget {
  const AppNavigationDrawer({super.key});

  @override
  Widget build(BuildContext context) {
    return NavigationDrawer(
      backgroundColor: Colors.white,
      children: [
        const _BrandHeader(),
        const Divider(height: 1),
        const _DrawerSection('NAVIGATION'),
        FutureBuilder<Map<String, dynamic>?>(
          future: AuthService().cachedUser(),
          builder: (context, snapshot) => Column(
            children: [
              for (final item in ApplicationAccess.navigation(snapshot.data))
                _NavigationTile(item: item),
            ],
          ),
        ),
        const Divider(height: 32),
        ListTile(
          leading: const Icon(Icons.logout_rounded, color: AppTheme.danger),
          title: const Text(
            'Déconnexion',
            style: TextStyle(color: AppTheme.danger, fontWeight: FontWeight.w700),
          ),
          onTap: () async {
            Navigator.maybePop(context);
            await AuthService().logout();
            if (context.mounted) context.go('/login');
          },
        ),
        const SizedBox(height: 24),
      ],
    );
  }
}

class _BrandHeader extends StatelessWidget {
  const _BrandHeader();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.lg, AppSpacing.xl, AppSpacing.lg, AppSpacing.md),
      child: Row(
        children: [
          Image.asset('assets/images/pharmacare-logo.png', width: 52),
          const SizedBox(width: AppSpacing.md),
          const Expanded(
            child: PharmaCareWordmark(fontSize: 19, textAlign: TextAlign.start),
          ),
        ],
      ),
    );
  }
}

class _NavigationTile extends StatelessWidget {
  const _NavigationTile({required this.item});

  final ApplicationNavigationItem item;

  @override
  Widget build(BuildContext context) {
    final location = GoRouterState.of(context).uri.path;
    final selected =
        location == item.path || location.startsWith('${item.path}/');
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: AppSpacing.sm, vertical: 2),
      decoration: BoxDecoration(
        color: selected ? AppTheme.orangeSoft : Colors.transparent,
        borderRadius: BorderRadius.circular(AppRadius.sm),
        border: Border(
          left: BorderSide(
            color: selected ? AppTheme.orange : Colors.transparent,
            width: 3,
          ),
        ),
      ),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 14),
        minLeadingWidth: 28,
        selected: selected,
        selectedColor: AppTheme.orange,
        iconColor: selected ? AppTheme.orange : AppTheme.gray,
        textColor: selected ? AppTheme.orange : AppTheme.ink,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.sm)),
        leading: Icon(item.icon),
        title: Text(
          item.label,
          style: const TextStyle(fontWeight: FontWeight.w600),
        ),
        onTap: () {
          Navigator.maybePop(context);
          context.go(item.path);
        },
      ),
    );
  }
}

class _DrawerSection extends StatelessWidget {
  const _DrawerSection(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 22, 20, 7),
      child: Text(
        label,
        style: const TextStyle(
          color: AppTheme.primaryDark,
          fontSize: 11,
          fontWeight: FontWeight.w800,
          letterSpacing: 1.1,
        ),
      ),
    );
  }
}
