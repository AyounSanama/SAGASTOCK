import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/features/auth/presentation/login_page.dart';

void main() {
  testWidgets('la page de connexion affiche les champs essentiels', (
    tester,
  ) async {
    await tester.pumpWidget(const MaterialApp(home: LoginPage()));
    expect(find.text('PharmaCare'), findsOneWidget);
    expect(find.text('Adresse e-mail ou identifiant'), findsOneWidget);
    expect(find.text('Mot de passe'), findsOneWidget);
    expect(find.text('Se connecter'), findsOneWidget);
    expect(find.byTooltip('Afficher le mot de passe'), findsOneWidget);

    await tester.tap(find.byTooltip('Afficher le mot de passe'));
    await tester.pump();
    expect(find.byTooltip('Masquer le mot de passe'), findsOneWidget);
  });

  for (final viewport in <({String name, Size size})>[
    (name: 'téléphone Android', size: Size(360, 800)),
    (name: 'tablette', size: Size(800, 1280)),
  ]) {
    testWidgets(
      'le Login ${viewport.name} masque le message offline sans espace résiduel',
      (tester) async {
        await tester.binding.setSurfaceSize(viewport.size);
        addTearDown(() => tester.binding.setSurfaceSize(null));

        await tester.pumpWidget(const MaterialApp(home: LoginPage()));
        await tester.pumpAndSettle();

        expect(
          find.text(
            'Mode hors connexion disponible après une première connexion réussie',
          ),
          findsNothing,
        );
        expect(find.text('Adresse e-mail ou identifiant'), findsOneWidget);
        expect(find.text('Mot de passe'), findsOneWidget);
        expect(find.text('Mot de passe oublié ?'), findsOneWidget);
        expect(find.text('Se connecter'), findsOneWidget);
        expect(tester.takeException(), isNull);
      },
    );
  }
}
