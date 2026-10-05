import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/connectivity/connectivity_service.dart';
import 'package:sagastock_mobile/src/features/coordination/data/coordination_service.dart';
import 'package:sagastock_mobile/src/features/coordination/presentation/coordination_page.dart';

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
    await tester.tap(find.text('Valider'));
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
}
