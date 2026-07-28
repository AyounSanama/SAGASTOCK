import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../data/auth_service.dart';

class SplashPage extends StatefulWidget {
  const SplashPage({super.key});
  @override
  State<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends State<SplashPage> {
  @override
  void initState() {
    super.initState();
    _route();
  }

  Future<void> _route() async {
    final service = AuthService();
    final hasSession = await service.hasSession();
    final user = hasSession ? await service.cachedUser() : null;
    if (mounted) {
      context.go(
        !hasSession
            ? '/login'
            : user?['must_change_password'] == true
            ? '/change-password'
            : '/home',
      );
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Image.asset(
            'assets/images/pharmacare-logo.png',
            height: 132,
            fit: BoxFit.contain,
            semanticLabel: 'Logo PharmaCare',
          ),
          const SizedBox(height: 16),
          const Text(
            'PharmaCare',
            style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 6),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 24),
            child: Text(
              'Putting Patients at the Heart of Every Supply.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 13),
            ),
          ),
          const SizedBox(height: 20),
          const CircularProgressIndicator(),
        ],
      ),
    ),
  );
}
