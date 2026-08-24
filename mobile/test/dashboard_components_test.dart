import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/widgets/app_dashboard_panel.dart';
import 'package:sagastock_mobile/src/core/widgets/app_kpi_card.dart';
import 'package:sagastock_mobile/src/features/home/presentation/home_page.dart';

void main() {
  test('le Dashboard réel reste raccordé et compilable', () {
    expect(const HomePage(), isA<HomePage>());
  });

  testWidgets('les KPI et panneaux utilisent la présentation globale', (tester) async {
    var opened = false;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Column(
            children: [
              AppKpiCard(label: 'Produits', value: '854', icon: Icons.inventory_2_outlined, caption: 'Produits actifs', tone: AppKpiTone.green, onTap: () => opened = true),
              const AppDashboardPanel(title: 'Activités récentes', description: 'Dernières opérations', child: Text('Aucune activité')),
            ],
          ),
        ),
      ),
    );

    expect(find.text('854'), findsOneWidget);
    expect(find.text('Activités récentes'), findsOneWidget);
    await tester.tap(find.text('854'));
    expect(opened, isTrue);
  });
}
