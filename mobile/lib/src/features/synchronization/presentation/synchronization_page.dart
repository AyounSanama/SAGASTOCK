import 'package:flutter/material.dart';

import '../../../core/connectivity/connectivity_service.dart';
import '../../../core/database/app_database.dart';
import '../../../core/sync/sync_bootstrap.dart';
import '../../../core/sync/sync_status_service.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../../core/widgets/app_navigation_drawer.dart';
import '../../auth/data/auth_service.dart';
import 'sync_conflict_page.dart';

/// Synchronisation du téléphone : état, envois en attente, refusés et
/// conflits. Seul l'utilisateur du téléphone peut réessayer ou abandonner.
class SynchronizationPage extends StatefulWidget {
  const SynchronizationPage({super.key});

  @override
  State<SynchronizationPage> createState() => _SynchronizationPageState();
}

class _SynchronizationPageState extends State<SynchronizationPage> {
  Map<String, dynamic>? _user;
  SyncStatusService? _service;
  bool _loading = true;
  bool _syncing = false;
  bool _online = false;
  DateTime? _lastSuccess;
  Map<String, int> _pending = const {};
  List<SyncIssue> _issues = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    _user ??= await AuthService().cachedUser();
    final ownerId = '${_user?['id'] ?? ''}';
    if (ownerId.isEmpty) {
      if (mounted) setState(() => _loading = false);
      return;
    }
    _service ??= SyncStatusService(
      database: AppDatabase.shared,
      ownerUserId: ownerId,
    );
    final online = await ConnectivityService().hasNetwork;
    final last = await _service!.lastSuccess();
    final pending = await _service!.pending();
    final issues = await _service!.issues();
    if (!mounted) return;
    setState(() {
      _online = online;
      _lastSuccess = last;
      _pending = pending;
      _issues = issues;
      _loading = false;
    });
  }

  Future<void> _syncNow() async {
    setState(() => _syncing = true);
    final online = await ConnectivityService().hasNetwork;
    final report = online ? await SyncBootstrap.syncNow() : null;
    await _load();
    if (!mounted) return;
    setState(() => _syncing = false);
    final message = report == null
        ? 'Pas de réseau : vos saisies restent sur ce téléphone et partiront au retour de la connexion.'
        : report.authenticationRequired
        ? 'Reconnectez-vous pour terminer la synchronisation.'
        : report.failed > 0
        ? '${report.succeeded} envoi(s) réussi(s), ${report.failed} refusé(s) ou reporté(s).'
        : 'Synchronisation terminée.';
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _retry(SyncIssue issue) async {
    await _service!.retry(issue.operationId);
    await _load();
    if (!mounted) return;
    final stillRefused = _issues.any((i) => i.operationId == issue.operationId);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          stillRefused
              ? 'Le serveur refuse toujours cet envoi.'
              : 'Envoi remis dans la file de synchronisation.',
        ),
      ),
    );
  }

  Future<void> _abandon(SyncIssue issue) async {
    final confirmed = await showAppDialogAsFormSheet<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Abandonner cet envoi ?'),
        content: Text(
          '${issue.title} ne sera plus envoyé au serveur. La saisie reste conservée sur ce téléphone.',
        ),
        actions: [
          AppButton.cancel(
            label: 'Retour',
            compact: true,
            onPressed: () => Navigator.pop(context, false),
          ),
          AppButton.danger(
            label: 'Abandonner',
            compact: true,
            onPressed: () => Navigator.pop(context, true),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    await _service!.abandon(issue.operationId);
    await _load();
  }

  Future<void> _openConflict(SyncIssue issue) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => SyncConflictPage(issue: issue, service: _service!),
      ),
    );
    if (changed == true) await _load();
  }

  String get _subtitle {
    final facility = '${_user?['facility']?['name'] ?? ''}'.trim();
    final project = '${_user?['project']?['code'] ?? ''}'.trim();
    return [facility, project].where((part) => part.isNotEmpty).join(' · ');
  }

  @override
  Widget build(BuildContext context) {
    final refused = _issues.where((issue) => !issue.conflict).toList();
    final conflicts = _issues.where((issue) => issue.conflict).toList();
    final pendingTotal = _pending.values.fold<int>(0, (sum, n) => sum + n);
    return Scaffold(
      drawer: const AppNavigationDrawer(),
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Synchronisation'),
            if (_subtitle.isNotEmpty)
              Text(
                _subtitle,
                style: TextStyle(fontSize: 13, color: AppColors.textMuted),
                overflow: TextOverflow.ellipsis,
              ),
          ],
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
                children: [
                  _statusCard(),
                  _sectionTitle(
                    'En attente d’envoi',
                    '$pendingTotal opération(s)',
                  ),
                  _pendingCard(),
                  _sectionTitle(
                    'Envois refusés',
                    '${refused.length}',
                    color: refused.isEmpty ? null : AppColors.dangerText,
                  ),
                  if (refused.isEmpty)
                    _emptyLine('Aucun envoi refusé.')
                  else
                    for (final issue in refused) _refusedCard(issue),
                  if (conflicts.isNotEmpty) ...[
                    _sectionTitle(
                      'Conflits',
                      '${conflicts.length}',
                      color: AppColors.primaryStrong,
                    ),
                    for (final issue in conflicts) _conflictCard(issue),
                  ],
                ],
              ),
            ),
    );
  }

  Widget _statusCard() => _card(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: _online
                    ? AppColors.successSurface
                    : AppColors.neutralSurface,
                borderRadius: BorderRadius.circular(999),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    Icons.circle,
                    size: 10,
                    color: _online
                        ? AppColors.successText
                        : AppColors.neutralText,
                  ),
                  const SizedBox(width: 6),
                  Text(
                    _online ? 'En ligne' : 'Hors connexion',
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      color: _online
                          ? AppColors.successText
                          : AppColors.neutralText,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                'Téléphone de ${_user?['name'] ?? ''}',
                textAlign: TextAlign.end,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(color: AppColors.textMuted),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        Text(
          'Dernière synchronisation réussie',
          style: TextStyle(color: AppColors.textMuted),
        ),
        const SizedBox(height: 2),
        Text(
          _when(_lastSuccess),
          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 14),
        AppButton.primary(
          label: 'Synchroniser maintenant',
          icon: Icons.sync,
          expanded: true,
          loading: _syncing,
          onPressed: _syncing ? null : _syncNow,
        ),
      ],
    ),
  );

  Widget _pendingCard() {
    const icons = {
      'receipts': Icons.inventory_2_outlined,
      'clinical': Icons.medication_outlined,
      'inventories': Icons.fact_check_outlined,
      'orders': Icons.shopping_cart_outlined,
      'stocks': Icons.swap_vert,
    };
    final rows = [
      for (final entry in SyncStatusService.modules.entries)
        // Les mouvements de stock n'apparaissent que s'il y en a en attente.
        if (entry.key != 'stocks' || (_pending['stocks'] ?? 0) > 0) entry,
    ];
    return _card(
      padding: EdgeInsets.zero,
      child: Column(
        children: [
          for (final (index, entry) in rows.indexed) ...[
            if (index > 0) Divider(height: 1, color: AppColors.border),
            ListTile(
              leading: Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: AppColors.infoSurface,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icons[entry.key], color: AppColors.infoText),
              ),
              title: Text(entry.value),
              trailing: Text(
                '${_pending[entry.key] ?? 0}',
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 16,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _refusedCard(SyncIssue issue) => _card(
    borderColor: AppColors.dangerSurface,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _issueHeader(issue),
        const SizedBox(height: 10),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: AppColors.dangerSurface,
            borderRadius: BorderRadius.circular(10),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.error_outline, size: 20, color: AppColors.dangerText),
              const SizedBox(width: 8),
              Expanded(
                child: Text.rich(
                  TextSpan(
                    children: [
                      const TextSpan(
                        text: 'Refusé par le serveur : ',
                        style: TextStyle(fontWeight: FontWeight.w800),
                      ),
                      TextSpan(text: issue.reason ?? 'motif non précisé.'),
                    ],
                  ),
                  style: TextStyle(color: AppColors.dangerText),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        Text(
          'Vos données restent sur ce téléphone.',
          style: TextStyle(color: AppColors.textMuted),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: AppButton.primary(
                label: 'Réessayer',
                onPressed: () => _retry(issue),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: AppButton.cancel(
                label: 'Abandonner',
                icon: Icons.block,
                onPressed: () => _abandon(issue),
              ),
            ),
          ],
        ),
      ],
    ),
  );

  Widget _conflictCard(SyncIssue issue) => _card(
    borderColor: AppColors.primarySoft,
    child: InkWell(
      onTap: () => _openConflict(issue),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _issueHeader(issue),
          const SizedBox(height: 6),
          Text(
            issue.reported
                ? 'Signalé à l’Admin. Touchez pour voir le détail.'
                : 'Votre saisie est conservée. Touchez pour voir le détail.',
            style: TextStyle(color: AppColors.textMuted),
          ),
        ],
      ),
    ),
  );

  Widget _issueHeader(SyncIssue issue) => Row(
    children: [
      Expanded(
        child: Text(
          issue.title,
          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
        ),
      ),
      Text(
        _short(issue.occurredAt),
        style: TextStyle(color: AppColors.textMuted),
      ),
    ],
  );

  Widget _sectionTitle(String title, String trailing, {Color? color}) =>
      Padding(
        padding: const EdgeInsets.fromLTRB(4, 22, 4, 10),
        child: Row(
          children: [
            Expanded(
              child: Text(
                title.toUpperCase(),
                style: TextStyle(
                  color: AppColors.textMuted,
                  fontWeight: FontWeight.w700,
                  letterSpacing: .6,
                ),
              ),
            ),
            Text(
              trailing,
              style: TextStyle(fontWeight: FontWeight.w800, color: color),
            ),
          ],
        ),
      );

  Widget _emptyLine(String text) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 4),
    child: Text(text, style: TextStyle(color: AppColors.textMuted)),
  );

  Widget _card({
    required Widget child,
    EdgeInsets padding = const EdgeInsets.all(16),
    Color? borderColor,
  }) => Container(
    margin: const EdgeInsets.only(bottom: 12),
    padding: padding,
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: borderColor ?? AppColors.border),
    ),
    child: child,
  );

  static String _two(int value) => value.toString().padLeft(2, '0');

  static String _time(DateTime at) => '${_two(at.hour)}:${_two(at.minute)}';

  static bool _sameDay(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;

  /// « Aujourd'hui à 09:42 », « Hier à 16:12 », « 07/10 à 08:05 », « Jamais ».
  static String _when(DateTime? at) {
    if (at == null) return 'Jamais';
    final now = DateTime.now();
    if (_sameDay(at, now)) return 'Aujourd’hui à ${_time(at)}';
    if (_sameDay(at, now.subtract(const Duration(days: 1)))) {
      return 'Hier à ${_time(at)}';
    }
    return '${_two(at.day)}/${_two(at.month)} à ${_time(at)}';
  }

  static String _short(DateTime at) {
    final now = DateTime.now();
    if (_sameDay(at, now)) return 'Auj. ${_time(at)}';
    if (_sameDay(at, now.subtract(const Duration(days: 1)))) {
      return 'Hier ${_time(at)}';
    }
    return '${_two(at.day)}/${_two(at.month)} ${_time(at)}';
  }
}
