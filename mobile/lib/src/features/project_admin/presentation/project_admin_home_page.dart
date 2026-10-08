import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_badge.dart';
import '../../../core/widgets/app_navigation_drawer.dart';
import '../data/project_admin_service.dart';
import 'project_admin_widgets.dart';

/// Niveau 5 — Accueil de l'Admin Projet (maquette mobile AdminProjet 05) :
/// indicateurs du projet, FOSA à surveiller, synchronisation des FOSA.
class ProjectAdminHomePage extends StatefulWidget {
  const ProjectAdminHomePage({this.service, super.key});

  final ProjectAdminService? service;

  @override
  State<ProjectAdminHomePage> createState() => _ProjectAdminHomePageState();
}

class _ProjectAdminHomePageState extends State<ProjectAdminHomePage> {
  late final ProjectAdminService _service =
      widget.service ?? ProjectAdminService();
  Map<String, dynamic> _data = const {};
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await _service.dashboard();
      if (mounted) setState(() => _data = data);
    } catch (_) {
      // Message « aucune donnée » ci-dessous.
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final project = asMap(_data['project']);
    final stats = asMap(_data['stats']);
    final sync = asMaps(_data['sync']);
    final watch = (_data['watch'] as num?)?.toInt() ?? 0;
    final failed = (stats['sync_failed'] as num?)?.toInt() ?? 0;
    return Scaffold(
      backgroundColor: AppColors.background,
      // Menu principal (Mon projet, Équipe FOSA…) : bouton de l'en-tête.
      drawer: const AppNavigationDrawer(),
      body: Column(
        children: [
          ProjectAdminHeader(
            trailing: Builder(
              builder: (context) => IconButton(
                tooltip: 'Menu principal',
                style: IconButton.styleFrom(
                  backgroundColor: const Color(0x26FFFFFF),
                  foregroundColor: Colors.white,
                ),
                icon: const Icon(Icons.menu, color: Colors.white),
                onPressed: () => Scaffold.of(context).openDrawer(),
              ),
            ),
            overline: [
              'Admin Projet',
              project['mission'],
            ].whereType<String>().join(' · '),
            title: project.isEmpty ? 'Mon projet' : 'Projet ${project['code']}',
            subtitle: _data.isEmpty
                ? null
                : _data['last_sync_at'] == null
                ? 'Aucune FOSA synchronisée'
                : 'Synchronisé ${agoLabel(_data['last_sync_at'])}',
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
                children: _loading && _data.isEmpty
                    ? const [
                        Padding(
                          padding: EdgeInsets.all(32),
                          child: Center(child: CircularProgressIndicator()),
                        ),
                      ]
                    : _data.isEmpty
                    ? const [
                        ProjectAdminMessage(
                          'Aucune donnée enregistrée sur ce téléphone. Connectez-vous à internet une première fois.',
                        ),
                      ]
                    : [
                        // Deux tuiles par ligne, hauteur selon le contenu (taille de texte du téléphone).
                        for (final pair in [
                          [
                            ProjectAdminKpi(
                              label: 'FOSA actives',
                              onTap: () => context.go('/health-facilities'),
                              value:
                                  '${stats['active_facilities'] ?? 0} / ${stats['facilities'] ?? 0}',
                            ),
                            ProjectAdminKpi(
                              label: 'Comptes FOSA',
                              onTap: () => context.go('/users'),
                              value: '${stats['accounts'] ?? 0}',
                            ),
                          ],
                          [
                            ProjectAdminKpi(
                              label: 'Échecs de synchro',
                              onTap: () => context.go('/health-facilities'),
                              value: '$failed',
                              color: failed > 0 ? AppColors.dangerText : null,
                            ),
                            ProjectAdminKpi(
                              label: 'Liste Standard',
                              onTap: () => context.go('/standard-lists'),
                              value: '${stats['standard_list_products'] ?? 0}',
                              color: AppColors.primaryStrong,
                            ),
                          ],
                        ])
                          Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: IntrinsicHeight(
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  Expanded(child: pair[0]),
                                  const SizedBox(width: 10),
                                  Expanded(child: pair[1]),
                                ],
                              ),
                            ),
                          ),
                        const SizedBox(height: 12),
                        if (watch > 0)
                          Container(
                            margin: const EdgeInsets.only(bottom: 12),
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: AppColors.dangerSurface,
                              borderRadius: BorderRadius.circular(AppRadius.md),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '$watch FOSA à surveiller',
                                  style: TextStyle(
                                    color: AppColors.dangerText,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                Text(
                                  'Pas de synchronisation depuis plus de 3 jours, ou opérations refusées.',
                                  style: TextStyle(color: AppColors.dangerText),
                                ),
                              ],
                            ),
                          ),
                        Row(
                          children: [
                            const Expanded(
                              child: Text(
                                'Synchronisation des FOSA',
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                            TextButton(
                              onPressed: () => context.go('/health-facilities'),
                              child: const Text('Tout voir'),
                            ),
                          ],
                        ),
                        if (sync.isEmpty)
                          const ProjectAdminMessage(
                            'Aucune FOSA validée dans ce projet.',
                          )
                        else
                          ProjectAdminCard(
                            padding: EdgeInsets.zero,
                            child: Column(
                              children: [
                                for (final (index, row)
                                    in sync.take(6).indexed) ...[
                                  if (index > 0) const Divider(height: 1),
                                  ListTile(
                                    title: Text(
                                      '${row['name']}',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                    subtitle: Text(
                                      [
                                        row['category'],
                                        agoLabel(row['last_contact_at']),
                                      ].whereType<String>().join(' · '),
                                    ),
                                    trailing: AppBadge(
                                      label: '${row['sync_label'] ?? ''}',
                                      variant: badgeVariant(row['sync_status']),
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                      ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
