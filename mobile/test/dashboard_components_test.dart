import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/widgets/app_dashboard_panel.dart';
import 'package:sagastock_mobile/src/core/widgets/app_kpi_card.dart';
import 'package:sagastock_mobile/src/features/home/presentation/home_page.dart';

void main() {
  test('le Dashboard réel reste raccordé et compilable', () {
    expect(const HomePage(), isA<HomePage>());
  });

  testWidgets('les KPI et panneaux utilisent la présentation globale', (
    tester,
  ) async {
    var opened = false;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Column(
            children: [
              AppKpiCard(
                label: 'Produits',
                value: '854',
                icon: Icons.inventory_2_outlined,
                caption: 'Produits actifs',
                tone: AppKpiTone.green,
                onTap: () => opened = true,
              ),
              const AppDashboardPanel(
                title: 'Activités récentes',
                description: 'Dernières opérations',
                child: Text('Aucune activité'),
              ),
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

  for (final viewport in <({String name, Size size})>[
    (name: 'petit téléphone', size: Size(320, 640)),
    (name: 'téléphone moyen', size: Size(390, 844)),
    (name: 'grand téléphone', size: Size(480, 960)),
    (name: 'tablette Android', size: Size(800, 1280)),
  ]) {
    testWidgets('Dashboard Admin Coordination responsive — ${viewport.name}', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(viewport.size);
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final data = <String, dynamic>{
        'stats': <String, dynamic>{
          'missions': 1,
          'projects': 2,
          'donors': 3,
          'programs': 4,
        },
        'widgets': <dynamic>[],
        'navigation': <dynamic>[],
        'recent_projects': <dynamic>[],
        'activities': <dynamic>[
          <String, dynamic>{
            'event': 'project.created',
            'created_at': '2026-08-28T10:00:00Z',
          },
        ],
        'offline': false,
      };
      final user = <String, dynamic>{
        'name': 'Marie Dupont',
        'role': 'coordination_admin',
        'permissions': <String>[
          'projects.manage',
          'funding.view',
          'standard_lists.view',
        ],
      };

      await tester.pumpWidget(
        MaterialApp(home: HomePage(loadFuture: Future.value([data, user]))),
      );
      await tester.pumpAndSettle();

      expect(find.text('Tableau de bord'), findsOneWidget);
      expect(find.text('Ma coordination'), findsOneWidget);
      expect(find.text('Bailleurs'), findsOneWidget);
      expect(find.text('Programmes'), findsOneWidget);
      if (find.text('Créer un projet').evaluate().isEmpty) {
        await tester.drag(find.byType(CustomScrollView), const Offset(0, -420));
        await tester.pumpAndSettle();
      }
      expect(find.text('Créer un projet'), findsOneWidget);
      await tester.drag(find.byType(CustomScrollView), const Offset(0, -420));
      await tester.pumpAndSettle();
      expect(find.text('Projet créé'), findsOneWidget);
      expect(find.text('project.created'), findsNothing);
      expect(find.text('Derniers mouvements de stock'), findsNothing);
      expect(find.byType(Image), findsNothing);
      expect(tester.takeException(), isNull);
    });
  }
}
