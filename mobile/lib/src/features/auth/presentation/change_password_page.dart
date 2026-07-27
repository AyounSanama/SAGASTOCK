import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../data/auth_service.dart';

class ChangePasswordPage extends StatefulWidget {
  const ChangePasswordPage({super.key});
  @override
  State<ChangePasswordPage> createState() => _ChangePasswordPageState();
}

class _ChangePasswordPageState extends State<ChangePasswordPage> {
  final _current = TextEditingController(),
      _password = TextEditingController(),
      _confirmation = TextEditingController();
  bool _loading = false;
  String? _error;
  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_password.text.length < 12 || _password.text != _confirmation.text) {
      setState(
        () => _error =
            'Le mot de passe doit contenir au moins 12 caract\u00e8res et les confirmations doivent correspondre.',
      );
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await AuthService().changePassword(
        currentPassword: _current.text,
        password: _password.text,
      );
      if (mounted) {
        context.go('/home');
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _error =
              'Mot de passe actuel incorrect ou nouveau mot de passe invalide.',
        );
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Changer le mot de passe')),
    body: ListView(
      padding: const EdgeInsets.all(24),
      children: [
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 440),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'Pour prot\u00e9ger votre compte, remplacez le mot de passe temporaire avant de continuer.',
                ),
                const SizedBox(height: 20),
                TextField(
                  controller: _current,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Mot de passe actuel',
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
                FilledButton(
                  onPressed: _loading ? null : _submit,
                  child: const Padding(
                    padding: EdgeInsets.all(14),
                    child: Text('Enregistrer le nouveau mot de passe'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );
}
