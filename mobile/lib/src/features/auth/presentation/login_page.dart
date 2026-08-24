import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/access/application_access.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_card.dart';
import '../../../core/widgets/app_text_field.dart';
import '../../../core/widgets/app_password_field.dart';
import '../../../core/widgets/pharmacare_wordmark.dart';
import '../../../core/theme/app_tokens.dart';
import '../data/auth_service.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});
  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _auth = AuthService();
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final user = await _auth.login(
        email: _email.text.trim(),
        password: _password.text,
      );
      if (mounted) {
        context.go(ApplicationAccess.landingPath(user));
      }
    } catch (error) {
      if (mounted) setState(() => _error = AuthService.messageFor(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: AppCard(
                padding: const EdgeInsets.all(AppSpacing.xl),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Image.asset(
                        'assets/images/pharmacare-logo.png',
                        height: 52,
                        fit: BoxFit.contain,
                        semanticLabel: 'Logo PharmaCare',
                      ),
                      const SizedBox(height: 2),
                      const PharmaCareWordmark(fontSize: 26),
                      const Text(
                        'Putting Patients at the Heart of Every Supply.',
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 8),
                      AppTextField(
                        label: 'Adresse e-mail ou identifiant',
                        controller: _email,
                        keyboardType: TextInputType.emailAddress,
                        textInputAction: TextInputAction.next,
                        autofillHints: const [
                          AutofillHints.username,
                          AutofillHints.email,
                        ],
                        prefixIcon: Icons.mail_outline,
                        validator: (value) =>
                            value == null || value.trim().isEmpty
                            ? 'Adresse e-mail ou identifiant requis'
                            : null,
                      ),
                      const SizedBox(height: 8),
                      AppPasswordField(
                        label: 'Mot de passe',
                        controller: _password,
                        textInputAction: TextInputAction.done,
                        autofillHints: const [AutofillHints.password],
                        validator: (value) => value == null || value.isEmpty
                            ? 'Mot de passe requis'
                            : null,
                        onSubmitted: (_) => _submit(),
                      ),
                      if (_error != null)
                        Container(
                          margin: const EdgeInsets.only(top: AppSpacing.sm),
                          padding: const EdgeInsets.symmetric(
                            horizontal: AppSpacing.md,
                            vertical: AppSpacing.sm,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.danger.withValues(alpha: .08),
                            borderRadius: BorderRadius.circular(AppRadius.md),
                          ),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Icon(
                                Icons.error_outline,
                                size: 18,
                                color: AppColors.danger,
                              ),
                              const SizedBox(width: AppSpacing.sm),
                              Expanded(
                                child: Text(
                                  _error!,
                                  style: AppTypography.secondary.copyWith(
                                    color: AppColors.danger,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      Align(
                        alignment: Alignment.centerRight,
                        child: AppButton.text(
                          compact: true,
                          label: 'Mot de passe oublié ?',
                          icon: Icons.help_outline_rounded,
                          onPressed: () => context.push('/forgot-password'),
                        ),
                      ),
                      const SizedBox(height: 4),
                      AppButton.primary(
                        label: 'Se connecter',
                        icon: Icons.login_rounded,
                        loading: _loading,
                        expanded: true,
                        onPressed: _submit,
                      ),
                      const SizedBox(height: 4),
                      const Text(
                        'Mode hors connexion disponible apr\u00e8s une premi\u00e8re connexion r\u00e9ussie.',
                        textAlign: TextAlign.center,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
