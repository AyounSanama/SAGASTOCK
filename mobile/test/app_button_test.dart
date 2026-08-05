import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/theme/app_theme.dart';
import 'package:sagastock_mobile/src/core/widgets/app_button.dart';

void main() {
  Widget subject(Widget child) => MaterialApp(
    theme: AppTheme.light,
    home: Scaffold(body: Center(child: child)),
  );

  testWidgets('le bouton principal expose icône, libellé et action', (
    tester,
  ) async {
    var pressed = false;
    await tester.pumpWidget(
      subject(
        AppButton.primary(
          label: 'Valider',
          icon: Icons.check_rounded,
          onPressed: () => pressed = true,
        ),
      ),
    );

    expect(find.text('Valider'), findsOneWidget);
    expect(find.byIcon(Icons.check_rounded), findsOneWidget);
    await tester.tap(find.text('Valider'));
    expect(pressed, isTrue);
  });

  testWidgets('le chargement bloque l’action et affiche une progression', (
    tester,
  ) async {
    var pressed = false;
    await tester.pumpWidget(
      subject(
        AppButton.primary(
          label: 'Enregistrer',
          loading: true,
          onPressed: () => pressed = true,
        ),
      ),
    );

    expect(find.byType(CircularProgressIndicator), findsOneWidget);
    await tester.tap(find.byType(FilledButton));
    expect(pressed, isFalse);
  });

  testWidgets('les variantes secondaire et danger sont disponibles', (
    tester,
  ) async {
    await tester.pumpWidget(
      subject(
        Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AppButton.secondary(label: 'Modifier', onPressed: () {}),
            AppButton.danger(label: 'Supprimer', onPressed: () {}),
          ],
        ),
      ),
    );

    expect(find.byType(OutlinedButton), findsOneWidget);
    expect(find.text('Supprimer'), findsOneWidget);
  });
}
