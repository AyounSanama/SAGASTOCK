import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';

import '../../features/auth/presentation/change_password_page.dart';
import '../../features/auth/presentation/forgot_password_page.dart';
import '../../features/auth/presentation/login_page.dart';
import '../../features/auth/presentation/profile_page.dart';
import '../../features/auth/presentation/reset_password_page.dart';
import '../../features/configuration/presentation/configuration_page.dart';
import '../../features/home/presentation/home_page.dart';
import '../../features/organizations/presentation/funding_page.dart';
import '../../features/organizations/presentation/missions_page.dart';
import '../../features/organizations/presentation/organizations_page.dart';
import '../../features/organizations/presentation/projects_page.dart';
import '../../features/receipts/presentation/receipts_page.dart';
import '../../features/dispensations/presentation/clinical_supply_page.dart';
import '../../features/stocks/presentation/stocks_page.dart';
import '../../features/stocks/presentation/batches_page.dart';
import '../../features/structures/presentation/facilities_page.dart';
import '../widgets/main_navigation_shell.dart';
import '../widgets/authorized_module_page.dart';
import '../access/application_access.dart';
import '../../features/auth/data/auth_service.dart';
import '../../features/catalog/presentation/catalog_page.dart';

GoRouter createAppRouter({required String initialLocation}) => GoRouter(
  initialLocation: initialLocation,
  redirect: (context, state) async {
    final public = {
      '/login',
      '/forgot-password',
      '/reset-password',
    }.contains(state.uri.path);
    final auth = AuthService();
    final hasSession = await auth.hasSession();
    if (!hasSession && !public) return '/login';
    if (hasSession && state.uri.path == '/login') {
      return ApplicationAccess.landingPath(await auth.cachedUser());
    }
    if (!hasSession || public || state.uri.path == '/change-password') {
      return null;
    }
    final user = await auth.cachedUser();
    final required = ApplicationAccess.requiredPermission(state.uri.path);
    return ApplicationAccess.allows(user, required) ? null : '/home';
  },
  routes: [
    GoRoute(path: '/login', builder: (context, state) => const LoginPage()),
    GoRoute(
      path: '/forgot-password',
      builder: (context, state) => const ForgotPasswordPage(),
    ),
    GoRoute(
      path: '/reset-password',
      builder: (context, state) => const ResetPasswordPage(),
    ),
    GoRoute(
      path: '/change-password',
      builder: (context, state) => const ChangePasswordPage(),
    ),
    ShellRoute(
      builder: (context, state, child) =>
          MainLayout(location: state.uri.path, child: child),
      routes: [
        GoRoute(
          path: '/configuration',
          builder: (context, state) => const ConfigurationPage(),
        ),
        GoRoute(path: '/home', builder: (context, state) => const HomePage()),
        GoRoute(
          path: '/stocks',
          builder: (context, state) => const StocksPage(),
        ),
        GoRoute(
          path: '/stocks/lots',
          builder: (context, state) => const BatchesPage(),
        ),
        GoRoute(
          path: '/prescriptions',
          builder: (context, state) => const ClinicalSupplyPage(initialTab: 1),
        ),
        GoRoute(
          path: '/dispensations',
          builder: (context, state) => const ClinicalSupplyPage(initialTab: 2),
        ),
        GoRoute(
          path: '/profile',
          builder: (context, state) => const ProfilePage(),
        ),
        GoRoute(
          path: '/receipts',
          builder: (context, state) => const ReceiptsPage(),
        ),
        GoRoute(
          path: '/organizations',
          builder: (context, state) => const OrganizationsPage(),
        ),
        GoRoute(
          path: '/products',
          builder: (context, state) => const CatalogPage(initialTab: 0),
        ),
        GoRoute(
          path: '/standard-lists',
          builder: (context, state) => const CatalogPage(initialTab: 2),
        ),
        for (final module in const <(String, String, IconData)>[
          ('/missions', 'Missions', Icons.public_outlined),
          ('/projects', 'Projets', Icons.account_tree_outlined),
          ('/funding', 'Bailleurs et programmes', Icons.handshake_outlined),
          (
            '/health-facilities',
            'Formations sanitaires',
            Icons.local_hospital_outlined,
          ),
          (
            '/dispensing-sites',
            'Sites de dispensation',
            Icons.location_on_outlined,
          ),
          ('/users', 'Utilisateurs', Icons.people_outline),
          ('/inventories', 'Inventaires', Icons.inventory_outlined),
          ('/orders', 'Commandes', Icons.shopping_cart_outlined),
          ('/reports', 'Rapports', Icons.assessment_outlined),
          ('/synchronization', 'Synchronisation', Icons.sync_outlined),
          ('/settings', 'Paramètres organisation', Icons.settings_outlined),
          ('/project-settings', 'Paramètres du projet', Icons.tune_outlined),
          ('/site-settings', 'Paramètres du site', Icons.tune_outlined),
          ('/activity-log', 'Journal des activités', Icons.history_outlined),
          ('/activity-log-local', 'Journal local', Icons.history_outlined),
        ])
          GoRoute(
            path: module.$1,
            builder: (context, state) =>
                AuthorizedModulePage(title: module.$2, icon: module.$3),
          ),
        GoRoute(
          path: '/organizations/:organizationId/facilities',
          builder: (context, state) => FacilitiesPage(
            organizationId: state.pathParameters['organizationId']!,
            organizationName: state.extra as String? ?? 'Organisation',
          ),
        ),
        GoRoute(
          path: '/organizations/:organizationId/missions',
          builder: (context, state) => MissionsPage(
            organizationId: state.pathParameters['organizationId']!,
            organizationName: state.extra as String? ?? 'Missions',
          ),
        ),
        GoRoute(
          path: '/organizations/:organizationId/missions/:missionId/projects',
          builder: (context, state) => ProjectsPage(
            organizationId: state.pathParameters['organizationId']!,
            missionId: state.pathParameters['missionId']!,
            missionName: state.extra as String? ?? 'Projets',
          ),
        ),
        GoRoute(
          path: '/organizations/:organizationId/projects/:projectId/funding',
          builder: (context, state) => FundingPage(
            organizationId: state.pathParameters['organizationId']!,
            projectId: state.pathParameters['projectId']!,
            projectName: state.extra as String? ?? 'Financements',
          ),
        ),
      ],
    ),
    GoRoute(path: '/medications', redirect: (context, state) => '/stocks'),
  ],
);
