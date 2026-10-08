import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:go_router/go_router.dart';
import '../../../core/access/application_access.dart';
import '../../../core/widgets/app_button.dart';
import '../data/auth_service.dart';
import '../../../core/widgets/app_navigation_drawer.dart';
import '../../../core/localization/app_locale.dart';
import '../../../core/theme/app_theme_mode.dart';

class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});
  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  final _auth = AuthService();
  Map<String, dynamic>? _user;
  bool _loading = true;
  bool _savingLocale = false;
  String _preferredLocale = 'fr';
  @override
  void initState() {
    super.initState();
    _load();
  }

  Widget _information(String label, Object? value, IconData icon) {
    final text = '${value ?? ''}'.trim();
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Icon(icon),
      title: Text(label),
      subtitle: Text(text.isEmpty ? 'Non renseign\u00e9' : text),
    );
  }

  Future<void> _load() async {
    final user = await _auth.cachedUser();
    if (mounted) {
      setState(() {
        _user = user;
        _preferredLocale = '${user?['preferred_locale'] ?? 'fr'}';
        _loading = false;
      });
    }
  }

  Future<void> _editProfile() async {
    final user = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _ProfileForm(user: _user ?? const {}, auth: _auth),
    );
    if (user == null || !mounted) return;
    setState(() => _user = user);
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Profil mis à jour.')),
    );
  }

  Future<void> _saveLocale() async {
    setState(() => _savingLocale = true);
    await _auth.updatePreferredLocale(_preferredLocale);
    AppLocale.apply(_preferredLocale);
    if (mounted) {
      setState(() => _savingLocale = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Préférence de langue enregistrée.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    drawer: const AppNavigationDrawer(),
    appBar: AppBar(title: const Text('Mon profil')),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : ListView(
            padding: const EdgeInsets.all(20),
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          const CircleAvatar(
                            radius: 28,
                            child: Icon(Icons.person_outline_rounded),
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${_user?['name'] ?? 'Utilisateur'}',
                                  style: Theme.of(context).textTheme.titleLarge
                                      ?.copyWith(fontWeight: FontWeight.w800),
                                ),
                                Text(ApplicationAccess.roleLabel(_user)),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const Divider(height: 28),
                      _information(
                        'Pr\u00e9nom',
                        _user?['first_name'],
                        Icons.badge_outlined,
                      ),
                      _information(
                        'Nom',
                        _user?['last_name'],
                        Icons.badge_outlined,
                      ),
                      _information(
                        'Adresse e-mail',
                        _user?['email'],
                        Icons.email_outlined,
                      ),
                      _information(
                        'T\u00e9l\u00e9phone',
                        _user?['phone'],
                        Icons.phone_outlined,
                      ),
                      _information(
                        'Identifiant',
                        _user?['username'],
                        Icons.account_circle_outlined,
                      ),
                      if (_user?['organization'] is Map)
                        _information(
                          'Organisation',
                          (_user!['organization'] as Map)['name'],
                          Icons.apartment_outlined,
                        ),
                      if (_user?['coordination'] is Map)
                        _information(
                          'Coordination',
                          (_user!['coordination'] as Map)['name'],
                          Icons.public_outlined,
                        ),
                      const SizedBox(height: 8),
                      AppButton.edit(
                        label: 'Modifier mes informations',
                        icon: Icons.edit_outlined,
                        expanded: true,
                        onPressed: _editProfile,
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        'Préférences',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 14),
                      DropdownButtonFormField<String>(
                        initialValue: _preferredLocale,
                        decoration: const InputDecoration(
                          labelText: 'Langue de l’interface',
                          prefixIcon: Icon(Icons.language_outlined),
                        ),
                        items: const [
                          DropdownMenuItem(
                            value: 'fr',
                            child: Text('Français'),
                          ),
                        ],
                        onChanged: (value) =>
                            setState(() => _preferredLocale = value ?? 'fr'),
                      ),
                      const SizedBox(height: 12),
                      AppButton.save(
                        label: 'Enregistrer la langue',
                        expanded: true,
                        loading: _savingLocale,
                        onPressed: _savingLocale ? null : _saveLocale,
                      ),
                      const SizedBox(height: 18),
                      // Niveau 4 : Clair / Sombre / Système, appliqué immédiatement.
                      const Text('Mode d’affichage', style: TextStyle(fontWeight: FontWeight.w600)),
                      const SizedBox(height: 8),
                      SegmentedButton<String>(
                        segments: [
                          for (final (value, icon) in const [
                            ('light', Icons.light_mode_outlined),
                            ('dark', Icons.dark_mode_outlined),
                            ('system', Icons.contrast),
                          ])
                            ButtonSegment(
                              value: value,
                              icon: Icon(icon),
                              // Jamais coupé sur deux lignes (« Systèm / e ») :
                              // réduit si l'écran est étroit.
                              label: FittedBox(
                                fit: BoxFit.scaleDown,
                                child: Text(
                                  AppThemeMode.labels[value]!,
                                  maxLines: 1,
                                  softWrap: false,
                                ),
                              ),
                            ),
                        ],
                        selected: {AppThemeMode.preference.value},
                        showSelectedIcon: false,
                        onSelectionChanged: (selection) {
                          final preference = selection.first;
                          AppThemeMode.apply(preference);
                          _auth.updateThemePreference(preference);
                        },
                      ),
                      const SizedBox(height: 6),
                      const Text(
                        '« Système » suit le réglage du téléphone.',
                        style: TextStyle(fontSize: 13),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              AppButton.edit(
                expanded: true,
                label: 'Changer mon mot de passe',
                icon: Icons.password_rounded,
                onPressed: () => context.push('/change-password'),
              ),
              const SizedBox(height: 20),
              AppButton.delete(
                expanded: true,
                label: 'Se déconnecter',
                icon: Icons.logout_rounded,
                onPressed: () async {
                  await _auth.logout();
                  if (context.mounted) context.go('/login');
                },
              ),
            ],
          ),
  );
}

/// « Modifier mes informations » : mêmes règles que le Web (serveur).
class _ProfileForm extends StatefulWidget {
  const _ProfileForm({required this.user, required this.auth});

  final Map<String, dynamic> user;
  final AuthService auth;

  @override
  State<_ProfileForm> createState() => _ProfileFormState();
}

class _ProfileFormState extends State<_ProfileForm> {
  late final _fields = {
    for (final key in ['first_name', 'last_name', 'username', 'email', 'phone'])
      key: TextEditingController(text: '${widget.user[key] ?? ''}'),
  };
  Map<String, String> _errors = {};
  bool _saving = false;

  static const _labels = {
    'first_name': 'Prénom *',
    'last_name': 'Nom *',
    'username': 'Identifiant *',
    'email': 'Adresse e-mail *',
    'phone': 'Téléphone',
  };

  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _errors = {};
    });
    try {
      final user = await widget.auth.updateProfile({
        for (final entry in _fields.entries) entry.key: entry.value.text.trim(),
      });
      if (mounted) Navigator.pop(context, user);
    } on DioException catch (error) {
      final errors = error.response?.data is Map
          ? (error.response!.data as Map)['errors']
          : null;
      if (!mounted) return;
      setState(() {
        _saving = false;
        _errors = errors is Map
            ? {
                for (final entry in errors.entries)
                  '${entry.key}': '${(entry.value as List).first}',
              }
            : {'_': 'Enregistrement impossible. Vérifiez la connexion.'};
      });
    }
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(
      20,
      0,
      20,
      20 + MediaQuery.viewInsetsOf(context).bottom,
    ),
    child: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Modifier mes informations',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: 16),
          for (final entry in _fields.entries) ...[
            TextField(
              controller: entry.value,
              keyboardType: switch (entry.key) {
                'email' => TextInputType.emailAddress,
                'phone' => TextInputType.phone,
                _ => TextInputType.text,
              },
              decoration: InputDecoration(
                labelText: _labels[entry.key],
                errorText: _errors[entry.key],
              ),
            ),
            const SizedBox(height: 12),
          ],
          if (_errors['_'] != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(
                _errors['_']!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          AppButton.save(
            label: 'Enregistrer',
            expanded: true,
            loading: _saving,
            onPressed: _saving ? null : _save,
          ),
        ],
      ),
    ),
  );
}
