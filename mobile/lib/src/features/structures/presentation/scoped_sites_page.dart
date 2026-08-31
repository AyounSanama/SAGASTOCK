import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_empty_state.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../auth/data/auth_service.dart';
import '../data/structure_service.dart';

class ScopedSitesPage extends StatefulWidget {
  const ScopedSitesPage({super.key});
  @override
  State<ScopedSitesPage> createState() => _ScopedSitesPageState();
}

class _ScopedSitesPageState extends State<ScopedSitesPage> {
  final _service = StructureService();
  final _search = TextEditingController();
  String? _organizationId;
  List<Map<String, dynamic>> _facilities = [], _sites = [], _archivedSites = [];
  bool _loading = true, _archived = false, _offline = false;
  String _facilityId = '', _type = '';
  String? _error;

  static const _types = <String, String>{
    'stock': 'Stockage',
    'dispensing': 'Dispensation',
    'stock_and_dispensing': 'Stockage et dispensation',
    'quarantine': 'Quarantaine',
    'other': 'Autre',
  };

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
      final user = await AuthService().cachedUser() ?? {};
      final organization = user['organization'] as Map?;
      _organizationId =
          '${user['organization_id'] ?? organization?['id'] ?? ''}';
      if (_organizationId!.isEmpty) throw StateError('missing scope');
      final data = await _service.list(_organizationId!);
      final pagination =
          data['facilities'] as Map<String, dynamic>? ?? const {};
      if (!mounted) return;
      setState(() {
        _facilities = (pagination['data'] as List<dynamic>? ?? const [])
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList();
        _sites = (data['sites'] as List<dynamic>? ?? const [])
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList();
        _archivedSites = (data['archived_sites'] as List<dynamic>? ?? const [])
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList();
        _offline = data['offline'] == true;
      });
    } on DioException catch (error) {
      if (mounted) {
        setState(
          () => _error = error.response?.statusCode == 403
              ? 'Vous n’avez pas accès aux sites de dispensation.'
              : 'Impossible de charger les sites de dispensation.',
        );
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = 'Aucune organisation n’est rattachée à ce compte.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _visibleSites {
    final query = _search.text.trim().toLowerCase();
    return (_archived ? _archivedSites : _sites)
        .where((site) {
          final matchesSearch =
              query.isEmpty ||
              '${site['name']} ${site['code']} ${site['location'] ?? ''}'
                  .toLowerCase()
                  .contains(query);
          final matchesFacility =
              _facilityId.isEmpty || site['health_facility_id'] == _facilityId;
          final matchesType = _type.isEmpty || site['site_type'] == _type;
          return matchesSearch && matchesFacility && matchesType;
        })
        .toList(growable: false);
  }

  Map<String, dynamic>? _facility(String id) {
    for (final facility in _facilities) {
      if ('${facility['id']}' == id) return facility;
    }
    return null;
  }

  Future<void> _openForm([Map<String, dynamic>? site]) async {
    if (_facilities.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Créez d’abord une formation sanitaire.')),
      );
      return;
    }
    final editing = site != null;
    final key = GlobalKey<FormState>();
    final code = TextEditingController(text: '${site?['code'] ?? ''}');
    final name = TextEditingController(text: '${site?['name'] ?? ''}');
    final location = TextEditingController(text: '${site?['location'] ?? ''}');
    String facilityId =
        '${site?['health_facility_id'] ?? _facilities.first['id']}';
    String? departmentId = site?['department_id']?.toString();
    String? pharmacyId = site?['pharmacy_id']?.toString();
    String siteType = '${site?['site_type'] ?? 'dispensing'}';
    bool active = site?['is_active'] != false, saving = false;
    final result = await showAppFormSheet<String>(
      context: context,
      title: editing ? 'Modifier le site' : 'Nouveau site de dispensation',
      description:
          'Rattachez le site à une formation sanitaire de votre organisation.',
      builder: (sheetContext) => StatefulBuilder(
        builder: (_, setSheetState) {
          final facility = _facility(facilityId) ?? _facilities.first;
          final departments =
              (facility['departments'] as List<dynamic>? ?? const [])
                  .whereType<Map>()
                  .toList();
          final pharmacies =
              (facility['pharmacies'] as List<dynamic>? ?? const [])
                  .whereType<Map>()
                  .toList();
          if (!departments.any((item) => '${item['id']}' == departmentId)) {
            departmentId = null;
          }
          if (!pharmacies.any((item) => '${item['id']}' == pharmacyId)) {
            pharmacyId = null;
          }
          return SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: Form(
              key: key,
              child: Column(
                children: [
                  DropdownButtonFormField<String>(
                    initialValue: facilityId,
                    decoration: const InputDecoration(
                      labelText: 'Formation sanitaire *',
                      prefixIcon: Icon(Icons.local_hospital_outlined),
                    ),
                    items: _facilities
                        .map(
                          (item) => DropdownMenuItem(
                            value: '${item['id']}',
                            child: Text('${item['name']}'),
                          ),
                        )
                        .toList(),
                    onChanged: editing
                        ? null
                        : (value) => setSheetState(() {
                            facilityId = value ?? facilityId;
                            departmentId = null;
                            pharmacyId = null;
                          }),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: code,
                          decoration: const InputDecoration(
                            labelText: 'Code *',
                          ),
                          validator: _required,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: DropdownButtonFormField<String>(
                          initialValue: siteType,
                          decoration: const InputDecoration(
                            labelText: 'Type *',
                          ),
                          items: _types.entries
                              .map(
                                (entry) => DropdownMenuItem(
                                  value: entry.key,
                                  child: Text(entry.value),
                                ),
                              )
                              .toList(),
                          onChanged: (value) => siteType = value ?? siteType,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: name,
                    decoration: const InputDecoration(labelText: 'Nom *'),
                    validator: _required,
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Expanded(
                        child: DropdownButtonFormField<String?>(
                          initialValue: departmentId,
                          decoration: const InputDecoration(
                            labelText: 'Département',
                          ),
                          items: [
                            const DropdownMenuItem<String?>(
                              value: null,
                              child: Text('Aucun'),
                            ),
                            for (final item in departments)
                              DropdownMenuItem<String?>(
                                value: '${item['id']}',
                                child: Text('${item['name']}'),
                              ),
                          ],
                          onChanged: (value) => departmentId = value,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: DropdownButtonFormField<String?>(
                          initialValue: pharmacyId,
                          decoration: const InputDecoration(
                            labelText: 'Pharmacie',
                          ),
                          items: [
                            const DropdownMenuItem<String?>(
                              value: null,
                              child: Text('Aucune'),
                            ),
                            for (final item in pharmacies)
                              DropdownMenuItem<String?>(
                                value: '${item['id']}',
                                child: Text('${item['name']}'),
                              ),
                          ],
                          onChanged: (value) => pharmacyId = value,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: location,
                    decoration: const InputDecoration(
                      labelText: 'Localisation',
                      prefixIcon: Icon(Icons.location_on_outlined),
                    ),
                  ),
                  SwitchListTile.adaptive(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Site actif'),
                    value: active,
                    onChanged: saving
                        ? null
                        : (value) => setSheetState(() => active = value),
                  ),
                  const SizedBox(height: 18),
                  Row(
                    children: [
                      Expanded(
                        child: AppButton.cancel(
                          onPressed: saving
                              ? null
                              : () => Navigator.pop(sheetContext),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: AppButton.save(
                          label: editing ? 'Enregistrer' : 'Créer le site',
                          loading: saving,
                          onPressed: saving
                              ? null
                              : () async {
                                  if (!(key.currentState?.validate() ??
                                      false)) {
                                    return;
                                  }
                                  setSheetState(() => saving = true);
                                  try {
                                    final synced = await _service.saveChild(
                                      organizationId: _organizationId!,
                                      facilityId: facilityId,
                                      kind: 'site',
                                      childId: site?['id']?.toString(),
                                      data: {
                                        'code': code.text.trim(),
                                        'name': name.text.trim(),
                                        'site_type': siteType,
                                        'department_id': departmentId,
                                        'pharmacy_id': pharmacyId,
                                        'location': location.text.trim(),
                                        'is_active': active,
                                      },
                                    );
                                    if (sheetContext.mounted) {
                                      Navigator.pop(
                                        sheetContext,
                                        synced ? 'saved' : 'pending',
                                      );
                                    }
                                  } on DioException catch (error) {
                                    if (!sheetContext.mounted) return;
                                    setSheetState(() => saving = false);
                                    ScaffoldMessenger.of(
                                      sheetContext,
                                    ).showSnackBar(
                                      SnackBar(
                                        content: Text(
                                          error.response?.statusCode == 422
                                              ? 'Vérifiez les relations et l’unicité du code.'
                                              : 'Enregistrement impossible.',
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
          );
        },
      ),
    );
    code.dispose();
    name.dispose();
    location.dispose();
    if (result == null || !mounted) return;
    await _load();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          result == 'pending'
              ? 'Site enregistré hors connexion. Synchronisation en attente.'
              : editing
              ? 'Site modifié.'
              : 'Site créé.',
        ),
      ),
    );
  }

  String? _required(String? value) =>
      value?.trim().isEmpty == true ? 'Champ obligatoire' : null;

  Future<void> _archiveOrRestore(Map<String, dynamic> site) async {
    final restoring = _archived;
    final facilityId = '${site['health_facility_id']}';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(restoring ? 'Restaurer le site ?' : 'Archiver le site ?'),
        content: Text(
          restoring
              ? 'Le site réapparaîtra dans la liste active.'
              : 'Le site et son historique seront conservés.',
        ),
        actions: [
          AppButton.cancel(
            onPressed: () => Navigator.pop(dialogContext, false),
          ),
          restoring
              ? AppButton.validate(
                  label: 'Restaurer',
                  onPressed: () => Navigator.pop(dialogContext, true),
                )
              : AppButton.archive(
                  onPressed: () => Navigator.pop(dialogContext, true),
                ),
        ],
      ),
    );
    if (confirmed != true) return;
    final synced = restoring
        ? await _service.restoreChild(
            organizationId: _organizationId!,
            facilityId: facilityId,
            kind: 'site',
            childId: '${site['id']}',
          )
        : await _service.archiveChild(
            organizationId: _organizationId!,
            facilityId: facilityId,
            kind: 'site',
            childId: '${site['id']}',
          );
    if (synced) {
      await _load();
    } else if (mounted) {
      setState(() {
        final pending = {...site, '_sync_status': 'pending'};
        if (restoring) {
          _archivedSites.removeWhere((item) => item['id'] == site['id']);
          _sites.add(pending);
        } else {
          _sites.removeWhere((item) => item['id'] == site['id']);
          _archivedSites.add(pending);
        }
      });
    }
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          synced
              ? (restoring ? 'Site restauré.' : 'Site archivé.')
              : 'Action enregistrée hors connexion. Synchronisation en attente.',
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final sites = _visibleSites;
    return Scaffold(
      appBar: AppBar(title: const Text('Sites de dispensation')),
      floatingActionButton: _archived
          ? null
          : AppFab(
              onPressed: _loading ? null : _openForm,
              tooltip: 'Ajouter un site',
            ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              'Sites de dispensation',
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
            ),
            const Text(
              'Gérez les sites de stockage et de dispensation de votre organisation.',
              style: TextStyle(color: AppTheme.muted),
            ),
            if (_offline)
              Container(
                margin: const EdgeInsets.only(top: 12),
                padding: const EdgeInsets.all(10),
                color: AppTheme.blue.withValues(alpha: .08),
                child: const Text(
                  'Mode hors connexion — les modifications seront synchronisées automatiquement.',
                  style: TextStyle(color: AppTheme.blue),
                ),
              ),
            const SizedBox(height: 16),
            TextField(
              controller: _search,
              onChanged: (_) => setState(() {}),
              decoration: const InputDecoration(
                hintText: 'Rechercher un site…',
                prefixIcon: Icon(Icons.search_rounded),
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                ChoiceChip(
                  label: const Text('Sites actifs'),
                  selected: !_archived,
                  onSelected: (_) => setState(() => _archived = false),
                ),
                ChoiceChip(
                  label: const Text('Archives'),
                  selected: _archived,
                  onSelected: (_) => setState(() => _archived = true),
                ),
                DropdownButton<String>(
                  value: _facilityId,
                  items: [
                    const DropdownMenuItem(
                      value: '',
                      child: Text('Toutes les formations'),
                    ),
                    for (final item in _facilities)
                      DropdownMenuItem(
                        value: '${item['id']}',
                        child: Text('${item['name']}'),
                      ),
                  ],
                  onChanged: (value) =>
                      setState(() => _facilityId = value ?? ''),
                ),
                DropdownButton<String>(
                  value: _type,
                  items: [
                    const DropdownMenuItem(
                      value: '',
                      child: Text('Tous les types'),
                    ),
                    ..._types.entries.map(
                      (entry) => DropdownMenuItem(
                        value: entry.key,
                        child: Text(entry.value),
                      ),
                    ),
                  ],
                  onChanged: (value) => setState(() => _type = value ?? ''),
                ),
              ],
            ),
            const SizedBox(height: 16),
            if (_loading)
              const Padding(
                padding: EdgeInsets.all(40),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      Text(_error!),
                      AppButton.text(label: 'Réessayer', onPressed: _load),
                    ],
                  ),
                ),
              )
            else if (sites.isEmpty)
              AppEmptyState(
                icon: Icons.location_on_outlined,
                title: _archived ? 'Aucun site archivé' : 'Aucun site',
                description: _archived
                    ? 'Les sites archivés apparaîtront ici.'
                    : 'Ajoutez le premier site de dispensation.',
              )
            else
              ...sites.map(
                (site) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Card(
                    child: ListTile(
                      leading: const CircleAvatar(
                        child: Icon(Icons.location_on_outlined),
                      ),
                      title: Text(
                        '${site['name']}',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      subtitle: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${site['code']} · ${_types['${site['site_type']}'] ?? 'Autre'}',
                          ),
                          Text(
                            '${(site['health_facility'] as Map?)?['name'] ?? _facility('${site['health_facility_id']}')?['name'] ?? 'Formation sanitaire'}',
                          ),
                          if (site['_sync_status'] == 'pending')
                            const Text(
                              'En attente de synchronisation',
                              style: TextStyle(
                                color: AppTheme.orange,
                                fontSize: 12,
                              ),
                            ),
                        ],
                      ),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          if (!_archived)
                            AppIconAction(
                              icon: Icons.edit_outlined,
                              tooltip: 'Modifier',
                              color: AppActionColor.orange,
                              onPressed: () => _openForm(site),
                            ),
                          const SizedBox(width: 4),
                          AppIconAction(
                            icon: _archived
                                ? Icons.restore_rounded
                                : Icons.archive_outlined,
                            tooltip: _archived ? 'Restaurer' : 'Archiver',
                            color: _archived
                                ? AppActionColor.green
                                : AppActionColor.red,
                            onPressed: () => _archiveOrRestore(site),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
