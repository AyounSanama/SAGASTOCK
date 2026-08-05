import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/widgets/app_button.dart';
import '../data/organization_service.dart';
import 'mission_form_sheet.dart';

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
      if (!mounted) return;
      setState(() {
        _missions = values[0];
        _countries = values[1];
      });
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

  Future<void> _openForm([Map<String, dynamic>? mission]) async {
    if (_countries.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Ajoutez d’abord un pays depuis le portail Web.'),
        ),
      );
      return;
    }

    final editing = mission != null;
    final saved = await showMissionFormSheet(
      context: context,
      organizationId: widget.organizationId,
      organizationName: widget.organizationName,
      countries: _countries,
      mode: editing ? MissionFormMode.edit : MissionFormMode.create,
      mission: mission,
      onSave: (data) => editing
          ? _service.updateMission(
              organizationId: widget.organizationId,
              missionId: '${mission['id']}',
              countryId: data.countryId,
              code: data.code,
              name: data.name,
              startsOn: data.startsOn,
              endsOn: data.endsOn,
              address: data.address,
              managerName: data.managerName,
              phone: data.phone,
              email: data.email,
              description: data.description,
              isActive: data.isActive,
            )
          : _service.createMission(
              organizationId: widget.organizationId,
              countryId: data.countryId,
              code: data.code,
              name: data.name,
              startsOn: data.startsOn,
              endsOn: data.endsOn,
              address: data.address,
              managerName: data.managerName,
              phone: data.phone,
              email: data.email,
              description: data.description,
              isActive: data.isActive,
            ),
    );
    if (saved != true || !mounted) return;
    await _load();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          editing
              ? 'Mission modifiée avec succès.'
              : 'Mission ajoutée avec succès.',
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.organizationName)),
      floatingActionButton: AppFab(
        onPressed: _loading ? null : _openForm,
        icon: Icons.add_location_alt_outlined,
        tooltip: 'Ajouter une mission',
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
            const SizedBox(height: 6),
            Text(
              'Ajoutez, consultez et modifiez les missions de cette organisation.',
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 16),
            if (_loading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(32),
                  child: CircularProgressIndicator(),
                ),
              )
            else if (_error != null)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      Text(_error!),
                      const SizedBox(height: 12),
                      AppButton.text(
                        label: 'Réessayer',
                        icon: Icons.refresh_rounded,
                        onPressed: _load,
                      ),
                    ],
                  ),
                ),
              )
            else if (_missions.isEmpty)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    children: [
                      const Text('Aucune mission enregistrée.'),
                      const SizedBox(height: 14),
                      AppButton.add(
                        label: 'Ajouter une mission',
                        onPressed: _openForm,
                      ),
                    ],
                  ),
                ),
              )
            else
              for (final mission in _missions) ...[
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.public),
                    title: Text('${mission['name']}'),
                    subtitle: Text(
                      '${mission['code']} · '
                      '${(mission['country'] as Map<String, dynamic>?)?['name'] ?? 'Pays non renseigné'}',
                    ),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        AppIconAction(
                          icon: Icons.edit_outlined,
                          tooltip: 'Modifier la mission',
                          color: AppActionColor.orange,
                          onPressed: () => _openForm(mission),
                        ),
                        const SizedBox(width: 4),
                        Icon(
                          mission['is_active'] == true
                              ? Icons.check_circle
                              : Icons.pause_circle,
                          color: mission['is_active'] == true
                              ? Colors.green
                              : Colors.grey,
                        ),
                      ],
                    ),
                    onTap: () => context.push(
                      '/organizations/${widget.organizationId}/missions/'
                      '${mission['id']}/projects',
                      extra: '${mission['name']}',
                    ),
                  ),
                ),
                const SizedBox(height: 10),
              ],
          ],
        ),
      ),
    );
  }
}
