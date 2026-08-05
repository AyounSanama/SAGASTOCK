import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/widgets/app_button.dart';
import '../data/auth_service.dart';

class ForgotPasswordPage extends StatefulWidget {
  const ForgotPasswordPage({super.key});
  @override
  State<ForgotPasswordPage> createState() => _ForgotPasswordPageState();
}

class _ForgotPasswordPageState extends State<ForgotPasswordPage> {
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
    if (!_email.text.contains('@')) {
      setState(() => _error = 'Adresse e-mail invalide.');
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final message = await AuthService().forgotPassword(_email.text.trim());
      if (mounted) {
        setState(() => _message = message);
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = 'Demande impossible. R\u00e9essayez plus tard.',
        );
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mot de passe oubli\u00e9')),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 440),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Icon(
                  Icons.lock_reset,
                  size: 72,
                  color: Theme.of(context).colorScheme.primary,
                ),
                const SizedBox(height: 20),
                Text(
                  'R\u00e9cup\u00e9rer votre acc\u00e8s',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  "Indiquez l'adresse e-mail associ\u00e9e \u00e0 votre compte. La r\u00e9ponse ne r\u00e9v\u00e9lera jamais si ce compte existe.",
                ),
                const SizedBox(height: 24),
                TextField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(
                    labelText: 'Adresse e-mail',
                    border: OutlineInputBorder(),
                    prefixIcon: Icon(Icons.email_outlined),
                  ),
                ),
                if (_message != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: Text(
                      _message!,
                      style: const TextStyle(color: Colors.green),
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
                AppButton.save(
                  label: 'Envoyer le lien',
                  icon: Icons.mark_email_read_outlined,
                  loading: _loading,
                  expanded: true,
                  onPressed: _submit,
                ),
                AppButton.text(
                  expanded: true,
                  icon: Icons.key_outlined,
                  label: "J'ai déjà un code de réinitialisation",
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
        ),
      ),
    );
  }
}
