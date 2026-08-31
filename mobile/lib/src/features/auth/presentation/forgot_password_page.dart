import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_text_field.dart';
import '../data/auth_service.dart';
import 'auth_page_shell.dart';

class ForgotPasswordPage extends StatefulWidget {
  const ForgotPasswordPage({super.key});
  @override
  State<ForgotPasswordPage> createState() => _ForgotPasswordPageState();
}

class _ForgotPasswordPageState extends State<ForgotPasswordPage> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  bool _loading = false;
  String? _message;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final message = await AuthService().forgotPassword(_email.text.trim());
      if (mounted) setState(() => _message = message);
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'Demande impossible. Réessayez plus tard.');
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => AuthPageShell(
    title: 'Mot de passe oublié',
    description: 'Indiquez l’adresse e-mail associée à votre compte.',
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
          if (_message != null)
            _FeedbackMessage(message: _message!, success: true),
          if (_error != null)
            _FeedbackMessage(message: _error!, success: false),
          const SizedBox(height: AppSpacing.md),
          AppButton.primary(
            label: 'Envoyer le lien',
            icon: Icons.mark_email_read_outlined,
            loading: _loading,
            expanded: true,
            onPressed: _submit,
          ),
          AppButton.text(
            expanded: true,
            icon: Icons.key_outlined,
            label: 'J’ai déjà un code',
            onPressed: () => context.push('/reset-password'),
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

class _FeedbackMessage extends StatelessWidget {
  const _FeedbackMessage({required this.message, required this.success});
  final String message;
  final bool success;
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(top: AppSpacing.md),
    padding: const EdgeInsets.all(AppSpacing.md),
    decoration: BoxDecoration(
      color: (success ? AppColors.success : AppColors.danger).withValues(
        alpha: .08,
      ),
      borderRadius: BorderRadius.circular(AppRadius.md),
    ),
    child: Text(
      message,
      style: AppTypography.secondary.copyWith(
        color: success ? AppColors.success : AppColors.danger,
      ),
    ),
  );
}
