import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/access/application_access.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_navigation_drawer.dart';
import '../../auth/data/auth_service.dart';
import '../data/organization_service.dart';
import 'organization_creation_page.dart';

class OrganizationsPage extends StatefulWidget {
  const OrganizationsPage({super.key});

  @override
  State<OrganizationsPage> createState() => _OrganizationsPageState();
}

class _OrganizationsPageState extends State<OrganizationsPage> {
  final _service = OrganizationService();
  final _search = TextEditingController();
  List<Map<String, dynamic>> _organizations = [];
  bool _loading = true;
  String? _error;
  // Actions de la carte selon les droits du compte (rien d'interdit n'est proposé).
  Map<String, dynamic>? _user;

  @override
  void initState() {
    super.initState();
    AuthService().cachedUser().then((user) {
      if (mounted) setState(() => _user = user);
    });
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final values = await _service.list(search: _search.text.trim());
      if (mounted) setState(() => _organizations = values);
    } on DioException catch (error) {
      if (mounted) {
        setState(
          () => _error = error.response?.statusCode == 403
              ? 'Vous n’avez pas la permission de consulter les organisations.'
              : 'Impossible de charger les organisations.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  /* Ancien mini-formulaire conservé temporairement dans l'historique Git.
  Future<void> _legacyCreate() async {
    final code = TextEditingController();
    final name = TextEditingController();
    final country = TextEditingController();
    final formKey = GlobalKey<FormState>();
    var saving = false;
    final saved = await showAppFormSheet<bool>(
      context: context,
      title: 'Nouvelle organisation',
      description:
          'Renseignez les informations principales de l’organisation.',
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: formKey,
            child: Column(
              children: [
                TextFormField(
                  controller: code,
                  decoration: const InputDecoration(
                    labelText: 'Code unique',
                    prefixIcon: Icon(Icons.tag_rounded),
                  ),
                  validator: (value) =>
                      value?.trim().isEmpty == true ? 'Champ obligatoire' : null,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: name,
                  decoration: const InputDecoration(
                    labelText: 'Nom de l’organisation',
                    prefixIcon: Icon(Icons.business_outlined),
                  ),
                  validator: (value) =>
                      value?.trim().isEmpty == true ? 'Champ obligatoire' : null,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: country,
                  maxLength: 2,
                  textCapitalization: TextCapitalization.characters,
                  decoration: const InputDecoration(
                    labelText: 'Code pays',
                    hintText: 'Ex. CM',
                    prefixIcon: Icon(Icons.public_rounded),
                  ),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: AppButton.cancel(
                        label: 'Annuler',
                        onPressed: saving
                            ? null
                            : () => Navigator.pop(sheetContext, false),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: AppButton.add(
                        label: 'Créer',
                        icon: Icons.add_business_rounded,
                        loading: saving,
                        onPressed: () async {
                          if (!(formKey.currentState?.validate() ?? false)) {
                            return;
                          }
                          setSheetState(() => saving = true);
                          try {
                            await _service.create(
                              code: code.text.trim(),
                              name: name.text.trim(),
                              countryCode: country.text.trim(),
                            );
                            if (sheetContext.mounted) {
                              Navigator.pop(sheetContext, true);
                            }
                          } on DioException catch (error) {
                            setSheetState(() => saving = false);
                            if (!sheetContext.mounted) return;
                            ScaffoldMessenger.of(sheetContext).showSnackBar(
                              SnackBar(
                                content: Text(
                                  error.response?.statusCode == 403
                                      ? 'Vous n’avez pas la permission de créer une organisation.'
                                      : 'Création impossible. Vérifiez les informations.',
                                ),
                              ),
                            );
                          }
                        },
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
    code.dispose();
    name.dispose();
    country.dispose();
    if (saved == true) await _load();
  }
  */

  Future<void> _create() async {
    final result = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(builder: (_) => const OrganizationCreationPage()),
    );
    if (result == null || !mounted) return;
    await _load();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: const Text(
          'Organisation et compte Admin Coordination créés avec succès. L’utilisateur devra modifier son mot de passe lors de sa première connexion.',
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      drawer: const AppNavigationDrawer(),
      appBar: AppBar(title: const Text('Organisations')),
      floatingActionButton: AppFab(
        onPressed: _create,
        tooltip: 'Ajouter une organisation',
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 20, 16, 28),
          children: [
            Text(
              'Organisations',
              style: Theme.of(
                context,
              ).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 6),
            Text(
              'Gérez les ONG et leur hiérarchie opérationnelle.',
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 18),
            SearchBar(
              controller: _search,
              hintText: 'Rechercher une organisation…',
              leading: const Icon(Icons.search_rounded),
              trailing: [
                IconButton(
                  tooltip: 'Rechercher',
                  onPressed: _load,
                  icon: const Icon(Icons.arrow_forward_rounded),
                ),
              ],
              onSubmitted: (_) => _load(),
            ),
            const SizedBox(height: 16),
            if (_loading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(36),
                  child: CircularProgressIndicator(),
                ),
              )
            else if (_error != null)
              _MessageCard(
                icon: Icons.cloud_off_rounded,
                title: 'Chargement impossible',
                message: _error!,
                action: AppButton.text(
                  label: 'Réessayer',
                  icon: Icons.refresh_rounded,
                  onPressed: _load,
                ),
              )
            else if (_organizations.isEmpty)
              _MessageCard(
                icon: Icons.business_outlined,
                title: 'Aucune organisation trouvée',
                message: 'Créez la première organisation pour commencer.',
                action: AppButton.add(
                  label: 'Nouvelle organisation',
                  icon: Icons.add_rounded,
                  onPressed: _create,
                ),
              )
            else
              for (final organization in _organizations) ...[
                _OrganizationCard(
                  organization: organization,
                  onTap: ApplicationAccess.allows(_user, 'missions.view')
                      ? () => context.push(
                          '/organizations/${organization['id']}/missions',
                          extra: organization['name'] as String,
                        )
                      : null,
                  onFacilities:
                      ApplicationAccess.allows(_user, 'health_facilities.view')
                      ? () => context.push(
                          '/organizations/${organization['id']}/facilities',
                          extra: organization['name'] as String,
                        )
                      : null,
                  // Admin Sago : détail et paramètres de plateforme de l'organisation.
                  onSettings:
                      ApplicationAccess.allows(_user, 'platform_standards.view')
                      ? () => context.push(
                          '/standards/assistance/${organization['id']}',
                        )
                      : null,
                ),
                const SizedBox(height: 12),
              ],
          ],
        ),
      ),
    );
  }
}

class _OrganizationCard extends StatelessWidget {
  const _OrganizationCard({
    required this.organization,
    this.onTap,
    this.onFacilities,
    this.onSettings,
  });
  final Map<String, dynamic> organization;
  final VoidCallback? onTap;
  final VoidCallback? onFacilities;
  final VoidCallback? onSettings;

  @override
  Widget build(BuildContext context) {
    final active = organization['is_active'] == true;
    final name = organization['name'] as String? ?? 'Organisation';
    final actions = <Widget>[
      if (onTap != null)
        AppButton.text(
          compact: true,
          label: 'Missions et projets',
          icon: Icons.account_tree_outlined,
          onPressed: onTap,
        ),
      if (onFacilities != null)
        AppButton.view(
          compact: true,
          label: 'Formations sanitaires',
          icon: Icons.local_hospital_outlined,
          onPressed: onFacilities,
        ),
      if (onSettings != null)
        AppButton.view(
          compact: true,
          label: 'Détail et paramètres',
          icon: Icons.tune_rounded,
          onPressed: onSettings,
        ),
    ];
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onSettings ?? onTap,
        child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            Row(
              children: [
                CircleAvatar(
                  radius: 24,
                  child: Text(name.substring(0, 1).toUpperCase()),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        name,
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${organization['code']} · ${organization['country_code'] ?? 'Pays non renseigné'}',
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                        ),
                      ),
                      const SizedBox(height: 9),
                      Chip(
                        avatar: Icon(
                          active ? Icons.check_circle : Icons.pause_circle,
                          size: 16,
                        ),
                        label: Text(active ? 'Active' : 'Inactive'),
                        visualDensity: VisualDensity.compact,
                      ),
                    ],
                  ),
                ),
              ],
            ),
            if (actions.isNotEmpty) ...[
              const Divider(height: 22),
              Row(
                children: [
                  for (var i = 0; i < actions.length; i++) ...[
                    if (i > 0) const SizedBox(width: 8),
                    Expanded(child: actions[i]),
                  ],
                ],
              ),
            ],
          ],
        ),
        ),
      ),
    );
  }
}

class _MessageCard extends StatelessWidget {
  const _MessageCard({
    required this.icon,
    required this.title,
    required this.message,
    required this.action,
  });
  final IconData icon;
  final String title;
  final String message;
  final Widget action;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          children: [
            Icon(icon, size: 42),
            const SizedBox(height: 12),
            Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
            const SizedBox(height: 6),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            action,
          ],
        ),
      ),
    );
  }
}
