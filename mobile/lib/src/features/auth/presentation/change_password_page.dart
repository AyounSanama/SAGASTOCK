import 'package:flutter/material.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_password_field.dart';
import '../../../core/security/password_policy.dart';
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
  // Mot de passe temporaire imposé, ou changement volontaire depuis Mon profil.
  bool _mustChange = false;

  @override
  void initState() {
    super.initState();
    AuthService().cachedUser().then((user) {
      if (mounted) {
        setState(() => _mustChange = user?['must_change_password'] == true);
      }
    });
  }

  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (PasswordPolicy.validate(_password.text) != null ||
        _password.text != _confirmation.text) {
      setState(
        () => _error =
            '${PasswordPolicy.helperText} Les confirmations doivent correspondre.',
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
      if (!mounted) return;
      if (!_mustChange && context.canPop()) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Mot de passe modifié.')),
        );
        context.pop();
      } else {
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
                Text(
                  _mustChange
                      ? 'Pour prot\u00e9ger votre compte, remplacez le mot de passe temporaire avant de continuer.'
                      : 'Saisissez votre mot de passe actuel, puis le nouveau.',
                ),
                const SizedBox(height: 20),
                AppPasswordField(
                  controller: _current,
                  label: 'Mot de passe actuel',
                ),
                const SizedBox(height: 14),
                AppPasswordField(
                  controller: _password,
                  label: 'Nouveau mot de passe',
                ),
                const SizedBox(height: 14),
                AppPasswordField(
                  controller: _confirmation,
                  label: 'Confirmer le mot de passe',
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
                  label: 'Enregistrer le nouveau mot de passe',
                  icon: Icons.lock_reset_rounded,
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
