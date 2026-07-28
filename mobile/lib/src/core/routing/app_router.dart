import 'package:go_router/go_router.dart';
import '../../features/auth/presentation/change_password_page.dart';
import '../../features/auth/presentation/forgot_password_page.dart';
import '../../features/auth/presentation/login_page.dart';
import '../../features/auth/presentation/profile_page.dart';
import '../../features/auth/presentation/reset_password_page.dart';
import '../../features/auth/presentation/splash_page.dart';
import '../../features/home/presentation/home_page.dart';
import '../../features/stocks/presentation/stocks_page.dart';
import '../../features/organizations/presentation/organizations_page.dart';
import '../../features/organizations/presentation/missions_page.dart';
import '../../features/organizations/presentation/projects_page.dart';
import '../../features/organizations/presentation/funding_page.dart';

final appRouter = GoRouter(
  initialLocation: '/splash',
  routes: [
    GoRoute(path: '/splash', builder: (context, state) => const SplashPage()),
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
    GoRoute(path: '/profile', builder: (context, state) => const ProfilePage()),
    GoRoute(path: '/home', builder: (context, state) => const HomePage()),
    GoRoute(path: '/stocks', builder: (context, state) => const StocksPage()),
    GoRoute(
      path: '/organizations',
      builder: (context, state) => const OrganizationsPage(),
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
);
