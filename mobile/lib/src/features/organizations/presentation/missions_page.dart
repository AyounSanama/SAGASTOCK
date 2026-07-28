import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../data/organization_service.dart';

class MissionsPage extends StatefulWidget {
  const MissionsPage({
    required this.organizationId,
    required this.organizationName,
    super.key,
  });
  final String organizationId;
  final String organizationName;

  @override
  State<MissionsPage> createState() => _MissionsPageState();
}

class _MissionsPageState extends State<MissionsPage> {
  final _service = OrganizationService();
  List<Map<String, dynamic>> _missions = [];
  List<Map<String, dynamic>> _countries = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final values = await Future.wait([
        _service.missions(widget.organizationId),
        _service.countries(),
      ]);
      if (mounted) {
        setState(() {
          _missions = values[0];
          _countries = values[1];
        });
      }
    } on DioException catch (error) {
      if (mounted) {
        setState(
          () => _error = error.response?.statusCode == 403
              ? 'Vous n’avez pas la permission de consulter les missions.'
              : 'Impossible de charger les missions.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _create() async {
    if (_countries.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Ajoutez d’abord un pays depuis le portail Web.'),
        ),
      );
      return;
    }
    final code = TextEditingController();
    final name = TextEditingController();
    var countryId = _countries.first['id'] as String;
    final key = GlobalKey<FormState>();
    final created = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Nouvelle mission'),
          content: Form(
            key: key,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextFormField(
                  controller: code,
                  decoration: const InputDecoration(labelText: 'Code mission'),
                  validator: (value) => value?.trim().isEmpty == true
                      ? 'Champ obligatoire'
                      : null,
                ),
                TextFormField(
                  controller: name,
                  decoration: const InputDecoration(labelText: 'Nom'),
                  validator: (value) => value?.trim().isEmpty == true
                      ? 'Champ obligatoire'
                      : null,
                ),
                DropdownButtonFormField<String>(
                  initialValue: countryId,
                  decoration: const InputDecoration(labelText: 'Pays'),
                  items: [
                    for (final country in _countries)
                      DropdownMenuItem(
                        value: country['id'] as String,
                        child: Text('${country['name']} (${country['iso2']})'),
                      ),
                  ],
                  onChanged: (value) {
                    if (value != null) setDialogState(() => countryId = value);
                  },
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
                if (!(key.currentState?.validate() ?? false)) return;
                try {
                  await _service.createMission(
                    organizationId: widget.organizationId,
                    countryId: countryId,
                    code: code.text.trim(),
                    name: name.text.trim(),
                  );
                  if (context.mounted) Navigator.pop(context, true);
                } on DioException catch (error) {
                  if (!context.mounted) return;
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(
                        error.response?.statusCode == 403
                            ? 'Vous n’avez pas la permission de créer une mission.'
                            : 'Création impossible. Vérifiez les informations.',
                      ),
                    ),
                  );
                }
              },
              child: const Text('Créer'),
            ),
          ],
        ),
      ),
    );
    code.dispose();
    name.dispose();
    if (created == true) await _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.organizationName)),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _create,
        icon: const Icon(Icons.add_location_alt_outlined),
        label: const Text('Mission'),
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              'Missions et pays d’intervention',
              style: Theme.of(context).textTheme.titleLarge,
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
            else if (_missions.isEmpty)
              const Card(
                child: Padding(
                  padding: EdgeInsets.all(20),
                  child: Text('Aucune mission enregistrée.'),
                ),
              )
            else
              for (final mission in _missions)
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.public),
                    title: Text(mission['name'] as String),
                    subtitle: Text(
                      '${mission['code']} · ${(mission['country'] as Map<String, dynamic>)['name']}',
                    ),
                    trailing: Icon(
                      mission['is_active'] == true
                          ? Icons.check_circle
                          : Icons.pause_circle,
                      color: mission['is_active'] == true
                          ? Colors.green
                          : Colors.grey,
                    ),
                    onTap: () => context.push(
                      '/organizations/${widget.organizationId}/missions/${mission['id']}/projects',
                      extra: mission['name'] as String,
                    ),
                  ),
                ),
          ],
        ),
      ),
    );
  }
}
