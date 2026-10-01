import 'package:flutter/material.dart';

import '../../../core/config/app_config.dart';
import '../../../core/config/server_settings.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_badge.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_text_field.dart';

/// Écran de test : adresse du serveur modifiable sans recompiler.
/// Absent des builds de production ([AppConfig.runtimeOverrideAllowed]).
class ServerSettingsPage extends StatefulWidget {
  const ServerSettingsPage({super.key});

  @override
  State<ServerSettingsPage> createState() => _ServerSettingsPageState();
}

class _ServerSettingsPageState extends State<ServerSettingsPage> {
  late final _address = TextEditingController(text: AppConfig.apiBaseUrl);
  String? _error;
  bool? _reachable;
  bool _busy = false;

  @override
  void dispose() {
    _address.dispose();
    super.dispose();
  }

  Future<void> _test() async {
    final url = ServerSettings.normalize(_address.text);
    if (url == null) {
      setState(() => _error = 'Adresse invalide. Exemple : 192.168.137.1:8000');
      return;
    }
    setState(() {
      _error = null;
      _busy = true;
      _reachable = null;
    });
    final ok = await ServerSettings.ping(url);
    if (mounted) {
      setState(() {
        _busy = false;
        _reachable = ok;
      });
    }
  }

  Future<void> _save() async {
    final url = ServerSettings.normalize(_address.text);
    if (url == null) {
      setState(() => _error = 'Adresse invalide. Exemple : 192.168.137.1:8000');
      return;
    }
    await ServerSettings.save(url);
    if (!mounted) return;
    _address.text = url;
    setState(() => _error = null);
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'Adresse enregistrée. Elle s’applique aux prochaines connexions.',
        ),
      ),
    );
  }

  Future<void> _reset() async {
    await ServerSettings.reset();
    if (!mounted) return;
    setState(() {
      _address.text = AppConfig.buildBaseUrl;
      _reachable = null;
      _error = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    final custom = AppConfig.runtimeBaseUrl != null;
    return Scaffold(
      appBar: AppBar(title: const Text('Paramètres serveur')),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          Text(
            'Réservé aux tests. L’adresse de compilation est '
            '${AppConfig.buildBaseUrl}.',
            style: AppTypography.secondary.copyWith(color: AppColors.textMuted),
          ),
          const SizedBox(height: AppSpacing.md),
          AppTextField(
            label: 'Adresse du serveur',
            controller: _address,
            hint: '192.168.137.1:8000',
            helperText: '« /api/v1 » est ajouté automatiquement.',
            errorText: _error,
            prefixIcon: Icons.dns_outlined,
            keyboardType: TextInputType.url,
            textInputAction: TextInputAction.done,
            onSubmitted: (_) => _test(),
          ),
          const SizedBox(height: AppSpacing.sm),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              AppBadge(
                label: custom
                    ? 'Adresse personnalisée'
                    : 'Adresse de compilation',
                variant: AppBadgeVariant.neutral,
              ),
              if (_reachable == true)
                const AppBadge(
                  label: 'Serveur joignable',
                  variant: AppBadgeVariant.success,
                  icon: Icons.check_circle_outline,
                ),
              if (_reachable == false)
                const AppBadge(
                  label: 'Serveur injoignable',
                  variant: AppBadgeVariant.danger,
                  icon: Icons.error_outline,
                ),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          AppButton(
            label: 'Tester la connexion',
            icon: Icons.wifi_tethering,
            variant: AppButtonVariant.edit,
            loading: _busy,
            expanded: true,
            onPressed: _test,
          ),
          const SizedBox(height: AppSpacing.sm),
          AppButton.primary(
            label: 'Enregistrer',
            icon: Icons.save_outlined,
            expanded: true,
            onPressed: _save,
          ),
          const SizedBox(height: AppSpacing.sm),
          AppButton.text(
            label: 'Revenir à l’adresse de compilation',
            icon: Icons.restart_alt,
            onPressed: _reset,
          ),
        ],
      ),
    );
  }
}
