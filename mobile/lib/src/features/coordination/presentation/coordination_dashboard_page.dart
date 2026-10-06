import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/connectivity/connectivity_service.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_badge.dart';
import '../data/coordination_service.dart';

/// AM-172 — Tableau de bord mobile de la Coordination (maquette 08) : chiffres
/// réels pour les FOSA et la synchronisation ; ruptures et péremptions
/// « Disponible avec les analyses de base » (niveau 9), jamais de chiffre inventé.
class CoordinationDashboardPage extends StatefulWidget {
  const CoordinationDashboardPage({this.service, this.connectivity, super.key});

  final CoordinationService? service;
  final ConnectivityService? connectivity;

  @override
  State<CoordinationDashboardPage> createState() =>
      _CoordinationDashboardPageState();
}

class _CoordinationDashboardPageState extends State<CoordinationDashboardPage> {
  late final CoordinationService _service =
      widget.service ?? CoordinationService();
  late final ConnectivityService _connectivity =
      widget.connectivity ?? ConnectivityService();
  Map<String, dynamic> _data = const {};
  bool _online = true;
  bool _loading = true;
  String? _donorId;

  static const _later = 'Disponible avec les analyses de base';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final online = await _connectivity.hasNetwork;
      final data = await _service.dashboard(donorId: _donorId);
      if (mounted) {
        setState(() {
          _online = online;
          _data = data;
        });
      }
    } catch (_) {
      // Écran vide avec message ci-dessous.
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> _list(String key) =>
      (_data[key] as List? ?? const [])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList(growable: false);

  @override
  Widget build(BuildContext context) {
    final mission = Map<String, dynamic>.from(_data['mission'] as Map? ?? const {});
    final stats = Map<String, dynamic>.from(_data['stats'] as Map? ?? const {});
    final country = '${mission['country'] ?? ''}';
    // Dernier contact d'une FOSA de la coordination (maquette 08).
    final syncedAgo = _syncedAgo(_data['last_sync_at']);
    final filters = Map<String, dynamic>.from(_data['filters'] as Map? ?? const {});
    final donors = (filters['donors'] as List? ?? const []).whereType<Map>().toList();
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Column(
        children: [
          Material(
            color: AppColors.sidebar,
            child: SafeArea(
              bottom: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            country.isEmpty ? 'Admin Coordination' : 'Admin Coordination, $country',
                            style: const TextStyle(color: AppColors.sidebarText, fontSize: 13),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            '${mission['name'] ?? 'Tableau de bord'}',
                            style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w700),
                          ),
                          if (_data.isNotEmpty) ...[
                            const SizedBox(height: 4),
                            Text(
                              !_online
                                  ? 'Hors ligne, données au ${_stamp(_data['generated_at'])}'
                                  : syncedAgo == null
                                  ? 'Données au ${_stamp(_data['generated_at'])}'
                                  : 'Synchronisé $syncedAgo, données au ${_stamp(_data['generated_at'])}',
                              style: TextStyle(
                                color: _online ? const Color(0xFF8FD3A5) : AppColors.sidebarText,
                                fontSize: 13,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
                children: _loading && _data.isEmpty
                    ? const [Padding(padding: EdgeInsets.all(32), child: Center(child: CircularProgressIndicator()))]
                    : _data.isEmpty
                    ? [
                        const Padding(
                          padding: EdgeInsets.all(24),
                          child: Text(
                            'Aucune donnée enregistrée sur ce téléphone. Connectez-vous à internet une première fois.',
                            textAlign: TextAlign.center,
                            style: TextStyle(color: AppColors.textMuted),
                          ),
                        ),
                      ]
                    : [
                        // Niveau 3 : couple ONG/Bailleur ; la période sert aux analyses (niveau 9).
                        Row(
                          children: [
                            Expanded(
                              flex: 3,
                              child: DropdownButtonFormField<String?>(
                                key: ValueKey('donor-$_donorId-${donors.length}'),
                                initialValue: donors.any((donor) => donor['id'] == _donorId) ? _donorId : null,
                                isExpanded: true,
                                decoration: const InputDecoration(isDense: true),
                                items: [
                                  const DropdownMenuItem<String?>(
                                    value: null,
                                    child: Text('Tous les couples ONG / Bailleur', overflow: TextOverflow.ellipsis),
                                  ),
                                  for (final donor in donors)
                                    DropdownMenuItem<String?>(
                                      value: '${donor['id']}',
                                      child: Text('${donor['label']}', overflow: TextOverflow.ellipsis),
                                    ),
                                ],
                                onChanged: (value) {
                                  setState(() => _donorId = value);
                                  _load();
                                },
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              flex: 2,
                              child: Tooltip(
                                message: _later,
                                child: DropdownButtonFormField<int>(
                                  initialValue: DateTime.now().month,
                                  isExpanded: true,
                                  decoration: const InputDecoration(isDense: true),
                                  items: [
                                    DropdownMenuItem(
                                      value: DateTime.now().month,
                                      child: Text(_monthName(DateTime.now().month)),
                                    ),
                                  ],
                                  onChanged: null,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        GridView(
                          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            mainAxisSpacing: 10,
                            crossAxisSpacing: 10,
                            mainAxisExtent: 160,
                          ),
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          children: [
                            const _Kpi(label: 'Produits en rupture', value: '—', note: _later),
                            const _Kpi(label: 'Produits en pré-rupture', value: '—', note: _later),
                            const _Kpi(label: 'Lots à risque de péremption', value: '—', note: _later),
                            _Kpi(
                              label: 'FOSA validées',
                              value: '${stats['validated'] ?? 0} / ${stats['total'] ?? 0}',
                              note: '${stats['pending'] ?? 0} en attente',
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        _Panel(
                          title: 'À traiter',
                          children: [
                            if (_list('todo').isEmpty)
                              const Text('Rien à traiter pour le moment.', style: TextStyle(color: AppColors.textMuted)),
                            for (final item in _list('todo'))
                              InkWell(
                                onTap: () => context.go('/missions'),
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(vertical: 10),
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Container(
                                        width: 9,
                                        height: 9,
                                        margin: const EdgeInsets.only(top: 6, right: 12),
                                        decoration: BoxDecoration(
                                          shape: BoxShape.circle,
                                          color: item['tone'] == 'danger' ? AppColors.dangerText : AppColors.infoText,
                                        ),
                                      ),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text('${item['title']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                                            Text('${item['detail']}', style: const TextStyle(color: AppColors.textMuted)),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        _Panel(
                          title: 'Synchronisation des FOSA',
                          children: [
                            if (_list('facilities').isEmpty)
                              const Text('Aucune FOSA validée.', style: TextStyle(color: AppColors.textMuted)),
                            for (final facility in _list('facilities'))
                              Padding(
                                padding: const EdgeInsets.symmetric(vertical: 8),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text('${facility['name']}', style: const TextStyle(fontWeight: FontWeight.w600)),
                                          Text(
                                            '${facility['category'] ?? '—'} · ${_ago(facility['last_contact_days'])}',
                                            style: const TextStyle(color: AppColors.textMuted, fontSize: 13),
                                          ),
                                        ],
                                      ),
                                    ),
                                    AppBadge(
                                      label: '${facility['sync_label']}',
                                      variant: switch (facility['sync_status']) {
                                        'ok' => AppBadgeVariant.success,
                                        'late' => AppBadgeVariant.info,
                                        'failed' || 'suspended' => AppBadgeVariant.danger,
                                        _ => AppBadgeVariant.neutral,
                                      },
                                    ),
                                  ],
                                ),
                              ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        const Text(
                          'Ruptures, péremptions et graphiques : disponibles avec les analyses de base.',
                          style: TextStyle(color: AppColors.textMuted),
                        ),
                      ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  static String _ago(Object? days) => switch (days) {
    null => 'jamais synchronisée',
    0 => 'aujourd’hui',
    1 => 'il y a 1 j',
    _ => 'il y a $days j',
  };

  static String _stamp(Object? value) {
    final date = DateTime.tryParse('${value ?? ''}')?.toLocal();
    if (date == null) return '—';
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(date.day)}/${two(date.month)} ${two(date.hour)}:${two(date.minute)}';
  }

  /// « il y a 2 min », « il y a 3 h », « il y a 6 j » ; null sans synchronisation.
  static String? _syncedAgo(Object? value) {
    final date = DateTime.tryParse('${value ?? ''}');
    if (date == null) return null;
    final elapsed = DateTime.now().difference(date);
    if (elapsed.inMinutes < 1) return 'à l’instant';
    if (elapsed.inHours < 1) return 'il y a ${elapsed.inMinutes} min';
    if (elapsed.inDays < 1) return 'il y a ${elapsed.inHours} h';
    return 'il y a ${elapsed.inDays} j';
  }

  static String _monthName(int month) => const [
    'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
  ][month - 1];
}

class _Kpi extends StatelessWidget {
  const _Kpi({required this.label, required this.value, required this.note});
  final String label;
  final String value;
  final String note;

  @override
  Widget build(BuildContext context) {
    final unavailable = value == '—';
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AppColors.textMuted, fontSize: 13)),
          const SizedBox(height: 6),
          Text(
            value,
            style: TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.w700,
              color: unavailable ? AppColors.textMuted : AppColors.text,
            ),
          ),
          Text(note, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AppColors.textMuted, fontSize: 11)),
        ],
      ),
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({required this.title, required this.children});
  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      border: Border.all(color: AppColors.border),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
        const SizedBox(height: 4),
        ...children,
      ],
    ),
  );
}
