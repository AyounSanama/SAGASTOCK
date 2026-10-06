import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/connectivity/connectivity_service.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_badge.dart';
import '../../users/presentation/coordination_account_sheet.dart';
import '../data/coordination_service.dart';

/// AM-172 — « Ma Coordination » mobile (maquettes Coordination 05 et 06) :
/// Projets, À valider, FOSA, Comptes. Mêmes règles serveur que le Web.
class CoordinationPage extends StatefulWidget {
  const CoordinationPage({
    this.organizationId,
    this.service,
    this.connectivity,
    super.key,
  });

  final String? organizationId;
  final CoordinationService? service;
  final ConnectivityService? connectivity;

  @override
  State<CoordinationPage> createState() => _CoordinationPageState();
}

class _CoordinationPageState extends State<CoordinationPage>
    with SingleTickerProviderStateMixin {
  late final CoordinationService _service =
      widget.service ?? CoordinationService();
  late final ConnectivityService _connectivity =
      widget.connectivity ?? ConnectivityService();
  late final TabController _tabs = TabController(length: 4, vsync: this);
  Map<String, dynamic> _data = const {};
  bool _loading = true;
  bool _online = true;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final online = await _connectivity.hasNetwork;
      final data = await _service.overview();
      if (!mounted) return;
      setState(() {
        _online = online;
        _data = data;
        if (data.isEmpty) {
          _error = online
              ? 'Impossible de charger votre coordination.'
              : 'Aucune donnée enregistrée sur ce téléphone. Connectez-vous à internet une première fois.';
        }
      });
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'Impossible de charger votre coordination.');
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> _list(String key) =>
      (_data[key] as List? ?? const [])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList(growable: false);

  Map<String, dynamic> get _stats =>
      Map<String, dynamic>.from(_data['stats'] as Map? ?? const {});
  Map<String, dynamic> get _mission =>
      Map<String, dynamic>.from(_data['mission'] as Map? ?? const {});
  bool get _canAct => _data['can_act'] == true;
  List<Map<String, dynamic>> get _pending => _list('facilities')
      .where((facility) => facility['validation_status'] == 'pending')
      .toList(growable: false);

  Future<void> _run(Future<void> Function() action, String success) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(success)));
      await _load();
    } on CoordinationActionException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<String?> _askReason({
    required String title,
    required String help,
    required String confirm,
  }) => showDialog<String>(
    context: context,
    builder: (context) => _ReasonDialog(title: title, help: help, confirm: confirm),
  );

  Future<void> _refuse(Map<String, dynamic> facility) async {
    final reason = await _askReason(
      title: 'Refuser ${facility['name']}',
      help:
          'Le motif est obligatoire et sera visible par l’Admin Projet. Après correction, la FOSA reviendra en attente.',
      confirm: 'Refuser la FOSA',
    );
    if (reason == null) return;
    await _run(
      () => _service.refuseFacility('${facility['id']}', reason),
      'FOSA refusée. Le motif est visible par l’Admin Projet.',
    );
  }

  Future<void> _suspend(Map<String, dynamic> facility) async {
    final reason = await _askReason(
      title: 'Suspendre ${facility['name']}',
      help:
          'Les comptes de la FOSA perdront l’accès à PharmaCare. Le motif est obligatoire.',
      confirm: 'Suspendre la FOSA',
    );
    if (reason == null) return;
    await _run(
      () => _service.suspendFacility('${facility['id']}', reason),
      'FOSA suspendue. Ses comptes n’ont plus accès à PharmaCare.',
    );
  }

  Future<void> _toggleAccount(Map<String, dynamic> account) async {
    final active = account['is_active'] == true;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(active ? 'Suspendre ce compte ?' : 'Réactiver ce compte ?'),
        content: Text('${account['name']} (${account['username']})'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(active ? 'Suspendre' : 'Réactiver'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    await _run(
      () => _service.setAccountActive('${account['id']}', active: !active),
      active ? 'Compte suspendu.' : 'Compte réactivé.',
    );
  }

  /// Niveau 3 — « Modifier » un compte de la coordination (maquette 04) :
  /// identité, contact, projet d'un Admin Projet ; suspension depuis la fiche.
  Future<void> _editAccount(Map<String, dynamic> account) async {
    final firstName = TextEditingController(text: '${account['first_name'] ?? ''}');
    final lastName = TextEditingController(
      text: '${account['last_name'] ?? account['name'] ?? ''}',
    );
    final email = TextEditingController(text: '${account['email'] ?? ''}');
    final phone = TextEditingController(text: '${account['phone'] ?? ''}');
    final projects = _list('projects');
    final isProjectAdmin = account['project_id'] != null;
    String? projectId = account['project_id'] as String?;
    String? error;
    var saving = false;
    final result = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (sheetContext, setSheetState) => Padding(
          padding: EdgeInsets.fromLTRB(
            16,
            0,
            16,
            16 + MediaQuery.viewInsetsOf(sheetContext).bottom,
          ),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'Modifier le compte',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
                ),
                _Muted(isProjectAdmin ? 'Admin Projet' : 'Coordination (lecture seule)'),
                const SizedBox(height: 12),
                if (error != null) ...[
                  Text(error!, style: const TextStyle(color: AppColors.dangerText)),
                  const SizedBox(height: 8),
                ],
                TextField(
                  controller: firstName,
                  decoration: const InputDecoration(labelText: 'Prénom *'),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: lastName,
                  decoration: const InputDecoration(labelText: 'Nom *'),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(labelText: 'E-mail *'),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: phone,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(labelText: 'Téléphone'),
                ),
                if (isProjectAdmin) ...[
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    initialValue: projects.any((project) => project['id'] == projectId)
                        ? projectId
                        : null,
                    isExpanded: true,
                    decoration: const InputDecoration(labelText: 'Projet *'),
                    items: [
                      for (final project in projects)
                        DropdownMenuItem(
                          value: '${project['id']}',
                          child: Text('${project['code']} · ${project['name']}'),
                        ),
                    ],
                    onChanged: (value) => projectId = value,
                  ),
                ],
                const SizedBox(height: 16),
                FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.primaryStrong,
                    minimumSize: const Size.fromHeight(46),
                  ),
                  onPressed: saving
                      ? null
                      : () async {
                          setSheetState(() {
                            saving = true;
                            error = null;
                          });
                          try {
                            await _service.updateAccount('${account['id']}', {
                              'first_name': firstName.text.trim(),
                              'last_name': lastName.text.trim(),
                              'email': email.text.trim(),
                              'phone': phone.text.trim(),
                              if (isProjectAdmin) 'project_id': projectId,
                            });
                            if (sheetContext.mounted) Navigator.pop(sheetContext, 'saved');
                          } on CoordinationActionException catch (exception) {
                            setSheetState(() {
                              saving = false;
                              error = exception.message;
                            });
                          }
                        },
                  child: const Text('Enregistrer'),
                ),
                const SizedBox(height: 8),
                OutlinedButton(
                  style: account['is_active'] == true
                      ? OutlinedButton.styleFrom(
                          foregroundColor: AppColors.dangerText,
                          side: const BorderSide(color: AppColors.dangerText),
                        )
                      : null,
                  onPressed: saving ? null : () => Navigator.pop(sheetContext, 'toggle'),
                  child: Text(
                    account['is_active'] == true ? 'Suspendre le compte' : 'Réactiver le compte',
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    firstName.dispose();
    lastName.dispose();
    email.dispose();
    phone.dispose();
    if (!mounted) return;
    if (result == 'saved') {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Compte mis à jour.')),
      );
      await _load();
    } else if (result == 'toggle') {
      await _toggleAccount(account);
    }
  }

  /// Niveau 2 — Assistant « Créer un projet / programme » (création ou reprise
  /// d'un brouillon) ; la liste est rechargée après enregistrement.
  Future<void> _openWizard(String location) async {
    final saved = await context.push<bool>(location);
    if (saved == true && mounted) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final country = '${_mission['country'] ?? ''}';
    final facilities = _list('facilities');
    final accounts = _list('accounts');
    return Scaffold(
      backgroundColor: AppColors.background,
      floatingActionButton: _canAct && _tabs.index == 0
          ? FloatingActionButton(
              tooltip: 'Créer un projet / programme',
              backgroundColor: AppColors.primaryStrong,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(AppRadius.lg),
              ),
              onPressed: () => _openWizard('/projects/new'),
              child: const Icon(Icons.add),
            )
          : null,
      body: Column(
        children: [
          Material(
            color: AppColors.sidebar,
            child: SafeArea(
              bottom: false,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 14, 16, 4),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          country.isEmpty
                              ? 'Admin Coordination'
                              : 'Admin Coordination, $country',
                          style: const TextStyle(
                            color: AppColors.sidebarText,
                            fontSize: 13,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${_mission['name'] ?? 'Ma Coordination'}',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 20,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                  TabBar(
                    controller: _tabs,
                    isScrollable: true,
                    tabAlignment: TabAlignment.start,
                    indicatorColor: AppColors.primary,
                    labelColor: AppColors.primary,
                    unselectedLabelColor: AppColors.sidebarText,
                    dividerColor: Colors.transparent,
                    onTap: (_) => setState(() {}),
                    tabs: [
                      _CountTab('Projets', _stats['projects']),
                      _CountTab('À valider', _stats['pending']),
                      _CountTab('FOSA', facilities.length),
                      _CountTab('Comptes', accounts.length),
                    ],
                  ),
                ],
              ),
            ),
          ),
          if (!_online && _data.isNotEmpty)
            Container(
              width: double.infinity,
              color: AppColors.infoSurface,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: const Text(
                'Hors ligne : données de la dernière synchronisation.',
                style: TextStyle(color: AppColors.infoText),
              ),
            ),
          if (_busy) const LinearProgressIndicator(minHeight: 2),
          Expanded(
            child: _loading && _data.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                ? _Message(text: _error!, onRetry: _load)
                : TabBarView(
                    controller: _tabs,
                    children: [
                      _refreshable(_projectsTab()),
                      _refreshable(_pendingTab()),
                      _refreshable(_facilitiesTab(facilities)),
                      _refreshable(_accountsTab(accounts)),
                    ],
                  ),
          ),
        ],
      ),
    );
  }

  Widget _refreshable(List<Widget> children) => RefreshIndicator(
    onRefresh: _load,
    child: ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
      children: children,
    ),
  );

  List<Widget> _projectsTab() {
    final pending = _stats['pending'] as int? ?? 0;
    final projects = _list('projects');
    return [
      if (pending > 0)
        _Banner(
          text: Text.rich(
            TextSpan(
              children: [
                TextSpan(
                  text: '$pending FOSA',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                TextSpan(
                  text: pending > 1
                      ? ' attendent votre validation.'
                      : ' attend votre validation.',
                ),
              ],
            ),
          ),
          action: 'Voir',
          onAction: () => setState(() => _tabs.animateTo(1)),
        ),
      if (projects.isEmpty)
        const _Message(text: 'Aucun projet ni programme dans cette coordination.'),
      for (final project in projects)
        _Card(
          children: [
            _TitleRow(
              title: '${project['code']}',
              badge: AppBadge(
                label: '${project['status_label'] ?? ''}',
                variant: switch (project['status']) {
                  'active' => AppBadgeVariant.success,
                  'draft' => AppBadgeVariant.info,
                  'suspended' => AppBadgeVariant.danger,
                  _ => AppBadgeVariant.neutral,
                },
              ),
            ),
            const SizedBox(height: 4),
            Text('${project['name']}'),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 6,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                _Chip(
                  '${project['type_label'] ?? 'Projet bailleur'}',
                  brand: project['type'] == 'national_program',
                ),
                if (project['donor_name'] != null)
                  Text(
                    '${project['donor_name']}',
                    style: const TextStyle(color: AppColors.textMuted),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            _Muted('Admin Projet : ${project['admin_name'] ?? 'Non attribué'}'),
            _Muted(
              'FOSA validées / en attente : ${project['validated_facilities_count'] ?? 0} / ${project['pending_facilities_count'] ?? 0}',
            ),
            if (_canAct && project['status'] == 'draft')
              Align(
                alignment: Alignment.centerRight,
                child: TextButton(
                  onPressed: () => _openWizard('/projects/${project['id']}/wizard'),
                  child: const Text('Reprendre'),
                ),
              ),
          ],
        ),
    ];
  }

  List<Widget> _pendingTab() => [
    const _Muted(CoordinationService.offlineMessage),
    const SizedBox(height: 10),
    if (_pending.isEmpty) const _Message(text: 'Aucune FOSA n’attend votre validation.'),
    for (final facility in _pending)
      _Card(
        children: [
          _TitleRow(
            title: '${facility['name']}',
            badge: const AppBadge(label: 'En attente', variant: AppBadgeVariant.info),
          ),
          const SizedBox(height: 6),
          _Muted(
            'Projet ${_projectCodes(facility)}, déclarée${facility['declared_by'] != null ? ' par ${facility['declared_by']}' : ''} le ${_date(facility['created_at'])}',
          ),
          _Muted(
            [facility['category'], facility['care_level']]
                .where((value) => value != null)
                .join(', '),
          ),
          _Muted('Liste Standard générée : ${facility['standard_list_count'] ?? 0} produits'),
          if (_canAct) ...[
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.dangerText,
                      side: const BorderSide(color: AppColors.dangerText),
                      minimumSize: const Size.fromHeight(46),
                    ),
                    onPressed: _busy ? null : () => _refuse(facility),
                    child: const Text('Refuser'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton.icon(
                    style: FilledButton.styleFrom(
                      backgroundColor: AppColors.primaryStrong,
                      minimumSize: const Size.fromHeight(46),
                    ),
                    onPressed: _busy
                        ? null
                        : () => _run(
                            () => _service.validateFacility('${facility['id']}'),
                            'FOSA validée. L’Admin Projet peut créer ses comptes.',
                          ),
                    icon: const Icon(Icons.check, size: 18),
                    label: const Text('Valider'),
                  ),
                ),
              ],
            ),
          ],
          Center(
            child: TextButton(
              style: TextButton.styleFrom(foregroundColor: AppColors.textMuted),
              onPressed: () => _showDetail(facility),
              child: const Text('Voir le détail complet'),
            ),
          ),
        ],
      ),
  ];

  List<Widget> _facilitiesTab(List<Map<String, dynamic>> facilities) => [
    if (facilities.isEmpty) const _Message(text: 'Aucune FOSA dans cette coordination.'),
    for (final facility in facilities)
      _Card(
        children: [
          _TitleRow(
            title: '${facility['name']}',
            badge: _facilityBadge('${facility['validation_status']}'),
          ),
          const SizedBox(height: 4),
          _Muted(
            '${_projectCodes(facility)} · ${facility['category'] ?? '—'} · ${_accountCount(facility)}',
          ),
          if (facility['refusal_reason'] != null && facility['validation_status'] == 'refused')
            _Muted('Motif du refus : ${facility['refusal_reason']}'),
          if (facility['suspension_reason'] != null && facility['validation_status'] == 'suspended')
            _Muted('Motif de la suspension : ${facility['suspension_reason']}'),
          for (final account in _accounts(facility))
            _AccountRow(
              account: account,
              subtitle: '${account['role']} · ${account['username']}',
              onToggle: _canAct && !_busy ? () => _toggleAccount(account) : null,
            ),
          if (_canAct) ...[
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerRight,
              child: switch (facility['validation_status']) {
                'validated' => OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.dangerText,
                    side: const BorderSide(color: AppColors.dangerText),
                  ),
                  onPressed: _busy ? null : () => _suspend(facility),
                  child: const Text('Suspendre la FOSA'),
                ),
                'suspended' => OutlinedButton(
                  onPressed: _busy
                      ? null
                      : () => _run(
                          () => _service.reactivateFacility('${facility['id']}'),
                          'FOSA réactivée.',
                        ),
                  child: const Text('Réactiver la FOSA'),
                ),
                'pending' => TextButton(
                  onPressed: () => setState(() => _tabs.animateTo(1)),
                  child: const Text('Voir dans « À valider »'),
                ),
                _ => const SizedBox.shrink(),
              },
            ),
          ],
        ],
      ),
  ];

  List<Widget> _accountsTab(List<Map<String, dynamic>> accounts) => [
    const _Muted(
      'Vous pouvez créer des comptes Admin Projet et Coordination (lecture seule). Les comptes FOSA sont créés par les Admin Projet.',
    ),
    if (_canAct && widget.organizationId != null && _mission['id'] != null) ...[
      const SizedBox(height: 10),
      Align(
        alignment: Alignment.centerLeft,
        child: FilledButton.icon(
          style: FilledButton.styleFrom(backgroundColor: AppColors.primaryStrong),
          onPressed: () async {
            final created = await openCoordinationAccountSheet(
              context,
              organizationId: widget.organizationId!,
              missionId: '${_mission['id']}',
            );
            if (created) await _load();
          },
          icon: const Icon(Icons.person_add_alt_1_outlined, size: 18),
          label: const Text('Créer un compte'),
        ),
      ),
    ],
    const SizedBox(height: 12),
    if (accounts.isEmpty) const _Message(text: 'Aucun compte de coordination.'),
    for (final account in accounts)
      _Card(
        children: [
          _AccountRow(
            account: account,
            title: true,
            subtitle: '${account['role']}\n${account['email'] ?? account['username']}',
            onEdit: _canAct && !_busy ? () => _editAccount(account) : null,
          ),
        ],
      ),
  ];

  void _showDetail(Map<String, dynamic> facility) => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (context) => DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.75,
      builder: (context, controller) => ListView(
        controller: controller,
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 32),
        children: [
          Text(
            '${facility['name']}',
            style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 4),
          _Muted(
            'Projet ${_projectCodes(facility)}, déclarée${facility['declared_by'] != null ? ' par ${facility['declared_by']}' : ''} le ${_date(facility['created_at'])}',
          ),
          const _Section('Détails de la FOSA'),
          _Line('Code', facility['code']),
          _Line('Catégorie', facility['category']),
          _Line('Niveau de soins', facility['care_level']),
          _Line(
            'Localisation',
            [facility['locality'], facility['district'], facility['region']]
                .where((value) => value != null && '$value'.isNotEmpty)
                .join(', '),
          ),
          const _Section('Paramètres d’approvisionnement'),
          _Line('Périodicité de commande', '${facility['order_period_months'] ?? '—'} mois'),
          _Line('Délai de livraison', '${facility['delivery_lead_time_months'] ?? '—'} mois'),
          _Line('Stock de sécurité', '${_months(facility['safety_stock_months'])} mois'),
          const _Section('Population cible'),
          _ChipWrap((facility['target_populations'] as List? ?? const []).map((e) => '$e')),
          const _Section('Pathologies et activités'),
          _ChipWrap((facility['pathologies'] as List? ?? const []).map((e) => '$e')),
          const SizedBox(height: 12),
          _Muted('Liste Standard générée : ${facility['standard_list_count'] ?? 0} produits'),
        ],
      ),
    ),
  );

  static AppBadge _facilityBadge(String status) => switch (status) {
    'pending' => const AppBadge(label: 'En attente', variant: AppBadgeVariant.info),
    'validated' => const AppBadge(label: 'Validée', variant: AppBadgeVariant.success),
    'refused' => const AppBadge(label: 'Refusée', variant: AppBadgeVariant.danger),
    'suspended' => const AppBadge(label: 'Suspendue', variant: AppBadgeVariant.danger),
    _ => AppBadge(label: status),
  };

  static List<Map<String, dynamic>> _accounts(Map<String, dynamic> facility) =>
      (facility['accounts'] as List? ?? const [])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList(growable: false);

  static String _accountCount(Map<String, dynamic> facility) {
    final count = _accounts(facility).length;
    return count == 0 ? 'Aucun compte' : '$count compte${count > 1 ? 's' : ''}';
  }

  static String _projectCodes(Map<String, dynamic> facility) {
    final codes = (facility['projects'] as List? ?? const [])
        .whereType<Map>()
        .map((project) => '${project['code']}')
        .join(', ');
    return codes.isEmpty ? '—' : codes;
  }

  static String _date(Object? value) {
    final date = DateTime.tryParse('${value ?? ''}')?.toLocal();
    if (date == null) return '—';
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(date.day)}/${two(date.month)}/${date.year}';
  }

  static String _months(Object? value) {
    final number = num.tryParse('${value ?? ''}');
    if (number == null) return '—';
    return number == number.roundToDouble()
        ? '${number.round()}'
        : number.toString().replaceAll('.', ',');
  }
}

class _CountTab extends StatelessWidget {
  const _CountTab(this.label, this.count);
  final String label;
  final Object? count;

  @override
  Widget build(BuildContext context) => Tab(
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
        const SizedBox(width: 6),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 1),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(AppRadius.pill),
          ),
          child: Text(
            '${count ?? 0}',
            style: const TextStyle(
              color: AppColors.text,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    ),
  );
}

class _ReasonDialog extends StatefulWidget {
  const _ReasonDialog({required this.title, required this.help, required this.confirm});
  final String title;
  final String help;
  final String confirm;

  @override
  State<_ReasonDialog> createState() => _ReasonDialogState();
}

class _ReasonDialogState extends State<_ReasonDialog> {
  final _controller = TextEditingController();
  String? _error;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(widget.title),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(widget.help),
        const SizedBox(height: 12),
        TextField(
          controller: _controller,
          maxLines: 3,
          maxLength: 1000,
          decoration: InputDecoration(labelText: 'Motif *', errorText: _error),
        ),
      ],
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Annuler'),
      ),
      FilledButton(
        style: FilledButton.styleFrom(backgroundColor: AppColors.dangerText),
        onPressed: () {
          final reason = _controller.text.trim();
          if (reason.length < 5) {
            setState(() => _error = 'Le motif doit contenir au moins 5 caractères.');
            return;
          }
          Navigator.pop(context, reason);
        },
        child: Text(widget.confirm),
      ),
    ],
  );
}

class _Card extends StatelessWidget {
  const _Card({required this.children});
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 12),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      border: Border.all(color: AppColors.border),
    ),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: children),
  );
}

class _TitleRow extends StatelessWidget {
  const _TitleRow({required this.title, required this.badge});
  final String title;
  final Widget badge;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Expanded(
        child: Text(
          title,
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
        ),
      ),
      const SizedBox(width: 8),
      badge,
    ],
  );
}

class _AccountRow extends StatelessWidget {
  const _AccountRow({
    required this.account,
    required this.subtitle,
    this.onToggle,
    this.onEdit,
    this.title = false,
  });
  final Map<String, dynamic> account;
  final String subtitle;
  final VoidCallback? onToggle;
  final VoidCallback? onEdit;
  final bool title;

  @override
  Widget build(BuildContext context) {
    final status = '${account['status'] ?? ''}';
    final active = account['is_active'] == true;
    return Padding(
      padding: EdgeInsets.only(top: title ? 0 : 10),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${account['name']}',
                  style: TextStyle(fontWeight: title ? FontWeight.w700 : FontWeight.w600),
                ),
                _Muted(subtitle),
                const SizedBox(height: 4),
                AppBadge(
                  label: status,
                  variant: switch (status) {
                    'Actif' => AppBadgeVariant.success,
                    'À activer' => AppBadgeVariant.info,
                    _ => AppBadgeVariant.danger,
                  },
                ),
              ],
            ),
          ),
          if (onEdit != null)
            OutlinedButton(onPressed: onEdit, child: const Text('Modifier'))
          else if (onToggle != null)
            OutlinedButton(
              style: active
                  ? OutlinedButton.styleFrom(
                      foregroundColor: AppColors.dangerText,
                      side: const BorderSide(color: AppColors.dangerText),
                    )
                  : null,
              onPressed: onToggle,
              child: Text(active ? 'Suspendre' : 'Réactiver'),
            ),
        ],
      ),
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner({required this.text, required this.action, required this.onAction});
  final Widget text;
  final String action;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 12),
    padding: const EdgeInsets.fromLTRB(14, 6, 6, 6),
    decoration: BoxDecoration(
      color: AppColors.infoSurface,
      borderRadius: BorderRadius.circular(AppRadius.md),
      border: Border.all(color: AppColors.infoSurface),
    ),
    child: Row(
      children: [
        Expanded(
          child: DefaultTextStyle.merge(
            style: const TextStyle(color: AppColors.infoText),
            child: text,
          ),
        ),
        TextButton(
          onPressed: onAction,
          style: TextButton.styleFrom(foregroundColor: AppColors.infoText),
          child: Text(action, style: const TextStyle(fontWeight: FontWeight.w700)),
        ),
      ],
    ),
  );
}

class _Chip extends StatelessWidget {
  const _Chip(this.label, {this.brand = false});
  final String label;
  final bool brand;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
    decoration: BoxDecoration(
      color: brand ? AppColors.primarySoft : AppColors.neutralSurface,
      borderRadius: BorderRadius.circular(AppRadius.pill),
    ),
    child: Text(
      label,
      style: TextStyle(
        fontSize: 13,
        color: brand ? AppColors.primaryStrong : AppColors.neutralText,
      ),
    ),
  );
}

class _ChipWrap extends StatelessWidget {
  const _ChipWrap(this.values);
  final Iterable<String> values;

  @override
  Widget build(BuildContext context) => values.isEmpty
      ? const _Muted('—')
      : Wrap(
          spacing: 8,
          runSpacing: 6,
          children: [for (final value in values) _Chip(value, brand: true)],
        );
}

class _Section extends StatelessWidget {
  const _Section(this.title);
  final String title;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 18, bottom: 6),
    child: Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
  );
}

class _Line extends StatelessWidget {
  const _Line(this.label, this.value);
  final String label;
  final Object? value;

  @override
  Widget build(BuildContext context) {
    final text = '${value ?? ''}'.trim();
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 8),
      decoration: const BoxDecoration(
        border: Border(top: BorderSide(color: AppColors.border)),
      ),
      child: Row(
        children: [
          Expanded(child: _Muted(label)),
          Flexible(child: Text(text.isEmpty ? '—' : text, textAlign: TextAlign.right)),
        ],
      ),
    );
  }
}

class _Muted extends StatelessWidget {
  const _Muted(this.text);
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 2),
    child: Text(text, style: const TextStyle(color: AppColors.textMuted)),
  );
}

class _Message extends StatelessWidget {
  const _Message({required this.text, this.onRetry});
  final String text;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(24),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(text, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.textMuted)),
        if (onRetry != null) ...[
          const SizedBox(height: 12),
          OutlinedButton(onPressed: onRetry, child: const Text('Réessayer')),
        ],
      ],
    ),
  );
}
