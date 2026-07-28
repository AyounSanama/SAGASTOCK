import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../auth/data/auth_service.dart';

class HomePage extends StatelessWidget {
  const HomePage({super.key});

  static const _modules = <({IconData icon, String label, String? route})>[
    (
      icon: Icons.business_outlined,
      label: 'Organisations / ONG',
      route: '/organizations',
    ),
    (icon: Icons.inventory_2_outlined, label: 'Stocks', route: '/stocks'),
    (icon: Icons.move_to_inbox_outlined, label: 'R\u00e9ceptions', route: null),
    (icon: Icons.medication_outlined, label: 'Dispensation', route: null),
    (icon: Icons.fact_check_outlined, label: 'Inventaires', route: null),
    (icon: Icons.shopping_cart_outlined, label: 'Commandes', route: null),
    (icon: Icons.assessment_outlined, label: 'Rapports', route: null),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: Padding(
          padding: const EdgeInsets.all(7),
          child: ClipOval(
            child: Image.asset(
              'assets/images/pharmacare-logo.png',
              fit: BoxFit.cover,
              semanticLabel: 'Logo PharmaCare',
            ),
          ),
        ),
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('PharmaCare'),
            Text(
              'Putting Patients at the Heart of Every Supply.',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(fontSize: 9, fontWeight: FontWeight.w400),
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Mon profil',
            onPressed: () => context.push('/profile'),
            icon: const Icon(Icons.account_circle_outlined),
          ),
          IconButton(
            tooltip: 'Synchroniser',
            onPressed: () {},
            icon: const Icon(Icons.sync),
          ),
          IconButton(
            tooltip: 'D\u00e9connexion',
            onPressed: () async {
              await AuthService().logout();
              if (context.mounted) context.go('/login');
            },
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            const _OfflineBanner(),
            const SizedBox(height: 24),
            Text(
              'Tableau de bord',
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 16),
            LayoutBuilder(
              builder: (context, constraints) {
                final columns = constraints.maxWidth >= 700 ? 3 : 2;
                return GridView.count(
                  crossAxisCount: columns,
                  crossAxisSpacing: 12,
                  mainAxisSpacing: 12,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  children: [
                    for (final module in _modules)
                      _ModuleCard(
                        icon: module.icon,
                        label: module.label,
                        route: module.route,
                      ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _OfflineBanner extends StatelessWidget {
  const _OfflineBanner();

  @override
  Widget build(BuildContext context) {
    return Card(
      color: Theme.of(context).colorScheme.primaryContainer,
      child: const Padding(
        padding: EdgeInsets.all(16),
        child: Row(
          children: [
            Icon(Icons.cloud_done_outlined),
            SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Pr\u00eat pour le travail hors connexion',
                    style: TextStyle(fontWeight: FontWeight.w700),
                  ),
                  SizedBox(height: 2),
                  Text('Derni\u00e8re synchronisation : aucune'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ModuleCard extends StatelessWidget {
  const _ModuleCard({required this.icon, required this.label, this.route});

  final IconData icon;
  final String label;
  final String? route;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: route == null ? null : () => context.push(route!),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                icon,
                size: 40,
                color: Theme.of(context).colorScheme.primary,
              ),
              const SizedBox(height: 12),
              Text(
                label,
                textAlign: TextAlign.center,
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
