import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_form_sheet.dart';
import '../../organizations/data/organization_service.dart';
import '../data/user_service.dart';

/// Configuration Mission, rubrique Comptes : depuis « Ma Coordination »,
/// l'Admin Coordination crée un Admin Projet (projet choisi dans la liste des
/// projets de la coordination) ou un Admin Coordination en lecture seule.
/// Même formulaire et mêmes règles que le Web ; création en ligne uniquement.
Future<bool> openCoordinationAccountSheet(
  BuildContext context, {
  required String organizationId,
  required String missionId,
  UserService? users,
  OrganizationService? organizations,
}) async {
  final userService = users ?? UserService();
  final organizationService = organizations ?? OrganizationService();
  final List<Map<String, dynamic>> roles;
  final List<Map<String, dynamic>> projects;
  try {
    roles = (await userService.assignableRoles())
        .where(
          (role) => coordinationAccountRoleLabel('${role['code']}') != null,
        )
        .toList();
    projects = await organizationService.projects(
      organizationId: organizationId,
      missionId: missionId,
    );
  } on DioException {
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Connexion requise pour créer un compte.'),
        ),
      );
    }
    return false;
  }
  if (!context.mounted || roles.isEmpty) return false;

  final formKey = GlobalKey<FormState>();
  final firstName = TextEditingController();
  final lastName = TextEditingController();
  final email = TextEditingController();
  final phone = TextEditingController();
  final username = TextEditingController();
  var roleId = roles.firstWhere(
    (role) => role['code'] == 'project_admin',
    orElse: () => roles.first,
  )['id'];
  String? projectId;
  String? error;
  var saving = false;

  String roleCode() =>
      '${roles.firstWhere((role) => role['id'] == roleId)['code']}';

  final created = await showAppFormSheet<bool>(
    context: context,
    title: 'Créer un compte',
    description:
        'L’utilisateur devra changer son mot de passe à la première connexion.',
    builder: (sheetContext) => StatefulBuilder(
      builder: (_, setSheetState) {
        final projectAdmin = roleCode() == 'project_admin';
        Future<void> submit() async {
          if (!formKey.currentState!.validate()) return;
          setSheetState(() {
            saving = true;
            error = null;
          });
          try {
            await userService.create({
              'name': '${firstName.text.trim()} ${lastName.text.trim()}',
              'first_name': firstName.text.trim(),
              'last_name': lastName.text.trim(),
              'username': username.text.trim().toLowerCase(),
              'email': email.text.trim(),
              if (phone.text.trim().isNotEmpty) 'phone': phone.text.trim(),
              'role_id': roleId,
              'scope_type': projectAdmin ? 'project' : 'mission',
              'scope_id': projectAdmin ? projectId : missionId,
              'must_change_password': true,
            });
            if (sheetContext.mounted) Navigator.of(sheetContext).pop(true);
          } on DioException catch (exception) {
            final data = exception.response?.data;
            setSheetState(() {
              saving = false;
              error = data is Map && data['message'] != null
                  ? '${data['message']}'
                  : 'Le compte n’a pas pu être créé. Vérifiez la connexion.';
            });
          }
        }

        return Form(
          key: formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Informations personnelles', style: AppTypography.label),
              const SizedBox(height: AppSpacing.sm),
              _field(firstName, 'Prénom *', required: true),
              _field(lastName, 'Nom *', required: true),
              _field(
                email,
                'Adresse e-mail *',
                required: true,
                keyboard: TextInputType.emailAddress,
              ),
              _field(phone, 'Téléphone', keyboard: TextInputType.phone),
              const SizedBox(height: AppSpacing.sm),
              Text('Identifiant et sécurité', style: AppTypography.label),
              const SizedBox(height: AppSpacing.sm),
              _field(username, 'Identifiant *', required: true),
              const SizedBox(height: AppSpacing.sm),
              Text('Rôle et niveau d’accès', style: AppTypography.label),
              const SizedBox(height: AppSpacing.sm),
              DropdownButtonFormField<Object?>(
                initialValue: roleId,
                decoration: const InputDecoration(labelText: 'Rôle *'),
                items: [
                  for (final role in roles)
                    DropdownMenuItem(
                      value: role['id'],
                      child: Text(
                        coordinationAccountRoleLabel('${role['code']}')!,
                      ),
                    ),
                ],
                onChanged: saving
                    ? null
                    : (value) => setSheetState(() {
                        roleId = value;
                        projectId = null;
                      }),
              ),
              if (!projectAdmin)
                Padding(
                  padding: const EdgeInsets.only(top: AppSpacing.xs),
                  child: Text(
                    'Voit les mêmes informations que la coordination, sans pouvoir créer, modifier ni supprimer.',
                    style: AppTypography.caption.copyWith(
                      color: AppColors.textMuted,
                    ),
                  ),
                ),
              if (projectAdmin) ...[
                const SizedBox(height: AppSpacing.md),
                DropdownButtonFormField<String>(
                  initialValue: projectId,
                  decoration: const InputDecoration(labelText: 'Projet *'),
                  items: [
                    for (final project in projects)
                      DropdownMenuItem(
                        value: '${project['id']}',
                        child: Text('${project['name']} (${project['code']})'),
                      ),
                  ],
                  validator: (value) => value == null
                      ? 'Choisissez un projet de la coordination'
                      : null,
                  onChanged: saving
                      ? null
                      : (value) => setSheetState(() => projectId = value),
                ),
              ],
              if (error != null) ...[
                const SizedBox(height: AppSpacing.md),
                Text(
                  error!,
                  style: AppTypography.secondary.copyWith(
                    color: AppColors.dangerText,
                  ),
                ),
              ],
              const SizedBox(height: AppSpacing.lg),
              FilledButton(
                onPressed: saving ? null : submit,
                child: Text(saving ? 'Création…' : 'Créer le compte'),
              ),
            ],
          ),
        );
      },
    ),
  );
  for (final controller in [firstName, lastName, email, phone, username]) {
    controller.dispose();
  }
  return created ?? false;
}

/// Libellé des rôles proposés à la Coordination (null : rôle non proposé).
String? coordinationAccountRoleLabel(String code) => switch (code) {
  'project_admin' => 'Admin Projet',
  'coordination_admin' => 'Admin Coordination (lecture seule)',
  _ => null,
};

Widget _field(
  TextEditingController controller,
  String label, {
  bool required = false,
  TextInputType? keyboard,
}) => Padding(
  padding: const EdgeInsets.only(bottom: AppSpacing.sm),
  child: TextFormField(
    controller: controller,
    keyboardType: keyboard,
    decoration: InputDecoration(labelText: label),
    validator: required
        ? (value) =>
              value == null || value.trim().isEmpty ? 'Champ obligatoire' : null
        : null,
  ),
);
