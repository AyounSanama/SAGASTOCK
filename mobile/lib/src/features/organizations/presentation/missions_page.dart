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
    this.allowedCountries = const [],
    super.key,
  });

  final String organizationId;
  final String organizationName;
  final List<Map<String, dynamic>> allowedCountries;

  @override
  State<MissionsPage> createState() => _MissionsPageState();
}

class _MissionsPageState extends State<MissionsPage> {
  final _service = OrganizationService();
  final _search = TextEditingController();
  List<Map<String, dynamic>> _missions = [];
  List<Map<String, dynamic>> _countries = [];
  bool _loading = true;
  bool _archived = false;
  String _status = '';
  String _countryId = '';
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
      final values = await Future.wait([
        _service.missions(
          widget.organizationId,
          search: _search.text.trim(),
          status: _status,
          countryId: _countryId,
          archived: _archived,
        ),
        if (widget.allowedCountries.isEmpty)
          _service.organizationCountries(widget.organizationId)
        else
          Future.value(widget.allowedCountries),
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

  Future<void> _changeArchiveState(Map<String, dynamic> mission) async {
    final restoring = _archived;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          restoring ? 'Restaurer la mission ?' : 'Archiver la mission ?',
        ),
        content: Text(
          restoring
              ? 'La mission réapparaîtra dans la liste active.'
              : 'La mission sera conservée et pourra être restaurée.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Annuler'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(restoring ? 'Restaurer' : 'Archiver'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      final synchronized = restoring
          ? await _service.restoreMission(
              organizationId: widget.organizationId,
              missionId: '${mission['id']}',
            )
          : await _service.archiveMission(
              organizationId: widget.organizationId,
              missionId: '${mission['id']}',
            );
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            synchronized
                ? (restoring ? 'Mission restaurée.' : 'Mission archivée.')
                : 'Action enregistrée hors connexion. Synchronisation en attente.',
          ),
        ),
      );
    } on DioException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            error.response?.statusCode == 403
                ? 'Vous n’avez pas la permission d’effectuer cette action.'
                : 'Action impossible. Réessayez.',
          ),
        ),
      );
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
    if (saved == null || !mounted) return;
    await _load();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          saved == 'pending'
              ? 'Mission enregistrée hors connexion. Elle sera synchronisée automatiquement.'
              : editing
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
      floatingActionButton: _archived
          ? null
          : AppFab(
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
            TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _load(),
              decoration: InputDecoration(
                hintText: 'Rechercher une mission…',
                prefixIcon: const Icon(Icons.search_rounded),
                suffixIcon: IconButton(
                  tooltip: 'Rechercher',
                  onPressed: _load,
                  icon: const Icon(Icons.arrow_forward_rounded),
                ),
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                ChoiceChip(
                  label: const Text('Missions actives'),
                  selected: !_archived,
                  onSelected: (_) {
                    setState(() => _archived = false);
                    _load();
                  },
                ),
                ChoiceChip(
                  label: const Text('Archives'),
                  selected: _archived,
                  onSelected: (_) {
                    setState(() => _archived = true);
                    _load();
                  },
                ),
                if (!_archived)
                  DropdownButton<String>(
                    value: _status,
                    items: const [
                      DropdownMenuItem(
                        value: '',
                        child: Text('Tous les statuts'),
                      ),
                      DropdownMenuItem(value: 'active', child: Text('Actives')),
                      DropdownMenuItem(
                        value: 'inactive',
                        child: Text('Inactives'),
                      ),
                    ],
                    onChanged: (value) {
                      setState(() => _status = value ?? '');
                      _load();
                    },
                  ),
                if (!_archived && _countries.isNotEmpty)
                  DropdownButton<String>(
                    value: _countryId,
                    items: [
                      const DropdownMenuItem(
                        value: '',
                        child: Text('Tous les pays'),
                      ),
                      for (final country in _countries)
                        DropdownMenuItem(
                          value: '${country['id']}',
                          child: Text('${country['name']}'),
                        ),
                    ],
                    onChanged: (value) {
                      setState(() => _countryId = value ?? '');
                      _load();
                    },
                  ),
              ],
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
                      Text(
                        _archived
                            ? 'Aucune mission archivée.'
                            : 'Aucune mission enregistrée.',
                      ),
                      const SizedBox(height: 14),
                      if (!_archived)
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
                    subtitle: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${mission['code']} · '
                          '${(mission['country'] as Map<String, dynamic>?)?['name'] ?? 'Pays non renseigné'}',
                        ),
                        if (mission['_sync_status'] == 'pending') ...[
                          const SizedBox(height: 4),
                          const Text(
                            'En attente de synchronisation',
                            style: TextStyle(
                              color: Color(0xFFF57C00),
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ],
                    ),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (!_archived)
                          AppIconAction(
                            icon: Icons.edit_outlined,
                            tooltip: 'Modifier la mission',
                            color: AppActionColor.orange,
                            onPressed: () => _openForm(mission),
                          ),
                        const SizedBox(width: 4),
                        AppIconAction(
                          icon: _archived
                              ? Icons.restore_rounded
                              : Icons.archive_outlined,
                          tooltip: _archived
                              ? 'Restaurer la mission'
                              : 'Archiver la mission',
                          color: _archived
                              ? AppActionColor.green
                              : AppActionColor.red,
                          onPressed: () => _changeArchiveState(mission),
                        ),
                      ],
                    ),
                    onTap: _archived
                        ? null
                        : () => context.push(
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
