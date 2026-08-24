import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/theme/app_theme.dart';
import 'package:sagastock_mobile/src/features/organizations/presentation/mission_form_sheet.dart';

void main() {
  const countries = [
    {'id': 'country-cm', 'name': 'Cameroun', 'iso2': 'CM'},
  ];

  Widget subject({
    MissionFormMode mode = MissionFormMode.create,
    Map<String, dynamic>? mission,
    Future<bool> Function(MissionFormData)? onSave,
  }) {
    return MaterialApp(
      theme: AppTheme.light,
      home: Scaffold(
        body: MissionFormSheet(
          organizationId: 'organization-1',
          organizationName: 'PharmaCare ONG',
          countries: countries,
          mode: mode,
          mission: mission,
          onSave: onSave ?? (_) async => true,
        ),
      ),
    );
  }

  testWidgets('le mode création repart avec un formulaire vide', (
    tester,
  ) async {
    await tester.pumpWidget(subject());
    final fields = tester.widgetList<TextFormField>(find.byType(TextFormField));
    expect(fields.first.controller?.text, isEmpty);
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Nom de la mission *'),
      'Mission temporaire',
    );

    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pumpWidget(subject());

    final reopened = tester.widgetList<TextFormField>(
      find.byType(TextFormField),
    );
    expect(reopened.first.controller?.text, isEmpty);
  });

  testWidgets('le mode modification préremplit et enregistre la mission', (
    tester,
  ) async {
    MissionFormData? submitted;
    await tester.pumpWidget(
      subject(
        mode: MissionFormMode.edit,
        mission: const {
          'id': 'mission-1',
          'country_id': 'country-cm',
          'code': 'CAM-01',
          'name': 'Mission Cameroun',
          'manager_name': 'Responsable',
          'is_active': true,
        },
        onSave: (data) async {
          submitted = data;
          return true;
        },
      ),
    );

    expect(find.text('Mission Cameroun'), findsOneWidget);
    expect(find.text('CAM-01'), findsOneWidget);
    await tester.ensureVisible(find.text('Enregistrer'));
    await tester.tap(find.text('Enregistrer'));
    await tester.pumpAndSettle();

    expect(submitted?.name, 'Mission Cameroun');
    expect(submitted?.code, 'CAM-01');
    expect(submitted?.managerName, 'Responsable');
    expect(submitted?.countryId, 'country-cm');
  });

  testWidgets('annuler ne déclenche aucun enregistrement', (tester) async {
    var saveCount = 0;
    await tester.pumpWidget(
      subject(
        onSave: (_) async {
          saveCount++;
          return true;
        },
      ),
    );

    await tester.ensureVisible(find.text('Annuler'));
    await tester.tap(find.text('Annuler'));
    await tester.pumpAndSettle();

    expect(saveCount, 0);
  });
}
