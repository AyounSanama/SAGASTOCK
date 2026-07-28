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
}
