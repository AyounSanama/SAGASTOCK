import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/widgets/app_button.dart';
import '../../../core/security/password_policy.dart';
import '../data/organization_service.dart';

// Comme le serveur (alpha_dash) : lettres accentuées acceptées, sans espace.
final RegExp _adminUsernamePattern = RegExp(r'^[\p{L}\p{M}\p{N}_-]+$', unicode: true);

String? validateOrganizationAdminUsername(String? value) {
  final username = value?.trim() ?? '';
  if (username.isEmpty) return 'Champ obligatoire.';
  if (!_adminUsernamePattern.hasMatch(username)) {
    return 'Utilisez uniquement des lettres, chiffres, tirets (-) et underscores (_).';
  }
  return null;
}

String organizationCreationErrorMessage(DioException exception) {
  final responseData = exception.response?.data;
  final errors = responseData is Map ? responseData['errors'] as Map? : null;
  if (errors != null) {
    if (errors.containsKey('country_ids') ||
        errors.keys.any((key) => '$key'.startsWith('country_ids.'))) {
      return 'Veuillez sélectionner un pays valide.';
    }
    if (errors.containsKey('admin_username')) {
      return 'Veuillez vérifier l’identifiant de l’administrateur. Utilisez uniquement des lettres, chiffres, tirets (-) et underscores (_).';
    }
    return errors.values
        .expand((value) => value is List ? value : [value])
        .join('\n');
  }
  return 'Création impossible. Vérifiez les informations saisies.';
}

class OrganizationCreationPage extends StatefulWidget {
  const OrganizationCreationPage({super.key});

  @override
  State<OrganizationCreationPage> createState() =>
      _OrganizationCreationPageState();
}

class _OrganizationCreationPageState extends State<OrganizationCreationPage> {
  final _service = OrganizationService();
  final _keys = List.generate(3, (_) => GlobalKey<FormState>());
  final name = TextEditingController(), code = TextEditingController();
  final email = TextEditingController(), phone = TextEditingController();
  final firstName = TextEditingController(), lastName = TextEditingController();
  final adminEmail = TextEditingController(),
      adminPhone = TextEditingController();
  final username = TextEditingController(), password = TextEditingController();
  final countrySearch = TextEditingController();
  final _picker = ImagePicker();
  XFile? logo;
  List<Map<String, dynamic>> countries = [];
  final Set<String> selectedCountries = {};
  int step = 0;
  bool loadingCountries = true, saving = false, multiCountry = false;
  String status = 'active', adminStatus = 'active';
  String activationMode = 'temporary_password';
  String adminCountryId = '';
  String? error;

  @override
  void initState() {
    super.initState();
    _loadCountries();
  }

  Future<void> _loadCountries() async {
    setState(() {
      loadingCountries = true;
      error = null;
    });
    try {
      final values = await _service.countries();
      if (mounted) {
        setState(() {
          countries = values;
          if (values.isEmpty) {
            error =
                'Aucun pays n\u2019est disponible. Synchronisez les r\u00e9f\u00e9rentiels puis r\u00e9essayez.';
          }
        });
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => error = 'Impossible de charger le référentiel des pays.',
        );
      }
    } finally {
      if (mounted) setState(() => loadingCountries = false);
    }
  }

  @override
  void dispose() {
    for (final controller in [
      name,
      code,
      email,
      phone,
      firstName,
      lastName,
      adminEmail,
      adminPhone,
      username,
      password,
      countrySearch,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<bool> _back() async {
    if (step > 0) {
      setState(() => step--);
      return false;
    }
    return true;
  }

  void _continue() {
    if (step < 3 && !(_keys[step].currentState?.validate() ?? false)) return;
    if (step == 1 && selectedCountries.isEmpty) {
      setState(() => error = 'Sélectionnez au moins un pays autorisé.');
      return;
    }
    if (step == 1 &&
        multiCountry &&
        !selectedCountries.contains(adminCountryId)) {
      setState(
        () =>
            error = 'Sélectionnez la coordination pays principale de l’Admin.',
      );
      return;
    }
    setState(() {
      error = null;
      step++;
    });
  }

  Future<void> _submit() async {
    if (saving) return;
    setState(() {
      saving = true;
      error = null;
    });
    try {
      final response = await _service.create({
        'name': name.text.trim(),
        'code': code.text.trim().toUpperCase(),
        'email': email.text.trim().isEmpty ? null : email.text.trim(),
        'phone': phone.text.trim().isEmpty ? null : phone.text.trim(),
        'status': status,
        'geographic_access_type': multiCountry
            ? 'multi_country'
            : 'single_country',
        'country_ids': selectedCountries.toList(),
        if (multiCountry) 'admin_country_id': adminCountryId,
        'admin_first_name': firstName.text.trim(),
        'admin_last_name': lastName.text.trim(),
        'admin_email': adminEmail.text.trim(),
        'admin_phone': adminPhone.text.trim().isEmpty
            ? null
            : adminPhone.text.trim(),
        'admin_username': username.text.trim(),
        'admin_status': adminStatus,
        'activation_mode': activationMode,
        if (activationMode == 'temporary_password')
          'admin_password': password.text,
      }, logo: logo);
      if (!mounted) return;
      Navigator.pop(context, response);
    } on DioException catch (exception) {
      setState(() => error = organizationCreationErrorMessage(exception));
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: step == 0,
    onPopInvokedWithResult: (didPop, _) {
      if (!didPop) _back();
    },
    child: Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: saving
              ? null
              : () {
                  if (step > 0) {
                    _back();
                  } else {
                    Navigator.pop(context);
                  }
                },
        ),
        title: const Text('Ajouter une organisation'),
      ),
      body: SafeArea(
        child: Column(
          children: [
            LinearProgressIndicator(value: (step + 1) / 4),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
              child: Row(
                children: [
                  CircleAvatar(radius: 18, child: Text('${step + 1}')),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Étape ${step + 1} sur 4',
                          style: Theme.of(context).textTheme.labelLarge,
                        ),
                        Text(
                          const [
                            'Organisation',
                            'Périmètre géographique',
                            'Admin Coordination',
                            'Résumé',
                          ][step],
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            if (error != null)
              Container(
                width: double.infinity,
                margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.errorContainer,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  error!,
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onErrorContainer,
                  ),
                ),
              ),
            Expanded(
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 180),
                child: SingleChildScrollView(
                  key: ValueKey(step),
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                  child: _content(),
                ),
              ),
            ),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.surface,
                border: Border(
                  top: BorderSide(color: Theme.of(context).dividerColor),
                ),
              ),
              child: Row(
                children: [
                  if (step > 0)
                    Expanded(
                      child: AppButton.text(
                        label: 'Retour',
                        icon: Icons.arrow_back_rounded,
                        onPressed: saving ? null : () => _back(),
                      ),
                    )
                  else
                    Expanded(
                      child: AppButton.cancel(
                        label: 'Annuler',
                        onPressed: saving ? null : () => Navigator.pop(context),
                      ),
                    ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: step == 3
                        ? AppButton.validate(
                            label: 'Créer l’organisation',
                            loading: saving,
                            onPressed: _submit,
                          )
                        : AppButton.primary(
                            label: 'Continuer',
                            icon: Icons.arrow_forward_rounded,
                            trailingIcon: true,
                            onPressed: _continue,
                          ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  );

  Widget _content() => switch (step) {
    0 => _organization(),
    1 => _scope(),
    2 => _admin(),
    _ => _summary(),
  };

  Widget _organization() => Form(
    key: _keys[0],
    child: _card(
      children: [
        _field(
          name,
          'Nom de l’organisation *',
          Icons.business_outlined,
          required: true,
        ),
        _field(code, 'Code organisation *', Icons.tag_rounded, required: true),
        _field(
          email,
          'Email institutionnel',
          Icons.email_outlined,
          emailField: true,
        ),
        _field(
          phone,
          'Téléphone',
          Icons.phone_outlined,
          keyboard: TextInputType.phone,
        ),
        DropdownButtonFormField(
          initialValue: status,
          decoration: const InputDecoration(
            labelText: 'Statut *',
            prefixIcon: Icon(Icons.toggle_on_outlined),
          ),
          items: const [
            DropdownMenuItem(value: 'active', child: Text('Actif')),
            DropdownMenuItem(value: 'inactive', child: Text('Inactif')),
          ],
          onChanged: (value) => setState(() => status = value!),
        ),
        OutlinedButton.icon(
          icon: const Icon(Icons.image_outlined),
          label: Text(
            logo == null
                ? 'Sélectionner le logo'
                : 'Changer le logo (${logo!.name})',
          ),
          onPressed: () async {
            final selected = await _picker.pickImage(
              source: ImageSource.gallery,
              maxWidth: 1600,
              imageQuality: 88,
              requestFullMetadata: false,
            );
            if (selected != null && mounted) setState(() => logo = selected);
          },
        ),
        if (logo != null)
          FutureBuilder<List<int>>(
            future: logo!.readAsBytes(),
            builder: (context, snapshot) => snapshot.hasData
                ? ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: Image.memory(
                      Uint8List.fromList(snapshot.data!),
                      height: 96,
                      fit: BoxFit.contain,
                    ),
                  )
                : const SizedBox(
                    height: 48,
                    child: Center(child: CircularProgressIndicator()),
                  ),
          ),
      ],
    ),
  );

  Widget _scope() => Form(
    key: _keys[1],
    child: _card(
      children: [
        DropdownButtonFormField<bool>(
          initialValue: multiCountry,
          decoration: const InputDecoration(
            labelText: 'Type d’accès *',
            prefixIcon: Icon(Icons.public_rounded),
          ),
          items: const [
            DropdownMenuItem(value: false, child: Text('Unipays')),
            DropdownMenuItem(value: true, child: Text('Multipays')),
          ],
          onChanged: (value) => setState(() {
            multiCountry = value ?? false;
            if (!multiCountry && selectedCountries.length > 1) {
              final first = selectedCountries.first;
              selectedCountries
                ..clear()
                ..add(first);
            }
            if (!multiCountry) adminCountryId = '';
          }),
        ),
        const SizedBox(height: 16),
        if (loadingCountries)
          const Column(
            children: [
              CircularProgressIndicator(),
              SizedBox(height: 10),
              Text('Chargement des pays\u2026'),
            ],
          )
        else if (countries.isEmpty)
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Le r\u00e9f\u00e9rentiel des pays est indisponible. V\u00e9rifiez la synchronisation ou la connexion au serveur.',
              ),
              const SizedBox(height: 10),
              AppButton.text(
                label: 'R\u00e9essayer',
                onPressed: _loadCountries,
              ),
            ],
          )
        else ...[
          OutlinedButton.icon(
            icon: const Icon(Icons.flag_outlined),
            label: Text(
              selectedCountries.isEmpty
                  ? (multiCountry
                        ? 'Sélectionner les pays'
                        : 'Sélectionner un pays')
                  : '${selectedCountries.length} pays sélectionné${selectedCountries.length > 1 ? 's' : ''}',
            ),
            onPressed: _selectCountries,
          ),
          if (selectedCountries.isNotEmpty)
            Wrap(
              spacing: 8,
              runSpacing: 6,
              children: countries
                  .where(
                    (country) => selectedCountries.contains('${country['id']}'),
                  )
                  .map(
                    (country) => InputChip(
                      label: Text('${country['name']}'),
                      onDeleted: () => setState(
                        () => selectedCountries.remove('${country['id']}'),
                      ),
                    ),
                  )
                  .toList(),
            ),
          if (multiCountry && selectedCountries.isNotEmpty)
            DropdownButtonFormField<String>(
              key: ValueKey('admin-country-${selectedCountries.join('-')}'),
              initialValue: selectedCountries.contains(adminCountryId)
                  ? adminCountryId
                  : null,
              decoration: const InputDecoration(
                labelText: 'Coordination principale de l’Admin *',
                prefixIcon: Icon(Icons.admin_panel_settings_outlined),
              ),
              items: countries
                  .where(
                    (country) => selectedCountries.contains('${country['id']}'),
                  )
                  .map(
                    (country) => DropdownMenuItem<String>(
                      value: '${country['id']}',
                      child: Text('${country['name']}'),
                    ),
                  )
                  .toList(),
              onChanged: (value) =>
                  setState(() => adminCountryId = value ?? ''),
            ),
        ],
      ],
    ),
  );

  Future<void> _selectCountries() async {
    final draft = Set<String>.from(selectedCountries);
    countrySearch.clear();
    final result = await showModalBottomSheet<Set<String>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, updateSheet) {
          final query = countrySearch.text.trim().toLowerCase();
          final visible = countries.where(
            (country) =>
                query.isEmpty ||
                '${country['name']}'.toLowerCase().contains(query) ||
                '${country['iso2']}'.toLowerCase().contains(query),
          );
          return FractionallySizedBox(
            heightFactor: .86,
            child: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 18, 20, 10),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        multiCountry
                            ? 'Sélectionner les pays'
                            : 'Sélectionner un pays',
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 14),
                      TextField(
                        controller: countrySearch,
                        autofocus: true,
                        decoration: const InputDecoration(
                          hintText: 'Rechercher un pays…',
                          prefixIcon: Icon(Icons.search_rounded),
                        ),
                        onChanged: (_) => updateSheet(() {}),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: ListView(
                    children: visible
                        .map(
                          (country) => CheckboxListTile(
                            value: draft.contains('${country['id']}'),
                            title: Text('${country['name']}'),
                            subtitle: Text('${country['iso2']}'),
                            controlAffinity: ListTileControlAffinity.leading,
                            onChanged: (checked) => updateSheet(() {
                              final id = '${country['id']}';
                              if (checked == true) {
                                if (!multiCountry) draft.clear();
                                draft.add(id);
                              } else {
                                draft.remove(id);
                              }
                            }),
                          ),
                        )
                        .toList(),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    children: [
                      Expanded(
                        child: AppButton.cancel(
                          label: 'Annuler',
                          onPressed: () => Navigator.pop(sheetContext),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: AppButton.validate(
                          label: 'Valider (${draft.length})',
                          onPressed: draft.isEmpty
                              ? null
                              : () => Navigator.pop(sheetContext, draft),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
    if (result != null && mounted) {
      setState(() {
        selectedCountries
          ..clear()
          ..addAll(result);
        if (!selectedCountries.contains(adminCountryId)) adminCountryId = '';
      });
    }
  }

  Widget _admin() => Form(
    key: _keys[2],
    child: _card(
      children: [
        const ListTile(
          contentPadding: EdgeInsets.zero,
          leading: Icon(Icons.lock_outline_rounded),
          title: Text('Rôle attribué automatiquement'),
          subtitle: Text('ADMIN_COORDINATION'),
        ),
        _field(firstName, 'Prénom *', Icons.person_outline, required: true),
        _field(lastName, 'Nom *', Icons.person_outline, required: true),
        _field(
          adminEmail,
          'Email de connexion *',
          Icons.email_outlined,
          required: true,
          emailField: true,
        ),
        _field(
          adminPhone,
          'Téléphone',
          Icons.phone_outlined,
          keyboard: TextInputType.phone,
        ),
        TextFormField(
          controller: username,
          textInputAction: TextInputAction.next,
          decoration: const InputDecoration(
            labelText: 'Identifiant *',
            prefixIcon: Icon(Icons.badge_outlined),
            helperText:
                'Lettres, chiffres, tirets (-) et underscores (_) uniquement.',
          ),
          validator: validateOrganizationAdminUsername,
        ),
        DropdownButtonFormField(
          initialValue: activationMode,
          decoration: const InputDecoration(
            labelText: 'Mode d’activation *',
            prefixIcon: Icon(Icons.key_outlined),
          ),
          items: const [
            DropdownMenuItem(
              value: 'temporary_password',
              child: Text('Mot de passe temporaire'),
            ),
            DropdownMenuItem(value: 'invitation', child: Text('Invitation')),
          ],
          onChanged: (value) => setState(() => activationMode = value!),
        ),
        if (activationMode == 'temporary_password')
          _field(
            password,
            'Mot de passe temporaire *',
            Icons.lock_outline,
            required: true,
            obscure: true,
            minimum: PasswordPolicy.minimumLength,
          ),
        DropdownButtonFormField(
          initialValue: adminStatus,
          decoration: const InputDecoration(labelText: 'Statut du compte *'),
          items: const [
            DropdownMenuItem(value: 'active', child: Text('Actif')),
            DropdownMenuItem(value: 'inactive', child: Text('Inactif')),
          ],
          onChanged: (value) => setState(() => adminStatus = value!),
        ),
      ],
    ),
  );

  Widget _summary() {
    final selected = countries
        .where((country) => selectedCountries.contains('${country['id']}'))
        .map((country) => country['name'])
        .join(', ');
    return _card(
      children: [
        _summaryRow('Organisation', name.text),
        _summaryRow('Code', code.text.toUpperCase()),
        _summaryRow('Type d’accès', multiCountry ? 'Multipays' : 'Unipays'),
        _summaryRow('Pays autorisés', selected),
        if (multiCountry)
          _summaryRow(
            'Coordination principale',
            countries
                    .where((country) => '${country['id']}' == adminCountryId)
                    .map((country) => '${country['name']}')
                    .join()
                    .trim()
                    .isEmpty
                ? 'À sélectionner'
                : countries
                      .where((country) => '${country['id']}' == adminCountryId)
                      .map((country) => '${country['name']}')
                      .join(),
          ),
        _summaryRow('Logo', logo?.name ?? 'Non renseigné'),
        _summaryRow('Admin Coordination', '${firstName.text} ${lastName.text}'),
        _summaryRow('Email Admin', adminEmail.text),
        _summaryRow('Téléphone Admin', adminPhone.text),
        _summaryRow('Identifiant Admin', username.text),
        _summaryRow(
          'Activation',
          activationMode == 'invitation'
              ? 'Invitation'
              : 'Mot de passe temporaire',
        ),
      ],
    );
  }

  Widget _summaryRow(String label, String value) => ListTile(
    contentPadding: EdgeInsets.zero,
    title: Text(label),
    subtitle: Text(
      value.isEmpty ? 'Non renseigné' : value,
      style: const TextStyle(fontWeight: FontWeight.w700),
    ),
  );
  Widget _card({required List<Widget> children}) => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children:
            children
                .expand((child) => [child, const SizedBox(height: 14)])
                .toList()
              ..removeLast(),
      ),
    ),
  );
  Widget _field(
    TextEditingController controller,
    String label,
    IconData icon, {
    bool required = false,
    bool emailField = false,
    bool obscure = false,
    int minimum = 1,
    TextInputType? keyboard,
  }) => TextFormField(
    controller: controller,
    obscureText: obscure,
    keyboardType: emailField ? TextInputType.emailAddress : keyboard,
    textInputAction: TextInputAction.next,
    decoration: InputDecoration(labelText: label, prefixIcon: Icon(icon)),
    validator: (value) {
      final text = value?.trim() ?? '';
      if (required && text.length < minimum) {
        return minimum > 1
            ? 'Au moins $minimum caractères requis.'
            : 'Champ obligatoire.';
      }
      if (emailField &&
          text.isNotEmpty &&
          !RegExp(r'^[^@]+@[^@]+\.[^@]+$').hasMatch(text)) {
        return 'Adresse e-mail invalide.';
      }
      return null;
    },
  );
}
