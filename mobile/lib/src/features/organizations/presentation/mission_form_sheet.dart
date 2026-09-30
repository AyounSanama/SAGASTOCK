import 'package:flutter/material.dart';

import '../../../core/theme/app_tokens.dart';

import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_form_sheet.dart';

enum MissionFormMode { create, edit }

class MissionFormData {
  const MissionFormData({
    required this.countryId,
    required this.code,
    required this.name,
    required this.isActive,
    this.startsOn,
    this.endsOn,
    this.address,
    this.managerName,
    this.phone,
    this.email,
    this.description,
  });

  final String countryId;
  final String code;
  final String name;
  final DateTime? startsOn;
  final DateTime? endsOn;
  final String? address;
  final String? managerName;
  final String? phone;
  final String? email;
  final String? description;
  final bool isActive;
}

Future<String?> showMissionFormSheet({
  required BuildContext context,
  required String organizationId,
  required String organizationName,
  required List<Map<String, dynamic>> countries,
  required Future<bool> Function(MissionFormData data) onSave,
  MissionFormMode mode = MissionFormMode.create,
  Map<String, dynamic>? mission,
}) {
  return showAppFormSheet<String>(
    context: context,
    title: mode == MissionFormMode.create
        ? 'Ajouter une mission'
        : 'Modifier la mission',
    description: mode == MissionFormMode.create
        ? 'Enregistrez une nouvelle mission sans quitter cette page.'
        : 'Mettez à jour les informations de la mission.',
    builder: (sheetContext) => MissionFormSheet(
      organizationId: organizationId,
      organizationName: organizationName,
      countries: countries,
      mode: mode,
      mission: mission,
      onSave: onSave,
    ),
  );
}

class MissionFormSheet extends StatefulWidget {
  const MissionFormSheet({
    required this.organizationId,
    required this.organizationName,
    required this.countries,
    required this.mode,
    required this.onSave,
    this.mission,
    super.key,
  });

  final String organizationId;
  final String organizationName;
  final List<Map<String, dynamic>> countries;
  final MissionFormMode mode;
  final Map<String, dynamic>? mission;
  final Future<bool> Function(MissionFormData data) onSave;

  @override
  State<MissionFormSheet> createState() => _MissionFormSheetState();
}

class _MissionFormSheetState extends State<MissionFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _code;
  late final TextEditingController _name;
  late final TextEditingController _address;
  late final TextEditingController _manager;
  late final TextEditingController _phone;
  late final TextEditingController _email;
  late final TextEditingController _description;
  late String _countryId;
  DateTime? _startsOn;
  DateTime? _endsOn;
  bool _isActive = true;
  bool _saving = false;
  String? _saveError;

  @override
  void initState() {
    super.initState();
    final mission = widget.mission ?? const <String, dynamic>{};
    final country = mission['country'] as Map<String, dynamic>?;
    _code = TextEditingController(text: '${mission['code'] ?? ''}');
    _name = TextEditingController(text: '${mission['name'] ?? ''}');
    _address = TextEditingController(text: '${mission['address'] ?? ''}');
    _manager = TextEditingController(text: '${mission['manager_name'] ?? ''}');
    _phone = TextEditingController(text: '${mission['phone'] ?? ''}');
    _email = TextEditingController(text: '${mission['email'] ?? ''}');
    _description = TextEditingController(
      text: '${mission['description'] ?? ''}',
    );
    _countryId =
        '${mission['country_id'] ?? country?['id'] ?? widget.countries.first['id']}';
    _startsOn = _parseDate(mission['starts_on']);
    _endsOn = _parseDate(mission['ends_on']);
    _isActive = mission['is_active'] as bool? ?? true;
  }

  DateTime? _parseDate(dynamic value) {
    if (value == null || '$value'.isEmpty) return null;
    return DateTime.tryParse('$value');
  }

  @override
  void dispose() {
    _code.dispose();
    _name.dispose();
    _address.dispose();
    _manager.dispose();
    _phone.dispose();
    _email.dispose();
    _description.dispose();
    super.dispose();
  }

  String? _optional(TextEditingController controller) {
    final value = controller.text.trim();
    return value.isEmpty ? null : value;
  }

  Future<void> _selectDate({required bool start}) async {
    final initial = (start ? _startsOn : _endsOn) ?? DateTime.now();
    final selected = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
    if (selected == null || !mounted) return;
    setState(() {
      if (start) {
        _startsOn = selected;
        if (_endsOn != null && _endsOn!.isBefore(selected)) _endsOn = null;
      } else {
        _endsOn = selected;
      }
    });
  }

  Future<void> _submit() async {
    setState(() => _saveError = null);
    if (!(_formKey.currentState?.validate() ?? false)) return;
    if (_startsOn != null && _endsOn != null && _endsOn!.isBefore(_startsOn!)) {
      setState(
        () => _saveError =
            'La date de fin doit être postérieure à la date de début.',
      );
      return;
    }
    setState(() => _saving = true);
    try {
      final synchronized = await widget.onSave(
        MissionFormData(
          countryId: _countryId,
          code: _code.text.trim(),
          name: _name.text.trim(),
          startsOn: _startsOn,
          endsOn: _endsOn,
          address: _optional(_address),
          managerName: _optional(_manager),
          phone: _optional(_phone),
          email: _optional(_email),
          description: _optional(_description),
          isActive: _isActive,
        ),
      );
      if (mounted) Navigator.pop(context, synchronized ? 'synced' : 'pending');
    } catch (_) {
      if (mounted) {
        setState(() {
          _saving = false;
          _saveError =
              'Enregistrement impossible. Vérifiez les informations et réessayez.';
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            LayoutBuilder(
              builder: (context, constraints) {
                final wide = constraints.maxWidth >= 560;
                final fields = [
                  _field(
                    controller: _name,
                    label: 'Nom de la mission',
                    icon: Icons.flag_outlined,
                    isRequired: true,
                  ),
                  _field(
                    controller: _code,
                    label: 'Code de la mission',
                    icon: Icons.tag_rounded,
                    isRequired: true,
                  ),
                ];
                return wide
                    ? Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(child: fields[0]),
                          const SizedBox(width: 14),
                          Expanded(child: fields[1]),
                        ],
                      )
                    : Column(
                        children: [
                          fields[0],
                          const SizedBox(height: 14),
                          fields[1],
                        ],
                      );
              },
            ),
            const SizedBox(height: 14),
            DropdownButtonFormField<String>(
              key: const Key('mission-country'),
              initialValue: _countryId,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Pays *',
                prefixIcon: Icon(Icons.public_rounded),
              ),
              items: [
                for (final country in widget.countries)
                  DropdownMenuItem(
                    value: '${country['id']}',
                    child: Text('${country['name']} (${country['iso2']})'),
                  ),
              ],
              onChanged: _saving
                  ? null
                  : (value) => setState(() => _countryId = value!),
              validator: (value) =>
                  value == null || value.isEmpty ? 'Champ obligatoire' : null,
            ),
            const SizedBox(height: 14),
            DropdownButtonFormField<String>(
              key: const Key('mission-organization'),
              initialValue: widget.organizationId,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Organisation associée *',
                prefixIcon: Icon(Icons.business_outlined),
              ),
              items: [
                DropdownMenuItem(
                  value: widget.organizationId,
                  child: Text(widget.organizationName),
                ),
              ],
              onChanged: _saving ? null : (_) {},
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: _DateField(
                    key: const Key('mission-starts-on'),
                    label: 'Date de début',
                    value: _startsOn,
                    enabled: !_saving,
                    onTap: () => _selectDate(start: true),
                    onClear: () => setState(() => _startsOn = null),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _DateField(
                    key: const Key('mission-ends-on'),
                    label: 'Date de fin',
                    value: _endsOn,
                    enabled: !_saving,
                    onTap: () => _selectDate(start: false),
                    onClear: () => setState(() => _endsOn = null),
                  ),
                ),
              ],
            ),
            if (_startsOn != null &&
                _endsOn != null &&
                _endsOn!.isBefore(_startsOn!)) ...[
              const SizedBox(height: 8),
              const Text(
                'La date de fin doit être postérieure à la date de début.',
                style: TextStyle(color: AppColors.dangerText),
              ),
            ],
            const SizedBox(height: 14),
            _field(
              controller: _address,
              label: 'Adresse',
              icon: Icons.location_on_outlined,
            ),
            const SizedBox(height: 14),
            _field(
              controller: _manager,
              label: 'Responsable',
              icon: Icons.person_outline_rounded,
            ),
            const SizedBox(height: 14),
            _field(
              controller: _phone,
              label: 'Téléphone',
              icon: Icons.phone_outlined,
              keyboardType: TextInputType.phone,
            ),
            const SizedBox(height: 14),
            _field(
              controller: _email,
              label: 'Email',
              icon: Icons.email_outlined,
              keyboardType: TextInputType.emailAddress,
              validator: (value) {
                final text = value?.trim() ?? '';
                if (text.isEmpty) return null;
                return RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(text)
                    ? null
                    : 'Adresse email invalide';
              },
            ),
            const SizedBox(height: 14),
            _field(
              controller: _description,
              label: 'Description',
              icon: Icons.notes_rounded,
              maxLines: 3,
            ),
            const SizedBox(height: 10),
            SwitchListTile.adaptive(
              contentPadding: EdgeInsets.zero,
              title: const Text('Mission active'),
              subtitle: Text(_isActive ? 'Statut actif' : 'Statut inactif'),
              value: _isActive,
              onChanged: _saving
                  ? null
                  : (value) => setState(() => _isActive = value),
            ),
            if (_saveError != null) ...[
              const SizedBox(height: 8),
              Text(
                _saveError!,
                key: const Key('mission-save-error'),
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ],
            const SizedBox(height: 18),
            Row(
              children: [
                Expanded(
                  child: AppButton.cancel(
                    onPressed: _saving ? null : () => Navigator.pop(context),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: widget.mode == MissionFormMode.create
                      ? AppButton.add(
                          label: 'Ajouter',
                          loading: _saving,
                          onPressed: _submit,
                        )
                      : AppButton.save(
                          label: 'Enregistrer',
                          loading: _saving,
                          onPressed: _submit,
                        ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _field({
    required TextEditingController controller,
    required String label,
    required IconData icon,
    bool isRequired = false,
    int maxLines = 1,
    TextInputType? keyboardType,
    String? Function(String?)? validator,
  }) {
    return TextFormField(
      controller: controller,
      enabled: !_saving,
      maxLines: maxLines,
      keyboardType: keyboardType,
      decoration: InputDecoration(
        labelText: isRequired ? '$label *' : label,
        prefixIcon: Icon(icon),
        alignLabelWithHint: maxLines > 1,
      ),
      validator:
          validator ??
          (isRequired
              ? (value) =>
                    value?.trim().isEmpty == true ? 'Champ obligatoire' : null
              : null),
    );
  }
}

class _DateField extends StatelessWidget {
  const _DateField({
    required this.label,
    required this.value,
    required this.enabled,
    required this.onTap,
    required this.onClear,
    super.key,
  });

  final String label;
  final DateTime? value;
  final bool enabled;
  final VoidCallback onTap;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final text = value == null
        ? 'Non renseignée'
        : '${value!.day.toString().padLeft(2, '0')}/'
              '${value!.month.toString().padLeft(2, '0')}/${value!.year}';
    return InkWell(
      onTap: enabled ? onTap : null,
      borderRadius: BorderRadius.circular(12),
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: const Icon(Icons.calendar_month_outlined),
          suffixIcon: value == null
              ? null
              : IconButton(
                  tooltip: 'Effacer',
                  onPressed: enabled ? onClear : null,
                  icon: const Icon(Icons.close_rounded),
                ),
        ),
        child: Text(text),
      ),
    );
  }
}
