import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/widgets/app_form_sheet.dart';

void main() {
  for (final size in [
    const Size(360, 640),
    const Size(800, 1024),
    const Size(1366, 768),
    const Size(1920, 1080),
  ]) {
    testWidgets('centered sheet and fixed footer at $size', (tester) async {
      tester.view.physicalSize = size;
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                onPressed: () => showAppFormSheet<void>(
                  context: context,
                  title: 'Créer une FOSA',
                  footerBuilder: (_) => const Text('Enregistrer'),
                  builder: (_) => SingleChildScrollView(
                    child: Column(
                      children: List.generate(
                        40,
                        (i) => SizedBox(height: 48, child: Text('Champ $i')),
                      ),
                    ),
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
      final panel = tester.getRect(
        find
            .descendant(
              of: find.byType(Dialog),
              matching: find.byType(Material),
            )
            .first,
      );
      expect(panel.center.dx, closeTo(size.width / 2, 1));
      expect(panel.center.dy, closeTo(size.height / 2, 1));
      expect(panel.width, lessThanOrEqualTo(720));
      final footer = tester.getRect(find.text('Enregistrer'));
      await tester.drag(
        find.byType(SingleChildScrollView),
        const Offset(0, -400),
      );
      await tester.pumpAndSettle();
      expect(tester.getRect(find.text('Enregistrer')), footer);
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets('dirty draft survives cancellation and confirms discard', (
    tester,
  ) async {
    final controller = TextEditingController();
    addTearDown(controller.dispose);
    await tester.pumpWidget(
      MaterialApp(
        home: Builder(
          builder: (context) => Scaffold(
            body: TextButton(
              onPressed: () => showAppFormSheet<void>(
                context: context,
                title: 'Brouillon',
                builder: (_) => AppFormSheetGuard(
                  isDirty: () => controller.text.isNotEmpty,
                  child: TextField(controller: controller),
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
    await tester.enterText(find.byType(TextField), 'Centre de santé');
    await tester.tap(find.byTooltip('Fermer'));
    await tester.pumpAndSettle();
    expect(find.text('Abandonner les modifications ?'), findsOneWidget);
    await tester.tap(find.text('Continuer la saisie'));
    await tester.pumpAndSettle();
    expect(controller.text, 'Centre de santé');
    await tester.tap(find.byTooltip('Fermer'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Abandonner'));
    await tester.pumpAndSettle();
    expect(find.text('Brouillon'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'le Form Sheet commun expose un header et une fermeture accessibles',
    (tester) async {
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
      expect(
        find.text('Renseignez les informations nécessaires.'),
        findsOneWidget,
      );
      expect(find.byTooltip('Fermer'), findsOneWidget);

      await tester.tap(find.byTooltip('Fermer'));
      await tester.pumpAndSettle();
      expect(find.text('Ajouter un élément'), findsNothing);
    },
  );
}
