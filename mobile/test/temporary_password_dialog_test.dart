import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/features/users/presentation/temporary_password_dialog.dart';

void main() {
  testWidgets('mot de passe temporaire montré une fois, à transmettre', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Builder(
            builder: (context) => TextButton(
              onPressed: () => showTemporaryPassword(
                context,
                password: 'Tmp-Pass-2026!',
                userName: 'Paul Owona',
              ),
              child: const Text('Créer'),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Créer'));
    await tester.pumpAndSettle();

    expect(find.text('Tmp-Pass-2026!'), findsOneWidget);
    expect(find.textContaining('Transmettez-le à Paul Owona'), findsOneWidget);
    expect(find.text('Copier'), findsOneWidget);
    await tester.tap(find.text('J’ai noté le mot de passe'));
    await tester.pumpAndSettle();
    expect(find.text('Tmp-Pass-2026!'), findsNothing);
  });
}
