import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../data/organization_service.dart';

class FundingPage extends StatefulWidget {
  const FundingPage({
    required this.organizationId,
    required this.projectId,
    required this.projectName,
    super.key,
  });

  final String organizationId;
  final String projectId;
  final String projectName;

  @override
  State<FundingPage> createState() => _FundingPageState();
}

class _FundingPageState extends State<FundingPage> {
  final _service = OrganizationService();
  Map<String, dynamic> _data = {};
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
      final value = await _service.funding(
        organizationId: widget.organizationId,
        projectId: widget.projectId,
      );
      if (mounted) setState(() => _data = value);
    } on DioException catch (error) {
      if (mounted) {
        setState(
          () => _error = error.response?.statusCode == 403
              ? 'Vous n’avez pas la permission de consulter les financements.'
              : 'Impossible de charger les financements.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _create({required bool donor}) async {
    final code = TextEditingController();
    final name = TextEditingController();
    final key = GlobalKey<FormState>();
    final saved = await showAppDialogAsFormSheet<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(donor ? 'Nouveau bailleur' : 'Nouveau programme'),
        content: Form(
          key: key,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: code,
                decoration: const InputDecoration(labelText: 'Code'),
                validator: (value) =>
                    value?.trim().isEmpty == true ? 'Champ obligatoire' : null,
              ),
              TextFormField(
                controller: name,
                decoration: const InputDecoration(labelText: 'Nom'),
                validator: (value) =>
                    value?.trim().isEmpty == true ? 'Champ obligatoire' : null,
              ),
            ],
          ),
        ),
        actions: [
          AppButton.cancel(
            label: 'Annuler',
            onPressed: () => Navigator.pop(context, false),
          ),
          AppButton.add(
            label: 'Créer et associer',
            icon: Icons.link_rounded,
            onPressed: () async {
              if (!(key.currentState?.validate() ?? false)) return;
              try {
                if (donor) {
                  await _service.createAndAttachDonor(
                    organizationId: widget.organizationId,
                    projectId: widget.projectId,
                    code: code.text.trim(),
                    name: name.text.trim(),
                  );
                } else {
                  await _service.createAndAttachProgram(
                    organizationId: widget.organizationId,
                    projectId: widget.projectId,
                    code: code.text.trim(),
                    name: name.text.trim(),
                  );
                }
                if (context.mounted) Navigator.pop(context, true);
              } on DioException catch (error) {
                if (!context.mounted) return;
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      error.response?.statusCode == 403
                          ? 'Vous n’avez pas la permission requise.'
                          : 'Création impossible. Vérifiez le code.',
                    ),
                  ),
                );
              }
            },
          ),
        ],
      ),
    );
    code.dispose();
    name.dispose();
    if (saved == true) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final donors = (_data['project_donors'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>();
    final programs = (_data['project_programs'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>();
    return Scaffold(
      appBar: AppBar(title: Text(widget.projectName)),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              'Bailleurs et programmes',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: AppButton.add(
                    expanded: true,
                    label: 'Bailleur',
                    icon: Icons.account_balance_outlined,
                    onPressed: () => _create(donor: true),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: AppButton.duplicate(
                    expanded: true,
                    label: 'Programme',
                    icon: Icons.category_outlined,
                    onPressed: () => _create(donor: false),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            if (_loading)
              const Center(child: CircularProgressIndicator())
            else if (_error != null)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text(_error!),
                ),
              )
            else ...[
              const Text(
                'Bailleurs associés',
                style: TextStyle(fontWeight: FontWeight.bold),
              ),
              if (donors.isEmpty)
                const ListTile(title: Text('Aucun bailleur'))
              else
                for (final donor in donors)
                  Card(
                    child: ListTile(
                      leading: const Icon(Icons.account_balance_outlined),
                      title: Text(donor['name'] as String),
                      subtitle: Text(donor['code'] as String),
                    ),
                  ),
              const SizedBox(height: 12),
              const Text(
                'Programmes associés',
                style: TextStyle(fontWeight: FontWeight.bold),
              ),
              if (programs.isEmpty)
                const ListTile(title: Text('Aucun programme'))
              else
                for (final program in programs)
                  Card(
                    child: ListTile(
                      leading: const Icon(Icons.category_outlined),
                      title: Text(program['name'] as String),
                      subtitle: Text(program['code'] as String),
                    ),
                  ),
            ],
          ],
        ),
      ),
    );
  }
}
