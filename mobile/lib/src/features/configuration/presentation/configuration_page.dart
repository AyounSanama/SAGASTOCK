import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_navigation_drawer.dart';
import '../data/configuration_service.dart';

class ConfigurationPage extends StatefulWidget {
  const ConfigurationPage({super.key});

  @override
  State<ConfigurationPage> createState() => _ConfigurationPageState();
}

class _ConfigurationPageState extends State<ConfigurationPage> {
  final _service = ConfigurationService();
  Map<String, dynamic>? _data;
  bool _loading = true;
  String? _starting;
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
      final data = await _service.load();
      if (mounted) setState(() => _data = data);
    } catch (_) {
      if (mounted) {
        setState(
          () => _error =
              'Impossible de charger la configuration. Vérifiez la connexion au serveur.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _start(String type) async {
    setState(() => _starting = type);
    try {
      await _service.start(type);
      await _load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Nouveau parcours créé avec succès.')),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Le parcours n’a pas pu être créé.'),
            backgroundColor: AppTheme.danger,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _starting = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      drawer: const AppNavigationDrawer(),
      appBar: AppBar(title: const Text('Centre de contrôle')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 32),
          children: [
            Text(
              'Configuration PharmaCare',
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w800,
                color: AppTheme.ink,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              'Configurez votre organisation et démarrez un parcours indépendant pour chaque nouvel élément.',
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: AppTheme.gray),
            ),
            const SizedBox(height: 20),
            if (_loading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(36),
                  child: CircularProgressIndicator(),
                ),
              )
            else if (_error != null)
              _ErrorCard(message: _error!, onRetry: _load)
            else ...[
              _ActiveWorkflow(data: _data?['active_workflow']),
              const SizedBox(height: 18),
              Text(
                'Nouveau parcours',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 10),
              ...((_data?['flow_types'] as List<dynamic>? ?? const [])
                  .map((item) => Map<String, dynamic>.from(item as Map))
                  .map(
                    (flow) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _FlowCard(
                        flow: flow,
                        loading: _starting == flow['value'],
                        onStart: () => _start(flow['value'].toString()),
                      ),
                    ),
                  )),
              const SizedBox(height: 12),
              Text(
                'Historique des parcours',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 10),
              ...((_data?['workflows'] as List<dynamic>? ?? const [])
                  .map((item) => Map<String, dynamic>.from(item as Map))
                  .map(
                    (workflow) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _WorkflowCard(workflow: workflow),
                    ),
                  )),
            ],
          ],
        ),
      ),
    );
  }
}

class _ActiveWorkflow extends StatelessWidget {
  const _ActiveWorkflow({required this.data});
  final dynamic data;

  @override
  Widget build(BuildContext context) {
    if (data is! Map) {
      return const _Panel(
        child: Text('Aucun parcours de configuration en cours.'),
      );
    }
    final workflow = Map<String, dynamic>.from(data as Map);
    final percent = (workflow['progress_percent'] as num?)?.toDouble() ?? 0;
    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.tune_rounded, color: AppTheme.orange),
              SizedBox(width: 10),
              Text(
                'Parcours en cours',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            workflow['label']?.toString() ?? 'Configuration',
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 8),
          LinearProgressIndicator(
            value: percent / 100,
            minHeight: 8,
            borderRadius: BorderRadius.circular(8),
          ),
          const SizedBox(height: 7),
          Text(
            '${percent.round()} % · étape ${workflow['current_step'] ?? 1} sur 12',
            style: const TextStyle(color: AppTheme.gray),
          ),
        ],
      ),
    );
  }
}

class _FlowCard extends StatelessWidget {
  const _FlowCard({
    required this.flow,
    required this.loading,
    required this.onStart,
  });
  final Map<String, dynamic> flow;
  final bool loading;
  final VoidCallback onStart;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: AppTheme.orange.withValues(alpha: .10),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(Icons.add_task_rounded, color: AppTheme.orange),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  flow['label']?.toString() ?? '',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                Text(
                  'Démarre à l’étape ${flow['start_step']}',
                  style: const TextStyle(color: AppTheme.gray, fontSize: 12),
                ),
              ],
            ),
          ),
          AppButton(
            label: 'Démarrer',
            icon: Icons.arrow_forward_rounded,
            onPressed: loading ? null : onStart,
            loading: loading,
          ),
        ],
      ),
    );
  }
}

class _WorkflowCard extends StatelessWidget {
  const _WorkflowCard({required this.workflow});
  final Map<String, dynamic> workflow;

  @override
  Widget build(BuildContext context) {
    final complete = workflow['status'] == 'completed';
    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  workflow['label']?.toString() ?? 'Configuration',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                decoration: BoxDecoration(
                  color: (complete ? AppTheme.green : AppTheme.blue).withValues(
                    alpha: .10,
                  ),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  complete ? 'Terminé' : 'En cours',
                  style: TextStyle(
                    color: complete ? AppTheme.green : AppTheme.blue,
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 7),
          Text(
            '${workflow['progress_percent'] ?? 0} % · étape ${workflow['current_step'] ?? 1}',
            style: const TextStyle(color: AppTheme.gray),
          ),
        ],
      ),
    );
  }
}

class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => _Panel(
    child: Column(
      children: [
        const Icon(Icons.cloud_off_rounded, color: AppTheme.danger, size: 34),
        const SizedBox(height: 10),
        Text(message, textAlign: TextAlign.center),
        const SizedBox(height: 12),
        AppButton(
          label: 'Réessayer',
          icon: Icons.refresh_rounded,
          onPressed: onRetry,
        ),
      ],
    ),
  );
}

class _Panel extends StatelessWidget {
  const _Panel({required this.child});
  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: const Color(0xFFE2E8F0)),
      boxShadow: const [
        BoxShadow(
          color: Color(0x0A0F172A),
          blurRadius: 18,
          offset: Offset(0, 6),
        ),
      ],
    ),
    child: child,
  );
}
