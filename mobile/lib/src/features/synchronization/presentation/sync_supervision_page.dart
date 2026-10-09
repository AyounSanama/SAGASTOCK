import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_navigation_drawer.dart';
import '../data/sync_supervision_service.dart';

/// Supervision de la synchronisation (Coordination, Admin Projet) : même
/// contenu que la page Web, en lecture seule.
class SyncSupervisionPage extends StatefulWidget {
  const SyncSupervisionPage({super.key});

  @override
  State<SyncSupervisionPage> createState() => _SyncSupervisionPageState();
}

class _SyncSupervisionPageState extends State<SyncSupervisionPage> {
  final _service = SyncSupervisionService();
  final _search = TextEditingController();
  Timer? _searchDelay;
  Map<String, dynamic> _board = const {};
  bool _loading = true;
  String? _error;
  String? _projectId;
  String _filter = 'all';

  static const _filters = {
    'all': 'Tous',
    'late': 'En retard',
    'refused': 'Avec refus',
    'conflicts': 'Avec conflits',
  };

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchDelay?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final board = await _service.overview(
        projectId: _projectId,
        filter: _filter,
        search: _search.text,
      );
      if (mounted) setState(() => _board = board);
    } on DioException catch (error) {
      if (mounted) {
        setState(
          () => _error = error.response == null
              ? 'Supervision indisponible hors connexion.'
              : error.response?.statusCode == 403
              ? 'Votre rôle ne permet pas de consulter la supervision.'
              : 'Supervision momentanément indisponible. Réessayez.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _onSearch(String _) {
    _searchDelay?.cancel();
    _searchDelay = Timer(const Duration(milliseconds: 400), _load);
  }

  List<Map<String, dynamic>> get _rows =>
      (_board['rows'] as List? ?? const []).cast<Map<String, dynamic>>();

  List<Map<String, dynamic>> get _projects =>
      (_board['projects'] as List? ?? const []).cast<Map<String, dynamic>>();

  Map<String, dynamic> get _stats =>
      (_board['stats'] as Map?)?.cast<String, dynamic>() ?? const {};

  String get _updatedAt {
    final at = DateTime.tryParse('${_board['generated_at'] ?? ''}')?.toLocal();
    if (at == null) return '';
    return 'Mis à jour à ${at.hour.toString().padLeft(2, '0')}:${at.minute.toString().padLeft(2, '0')}';
  }

  String get _scopeLine {
    final project = _projects
        .where((p) => p['id'] == _projectId)
        .map((p) => '${p['name']}')
        .firstOrNull;
    final context = '${_board['context'] ?? ''}'.trim();
    return [
      if (context.isNotEmpty) context,
      project ?? 'Tous les projets',
    ].join(' · ');
  }

  Future<void> _openDetail(Map<String, dynamic> row) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => _DeviceDetailPage(
          service: _service,
          deviceId: '${row['key']}',
          projectId: _projectId,
          title: '${row['facility']} · ${row['device']} · ${row['user']}',
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    drawer: const AppNavigationDrawer(),
    appBar: AppBar(title: const Text('Synchronisation')),
    body: RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
        children: [
          Text(_scopeLine, style: TextStyle(color: AppColors.textMuted)),
          const SizedBox(height: 2),
          const Text(
            'Supervision de la synchronisation',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 10),
          if (_projects.length > 1)
            DropdownButtonFormField<String?>(
              isExpanded: true,
              initialValue: _projectId,
              decoration: const InputDecoration(labelText: 'Projet'),
              items: [
                const DropdownMenuItem(
                  value: null,
                  child: Text('Tous les projets'),
                ),
                for (final project in _projects)
                  DropdownMenuItem(
                    value: '${project['id']}',
                    child: Text(
                      '${project['name']}',
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
              ],
              onChanged: (value) {
                _projectId = value;
                _load();
              },
            ),
          if (_updatedAt.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                _updatedAt,
                style: TextStyle(color: AppColors.textMuted, fontSize: 13),
              ),
            ),
          const SizedBox(height: 14),
          if (_error != null)
            _box(child: Text(_error!))
          else if (_loading && _board.isEmpty)
            const Padding(
              padding: EdgeInsets.all(40),
              child: Center(child: CircularProgressIndicator()),
            )
          else ...[
            _kpis(),
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final entry in _filters.entries)
                  ChoiceChip(
                    label: Text(entry.value),
                    selected: _filter == entry.key,
                    onSelected: (_) {
                      _filter = entry.key;
                      _load();
                    },
                  ),
              ],
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _search,
              onChanged: _onSearch,
              decoration: const InputDecoration(
                prefixIcon: Icon(Icons.search),
                hintText: 'Rechercher une FOSA ou un utilisateur',
              ),
            ),
            const SizedBox(height: 12),
            if (_rows.isEmpty)
              _box(
                child: Text(
                  (_stats['devices'] ?? 0) == 0
                      ? 'Aucun téléphone de FOSA n’est encore connecté dans ce périmètre.'
                      : 'Aucun appareil ne correspond à ce filtre.',
                  style: TextStyle(color: AppColors.textMuted),
                ),
              )
            else
              for (final row in _rows) _deviceCard(row),
            const SizedBox(height: 6),
            Text(
              '« En attente » = opérations signalées par l’appareil lors de sa dernière connexion. Touchez un appareil pour voir le détail.',
              style: TextStyle(color: AppColors.textMuted, fontSize: 13),
            ),
          ],
        ],
      ),
    ),
  );

  Widget _kpis() {
    Widget kpi(String label, String value, {String? unit, Color? color}) =>
        Expanded(
          child: _box(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  height: 36,
                  child: Text(
                    label,
                    maxLines: 2,
                    style: TextStyle(color: AppColors.textMuted, fontSize: 13),
                  ),
                ),
                Text.rich(
                  TextSpan(
                    children: [
                      TextSpan(
                        text: value,
                        style: TextStyle(
                          fontSize: 26,
                          fontWeight: FontWeight.w800,
                          color: color,
                        ),
                      ),
                      if (unit != null)
                        TextSpan(
                          text: ' $unit',
                          style: TextStyle(color: AppColors.textMuted),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
    final late = (_stats['late'] ?? 0) as int;
    final refused = (_stats['refused'] ?? 0) as int;
    final conflicts = (_stats['conflicts'] ?? 0) as int;
    return Column(
      children: [
        Row(
          children: [
            kpi(
              'Appareils à jour (moins de 24 h)',
              '${_stats['up_to_date'] ?? 0}',
              unit: 'sur ${_stats['devices'] ?? 0}',
            ),
            const SizedBox(width: 10),
            kpi(
              'En retard (plus de 24 h)',
              '$late',
              unit: late > 1 ? 'appareils' : 'appareil',
              color: late > 0 ? AppColors.primaryStrong : null,
            ),
          ],
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            kpi(
              'Envois refusés',
              '$refused',
              color: refused > 0 ? AppColors.dangerText : null,
            ),
            const SizedBox(width: 10),
            kpi(
              'Conflits à examiner',
              '$conflicts',
              color: conflicts > 0 ? AppColors.primaryStrong : null,
            ),
          ],
        ),
      ],
    );
  }

  Widget _deviceCard(Map<String, dynamic> row) {
    final projects = (row['projects'] as List? ?? const []).join(', ');
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: () => _openDetail(row),
      child: _box(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${row['facility']}',
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                    ),
                  ),
                ),
                StatusBadge(
                  status: '${row['status']}',
                  label: '${row['status_label']}',
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              [
                if (projects.isNotEmpty) projects,
                '${row['device']} · ${row['user']}',
              ].join('\n'),
              style: TextStyle(color: AppColors.textMuted),
            ),
            const SizedBox(height: 8),
            Text('Dernière synchro : ${row['last_success_label']}'),
            const SizedBox(height: 8),
            Row(
              children: [
                _count('En attente', row['pending'], null),
                _count('Refusés', row['refused'], AppColors.dangerText),
                _count('Conflits', row['conflicts'], AppColors.primaryStrong),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _count(String label, Object? value, Color? color) {
    final number = (value as num?)?.toInt() ?? 0;
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: TextStyle(color: AppColors.textMuted, fontSize: 12)),
          Text(
            '$number',
            style: TextStyle(
              fontWeight: FontWeight.w800,
              color: number > 0 ? color : AppColors.textMuted,
            ),
          ),
        ],
      ),
    );
  }
}

/// Détail d'un appareil : envois refusés et conflits, en lecture seule.
class _DeviceDetailPage extends StatefulWidget {
  const _DeviceDetailPage({
    required this.service,
    required this.deviceId,
    required this.projectId,
    required this.title,
  });

  final SyncSupervisionService service;
  final String deviceId;
  final String? projectId;
  final String title;

  @override
  State<_DeviceDetailPage> createState() => _DeviceDetailPageState();
}

class _DeviceDetailPageState extends State<_DeviceDetailPage> {
  Map<String, dynamic>? _selected;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final board = await widget.service.overview(
        projectId: widget.projectId,
        deviceId: widget.deviceId,
      );
      _selected = (board['selected'] as Map?)?.cast<String, dynamic>();
    } on DioException {
      _selected = null;
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final selected = _selected;
    final issues = (selected?['issues'] as List? ?? const [])
        .cast<Map<String, dynamic>>();
    // Vide, le serveur (PHP) renvoie une liste « [] » et non un objet.
    final byModule = selected?['pending_by_module'];
    final pending = (byModule is Map ? byModule : const {})
        .cast<String, dynamic>()
        .entries
        .where((entry) => (entry.value as num) > 0);
    return Scaffold(
      appBar: AppBar(title: const Text('Détail de l’appareil')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
              children: [
                Text(
                  widget.title,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Dernière synchronisation : ${selected?['last_success_label'] ?? '—'}',
                  style: TextStyle(color: AppColors.textMuted),
                ),
                if (pending.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(
                    pending.map((e) => '${e.key} en attente : ${e.value}').join(' · '),
                    style: TextStyle(color: AppColors.textMuted),
                  ),
                ],
                const SizedBox(height: 14),
                if (issues.isEmpty)
                  _box(
                    child: Text(
                      'Aucun envoi refusé ni conflit sur cet appareil.',
                      style: TextStyle(color: AppColors.textMuted),
                    ),
                  )
                else
                  for (final issue in issues)
                    _box(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              StatusBadge(
                                status: issue['kind'] == 'conflict'
                                    ? 'check'
                                    : 'late',
                                label: '${issue['kind_label']}',
                              ),
                              const Spacer(),
                              Text(
                                '${issue['occurred_label']}',
                                style: TextStyle(color: AppColors.textMuted),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          Text(
                            '${issue['module_label']} ${issue['reference'] ?? ''}'.trim(),
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            '${issue['kind'] == 'conflict' ? '' : 'Refusé : '}${issue['reason'] ?? 'Motif non transmis.'}',
                            style: TextStyle(color: AppColors.textMuted),
                          ),
                          if (issue['reported'] == true)
                            Padding(
                              padding: const EdgeInsets.only(top: 4),
                              child: Text(
                                'Signalé par l’utilisateur',
                                style: TextStyle(
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.primaryStrong,
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                const SizedBox(height: 6),
                Text(
                  'Seul l’utilisateur du téléphone peut réessayer ou abandonner un envoi. La supervision est en lecture seule.',
                  style: TextStyle(color: AppColors.textMuted, fontSize: 13),
                ),
              ],
            ),
    );
  }
}

/// Pastille d'état : À jour (vert), À vérifier (orange), En retard (rouge).
class StatusBadge extends StatelessWidget {
  const StatusBadge({super.key, required this.status, required this.label});

  final String status;
  final String label;

  @override
  Widget build(BuildContext context) {
    final (background, foreground) = switch (status) {
      'ok' => (AppColors.successSurface, AppColors.successText),
      'check' => (AppColors.primarySoft, AppColors.primaryStrong),
      _ => (AppColors.dangerSurface, AppColors.dangerText),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: foreground,
          fontWeight: FontWeight.w700,
          fontSize: 13,
        ),
      ),
    );
  }
}

Widget _box({required Widget child}) => Container(
  width: double.infinity,
  margin: const EdgeInsets.only(bottom: 10),
  padding: const EdgeInsets.all(14),
  decoration: BoxDecoration(
    color: AppColors.surface,
    borderRadius: BorderRadius.circular(14),
    border: Border.all(color: AppColors.border),
  ),
  child: child,
);
