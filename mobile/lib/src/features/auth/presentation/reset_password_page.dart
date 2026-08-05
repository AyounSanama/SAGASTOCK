import 'package:flutter/material.dart';
import '../../../core/widgets/app_button.dart';
import 'package:go_router/go_router.dart';
import '../data/auth_service.dart';

class ResetPasswordPage extends StatefulWidget {
  const ResetPasswordPage({super.key});
  @override
  State<ResetPasswordPage> createState() => _ResetPasswordPageState();
}

class _ResetPasswordPageState extends State<ResetPasswordPage> {
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
    if (!_email.text.contains('@') ||
        _token.text.isEmpty ||
        _password.text.length < 12 ||
        _password.text != _confirmation.text) {
      setState(
        () => _error =
            'Vérifiez les champs : le mot de passe doit contenir au moins 12 caractères et les confirmations doivent correspondre.',
      );
      return;
    }
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
          const SnackBar(content: Text('Mot de passe réinitialisé.')),
        );
        context.go('/login');
      }
    } catch (_) {
      if (mounted) setState(() => _error = 'Code invalide ou expiré.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Nouveau mot de passe')),
    body: ListView(
      padding: const EdgeInsets.all(24),
      children: [
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 440),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextField(
                  controller: _email,
                  decoration: const InputDecoration(
                    labelText: 'Adresse e-mail',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: _token,
                  decoration: const InputDecoration(
                    labelText: 'Code de réinitialisation',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: _password,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Nouveau mot de passe',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: _confirmation,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Confirmer le mot de passe',
                    border: OutlineInputBorder(),
                  ),
                ),
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: Text(
                      _error!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  ),
                const SizedBox(height: 18),
                AppButton.validate(
                  label: 'Réinitialiser le mot de passe',
                  icon: Icons.restart_alt_rounded,
                  loading: _loading,
                  expanded: true,
                  onPressed: _submit,
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );
}
