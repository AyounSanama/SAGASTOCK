import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:sagastock_mobile/src/features/projects_wizard/data/project_wizard_service.dart';
import 'package:sagastock_mobile/src/features/projects_wizard/presentation/project_wizard_page.dart';

class _FakeWizard implements ProjectWizardService {
  Map<String, dynamic>? identity;
  Map<String, dynamic>? list;
  Map<String, dynamic>? supply;

  static const _project = {
    'id': 'p1',
    'mission_id': 'm1',
    'type': 'national_program',
    'implementing_partner': 'Ministère de la Santé',
    'code': 'PNLT',
    'name': 'Tuberculose',
    'status': 'draft',
    'donor_id': null,
    'order_period_months': 3,
    'delivery_lead_time_months': 1,
    'safety_stock_months': 0.5,
  };

  @override
  Future<Map<String, dynamic>> options() async => {
    'missions': [
      {
        'id': 'm1',
        'name': 'Coordination Yaoundé',
        'country': 'Cameroun',
        'organization_id': 'o1',
        'organization_name': 'ONG Santé',
      },
    ],
    'donors': [
      {'id': 'd1', 'organization_id': 'o1', 'code': 'BA', 'name': 'Bailleur A'},
    ],
    'national_program_default_donor': 'Ministère de la Santé',
    'safety_stock_options': [0.25, 0.5, 0.75, 1, 1.5, 2],
    'can_add_donor': false,
  };

  @override
  Future<Map<String, dynamic>> project(String id) async => {
    'project': _project,
    'resume_step': 'standard-list',
  };

  @override
  Future<Map<String, dynamic>> saveIdentity(
    String? id,
    Map<String, dynamic> data, {
    bool draft = false,
  }) async {
    identity = data;
    return {'project': _project};
  }

  @override
  Future<Map<String, dynamic>> standardList(
    String id, {
    Map<String, List<String>>? selection,
  }) async => {
    'options': {
      'care_levels': [
        {'id': 'l1', 'name': 'Soins de santé primaire', 'depth': 1},
      ],
      'target_populations': [
        {'id': 'a', 'name': 'Adultes'},
      ],
      'pathologies': [
        {'id': 'palu', 'name': 'Paludisme simple'},
      ],
    },
    'care_level_ids': ['l1'],
    'target_population_ids': ['a'],
    'pathology_ids': ['palu'],
    'products': [
      {'id': 'x', 'code': 'ACT', 'name': 'Artéméther', 'retained': true, 'added': false},
      {'id': 'y', 'code': 'PARA', 'name': 'Paracétamol', 'retained': false, 'added': false},
    ],
    'addable': [],
  };

  @override
  Future<Map<String, dynamic>> saveStandardList(
    String id,
    Map<String, dynamic> data, {
    bool draft = false,
  }) async {
    list = data;
    return {'project': _project};
  }

  @override
  Future<Map<String, dynamic>> saveSupply(
    String id,
    Map<String, dynamic> data, {
    bool draft = false,
  }) async {
    supply = data;
    return {
      'project': {..._project, 'status': 'active'},
    };
  }

  @override
  Future<Map<String, dynamic>> addDonor({
    required String missionId,
    required String name,
    required String code,
  }) async => const {};
}

void main() {
  testWidgets('assistant en 4 étapes : brouillon, Liste Standard, projet actif', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3.0;
    addTearDown(tester.view.reset);

    final fake = _FakeWizard();
    final router = GoRouter(
      initialLocation: '/',
      routes: [
        GoRoute(
          path: '/',
          builder: (_, _) => const Scaffold(body: Text('Accueil')),
        ),
        GoRoute(
          path: '/projects/new',
          builder: (_, _) => ProjectWizardPage(service: fake),
        ),
      ],
    );
    await tester.pumpWidget(MaterialApp.router(routerConfig: router));
    router.push('/projects/new');
    await tester.pumpAndSettle();

    // Étape 1.
    expect(find.text('Étape 1 sur 4'), findsOneWidget);
    expect(find.text('Coordination Yaoundé (votre coordination)'), findsOneWidget);
    await tester.tap(find.text('Programme national'));
    await tester.tap(find.text('Suivant'));
    await tester.pumpAndSettle();

    // Étape 2 : bailleur facultatif pour un programme national.
    expect(find.text('Étape 2 sur 4'), findsOneWidget);
    expect(find.text('Bailleur (facultatif)'), findsOneWidget);
    await tester.tap(find.text('Suivant'));
    await tester.pump();
    expect(find.text('Champ obligatoire.'), findsNWidgets(2));
    await tester.enterText(
      find.widgetWithText(TextField, 'Code bailleur / programme MoH *'),
      'PNLT',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Intitulé du projet *'),
      'Tuberculose',
    );
    await tester.tap(find.text('Suivant'));
    await tester.pumpAndSettle();
    expect(fake.identity?['type'], 'national_program');
    expect(fake.identity?['code'], 'PNLT');

    // Étape 3 : produit décoché conservé.
    expect(find.text('Étape 3 sur 4'), findsOneWidget);
    expect(find.text('Liste Standard : 1 / 2'), findsOneWidget);
    await tester.tap(find.text('Suivant'));
    await tester.pumpAndSettle();
    expect(fake.list?['retained'], ['x']);

    // Étape 4 : valeurs reprises du projet, projet créé.
    expect(find.text('Étape 4 sur 4'), findsOneWidget);
    await tester.tap(find.text('Créer le projet'));
    await tester.pumpAndSettle();
    expect(fake.supply?['safety_stock_months'], 0.5);
    expect(find.text('Accueil'), findsOneWidget);
    expect(find.textContaining('Projet PNLT créé'), findsOneWidget);
  });
}
