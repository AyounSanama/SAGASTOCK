import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../../core/security/password_policy.dart';
import '../../../core/sync/sync_bootstrap.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_empty_state.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../auth/data/auth_service.dart';
import '../../organizations/data/organization_service.dart';
import '../../structures/data/structure_service.dart';
import '../../structures/presentation/facilities_page.dart';
import '../data/user_service.dart';

@visibleForTesting
List<Map<String, dynamic>> sitesForFacility(
  Iterable<Map<String, dynamic>> sites,
  String facilityId,
) {
  final unique = <String, Map<String, dynamic>>{};
  for (final site in sites.where(
    (site) => '${site['health_facility_id']}' == facilityId,
  )) {
    final id = '${site['id'] ?? ''}';
    if (id.isNotEmpty) unique[id] = site;
  }
  return unique.values.toList(growable: false);
}

@visibleForTesting
bool isPersistedScopeId(Object? value) => RegExp(
  r'^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$',
).hasMatch('$value');

List<Map<String, dynamic>> persistedSitesForFacility(
  Iterable<Map<String, dynamic>> sites,
  String facilityId,
) => sitesForFacility(
  sites,
  facilityId,
).where((site) => isPersistedScopeId(site['id'])).toList(growable: false);

class ScopedUsersPage extends StatefulWidget {
  const ScopedUsersPage({super.key});
  @override
  State<ScopedUsersPage> createState() => _ScopedUsersPageState();
}

class _ScopedUsersPageState extends State<ScopedUsersPage> {
  final _service = UserService();
  final _organizations = OrganizationService();
  final _structures = StructureService();
  final _search = TextEditingController();
  List<Map<String, dynamic>> _users = [],
      _roles = [],
      _projects = [],
      _facilities = [],
      _sites = [];
  String? _organizationId, _currentUserId, _error;
  bool _loading = true, _archived = false, _offline = false;

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
      final current = await AuthService().cachedUser() ?? {};
      final organization = current['organization'] as Map?;
      _currentUserId = '${current['id'] ?? ''}';
      _organizationId =
          '${current['organization_id'] ?? organization?['id'] ?? ''}';
      if (_organizationId!.isEmpty) throw StateError('missing scope');
      final users = await _service.users(
        search: _search.text.trim(),
        archived: _archived,
      );
      var roles = <Map<String, dynamic>>[];
      var projects = <Map<String, dynamic>>[];
      var facilities = <Map<String, dynamic>>[];
      var sites = <Map<String, dynamic>>[];
      var offline = false;
      try {
        final values = await Future.wait([
          _service.assignableRoles(),
          _organizations.projects(organizationId: _organizationId!),
          _structures.list(_organizationId!),
        ]);
        roles = _deduplicateById(values[0] as List<Map<String, dynamic>>);
        projects = values[1] as List<Map<String, dynamic>>;
        final structures = values[2] as Map<String, dynamic>;
        final facilityPagination =
            structures['facilities'] as Map<String, dynamic>? ?? const {};
        facilities = _deduplicateById(
          (facilityPagination['data'] as List<dynamic>? ?? const [])
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList(),
        );
        sites = _deduplicateById(
          (structures['sites'] as List<dynamic>? ?? const [])
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList(),
        );
        offline = structures['offline'] == true;
      } on DioException catch (error) {
        if (error.response != null) rethrow;
        offline = true;
      }
      if (!mounted) return;
      setState(() {
        _users = users;
        _roles = roles;
        _projects = projects;
        _facilities = facilities;
        _sites = sites;
        _offline = offline;
      });
    } on DioException catch (error) {
      if (mounted) {
        setState(
          () => _error = error.response?.statusCode == 403
              ? 'Vous n’avez pas accès aux utilisateurs.'
              : 'Impossible de charger les utilisateurs.',
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

  Future<void> _openForm([Map<String, dynamic>? user]) async {
    if (_offline) {
      _message(
        'La gestion des comptes nécessite une connexion sécurisée au serveur.',
        error: true,
      );
      return;
    }
    if (_roles.isEmpty) {
      _message(
        'Aucun rôle ne peut être attribué par votre compte.',
        error: true,
      );
      return;
    }
    final editing = user != null;
    final key = GlobalKey<FormState>();
    final firstName = TextEditingController(
      text: '${user?['first_name'] ?? ''}',
    );
    final lastName = TextEditingController(
      text: '${user?['last_name'] ?? user?['name'] ?? ''}',
    );
    final username = TextEditingController(text: '${user?['username'] ?? ''}');
    final email = TextEditingController(text: '${user?['email'] ?? ''}');
    final phone = TextEditingController(text: '${user?['phone'] ?? ''}');
    final password = TextEditingController();
    final confirmation = TextEditingController();
    final currentRole = (user?['roles'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .firstOrNull;
    int roleId =
        int.tryParse('${currentRole?['id'] ?? _roles.first['id']}') ??
        _roles.first['id'] as int;
    String roleCode =
        '${_roles.firstWhere((item) => item['id'] == roleId, orElse: () => _roles.first)['code']}';
    String scopeId = _initialScopeId(user, roleCode);
    String facilityId = _facilityIdForSite(scopeId);
    bool active = user?['is_active'] != false,
        obscure = true,
        saving = false,
        synchronizing = false;
    String draft() => jsonEncode([
      firstName.text,
      lastName.text,
      username.text,
      email.text,
      phone.text,
      password.text,
      confirmation.text,
      roleId,
      scopeId,
      facilityId,
      active,
    ]);
    String? initialDraft;
    final result = await showAppFormSheet<Map<String, dynamic>>(
      context: context,
      title: editing ? 'Modifier l’utilisateur' : 'Créer un utilisateur',
      description: 'Attribuez uniquement un rôle et un périmètre autorisés.',
      builder: (sheetContext) => StatefulBuilder(
        builder: (_, setSheetState) {
          final scopes = roleCode == 'project_admin' ? _projects : _sites;
          if (!scopes.any((item) => '${item['id']}' == scopeId)) {
            scopeId = scopes.isEmpty ? '' : '${scopes.first['id']}';
          }
          if (roleCode != 'project_admin') {
            if (!_facilities.any((item) => '${item['id']}' == facilityId)) {
              facilityId = _facilities.isEmpty
                  ? ''
                  : '${_facilities.first['id']}';
            }
            final filteredSites = persistedSitesForFacility(_sites, facilityId);
            if (!filteredSites.any((item) => '${item['id']}' == scopeId)) {
              scopeId = filteredSites.isEmpty
                  ? ''
                  : '${filteredSites.first['id']}';
            }
          }
          initialDraft ??= draft();
          return AppFormSheetGuard(
            isDirty: () => draft() != initialDraft,
            isBusy: saving || synchronizing,
            child: Form(
              key: key,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Flexible(
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: TextFormField(
                                  controller: firstName,
                                  decoration: const InputDecoration(
                                    labelText: 'Prénom *',
                                  ),
                                  validator: _required,
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: TextFormField(
                                  controller: lastName,
                                  decoration: const InputDecoration(
                                    labelText: 'Nom *',
                                  ),
                                  validator: _required,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 14),
                          TextFormField(
                            controller: email,
                            keyboardType: TextInputType.emailAddress,
                            decoration: const InputDecoration(
                              labelText: 'Email *',
                              prefixIcon: Icon(Icons.email_outlined),
                            ),
                            validator: (value) =>
                                value == null || !value.contains('@')
                                ? 'Adresse e-mail invalide'
                                : null,
                          ),
                          const SizedBox(height: 14),
                          Row(
                            children: [
                              Expanded(
                                child: TextFormField(
                                  controller: username,
                                  decoration: const InputDecoration(
                                    labelText: 'Identifiant',
                                  ),
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: TextFormField(
                                  controller: phone,
                                  keyboardType: TextInputType.phone,
                                  decoration: const InputDecoration(
                                    labelText: 'Téléphone',
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 14),
                          if (editing)
                            InputDecorator(
                              decoration: const InputDecoration(
                                labelText: 'Rôle',
                              ),
                              child: Text(
                                '${currentRole?['name'] ?? 'Rôle actuel'}',
                              ),
                            )
                          else
                            DropdownButtonFormField<int>(
                              initialValue: roleId,
                              decoration: const InputDecoration(
                                labelText: 'Rôle *',
                              ),
                              items: _roles
                                  .map(
                                    (role) => DropdownMenuItem(
                                      value: role['id'] as int,
                                      child: Text('${role['name']}'),
                                    ),
                                  )
                                  .toList(),
                              onChanged: (value) => setSheetState(() {
                                roleId = value ?? roleId;
                                roleCode =
                                    '${_roles.firstWhere((item) => item['id'] == roleId)['code']}';
                                scopeId = '';
                                facilityId = _facilities.isEmpty
                                    ? ''
                                    : '${_facilities.first['id']}';
                              }),
                            ),
                          const SizedBox(height: 14),
                          if (editing)
                            InputDecorator(
                              decoration: const InputDecoration(
                                labelText: 'Périmètre actuel',
                              ),
                              child: Text(_currentScopeLabel(user, roleCode)),
                            )
                          else if (roleCode == 'project_admin')
                            DropdownButtonFormField<String>(
                              initialValue: scopeId.isEmpty ? null : scopeId,
                              decoration: InputDecoration(
                                labelText: 'Projet *',
                              ),
                              items: scopes
                                  .map(
                                    (scope) => DropdownMenuItem(
                                      value: '${scope['id']}',
                                      child: Text(_scopeLabel(scope, roleCode)),
                                    ),
                                  )
                                  .toList(),
                              onChanged: (value) => scopeId = value ?? scopeId,
                              validator: (value) =>
                                  value == null || value.isEmpty
                                  ? 'Périmètre obligatoire'
                                  : null,
                            ),
                          if (!editing && roleCode != 'project_admin') ...[
                            DropdownButtonFormField<String>(
                              key: ValueKey('facility-$facilityId'),
                              initialValue: facilityId.isEmpty
                                  ? null
                                  : facilityId,
                              decoration: const InputDecoration(
                                labelText: 'Formation sanitaire *',
                              ),
                              items: _facilities
                                  .map(
                                    (facility) => DropdownMenuItem(
                                      value: '${facility['id']}',
                                      child: Text('${facility['name']}'),
                                    ),
                                  )
                                  .toList(),
                              onChanged: (value) => setSheetState(() {
                                facilityId = value ?? '';
                                scopeId = '';
                              }),
                              validator: (value) =>
                                  value == null || value.isEmpty
                                  ? 'Formation sanitaire obligatoire'
                                  : null,
                            ),
                            const SizedBox(height: 14),
                            DropdownButtonFormField<String>(
                              key: ValueKey('site-$facilityId-$scopeId'),
                              initialValue: scopeId.isEmpty ? null : scopeId,
                              decoration: const InputDecoration(
                                labelText: 'Point de dispensation *',
                              ),
                              items: sitesForFacility(_sites, facilityId)
                                  .where(
                                    (site) => isPersistedScopeId(site['id']),
                                  )
                                  .map(
                                    (site) => DropdownMenuItem(
                                      value: '${site['id']}',
                                      child: Text('${site['name']}'),
                                    ),
                                  )
                                  .toList(),
                              onChanged: (value) => scopeId = value ?? '',
                              validator: (value) =>
                                  value == null || value.isEmpty
                                  ? 'Point de dispensation obligatoire'
                                  : null,
                            ),
                          ],
                          if (roleCode != 'project_admin' &&
                              persistedSitesForFacility(
                                _sites,
                                facilityId,
                              ).isEmpty) ...[
                            const SizedBox(height: 8),
                            Align(
                              alignment: Alignment.centerLeft,
                              child: Text(
                                facilityId.isEmpty
                                    ? 'Aucune formation sanitaire accessible.'
                                    : sitesForFacility(
                                        _sites,
                                        facilityId,
                                      ).isNotEmpty
                                    ? 'Le point de dispensation est en attente de synchronisation. Synchronisez les données avant de créer son utilisateur.'
                                    : 'Aucun point de dispensation n’est encore configuré pour cette formation sanitaire.',
                              ),
                            ),
                            Align(
                              alignment: Alignment.centerLeft,
                              child: TextButton.icon(
                                onPressed: facilityId.isEmpty || synchronizing
                                    ? null
                                    : () async {
                                        if (sitesForFacility(
                                          _sites,
                                          facilityId,
                                        ).isNotEmpty) {
                                          setSheetState(
                                            () => synchronizing = true,
                                          );
                                          final report =
                                              await SyncBootstrap.syncNow();
                                          final structures = await _structures
                                              .list(_organizationId!);
                                          final refreshedSites =
                                              _deduplicateById(
                                                (structures['sites']
                                                            as List<dynamic>? ??
                                                        const [])
                                                    .whereType<Map>()
                                                    .map(
                                                      (item) =>
                                                          Map<
                                                            String,
                                                            dynamic
                                                          >.from(item),
                                                    )
                                                    .toList(),
                                              );
                                          if (!sheetContext.mounted) return;
                                          setState(
                                            () => _sites = refreshedSites,
                                          );
                                          setSheetState(() {
                                            synchronizing = false;
                                            final available =
                                                persistedSitesForFacility(
                                                  refreshedSites,
                                                  facilityId,
                                                );
                                            scopeId = available.isEmpty
                                                ? ''
                                                : '${available.last['id']}';
                                          });
                                          if (report.failed > 0 &&
                                              sheetContext.mounted) {
                                            ScaffoldMessenger.of(
                                              sheetContext,
                                            ).showSnackBar(
                                              const SnackBar(
                                                content: Text(
                                                  'La synchronisation a échoué. La donnée reste conservée pour une nouvelle tentative.',
                                                ),
                                              ),
                                            );
                                          }
                                          return;
                                        }
                                        final facility = _facilities.firstWhere(
                                          (item) =>
                                              '${item['id']}' == facilityId,
                                        );
                                        final saved =
                                            await showDispensingSiteFormSheet(
                                              context: sheetContext,
                                              organizationId: _organizationId!,
                                              facility: facility,
                                              service: _structures,
                                            );
                                        if (saved == null) return;
                                        final structures = await _structures
                                            .list(_organizationId!);
                                        final refreshedSites = _deduplicateById(
                                          (structures['sites']
                                                      as List<dynamic>? ??
                                                  const [])
                                              .whereType<Map>()
                                              .map(
                                                (item) =>
                                                    Map<String, dynamic>.from(
                                                      item,
                                                    ),
                                              )
                                              .toList(),
                                        );
                                        if (!sheetContext.mounted) return;
                                        setState(() => _sites = refreshedSites);
                                        setSheetState(() {
                                          final available =
                                              persistedSitesForFacility(
                                                refreshedSites,
                                                facilityId,
                                              );
                                          scopeId = available.isEmpty
                                              ? ''
                                              : '${available.last['id']}';
                                        });
                                      },
                                icon: Icon(
                                  sitesForFacility(
                                        _sites,
                                        facilityId,
                                      ).isNotEmpty
                                      ? Icons.sync
                                      : Icons.add_business_outlined,
                                ),
                                label: Text(
                                  synchronizing
                                      ? 'Synchronisation…'
                                      : sitesForFacility(
                                          _sites,
                                          facilityId,
                                        ).isNotEmpty
                                      ? 'Synchroniser maintenant'
                                      : 'Créer un point de dispensation',
                                ),
                              ),
                            ),
                          ],
                          if (!editing) ...[
                            const SizedBox(height: 14),
                            TextFormField(
                              controller: password,
                              obscureText: obscure,
                              decoration: InputDecoration(
                                labelText: 'Mot de passe',
                                helperText:
                                    'Laissez vide pour générer un mot de passe temporaire.',
                                suffixIcon: IconButton(
                                  onPressed: () =>
                                      setSheetState(() => obscure = !obscure),
                                  icon: Icon(
                                    obscure
                                        ? Icons.visibility_outlined
                                        : Icons.visibility_off_outlined,
                                  ),
                                ),
                              ),
                              validator: (value) => (value ?? '').isEmpty
                                  ? null
                                  : PasswordPolicy.validate(value),
                            ),
                            const SizedBox(height: 14),
                            TextFormField(
                              controller: confirmation,
                              obscureText: obscure,
                              decoration: const InputDecoration(
                                labelText: 'Confirmation',
                              ),
                              validator: (value) =>
                                  password.text.isNotEmpty &&
                                      value != password.text
                                  ? 'Les mots de passe diffèrent'
                                  : null,
                            ),
                          ],
                          SwitchListTile.adaptive(
                            contentPadding: EdgeInsets.zero,
                            title: const Text('Compte actif'),
                            value: active,
                            onChanged: saving
                                ? null
                                : (value) =>
                                      setSheetState(() => active = value),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const Divider(height: 1),
                  Padding(
                    padding: const EdgeInsets.all(16),
                    child: Row(
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
                            label: editing
                                ? 'Enregistrer'
                                : 'Créer l’utilisateur',
                            loading: saving,
                            onPressed: saving
                                ? null
                                : () async {
                                    if (!(key.currentState?.validate() ??
                                        false)) {
                                      return;
                                    }
                                    if (!editing &&
                                        roleCode != 'project_admin' &&
                                        !isPersistedScopeId(scopeId)) {
                                      ScaffoldMessenger.of(
                                        sheetContext,
                                      ).showSnackBar(
                                        const SnackBar(
                                          content: Text(
                                            'Synchronisez d’abord le point de dispensation avant de créer son utilisateur.',
                                          ),
                                        ),
                                      );
                                      return;
                                    }
                                    setSheetState(() => saving = true);
                                    final payload = <String, dynamic>{
                                      'name':
                                          '${firstName.text.trim()} ${lastName.text.trim()}'
                                              .trim(),
                                      'first_name': firstName.text.trim(),
                                      'last_name': lastName.text.trim(),
                                      'username': username.text.trim().isEmpty
                                          ? null
                                          : username.text.trim(),
                                      'email': email.text.trim(),
                                      'phone': phone.text.trim(),
                                      'is_active': active,
                                      if (!editing) 'role_id': roleId,
                                      if (!editing)
                                        'scope_type':
                                            roleCode == 'project_admin'
                                            ? 'project'
                                            : 'site',
                                      if (!editing) 'scope_id': scopeId,
                                      if (!editing && password.text.isNotEmpty)
                                        'password': password.text,
                                      if (!editing && password.text.isNotEmpty)
                                        'password_confirmation':
                                            confirmation.text,
                                    };
                                    try {
                                      final response = editing
                                          ? await _service.update(
                                              '${user['id']}',
                                              payload,
                                            )
                                          : await _service.create(payload);
                                      if (sheetContext.mounted) {
                                        Navigator.pop(sheetContext, response);
                                      }
                                    } on DioException catch (error) {
                                      if (!sheetContext.mounted) return;
                                      setSheetState(() => saving = false);
                                      ScaffoldMessenger.of(
                                        sheetContext,
                                      ).showSnackBar(
                                        SnackBar(
                                          content: Text(
                                            error.response?.statusCode == 403
                                                ? 'Rôle ou périmètre non autorisé.'
                                                : _userCreationError(error),
                                          ),
                                        ),
                                      );
                                    }
                                  },
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
    firstName.dispose();
    lastName.dispose();
    username.dispose();
    email.dispose();
    phone.dispose();
    password.dispose();
    confirmation.dispose();
    if (result == null || !mounted) return;
    await _load();
    if (!mounted) return;
    _message(
      editing
          ? 'Utilisateur modifié.'
          : 'Compte créé avec succès. L’utilisateur devra modifier son mot de passe lors de sa première connexion.',
    );
  }

  String _userCreationError(DioException error) {
    final data = error.response?.data;
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map) {
        for (final value in errors.values) {
          if (value is List && value.isNotEmpty) return '${value.first}';
          if (value is String && value.isNotEmpty) return value;
        }
      }
      final message = data['message'];
      if (message is String && message.isNotEmpty) return message;
    }
    return 'Impossible de créer l’utilisateur. Veuillez réessayer.';
  }

  String _scopeLabel(Map<String, dynamic> scope, String roleCode) {
    if (roleCode == 'project_admin') return '${scope['name']}';
    final facility = scope['health_facility'] as Map?;
    final facilityName = '${facility?['name'] ?? 'Formation sanitaire'}';
    final code = '${facility?['code'] ?? ''}';
    final locality = '${facility?['locality'] ?? ''}';
    final details = [
      code,
      locality,
    ].where((value) => value.isNotEmpty).join(' · ');
    return '$facilityName — ${scope['name']}${details.isEmpty ? '' : ' ($details)'}';
  }

  String _currentScopeLabel(Map<String, dynamic>? user, String roleCode) {
    final id = _initialScopeId(user, roleCode);
    final source = roleCode == 'project_admin' ? _projects : _sites;
    final match = source.where((item) => '${item['id']}' == id).firstOrNull;
    return match == null
        ? 'Périmètre actuel conservé'
        : _scopeLabel(match, roleCode);
  }

  static List<Map<String, dynamic>> _deduplicateById(
    List<Map<String, dynamic>> values,
  ) {
    final unique = <String, Map<String, dynamic>>{};
    for (final value in values) {
      final id = '${value['id'] ?? ''}';
      if (id.isNotEmpty) unique[id] = value;
    }
    return unique.values.toList(growable: false);
  }

  String _initialScopeId(Map<String, dynamic>? user, String roleCode) {
    final roles = user?['roles'] as List<dynamic>? ?? const [];
    final role = roles.whereType<Map>().firstOrNull;
    final pivot = role?['pivot'] as Map?;
    return '${pivot?['scope_id'] ?? (roleCode == 'project_admin' ? (_projects.firstOrNull?['id'] ?? '') : (_sites.firstOrNull?['id'] ?? ''))}';
  }

  String _facilityIdForSite(String siteId) {
    final site = _sites.where((item) => '${item['id']}' == siteId).firstOrNull;
    return '${site?['health_facility_id'] ?? (_facilities.firstOrNull?['id'] ?? '')}';
  }

  String? _required(String? value) =>
      value?.trim().isEmpty == true ? 'Champ obligatoire' : null;

  Future<void> _archiveOrRestore(Map<String, dynamic> user) async {
    if (_offline) {
      _message('Cette action nécessite une connexion sécurisée.', error: true);
      return;
    }
    if ('${user['id']}' == _currentUserId) {
      _message('Vous ne pouvez pas archiver votre propre compte.', error: true);
      return;
    }
    final restoring = _archived;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(
          restoring ? 'Restaurer l’utilisateur ?' : 'Archiver l’utilisateur ?',
        ),
        content: const Text(
          'Les données, rôles et historiques seront conservés.',
        ),
        actions: [
          AppButton.cancel(onPressed: () => Navigator.pop(context, false)),
          restoring
              ? AppButton.validate(
                  label: 'Restaurer',
                  onPressed: () => Navigator.pop(context, true),
                )
              : AppButton.archive(
                  onPressed: () => Navigator.pop(context, true),
                ),
        ],
      ),
    );
    if (confirmed != true) return;
    restoring
        ? await _service.restore('${user['id']}')
        : await _service.archive('${user['id']}');
    await _load();
    _message(restoring ? 'Utilisateur restauré.' : 'Utilisateur archivé.');
  }

  Future<void> _resetPassword(Map<String, dynamic> user) async {
    if (_offline) {
      _message(
        'La réinitialisation nécessite une connexion sécurisée.',
        error: true,
      );
      return;
    }
    await _service.resetPassword('${user['id']}');
    if (!mounted) return;
    _message(
      'Mot de passe réinitialisé. L’utilisateur devra le modifier lors de sa prochaine connexion.',
    );
  }

  void _message(String text, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: error ? AppTheme.red : AppTheme.green,
        content: Text(text),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Utilisateurs et accès')),
    floatingActionButton: _archived || _offline
        ? null
        : AppFab(
            onPressed: _loading ? null : _openForm,
            tooltip: 'Créer un utilisateur',
          ),
    body: RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Utilisateurs et accès',
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
          ),
          const Text(
            'Gérez les comptes autorisés dans votre organisation.',
            style: TextStyle(color: AppTheme.muted),
          ),
          if (_offline)
            Container(
              margin: const EdgeInsets.only(top: 12),
              padding: const EdgeInsets.all(10),
              color: AppTheme.blue.withValues(alpha: .08),
              child: const Text(
                'Mode hors connexion — consultation locale uniquement pour protéger les comptes.',
                style: TextStyle(color: AppTheme.blue),
              ),
            ),
          const SizedBox(height: 16),
          TextField(
            controller: _search,
            textInputAction: TextInputAction.search,
            onSubmitted: (_) => _load(),
            decoration: InputDecoration(
              hintText: 'Rechercher un utilisateur…',
              prefixIcon: const Icon(Icons.search_rounded),
              suffixIcon: IconButton(
                onPressed: _load,
                icon: const Icon(Icons.arrow_forward_rounded),
              ),
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            children: [
              ChoiceChip(
                label: const Text('Utilisateurs actifs'),
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
          else if (_users.isEmpty)
            AppEmptyState(
              icon: Icons.people_outline,
              title: _archived
                  ? 'Aucun utilisateur archivé'
                  : 'Aucun utilisateur',
              description: _archived
                  ? 'Les comptes archivés apparaîtront ici.'
                  : 'Créez le premier compte autorisé.',
            )
          else
            ..._users.map(
              (user) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Card(
                  child: ListTile(
                    leading: CircleAvatar(
                      child: Text(
                        '${user['name'] ?? '?'}'.trim().isEmpty
                            ? '?'
                            : '${user['name']}'.trim()[0].toUpperCase(),
                      ),
                    ),
                    title: Text(
                      '${user['name']}',
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    subtitle: Text(
                      '${user['email']}\n${(user['roles'] as List<dynamic>? ?? const []).whereType<Map>().map((role) => role['name']).join(', ')}',
                    ),
                    isThreeLine: true,
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (!_archived && !_offline)
                          AppIconAction(
                            icon: Icons.edit_outlined,
                            tooltip: 'Modifier',
                            color: AppActionColor.orange,
                            onPressed: () => _openForm(user),
                          ),
                        if (!_archived && !_offline)
                          AppIconAction(
                            icon: Icons.password_outlined,
                            tooltip: 'Réinitialiser le mot de passe',
                            color: AppActionColor.blue,
                            onPressed: () => _resetPassword(user),
                          ),
                        if (!_offline)
                          AppIconAction(
                            icon: _archived
                                ? Icons.restore_rounded
                                : Icons.archive_outlined,
                            tooltip: _archived ? 'Restaurer' : 'Archiver',
                            color: _archived
                                ? AppActionColor.green
                                : AppActionColor.red,
                            onPressed: () => _archiveOrRestore(user),
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
