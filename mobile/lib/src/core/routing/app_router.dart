import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';

import '../../features/auth/presentation/change_password_page.dart';
import '../../features/auth/presentation/forgot_password_page.dart';
import '../../features/auth/presentation/login_page.dart';
import '../../features/auth/presentation/server_settings_page.dart';
import '../config/app_config.dart';
import '../../features/auth/presentation/profile_page.dart';
import '../../features/auth/presentation/reset_password_page.dart';
import '../../features/configuration/presentation/configuration_page.dart';
import '../../features/configuration/presentation/platform_standards_page.dart';
import '../../features/configuration/presentation/organization_assistance_page.dart';
import '../../features/home/presentation/home_page.dart';
import '../../features/home/presentation/role_home_page.dart';
import '../../features/organizations/presentation/funding_page.dart';
import '../../features/organizations/presentation/scoped_funding_page.dart';
import '../../features/organizations/presentation/missions_page.dart';
import '../../features/organizations/presentation/organizations_page.dart';
import '../../features/organizations/presentation/projects_page.dart';
import '../../features/organizations/presentation/scoped_missions_page.dart';
import '../../features/organizations/presentation/project_configuration_page.dart';
import '../../features/receipts/presentation/receipts_page.dart';
import '../../features/dispensations/presentation/clinical_supply_page.dart';
import '../../features/inventories/presentation/inventories_page.dart';
import '../../features/notifications/presentation/notifications_page.dart';
import '../../features/reports/presentation/operational_reports_page.dart';
import '../../features/orders/presentation/orders_page.dart';
import '../../features/stocks/presentation/stocks_page.dart';
import '../../features/stocks/presentation/batches_page.dart';
import '../../features/structures/presentation/facilities_page.dart';
import '../../features/structures/presentation/scoped_facilities_page.dart';
import '../../features/structures/presentation/scoped_sites_page.dart';
import '../../features/users/presentation/scoped_users_page.dart';
import '../widgets/main_navigation_shell.dart';
import '../widgets/authorized_module_page.dart';
import '../access/application_access.dart';
import '../../features/auth/data/auth_service.dart';
import '../../features/catalog/presentation/catalog_page.dart';
import '../../features/catalog/presentation/standard_list_entry_page.dart';
import '../../features/projects_wizard/presentation/project_wizard_page.dart';
import '../../features/coordination/presentation/coordination_standard_list_page.dart';
import '../../features/project_admin/presentation/project_admin_facilities_page.dart';
import '../../features/project_admin/presentation/project_admin_facility_page.dart';
import '../../features/project_admin/presentation/project_admin_standard_list_page.dart';
import '../../features/project_admin/presentation/role_page.dart';

GoRouter createAppRouter({required String initialLocation}) => GoRouter(
  initialLocation: initialLocation,
  redirect: (context, state) async {
    final public = {
      '/login',
      '/forgot-password',
      '/reset-password',
      if (AppConfig.runtimeOverrideAllowed) '/server-settings',
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
    if (!ApplicationAccess.moduleAvailable(user, state.uri.path)) {
      return '/home';
    }
    final required = ApplicationAccess.requiredPermission(state.uri.path);
    return ApplicationAccess.allows(user, required) ? null : '/home';
  },
  routes: [
    GoRoute(path: '/login', builder: (context, state) => const LoginPage()),
    if (AppConfig.runtimeOverrideAllowed)
      GoRoute(
        path: '/server-settings',
        builder: (context, state) => const ServerSettingsPage(),
      ),
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
    // Niveau 5 — Fiche FOSA de l'Admin Projet (maquette mobile 07), plein écran.
    GoRoute(
      path: '/health-facilities/new',
      builder: (context, state) => const ProjectAdminFacilityPage(),
    ),
    GoRoute(
      path: '/health-facilities/:facilityId/configure',
      builder: (context, state) => ProjectAdminFacilityPage(
        facilityId: state.pathParameters['facilityId'],
      ),
    ),
    // Niveau 6 — Liste Standard de la Coordination (décochage par FOSA, code-barres).
    GoRoute(
      path: '/coordination/standard-list',
      builder: (context, state) => const CoordinationStandardListPage(),
    ),
    // Niveau 2 — Assistant « Créer un projet / programme », plein écran.
    GoRoute(
      path: '/projects/new',
      builder: (context, state) => const ProjectWizardPage(),
    ),
    GoRoute(
      path: '/projects/:projectId/wizard',
      builder: (context, state) => ProjectWizardPage(
        projectId: state.pathParameters['projectId'],
        initialStep: state.uri.queryParameters['step'],
      ),
    ),
    ShellRoute(
      builder: (context, state, child) =>
          MainLayout(location: state.uri.path, child: child),
      routes: [
        GoRoute(
          path: '/configuration',
          builder: (context, state) => const ConfigurationPage(),
        ),
        GoRoute(
          path: '/standards',
          builder: (context, state) => const PlatformStandardsPage(),
        ),
        GoRoute(
          path: '/standards/assistance',
          builder: (context, state) => const PlatformStandardsPage(
            section: PlatformStandardsSection.assistance,
          ),
        ),
        GoRoute(
          path: '/standards/history',
          builder: (context, state) => const PlatformStandardsPage(
            section: PlatformStandardsSection.history,
          ),
        ),
        GoRoute(
          path: '/standards/assistance/:organizationId',
          builder: (context, state) => OrganizationAssistancePage(
            organizationId: state.pathParameters['organizationId']!,
          ),
        ),
        GoRoute(path: '/home', builder: (context, state) => const RoleHomePage()),
        GoRoute(
          path: '/notifications',
          builder: (context, state) => const NotificationsPage(),
        ),
        GoRoute(
          path: '/sago/dashboard',
          builder: (context, state) => const HomePage(),
        ),
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
          path: '/inventories',
          builder: (context, state) => const InventoriesPage(),
        ),
        GoRoute(
          path: '/orders',
          builder: (context, state) => const OrdersPage(),
        ),
        GoRoute(
          path: '/reports',
          builder: (context, state) => const OperationalReportsPage(),
        ),
        GoRoute(
          path: '/organizations',
          builder: (context, state) => const OrganizationsPage(),
        ),
        GoRoute(
          path: '/missions',
          builder: (context, state) => ScopedMissionsPage(
            initialTab: state.uri.queryParameters['tab'],
          ),
        ),
        GoRoute(
          path: '/projects',
          builder: (context, state) => ProjectConfigurationPage(
            openCreate: state.uri.queryParameters['create'] == '1',
          ),
        ),
        GoRoute(
          path: '/funding',
          builder: (context, state) => const ScopedFundingPage(),
        ),
        // Niveau 5 : écrans de l'Admin Projet (maquettes mobiles 06 et 08).
        GoRoute(
          path: '/health-facilities',
          builder: (context, state) => const ProjectAdminOr(
            projectAdmin: ProjectAdminFacilitiesPage(),
            other: ScopedFacilitiesPage(),
          ),
        ),
        GoRoute(
          path: '/dispensing-sites',
          builder: (context, state) => const ScopedSitesPage(),
        ),
        GoRoute(
          path: '/users',
          builder: (context, state) => const ScopedUsersPage(),
        ),
        GoRoute(
          path: '/products',
          builder: (context, state) => const CatalogPage(initialTab: 0),
        ),
        GoRoute(
          path: '/standard-lists',
          builder: (context, state) => const ProjectAdminOr(
            projectAdmin: ProjectAdminStandardListPage(),
            other: StandardListEntryPage(),
          ),
        ),
        for (final module in const <(String, String, IconData)>[
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
