import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_empty_state.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../../core/access/application_access.dart';
import '../../auth/data/auth_service.dart';
import '../data/organization_service.dart';

enum FundingSection { donors, programs }

class ScopedFundingPage extends StatefulWidget {
  const ScopedFundingPage({super.key, this.embedded = false, this.section});

  final bool embedded;
  final FundingSection? section;

  @override
  State<ScopedFundingPage> createState() => _ScopedFundingPageState();
}

class _ScopedFundingPageState extends State<ScopedFundingPage> {
  final _service = OrganizationService();
  final _search = TextEditingController();
  String? _organizationId;
  Map<String, dynamic> _setup = {};
  bool _loading = true;
  bool _archived = false;
  bool _canManage = false;
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
      final user = await AuthService().refreshedUser() ?? {};
      _canManage = ApplicationAccess.allows(user, 'funding.manage');
      final organization = user['organization'] as Map?;
      _organizationId =
          '${user['organization_id'] ?? organization?['id'] ?? ''}';
      if (_organizationId!.isEmpty) throw StateError('missing scope');
      final setup = await _service.projectSetup(_organizationId!);
      if (mounted) setState(() => _setup = setup);
    } on DioException catch (error) {
      if (mounted) {
        setState(
          () => _error = error.response?.statusCode == 403
              ? 'Vous n’avez pas la permission de gérer ces référentiels.'
              : 'Impossible de charger les bailleurs et programmes.',
        );
      }
    } catch (_) {
      if (mounted) setState(() => _error = 'Organisation introuvable.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _create({
    required bool donor,
    Map<String, dynamic>? item,
  }) async {
    final editing = item != null;
    final code = TextEditingController(text: '${item?['code'] ?? ''}');
    final name = TextEditingController(text: '${item?['name'] ?? ''}');
    final key = GlobalKey<FormState>();
    var saving = false;
    final saved = await showAppFormSheet<bool>(
      context: context,
      title: editing
          ? donor
                ? 'Modifier le bailleur'
                : 'Modifier le programme'
          : donor
          ? 'Ajouter un bailleur'
          : 'Ajouter un programme',
      description:
          'Ce référentiel sera disponible dans les projets de votre coordination.',
      builder: (sheetContext) => StatefulBuilder(
        builder: (_, setSheetState) => SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: key,
            child: Column(
              children: [
                TextFormField(
                  controller: code,
                  decoration: const InputDecoration(labelText: 'Code *'),
                  validator: _required,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: name,
                  decoration: const InputDecoration(labelText: 'Nom *'),
                  validator: _required,
                ),
                const SizedBox(height: 20),
                Row(
                  children: [
                    Expanded(
                      child: AppButton.cancel(
                        onPressed: saving
                            ? null
                            : () => Navigator.pop(sheetContext, false),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: AppButton.add(
                        label: editing ? 'Enregistrer' : 'Ajouter',
                        loading: saving,
                        onPressed: saving
                            ? null
                            : () async {
                                if (!(key.currentState?.validate() ?? false)) {
                                  return;
                                }
                                setSheetState(() => saving = true);
                                try {
                                  if (editing && donor) {
                                    await _service.updateDonor(
                                      organizationId: _organizationId!,
                                      donorId: '${item['id']}',
                                      code: code.text.trim(),
                                      name: name.text.trim(),
                                    );
                                  } else if (editing) {
                                    await _service.updateProgram(
                                      organizationId: _organizationId!,
                                      programId: '${item['id']}',
                                      code: code.text.trim(),
                                      name: name.text.trim(),
                                    );
                                  } else if (donor) {
                                    await _service.createDonor(
                                      organizationId: _organizationId!,
                                      code: code.text.trim(),
                                      name: name.text.trim(),
                                    );
                                  } else {
                                    await _service.createProgram(
                                      organizationId: _organizationId!,
                                      code: code.text.trim(),
                                      name: name.text.trim(),
                                    );
                                  }
                                  if (sheetContext.mounted) {
                                    Navigator.pop(sheetContext, true);
                                  }
                                } on DioException catch (error) {
                                  if (sheetContext.mounted) {
                                    setSheetState(() => saving = false);
                                    ScaffoldMessenger.of(
                                      sheetContext,
                                    ).showSnackBar(
                                      SnackBar(content: Text(_message(error))),
                                    );
                                  }
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
    await Future<void>.delayed(const Duration(milliseconds: 350));
    // Champs de la fenêtre : jamais libérés pendant sa fermeture animée
    // (écran rouge « _dependents.isEmpty ») ; la mémoire les récupère.
    if (saved == true) {
      await _load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              editing
                  ? 'Modification enregistrée.'
                  : donor
                  ? 'Bailleur créé avec succès.'
                  : 'Programme créé avec succès.',
            ),
          ),
        );
      }
    }
  }

  String _message(DioException error) {
    final status = error.response?.statusCode;
    final errors = error.response?.data is Map
        ? (error.response?.data as Map)['errors'] as Map?
        : null;
    final first = errors?.values
        .whereType<List>()
        .expand((value) => value)
        .firstOrNull;
    if (status == 422 && first != null) return '$first';
    if (status == 403) {
      return 'Vous n’avez pas la permission d’effectuer cette action.';
    }
    if (error.response == null) {
      return 'Cette opération nécessite une connexion au serveur.';
    }
    return 'L’enregistrement a échoué. Vérifiez les informations saisies.';
  }

  Future<void> _changeState(
    Map<String, dynamic> item, {
    required bool donor,
  }) async {
    final restore = _archived;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          restore ? 'Restaurer cet élément ?' : 'Archiver cet élément ?',
        ),
        content: Text(
          restore
              ? 'L’élément réapparaîtra dans la liste active.'
              : 'Les données seront conservées et pourront être restaurées.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(restore ? 'Restaurer' : 'Archiver'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      if (restore) {
        await _service.restoreFundingReference(
          organizationId: _organizationId!,
          referenceId: '${item['id']}',
          donor: donor,
        );
      } else {
        await _service.archiveFundingReference(
          organizationId: _organizationId!,
          referenceId: '${item['id']}',
          donor: donor,
        );
      }
      await _load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(restore ? 'Élément restauré.' : 'Élément archivé.'),
          ),
        );
      }
    } on DioException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(_message(error))));
      }
    }
  }

  static String? _required(String? value) =>
      value?.trim().isEmpty == true ? 'Champ obligatoire' : null;

  @override
  Widget build(BuildContext context) {
    final query = _search.text.trim().toLowerCase();
    final donors =
        ((_setup[_archived ? 'archived_donors' : 'donors'] as List?) ??
                const [])
            .whereType<Map>()
            .where(
              (item) =>
                  query.isEmpty ||
                  '${item['name']} ${item['code']}'.toLowerCase().contains(
                    query,
                  ),
            )
            .toList();
    final programs =
        ((_setup[_archived ? 'archived_programs' : 'programs'] as List?) ??
                const [])
            .whereType<Map>()
            .where(
              (item) =>
                  query.isEmpty ||
                  '${item['name']} ${item['code']}'.toLowerCase().contains(
                    query,
                  ),
            )
            .toList();
    final content = RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: EdgeInsets.fromLTRB(16, widget.embedded ? 10 : 16, 16, 96),
        children: [
          Text(
            widget.section == FundingSection.donors
                ? 'Bailleurs'
                : widget.section == FundingSection.programs
                ? 'Programmes'
                : 'Bailleurs & Programmes',
            style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
          ),
          const Text(
            'Gérez les référentiels utilisés par les projets de votre coordination.',
          ),
          const SizedBox(height: 18),
          TextField(
            controller: _search,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(
              hintText: 'Rechercher…',
              prefixIcon: Icon(Icons.search_rounded),
            ),
          ),
          const SizedBox(height: 12),
          if (_canManage)
            Align(
              alignment: Alignment.centerLeft,
              child: SegmentedButton<bool>(
                segments: const [
                  ButtonSegment(value: false, label: Text('Actifs')),
                  ButtonSegment(value: true, label: Text('Archives')),
                ],
                selected: {_archived},
                onSelectionChanged: (value) =>
                    setState(() => _archived = value.first),
              ),
            ),
          const SizedBox(height: 12),
          if (_loading)
            const Center(child: CircularProgressIndicator())
          else if (_error != null)
            AppEmptyState(
              icon: Icons.error_outline,
              title: 'Chargement impossible',
              description: _error!,
              action: AppButton.text(label: 'Réessayer', onPressed: _load),
            )
          else ...[
            if (widget.section != FundingSection.programs)
              _ReferenceSection(
                title: 'Bailleurs',
                items: donors,
                canManage: _canManage,
                archived: _archived,
                onAdd: () => _create(donor: true),
                onEdit: (item) => _create(donor: true, item: item),
                onStateChange: (item) => _changeState(item, donor: true),
              ),
            if (widget.section == null) const SizedBox(height: 18),
            if (widget.section != FundingSection.donors)
              _ReferenceSection(
                title: 'Programmes',
                items: programs,
                canManage: _canManage,
                archived: _archived,
                onAdd: () => _create(donor: false),
                onEdit: (item) => _create(donor: false, item: item),
                onStateChange: (item) => _changeState(item, donor: false),
              ),
          ],
        ],
      ),
    );
    if (widget.embedded) return content;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          widget.section == FundingSection.donors
              ? 'Référentiels'
              : 'Bailleurs & Programmes',
        ),
      ),
      body: content,
    );
  }
}

class _ReferenceSection extends StatelessWidget {
  const _ReferenceSection({
    required this.title,
    required this.items,
    required this.onAdd,
    required this.onEdit,
    required this.onStateChange,
    required this.canManage,
    required this.archived,
  });
  final String title;
  final List<Map> items;
  final VoidCallback onAdd;
  final ValueChanged<Map<String, dynamic>> onEdit;
  final ValueChanged<Map<String, dynamic>> onStateChange;
  final bool canManage;
  final bool archived;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              if (canManage && !archived)
                AppButton.add(label: 'Ajouter', onPressed: onAdd),
            ],
          ),
          const SizedBox(height: 10),
          if (items.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 18),
              child: Text('Aucun ${title.toLowerCase()} disponible.'),
            )
          else
            for (final raw in items)
              ListTile(
                key: ValueKey(raw['id']),
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.account_balance_outlined),
                title: Text('${raw['name'] ?? ''}'),
                subtitle: Text('${raw['code'] ?? ''}'),
                trailing: canManage
                    ? PopupMenuButton<String>(
                        onSelected: (action) {
                          final item = Map<String, dynamic>.from(raw);
                          if (action == 'edit') onEdit(item);
                          if (action == 'state') onStateChange(item);
                        },
                        itemBuilder: (_) => [
                          if (!archived)
                            const PopupMenuItem(
                              value: 'edit',
                              child: Text('Modifier'),
                            ),
                          PopupMenuItem(
                            value: 'state',
                            child: Text(archived ? 'Restaurer' : 'Archiver'),
                          ),
                        ],
                      )
                    : const Icon(Icons.chevron_right_rounded),
              ),
        ],
      ),
    ),
  );
}
