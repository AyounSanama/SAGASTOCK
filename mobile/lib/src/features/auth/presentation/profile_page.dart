import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../core/widgets/app_button.dart';
import '../data/auth_service.dart';
import '../../../core/widgets/app_navigation_drawer.dart';

class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});
  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  final _auth = AuthService();
  Map<String, dynamic>? _user;
  List<Map<String, dynamic>> _devices = [];
  bool _loading = true;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final user = await _auth.cachedUser();
    List<Map<String, dynamic>> devices = [];
    try {
      devices = await _auth.devices();
    } catch (_) {}
    if (mounted) {
      setState(() {
        _user = user;
        _devices = devices;
        _loading = false;
      });
    }
  }

  Future<void> _revoke(String id) async {
    await _auth.revokeDevice(id);
    await _load();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    drawer: const AppNavigationDrawer(),
    appBar: AppBar(title: const Text('Mon profil')),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : ListView(
            padding: const EdgeInsets.all(20),
            children: [
              Card(
                child: ListTile(
                  leading: const CircleAvatar(child: Icon(Icons.person)),
                  title: Text(_user?['name'] as String? ?? 'Utilisateur'),
                  subtitle: Text(_user?['email'] as String? ?? ''),
                ),
              ),
              const SizedBox(height: 16),
              AppButton.edit(
                expanded: true,
                label: 'Changer mon mot de passe',
                icon: Icons.password_rounded,
                onPressed: () => context.push('/change-password'),
              ),
              const SizedBox(height: 24),
              Text(
                'Appareils autoris\u00e9s',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              if (_devices.isEmpty)
                const Text(
                  'Aucun appareil synchronis\u00e9 ou serveur indisponible.',
                ),
              for (final device in _devices)
                Card(
                  child: ListTile(
                    leading: Icon(
                      device['platform'] == 'ios'
                          ? Icons.phone_iphone
                          : Icons.android,
                    ),
                    title: Text(device['name'] as String? ?? 'Appareil'),
                    subtitle: Text(
                      device['revoked_at'] == null
                          ? 'Actif'
                          : 'R\u00e9voqu\u00e9',
                    ),
                    trailing: device['revoked_at'] == null
                        ? IconButton(
                            tooltip: 'R\u00e9voquer',
                            onPressed: () => _revoke(device['id'] as String),
                            color: Theme.of(context).colorScheme.error,
                            icon: const Icon(Icons.block_rounded),
                          )
                        : null,
                  ),
                ),
              const SizedBox(height: 20),
              AppButton.delete(
                expanded: true,
                label: 'Se déconnecter',
                icon: Icons.logout_rounded,
                onPressed: () async {
                  await _auth.logout();
                  if (context.mounted) context.go('/login');
                },
              ),
            ],
          ),
  );
}
