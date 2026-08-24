import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/theme/app_theme.dart';
import 'package:sagastock_mobile/src/core/widgets/app_badge.dart';
import 'package:sagastock_mobile/src/core/widgets/app_breadcrumb.dart';
import 'package:sagastock_mobile/src/core/widgets/app_button.dart';
import 'package:sagastock_mobile/src/core/widgets/app_card.dart';
import 'package:sagastock_mobile/src/core/widgets/app_text_field.dart';
import 'package:sagastock_mobile/src/core/widgets/app_page_header.dart';
import 'package:sagastock_mobile/src/core/widgets/app_page_layout.dart';
import 'package:sagastock_mobile/src/core/widgets/app_top_bar.dart';

void main() {
  Widget subject(Widget child) => MaterialApp(
    theme: AppTheme.light,
    home: Scaffold(body: SingleChildScrollView(child: child)),
  );

  testWidgets('les composants fondamentaux utilisent une présentation commune', (tester) async {
    await tester.pumpWidget(subject(Column(children: [
      const AppTextField(label: 'Adresse e-mail', prefixIcon: Icons.mail_outline, required: true),
      const AppCard(title: 'Organisation', description: 'Informations', child: Text('Contenu')),
      const AppBadge(label: 'Actif', variant: AppBadgeVariant.success),
      AppBreadcrumb(items: const [AppBreadcrumbItem('Tableau de bord'), AppBreadcrumbItem('Organisations')]),
      AppIconButton(icon: Icons.edit_outlined, tooltip: 'Modifier', onPressed: () {}),
    ])));

    expect(find.byType(TextFormField), findsOneWidget);
    expect(find.text('Organisation'), findsOneWidget);
    expect(find.text('Actif'), findsOneWidget);
    expect(find.byTooltip('Modifier'), findsOneWidget);
  });

  testWidgets('AppIconAction reste un alias compatible pendant la migration', (tester) async {
    await tester.pumpWidget(subject(AppIconAction(icon: Icons.visibility_outlined, tooltip: 'Voir', onPressed: () {})));
    expect(find.byTooltip('Voir'), findsOneWidget);
  });

  testWidgets('la structure de page reste unique et responsive', (tester) async {
    await tester.pumpWidget(MaterialApp(
      theme: AppTheme.light,
      home: Scaffold(
        appBar: const AppTopBar(),
        body: const AppPageLayout(
          header: AppPageHeader(title: 'Organisations', description: 'Gérez les organisations.'),
          child: Text('Contenu'),
        ),
      ),
    ));
    expect(find.byType(AppTopBar), findsOneWidget);
    expect(find.text('Organisations'), findsOneWidget);
    expect(find.text('Contenu'), findsOneWidget);
  });
}
