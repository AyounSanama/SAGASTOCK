import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../data/organization_service.dart';

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

  @override
  void initState() {
    super.initState();
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

  Future<void> _create() async {
    final code = TextEditingController();
    final name = TextEditingController();
    final country = TextEditingController();
    final formKey = GlobalKey<FormState>();
    final saved = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Nouvelle organisation'),
        content: Form(
          key: formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: code,
                decoration: const InputDecoration(labelText: 'Code unique'),
                validator: (value) =>
                    value?.trim().isEmpty == true ? 'Champ obligatoire' : null,
              ),
              TextFormField(
                controller: name,
                decoration: const InputDecoration(labelText: 'Nom'),
                validator: (value) =>
                    value?.trim().isEmpty == true ? 'Champ obligatoire' : null,
              ),
              TextFormField(
                controller: country,
                maxLength: 2,
                decoration: const InputDecoration(
                  labelText: 'Code pays (ex. CM)',
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () async {
              if (!(formKey.currentState?.validate() ?? false)) return;
              try {
                await _service.create(
                  code: code.text.trim(),
                  name: name.text.trim(),
                  countryCode: country.text.trim(),
                );
                if (context.mounted) Navigator.pop(context, true);
              } on DioException catch (error) {
                if (!context.mounted) return;
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      error.response?.statusCode == 403
                          ? 'Vous n’avez pas la permission de créer une organisation.'
                          : 'Création impossible. Vérifiez le code et les informations.',
                    ),
                  ),
                );
              }
            },
            child: const Text('Créer'),
          ),
        ],
      ),
    );
    code.dispose();
    name.dispose();
    country.dispose();
    if (saved == true) await _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Organisations / ONG')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _create,
        icon: const Icon(Icons.add),
        label: const Text('Ajouter'),
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            SearchBar(
              controller: _search,
              hintText: 'Rechercher par nom ou code',
              trailing: [
                IconButton(onPressed: _load, icon: const Icon(Icons.search)),
              ],
              onSubmitted: (_) => _load(),
            ),
            const SizedBox(height: 16),
            if (_loading)
              const Center(child: CircularProgressIndicator())
            else if (_error != null)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text(_error!),
                ),
              )
            else if (_organizations.isEmpty)
              const Card(
                child: Padding(
                  padding: EdgeInsets.all(20),
                  child: Text('Aucune organisation enregistrée.'),
                ),
              )
            else
              for (final organization in _organizations)
                Card(
                  child: ListTile(
                    leading: CircleAvatar(
                      child: Text(
                        (organization['name'] as String)
                            .substring(0, 1)
                            .toUpperCase(),
                      ),
                    ),
                    title: Text(organization['name'] as String),
                    subtitle: Text(
                      '${organization['code']} · ${organization['country_code'] ?? 'Pays non renseigné'}',
                    ),
                    trailing: Icon(
                      organization['is_active'] == true
                          ? Icons.check_circle
                          : Icons.pause_circle,
                      color: organization['is_active'] == true
                          ? Colors.green
                          : Colors.grey,
                    ),
                    onTap: () => context.push(
                      '/organizations/${organization['id']}/missions',
                      extra: organization['name'] as String,
                    ),
                  ),
                ),
          ],
        ),
      ),
    );
  }
}
