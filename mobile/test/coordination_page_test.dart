import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/connectivity/connectivity_service.dart';
import 'package:sagastock_mobile/src/features/coordination/data/coordination_service.dart';
import 'package:sagastock_mobile/src/features/coordination/presentation/coordination_dashboard_page.dart';
import 'package:sagastock_mobile/src/features/coordination/presentation/coordination_page.dart';
import 'package:sagastock_mobile/src/features/coordination/presentation/coordination_standard_list_page.dart';

class _FakeConnectivity implements ConnectivityService {
  _FakeConnectivity(this.online);
  final bool online;
  @override
  Future<bool> get hasNetwork async => online;
  @override
  Stream<bool> get networkChanges => const Stream.empty();
}

class _FakeService implements CoordinationService {
  _FakeService({this.canAct = true, this.failWith});
  final bool canAct;
  final String? failWith;
  final validated = <String>[];
  String pendingStatus = 'pending';

  @override
  Future<Map<String, dynamic>> overview() async => {
    'mission': {'id': 'm1', 'name': 'Coordination Yaoundé', 'country': 'Cameroun'},
    'can_act': canAct,
    'stats': {'projects': 1, 'pending': pendingStatus == 'pending' ? 1 : 0},
    'projects': [
      {
        'id': 'p1', 'code': 'GFFO5', 'name': 'Appui aux soins de santé primaire',
        'type': 'donor_project', 'type_label': 'Projet bailleur', 'status': 'active',
        'status_label': 'Actif', 'donor_name': 'Bailleur A', 'admin_name': 'M. Atangana',
        'validated_facilities_count': 6, 'pending_facilities_count': 1,
      },
    ],
    'facilities': [
      {
        'id': 'f1', 'code': 'FOSA-015', 'name': 'CSI d’Ekoumdoum',
        'validation_status': pendingStatus, 'category': 'Centre de santé intégré (CSI)',
        'care_level': 'Soins de santé primaire', 'declared_by': 'M. Atangana',
        'created_at': '2026-09-28T10:00:00Z', 'standard_list_count': 186,
        'projects': [{'id': 'p1', 'code': 'GFFO5'}], 'accounts': [],
        'target_populations': ['Adultes'], 'pathologies': ['Paludisme simple'],
      },
    ],
    'accounts': [
      {'id': 'u1', 'name': 'M. Atangana Joseph', 'username': 'j.atangana', 'is_active': true, 'role': 'Admin Projet, GFFO5', 'status': 'Actif'},
    ],
  };

  @override
  Future<Map<String, dynamic>> dashboard({String? donorId}) async => {
    'mission': {'id': 'm1', 'name': 'Coordination Yaoundé', 'country': 'Cameroun'},
    'generated_at': '2026-10-05T10:45:00Z',
    'stats': {'validated': 14, 'total': 17, 'pending': 3, 'sync_failed': 1, 'sync_late': 1},
    'todo': [
      {'tone': 'info', 'action': 'validate', 'title': '3 FOSA attendent votre validation', 'detail': 'Projets GFFO5 et FH4'},
      {'tone': 'danger', 'action': 'facility', 'title': 'CSI d’Ekounou ne s’est pas synchronisée depuis 6 jours', 'detail': 'Projet GFFO5'},
    ],
    'facilities': [
      {'name': 'CSI de Nkolndongo', 'category': 'CSI', 'last_contact_days': 0, 'sync_status': 'ok', 'sync_label': 'À jour'},
      {'name': 'CSI d’Ekounou', 'category': 'CSI', 'last_contact_days': 6, 'sync_status': 'late', 'sync_label': 'À surveiller'},
      {'name': 'CMA de Mvog-Ada', 'category': 'CMA', 'last_contact_days': 1, 'sync_status': 'failed', 'sync_label': 'Échec de synchro'},
    ],
    'analyses_available': false,
  };

  @override
  Future<void> validateFacility(String id) async {
    if (failWith != null) throw CoordinationActionException(failWith!);
    validated.add(id);
    pendingStatus = 'validated';
  }

  @override
  Future<void> refuseFacility(String id, String reason) async {}
  @override
  Future<void> suspendFacility(String id, String reason) async {}
  @override
  Future<void> reactivateFacility(String id) async {}
  @override
  Future<void> setAccountActive(String userId, {required bool active}) async {}
  @override
  Future<void> updateAccount(String userId, Map<String, dynamic> data) async {}

  final savedFacilityLists = <String, List<String>>{};

  @override
  Future<Map<String, dynamic>> standardList({String? projectId, String? facilityId}) async => {
    'projects': [
      {'id': 'p1', 'code': 'GFFO5', 'name': 'Appui aux soins', 'status': 'active'},
    ],
    'project': {'id': 'p1', 'code': 'GFFO5', 'name': 'Appui aux soins', 'status': 'active'},
    'title': 'ONG Santé · Bailleur A GFFO5',
    'facilities': [
      {'id': 'f1', 'code': 'FOSA-001', 'name': 'CSI de Nkolndongo'},
    ],
    'facility': facilityId == null ? null : {'id': 'f1', 'code': 'FOSA-001', 'name': 'CSI de Nkolndongo'},
    'can_manage': canAct,
    'pathologies': ['Paludisme simple'],
    'products': [
      {'id': 'a1', 'code': 'ACT', 'name': 'Artéméther / Luméfantrine', 'packaging': 'Plaquette de 24', 'pathology': 'Paludisme simple', 'retained': true, 'barcode': '6001234567890'},
      {'id': 'd1', 'code': 'DIAZ', 'name': 'Diazépam injectable', 'packaging': 'Ampoule', 'pathology': 'Paludisme simple', 'retained': true, 'barcode': null},
    ],
  };

  @override
  Future<void> saveFacilityList(String projectId, String facilityId, List<String> retainedIds) async {
    savedFacilityLists[facilityId] = retainedIds;
  }

  @override
  Future<void> saveBarcode(String projectId, String productId, String barcode) async {}

  final savedLanguages = <String, Object>{};

  @override
  Future<void> updateLanguages(String missionId, String defaultLanguage, List<String> additional) async {
    savedLanguages
      ..['mission'] = missionId
      ..['default'] = defaultLanguage
      ..['additional'] = additional;
  }
}

Future<void> _pump(WidgetTester tester, CoordinationService service, {bool online = true}) async {
  tester.view.physicalSize = const Size(390 * 3, 844 * 3);
  tester.view.devicePixelRatio = 3;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(MaterialApp(
    home: CoordinationPage(organizationId: 'o1', service: service, connectivity: _FakeConnectivity(online)),
  ));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('onglets et projets de la coordination (maquette 05)', (tester) async {
    await _pump(tester, _FakeService());
    expect(find.text('Coordination Yaoundé'), findsOneWidget);
    expect(find.text('Admin Coordination, Cameroun'), findsOneWidget);
    for (final tab in ['Projets', 'À valider', 'FOSA', 'Comptes']) {
      expect(find.text(tab), findsOneWidget);
    }
    expect(find.text('GFFO5'), findsOneWidget);
    expect(find.text('Admin Projet : M. Atangana'), findsOneWidget);
    expect(find.text('FOSA validées / en attente : 6 / 1'), findsOneWidget);
    expect(find.byTooltip('Créer un projet / programme'), findsOneWidget);
  });

  testWidgets('valider une FOSA en attente (maquette 06)', (tester) async {
    final service = _FakeService();
    await _pump(tester, service);
    await tester.tap(find.text('À valider'));
    await tester.pumpAndSettle();
    expect(find.text('Liste Standard générée : 186 produits'), findsOneWidget);
    // Confirmation demandée : « Annuler » ne valide rien.
    await tester.tap(find.text('Valider'));
    await tester.pumpAndSettle();
    expect(find.text('Valider CSI d’Ekoumdoum ?'), findsOneWidget);
    await tester.tap(find.text('Annuler'));
    await tester.pumpAndSettle();
    expect(service.validated, isEmpty);
    await tester.tap(find.text('Valider'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Valider').last);
    await tester.pumpAndSettle();
    expect(service.validated, ['f1']);
    expect(find.text('Aucune FOSA n’attend votre validation.'), findsOneWidget);
  });

  testWidgets('hors ligne : action refusée avec un message, liste consultable', (tester) async {
    await _pump(tester, _FakeService(failWith: CoordinationService.offlineMessage), online: false);
    expect(find.text('Hors ligne : données de la dernière synchronisation.'), findsOneWidget);
    await tester.tap(find.text('À valider'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Valider'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Valider').last);
    await tester.pump();
    expect(find.text(CoordinationService.offlineMessage), findsWidgets);
  });

  testWidgets('lecture seule : tout visible, aucune action', (tester) async {
    await _pump(tester, _FakeService(canAct: false));
    expect(find.byTooltip('Créer un projet / programme'), findsNothing);
    await tester.tap(find.text('À valider'));
    await tester.pumpAndSettle();
    expect(find.text('CSI d’Ekoumdoum'), findsOneWidget);
    expect(find.text('Valider'), findsNothing);
    expect(find.text('Refuser'), findsNothing);
  });

  testWidgets('niveau 6 : la Coordination décoche un article pour une FOSA', (tester) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    final service = _FakeService();
    await tester.pumpWidget(MaterialApp(home: CoordinationStandardListPage(service: service)));
    await tester.pumpAndSettle();
    expect(find.text('ONG Santé · Bailleur A GFFO5'), findsOneWidget);
    expect(find.text('6001234567890'), findsOneWidget);
    expect(find.text('Associer'), findsOneWidget);
    expect(find.byType(Switch), findsNothing, reason: 'liste du projet : consultation');

    await tester.tap(find.text('Toutes les FOSA du projet'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('CSI de Nkolndongo').last);
    await tester.pumpAndSettle();
    expect(find.byType(Switch), findsNWidgets(2));
    await tester.tap(find.byType(Switch).last);
    await tester.pump();
    await tester.tap(find.text('Enregistrer pour cette FOSA (1 / 2)'));
    await tester.pumpAndSettle();
    expect(service.savedFacilityLists['f1'], ['a1']);
  });

  testWidgets('langues : la Coordination choisit elle-même ses langues', (tester) async {
    final service = _FakeService();
    await _pump(tester, service);
    await tester.tap(find.byTooltip('Langues de la coordination'));
    await tester.pumpAndSettle();
    expect(find.text('Langues de la coordination'), findsOneWidget);
    await tester.tap(find.text('Choisir des langues'));
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextField, 'Rechercher une langue'), 'wolof');
    await tester.pumpAndSettle();
    await tester.tap(find.text('Wollof (wolof)'));
    await tester.pump();
    await tester.tap(find.text('Valider (1)'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Enregistrer'));
    await tester.pumpAndSettle();
    expect(service.savedLanguages, {'mission': 'm1', 'default': 'fr', 'additional': ['wo']});
  });

  testWidgets('langues : bouton absent en lecture seule', (tester) async {
    await _pump(tester, _FakeService(canAct: false));
    expect(find.byTooltip('Langues de la coordination'), findsNothing);
  });

  testWidgets('niveau 6 : lecture seule, aucune modification de la Liste Standard', (tester) async {
    await tester.pumpWidget(MaterialApp(home: CoordinationStandardListPage(service: _FakeService(canAct: false))));
    await tester.pumpAndSettle();
    expect(find.text('Artéméther / Luméfantrine'), findsOneWidget);
    expect(find.text('Associer'), findsNothing);
    expect(find.text('Modifier'), findsNothing);
  });

  testWidgets('tableau de bord : chiffres réels FOSA et synchro, le reste annoncé (maquette 08)', (tester) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(MaterialApp(
      home: CoordinationDashboardPage(service: _FakeService(), connectivity: _FakeConnectivity(true)),
    ));
    await tester.pumpAndSettle();
    expect(find.text('14 / 17'), findsOneWidget);
    expect(find.text('Disponible avec les analyses de base'), findsNWidgets(3));
    expect(find.text('3 FOSA attendent votre validation'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('Échec de synchro'), 200, scrollable: find.byType(Scrollable).first);
    expect(find.text('Échec de synchro'), findsOneWidget);
  });
}
