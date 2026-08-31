import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/security/password_policy.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_password_field.dart';
import '../../../core/widgets/app_text_field.dart';
import '../data/auth_service.dart';
import 'auth_page_shell.dart';

class ResetPasswordPage extends StatefulWidget {
  const ResetPasswordPage({super.key});
  @override
  State<ResetPasswordPage> createState() => _ResetPasswordPageState();
}

class _ResetPasswordPageState extends State<ResetPasswordPage> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController(),
      _token = TextEditingController(),
      _password = TextEditingController(),
      _confirmation = TextEditingController();
  bool _loading = false;
  String? _error;
  @override
  void dispose() {
    _email.dispose();
    _token.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await AuthService().resetPassword(
        email: _email.text.trim(),
        token: _token.text.trim(),
        password: _password.text,
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Mot de passe modifié avec succès.')),
        );
        context.go('/login');
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _error =
              'Le code est invalide ou a expiré. Demandez un nouveau lien.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => AuthPageShell(
    title: 'Nouveau mot de passe',
    description: 'Choisissez un nouveau mot de passe sécurisé.',
    child: Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AppTextField(
            label: 'Adresse e-mail',
            controller: _email,
            keyboardType: TextInputType.emailAddress,
            prefixIcon: Icons.mail_outline,
            validator: (value) => value != null && value.contains('@')
                ? null
                : 'Adresse e-mail invalide.',
          ),
          const SizedBox(height: AppSpacing.md),
          AppTextField(
            label: 'Code de réinitialisation',
            controller: _token,
            prefixIcon: Icons.password_outlined,
            validator: (value) =>
                value == null || value.trim().isEmpty ? 'Code requis.' : null,
          ),
          const SizedBox(height: AppSpacing.md),
          AppPasswordField(
            label: 'Nouveau mot de passe',
            controller: _password,
            validator: PasswordPolicy.validate,
          ),
          const SizedBox(height: AppSpacing.md),
          AppPasswordField(
            label: 'Confirmer le mot de passe',
            controller: _confirmation,
            validator: (value) => value != _password.text
                ? 'Les mots de passe ne correspondent pas.'
                : null,
          ),
          if (_error != null)
            Container(
              margin: const EdgeInsets.only(top: AppSpacing.md),
              padding: const EdgeInsets.all(AppSpacing.md),
              decoration: BoxDecoration(
                color: AppColors.danger.withValues(alpha: .08),
                borderRadius: BorderRadius.circular(AppRadius.md),
              ),
              child: Text(
                _error!,
                style: AppTypography.secondary.copyWith(
                  color: AppColors.danger,
                ),
              ),
            ),
          const SizedBox(height: AppSpacing.md),
          AppButton.validate(
            label: 'Réinitialiser le mot de passe',
            icon: Icons.restart_alt_rounded,
            loading: _loading,
            expanded: true,
            onPressed: _submit,
          ),
          AppButton.text(
            expanded: true,
            icon: Icons.arrow_back,
            label: 'Retour à la connexion',
            onPressed: () => context.go('/login'),
          ),
        ],
      ),
    ),
  );
}
