import 'package:flutter/material.dart';

import '../../../core/access/session_scope.dart';
import '../../../core/theme/app_theme.dart';
import '../../auth/data/auth_service.dart';
import '../data/catalog_service.dart';
import 'catalog_page.dart';

class StandardListEntryPage extends StatelessWidget {
  const StandardListEntryPage({super.key});

  @override
  Widget build(BuildContext context) => FutureBuilder<Map<String, dynamic>?>(
    future: AuthService().cachedUser(),
    builder: (context, snapshot) {
      if (!snapshot.hasData) {
        return const Scaffold(body: Center(child: CircularProgressIndicator()));
      }
      return '${snapshot.data?['role'] ?? ''}'.toLowerCase() == 'project_admin'
          ? ProjectStandardListPage(user: snapshot.data!)
          : const CatalogPage(initialTab: 2);
    },
  );
}

class ProjectStandardListPage extends StatefulWidget {
  const ProjectStandardListPage({required this.user, super.key});
  final Map<String, dynamic> user;

  @override
  State<ProjectStandardListPage> createState() =>
      _ProjectStandardListPageState();
}

class _ProjectStandardListPageState extends State<ProjectStandardListPage> {
  final _service = CatalogService();
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _data = const {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final projectId = SessionScope.projectId(widget.user);
      final organizationId = SessionScope.organizationId(widget.user);
      if (projectId.isEmpty || organizationId.isEmpty) {
        throw StateError('missing scope');
      }
      final data = await _service.projectStandardList(
        projectId,
        organizationId: organizationId,
      );
      if (mounted) setState(() => _data = data);
    } catch (_) {
      if (mounted) {
        setState(
          () => _error =
              'Impossible de charger la liste standard. Vérifiez la connexion puis réessayez.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final project = _data['project'] as Map?;
    final list = _data['list'] as Map?;
    final versions = (list?['versions'] as List? ?? const [])
        .whereType<Map>()
        .toList();
    final published = versions
        .where((v) => '${v['status']}' == 'published')
        .firstOrNull;
    final version = published ?? versions.firstOrNull;
    final products = (version?['products'] as List? ?? const [])
        .whereType<Map>()
        .toList();
    return Scaffold(
      appBar: AppBar(title: const Text('Liste standard du projet')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (_loading)
              const Padding(
                padding: EdgeInsets.all(48),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null)
              _Message(message: _error!, retry: _load)
            else ...[
              Card(
                child: ListTile(
                  leading: const CircleAvatar(
                    backgroundColor: AppTheme.orangeSoft,
                    child: Icon(
                      Icons.business_center_outlined,
                      color: AppTheme.orange,
                    ),
                  ),
                  title: Text(
                    '${project?['name'] ?? 'Mon projet'}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  subtitle: Text('Code : ${project?['code'] ?? '—'}'),
                ),
              ),
              const SizedBox(height: 16),
              if (list == null)
                const _Message(
                  message:
                      'Aucune liste standard n’est encore publiée pour ce projet.',
                )
              else ...[
                Text(
                  '${list['name'] ?? 'Liste standard'}',
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Version ${version?['version_number'] ?? '—'}',
                  style: const TextStyle(color: AppTheme.muted),
                ),
                const SizedBox(height: 16),
                if (products.isEmpty)
                  const _Message(
                    message: 'Cette liste standard ne contient aucun produit.',
                  )
                else
                  ...products.map(
                    (product) => Card(
                      child: ListTile(
                        leading: const Icon(
                          Icons.medication_outlined,
                          color: AppTheme.orange,
                        ),
                        title: Text(
                          '${product['name'] ?? product['designation'] ?? 'Produit'}',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        subtitle: Text(
                          [
                                product['code'],
                                (product['base_unit'] as Map?)?['name'],
                                (product['dosage_form'] as Map?)?['name'],
                              ]
                              .where((v) => v != null && '$v'.isNotEmpty)
                              .join(' · '),
                        ),
                      ),
                    ),
                  ),
              ],
            ],
          ],
        ),
      ),
    );
  }
}

class _Message extends StatelessWidget {
  const _Message({required this.message, this.retry});
  final String message;
  final Future<void> Function()? retry;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          const Icon(Icons.info_outline, size: 36),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          if (retry != null)
            TextButton(onPressed: retry, child: const Text('Réessayer')),
        ],
      ),
    ),
  );
}
