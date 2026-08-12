import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../../core/widgets/app_navigation_drawer.dart';
import '../data/clinical_supply_service.dart';

class ClinicalSupplyPage extends StatefulWidget {
  const ClinicalSupplyPage({super.key, this.initialTab = 0});
  final int initialTab;
  @override
  State<ClinicalSupplyPage> createState() => _ClinicalSupplyPageState();
}

class _ClinicalSupplyPageState extends State<ClinicalSupplyPage>
    with SingleTickerProviderStateMixin {
  final service = ClinicalSupplyService();
  late final TabController tabs;
  List<Map<String, dynamic>> organizations = [],
      patients = [],
      prescriptions = [],
      dispensations = [];
  String? organizationId;
  bool loading = true;
  String? error;
  int pending = 0;
  @override
  void initState() {
    super.initState();
    tabs = TabController(
      length: 3,
      vsync: this,
      initialIndex: widget.initialTab,
    );
    _init();
  }

  @override
  void dispose() {
    tabs.dispose();
    super.dispose();
  }

  Future<void> _init() async {
    try {
      organizations = await service.organizations();
      organizationId = organizations.isEmpty
          ? null
          : '${organizations.first['id']}';
      await _load();
    } catch (_) {
      error = 'Chargement impossible.';
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _load() async {
    if (organizationId == null) return;
    if (mounted) setState(() => loading = true);
    try {
      final v = await Future.wait([
        service.patients(organizationId!),
        service.prescriptions(organizationId!),
        service.dispensations(organizationId!),
        service.pendingCount(),
      ]);
      patients = v[0] as List<Map<String, dynamic>>;
      prescriptions = v[1] as List<Map<String, dynamic>>;
      dispensations = v[2] as List<Map<String, dynamic>>;
      pending = v[3] as int;
      error = null;
    } on DioException catch (e) {
      error = e.response?.statusCode == 403
          ? 'Accès non autorisé.'
          : 'Mode hors connexion : données locales affichées.';
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  void message(String text) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
    }
  }

  String apiError(DioException e) {
    final d = e.response?.data;
    if (d is Map && d['errors'] is Map) {
      final value = (d['errors'] as Map).values.first;
      return value is List ? '${value.first}' : '$value';
    }
    return d is Map && d['message'] != null
        ? '${d['message']}'
        : 'Opération impossible.';
  }

  Future<void> execute(Future<Object?> Function() action, String success) async {
    try {
      final result = await action();
      message(result == false
          ? 'Action conservée hors connexion et à synchroniser.'
          : success);
      await _load();
    } on DioException catch (e) {
      message(apiError(e));
    }
  }

  Future<void> validateClinical(Map<String, dynamic> prescription) async {
    final data = await showAppFormSheet<Map<String, dynamic>>(
      context: context,
      title: 'Validation clinique',
      description:
          'Contrôlez le protocole, la posologie, les allergies et les contre-indications.',
      builder: (_) => const ClinicalValidationForm(),
    );
    if (data != null && organizationId != null) {
      await execute(
        () => service.validatePrescription(
          organizationId!,
          '${prescription['id']}',
          data,
        ),
        data['decision'] == 'reject'
            ? 'Ordonnance rejetée.'
            : 'Ordonnance validée cliniquement.',
      );
    }
  }

  Future<void> addPatient() async {
    final data = await showAppFormSheet<Map<String, dynamic>>(
      context: context,
      title: 'Nouveau patient',
      description: 'Créez un dossier patient rattaché à cette organisation.',
      builder: (_) => const PatientForm(),
    );
    if (data != null && organizationId != null) {
      await execute(
        () => service.createPatient(organizationId!, data),
        'Patient enregistré.',
      );
    }
  }

  Future<void> addPrescription() async {
    if (organizationId == null) return;
    try {
      final options = await service.options(organizationId!);
      if (!mounted) return;
      final data = await showAppFormSheet<Map<String, dynamic>>(
        context: context,
        title: 'Nouvelle ordonnance',
        description: 'Renseignez le patient, le prescripteur et le traitement.',
        builder: (_) => PrescriptionForm(options: options),
      );
      if (data != null) {
        await execute(
          () => service.createPrescription(organizationId!, data),
          'Ordonnance enregistrée.',
        );
      }
    } on DioException catch (e) {
      message(apiError(e));
    }
  }

  Future<void> addDispensation() async {
    if (organizationId == null) return;
    try {
      final options = await service.options(organizationId!);
      if (!mounted) return;
      final data = await showAppFormSheet<Map<String, dynamic>>(
        context: context,
        title: 'Dispenser des médicaments',
        description: 'Les lots sont sélectionnés automatiquement selon FEFO.',
        builder: (_) => DispensationForm(options: options),
      );
      if (data == null) return;
      final online = await service.dispense(organizationId!, data);
      message(
        online
            ? 'Dispensation validée et stock mis à jour.'
            : 'Dispensation conservée hors connexion.',
      );
      await _load();
    } on DioException catch (e) {
      message(apiError(e));
    }
  }

  @override
  Widget build(BuildContext c) => Scaffold(
    drawer: const AppNavigationDrawer(),
    appBar: AppBar(
      title: const Text('Patients et dispensation'),
      bottom: TabBar(
        controller: tabs,
        tabs: const [
          Tab(text: 'Patients'),
          Tab(text: 'Ordonnances'),
          Tab(text: 'Dispensations'),
        ],
      ),
    ),
    floatingActionButton: AppFab(
      tooltip: 'Ajouter',
      onPressed: organizationId == null
          ? null
          : () => switch (tabs.index) {
              0 => addPatient(),
              1 => addPrescription(),
              _ => addDispensation(),
            },
    ),
    body: Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: DropdownButtonFormField<String>(
            initialValue: organizationId,
            decoration: const InputDecoration(
              labelText: 'Organisation',
              prefixIcon: Icon(Icons.apartment_outlined),
            ),
            items: organizations
                .map(
                  (o) => DropdownMenuItem(
                    value: '${o['id']}',
                    child: Text('${o['name']}'),
                  ),
                )
                .toList(),
            onChanged: (v) {
              organizationId = v;
              _load();
            },
          ),
        ),
        if (pending > 0)
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 16),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: AppTheme.orangeSoft,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              '$pending dispensation(s) en attente de synchronisation',
            ),
          ),
        if (error != null)
          Padding(
            padding: const EdgeInsets.all(8),
            child: Text(error!, style: const TextStyle(color: AppTheme.red)),
          ),
        Expanded(
          child: loading
              ? const Center(child: CircularProgressIndicator())
              : TabBarView(
                  controller: tabs,
                  children: [
                    listPatients(),
                    listPrescriptions(),
                    listDispensations(),
                  ],
                ),
        ),
      ],
    ),
  );
  Widget empty(String text, IconData icon) => ListView(
    children: [
      const SizedBox(height: 80),
      Icon(icon, size: 52, color: AppTheme.muted),
      const SizedBox(height: 12),
      Center(child: Text(text)),
    ],
  );
  Widget listPatients() => patients.isEmpty
      ? empty('Aucun patient', Icons.people_outline)
      : RefreshIndicator(
          onRefresh: _load,
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: patients.length,
            itemBuilder: (_, i) {
              final p = patients[i];
              return Card(
                child: ListTile(
                  leading: const CircleAvatar(
                    backgroundColor: AppTheme.orangeSoft,
                    child: Icon(Icons.person_outline, color: AppTheme.orange),
                  ),
                  title: Text(
                    '${p['last_name']} ${p['first_name']}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  subtitle: Text(
                    '${p['code']} · ${p['phone'] ?? 'Sans téléphone'}',
                  ),
                ),
              );
            },
          ),
        );
  Widget listPrescriptions() => prescriptions.isEmpty
      ? empty('Aucune ordonnance', Icons.description_outlined)
      : RefreshIndicator(
          onRefresh: _load,
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: prescriptions.length,
            itemBuilder: (_, i) {
              final p = prescriptions[i], draft = p['status'] == 'draft';
              return Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              '${p['reference']}',
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          Status('${p['status']}'),
                        ],
                      ),
                      Text(
                        '${p['patient']?['last_name'] ?? ''} ${p['patient']?['first_name'] ?? ''}',
                      ),
                      Text(
                        '${(p['items'] as List? ?? []).length} produit(s) · ${p['prescriber_name']}',
                        style: const TextStyle(color: AppTheme.muted),
                      ),
                      if (draft) ...[
                        const SizedBox(height: 10),
                        AppButton.validate(
                          label: 'Valider l’ordonnance',
                          expanded: true,
                          compact: true,
                          onPressed: () => validateClinical(p),
                        ),
                      ],
                    ],
                  ),
                ),
              );
            },
          ),
        );
  Widget listDispensations() => dispensations.isEmpty
      ? empty('Aucune dispensation', Icons.local_pharmacy_outlined)
      : RefreshIndicator(
          onRefresh: _load,
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: dispensations.length,
            itemBuilder: (_, i) {
              final d = dispensations[i];
              return Card(
                child: ListTile(
                  leading: const CircleAvatar(
                    backgroundColor: Color(0xFFE8F7EE),
                    child: Icon(
                      Icons.medication_outlined,
                      color: AppTheme.green,
                    ),
                  ),
                  title: Text(
                    '${d['reference']}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  subtitle: Text(
                    '${d['patient']?['last_name'] ?? ''} · ${(d['items'] as List? ?? []).length} lot(s)',
                  ),
                  trailing: const Icon(
                    Icons.verified_outlined,
                    color: AppTheme.green,
                  ),
                ),
              );
            },
          ),
        );
}

class Status extends StatelessWidget {
  const Status(this.value, {super.key});
  final String value;
  @override
  Widget build(BuildContext c) {
    final ok = value != 'draft';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: (ok ? AppTheme.green : AppTheme.orange).withValues(alpha: .1),
        borderRadius: BorderRadius.circular(99),
      ),
      child: Text(
        value,
        style: TextStyle(
          color: ok ? AppTheme.green : AppTheme.orange,
          fontSize: 10,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class PatientForm extends StatefulWidget {
  const PatientForm({super.key});
  @override
  State<PatientForm> createState() => _PatientFormState();
}

class _PatientFormState extends State<PatientForm> {
  final key = GlobalKey<FormState>(),
      code = TextEditingController(
        text: 'PAT-${DateTime.now().millisecondsSinceEpoch}',
      ),
      first = TextEditingController(),
      last = TextEditingController(),
      phone = TextEditingController(),
      birth = TextEditingController(),
      allergies = TextEditingController();
  String? sex;
  @override
  Widget build(BuildContext c) => FormBody(
    keyForm: key,
    onSave: () {
      if (key.currentState!.validate()) {
        Navigator.pop(c, {
          'code': code.text,
          'first_name': first.text,
          'last_name': last.text,
          if (phone.text.isNotEmpty) 'phone': phone.text,
          if (birth.text.isNotEmpty) 'date_of_birth': birth.text,
          if (sex != null) 'sex': sex,
          if (allergies.text.isNotEmpty) 'allergies': allergies.text,
        });
      }
    },
    children: [
      field(code, 'Code patient'),
      field(last, 'Nom'),
      field(first, 'Prénom'),
      DropdownButtonFormField<String>(
        decoration: const InputDecoration(labelText: 'Sexe'),
        items: const [
          DropdownMenuItem(value: 'female', child: Text('Féminin')),
          DropdownMenuItem(value: 'male', child: Text('Masculin')),
          DropdownMenuItem(value: 'other', child: Text('Autre')),
        ],
        onChanged: (v) => sex = v,
      ),
      field(birth, 'Date de naissance (AAAA-MM-JJ)', required: false),
      field(phone, 'Téléphone', required: false),
      field(allergies, 'Allergies connues', required: false),
    ],
  );
}

class PrescriptionForm extends StatefulWidget {
  const PrescriptionForm({super.key, required this.options});
  final Map<String, List<Map<String, dynamic>>> options;
  @override
  State<PrescriptionForm> createState() => _PrescriptionFormState();
}

class _PrescriptionFormState extends State<PrescriptionForm> {
  final key = GlobalKey<FormState>(),
      ref = TextEditingController(
        text: 'ORD-${DateTime.now().millisecondsSinceEpoch}',
      ),
      doctor = TextEditingController(),
      qty = TextEditingController(),
      dosage = TextEditingController(),
      frequency = TextEditingController(),
      duration = TextEditingController();
  String? patient, site, product;
  @override
  Widget build(BuildContext c) => FormBody(
    keyForm: key,
    onSave: () {
      if (key.currentState!.validate()) {
        Navigator.pop(c, {
          'patient_id': patient,
          'site_id': site,
          'reference': ref.text,
          'prescribed_on': DateTime.now().toIso8601String().substring(0, 10),
          'prescriber_name': doctor.text,
          'items': [
            {
              'product_id': product,
              'quantity_prescribed': double.parse(
                qty.text.replaceAll(',', '.'),
              ),
              if (dosage.text.isNotEmpty) 'dosage': dosage.text,
              'frequency': frequency.text,
              'duration': duration.text,
            },
          ],
        });
      }
    },
    children: [
      drop(
        'Patient',
        widget.options['patients']!,
        patient,
        (v) => patient = v,
        (v) => '${v['last_name']} ${v['first_name']}',
      ),
      drop(
        'Site',
        widget.options['sites']!,
        site,
        (v) => site = v,
        (v) => '${v['name']}',
      ),
      field(ref, 'Référence'),
      field(doctor, 'Prescripteur'),
      drop(
        'Médicament',
        widget.options['products']!,
        product,
        (v) => product = v,
        (v) => '${v['code']} · ${v['name']}',
      ),
      field(qty, 'Quantité prescrite'),
      field(dosage, 'Posologie', required: false),
      field(frequency, 'Fréquence'),
      field(duration, 'Durée du traitement'),
    ],
  );
}

class DispensationForm extends StatefulWidget {
  const DispensationForm({super.key, required this.options});
  final Map<String, List<Map<String, dynamic>>> options;
  @override
  State<DispensationForm> createState() => _DispensationFormState();
}

class _DispensationFormState extends State<DispensationForm> {
  final key = GlobalKey<FormState>(),
      ref = TextEditingController(
        text: 'DIS-${DateTime.now().millisecondsSinceEpoch}',
      ),
      qty = TextEditingController();
  String? prescription, patient, site, item, product;
  @override
  Widget build(BuildContext c) {
    final all = widget.options['prescriptions']!;
    final lines = prescription == null
        ? <Map<String, dynamic>>[]
        : (all.firstWhere((p) => '${p['id']}' == prescription)['items'] as List)
              .cast<Map<String, dynamic>>();
    return FormBody(
      keyForm: key,
      onSave: () {
        if (key.currentState!.validate()) {
          Navigator.pop(c, {
            'reference': ref.text,
            'patient_id': patient,
            'prescription_id': prescription,
            'site_id': site,
            'dispensed_at': DateTime.now().toIso8601String(),
            'allow_partial': true,
            'items': [
              {
                'prescription_item_id': item,
                'product_id': product,
                'quantity': double.parse(qty.text.replaceAll(',', '.')),
              },
            ],
          });
        }
      },
      children: [
        drop(
          'Ordonnance validée',
          all,
          prescription,
          (v) {
            setState(() {
              prescription = v;
              final p = all.firstWhere((x) => '${x['id']}' == v);
              patient = '${p['patient_id']}';
              site = '${p['site_id']}';
              item = null;
            });
          },
          (v) => '${v['reference']} · ${v['patient']?['last_name'] ?? ''}',
        ),
        field(ref, 'Référence'),
        if (prescription != null)
          drop(
            'Produit prescrit',
            lines,
            item,
            (v) {
              item = v;
              product =
                  '${lines.firstWhere((x) => '${x['id']}' == v)['product_id']}';
            },
            (v) =>
                '${v['product']?['name']} · prescrit ${v['quantity_prescribed']}',
          ),
        field(qty, 'Quantité à dispenser'),
      ],
    );
  }
}

class ClinicalValidationForm extends StatefulWidget {
  const ClinicalValidationForm({super.key});
  @override
  State<ClinicalValidationForm> createState() =>
      _ClinicalValidationFormState();
}

class _ClinicalValidationFormState extends State<ClinicalValidationForm> {
  final key = GlobalKey<FormState>();
  final notes = TextEditingController();
  final reason = TextEditingController();
  String decision = 'approve';
  bool protocol = false, dosage = false, contraindications = false;

  @override
  Widget build(BuildContext context) => FormBody(
    keyForm: key,
    onSave: () {
      if (!key.currentState!.validate()) return;
      if (decision == 'approve' &&
          (!protocol || !dosage || !contraindications)) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Confirmez les trois contrôles cliniques.')),
        );
        return;
      }
      Navigator.pop(context, {
        'decision': decision,
        'protocol_confirmed': protocol,
        'dosage_confirmed': dosage,
        'contraindications_checked': contraindications,
        if (notes.text.trim().isNotEmpty)
          'clinical_validation_notes': notes.text.trim(),
        if (decision == 'reject') 'rejection_reason': reason.text.trim(),
      });
    },
    children: [
      DropdownButtonFormField<String>(
        initialValue: decision,
        decoration: const InputDecoration(labelText: 'Décision clinique'),
        items: const [
          DropdownMenuItem(value: 'approve', child: Text('Approuver')),
          DropdownMenuItem(value: 'reject', child: Text('Rejeter')),
        ],
        onChanged: (value) => setState(() => decision = value ?? 'approve'),
      ),
      if (decision == 'approve') ...[
        CheckboxListTile(
          value: protocol,
          title: const Text('Protocole thérapeutique vérifié'),
          onChanged: (value) => setState(() => protocol = value ?? false),
        ),
        CheckboxListTile(
          value: dosage,
          title: const Text('Posologie et durée vérifiées'),
          onChanged: (value) => setState(() => dosage = value ?? false),
        ),
        CheckboxListTile(
          value: contraindications,
          title: const Text('Allergies et contre-indications vérifiées'),
          onChanged: (value) =>
              setState(() => contraindications = value ?? false),
        ),
      ] else
        TextFormField(
          controller: reason,
          decoration: const InputDecoration(labelText: 'Motif du rejet'),
          minLines: 2,
          maxLines: 4,
          validator: (value) => value == null || value.trim().length < 5
              ? 'Indiquez un motif précis.'
              : null,
        ),
      TextFormField(
        controller: notes,
        decoration: const InputDecoration(labelText: 'Note clinique'),
        minLines: 2,
        maxLines: 4,
      ),
    ],
  );
}

class FormBody extends StatelessWidget {
  const FormBody({
    super.key,
    required this.keyForm,
    required this.children,
    required this.onSave,
  });
  final GlobalKey<FormState> keyForm;
  final List<Widget> children;
  final VoidCallback onSave;
  @override
  Widget build(BuildContext c) => SingleChildScrollView(
    padding: const EdgeInsets.all(20),
    child: Form(
      key: keyForm,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final w in children) ...[w, const SizedBox(height: 12)],
          Row(
            children: [
              Expanded(
                child: AppButton.cancel(onPressed: () => Navigator.pop(c)),
              ),
              const SizedBox(width: 10),
              Expanded(child: AppButton.save(onPressed: onSave)),
            ],
          ),
        ],
      ),
    ),
  );
}

Widget field(TextEditingController c, String label, {bool required = true}) =>
    TextFormField(
      controller: c,
      decoration: InputDecoration(labelText: label),
      validator: (v) => required && (v == null || v.trim().isEmpty)
          ? 'Champ obligatoire.'
          : null,
    );
Widget drop(
  String label,
  List<Map<String, dynamic>> values,
  String? value,
  ValueChanged<String?> changed,
  String Function(Map<String, dynamic>) labelOf,
) => DropdownButtonFormField<String>(
  initialValue: value,
  decoration: InputDecoration(labelText: label),
  items: values
      .map(
        (v) => DropdownMenuItem(
          value: '${v['id']}',
          child: Text(labelOf(v), overflow: TextOverflow.ellipsis),
        ),
      )
      .toList(),
  onChanged: changed,
  validator: (v) => v == null ? 'Sélection obligatoire.' : null,
);
