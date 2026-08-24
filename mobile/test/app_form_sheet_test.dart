import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/widgets/app_form_sheet.dart';

void main() {
  testWidgets('le Form Sheet commun expose un header et une fermeture accessibles', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Builder(
          builder: (context) => Scaffold(
            body: ElevatedButton(
              onPressed: () => showAppFormSheet<void>(
                context: context,
                title: 'Ajouter un élément',
                description: 'Renseignez les informations nécessaires.',
                builder: (_) => const Padding(
                  padding: EdgeInsets.all(20),
                  child: Text('Contenu du formulaire'),
                ),
              ),
              child: const Text('Ouvrir'),
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Ouvrir'));
    await tester.pumpAndSettle();

    expect(find.text('Ajouter un élément'), findsOneWidget);
    expect(find.text('Renseignez les informations nécessaires.'), findsOneWidget);
    expect(find.byTooltip('Fermer'), findsOneWidget);

    await tester.tap(find.byTooltip('Fermer'));
    await tester.pumpAndSettle();
    expect(find.text('Ajouter un élément'), findsNothing);
  });
}
