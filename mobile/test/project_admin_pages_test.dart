import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/features/project_admin/data/project_admin_service.dart';
import 'package:sagastock_mobile/src/features/project_admin/presentation/project_admin_home_page.dart';
import 'package:sagastock_mobile/src/features/project_admin/presentation/project_admin_standard_list_page.dart';

class _FakeService implements ProjectAdminService {
  @override
  Future<Map<String, dynamic>> dashboard() async => {
    'project': {'code': 'GFFO5', 'mission': 'Coordination Yaoundé'},
    'stats': {'active_facilities': 12, 'facilities': 14, 'accounts': 31, 'sync_failed': 2, 'standard_list_products': 248},
    'watch': 2,
    'last_sync_at': DateTime.now().subtract(const Duration(minutes: 2)).toIso8601String(),
    'sync': [
      {'name': 'CSI de Nkolndongo', 'category': 'CSI', 'last_contact_at': DateTime.now().toIso8601String(), 'sync_status': 'ok', 'sync_label': 'À jour'},
      {'name': 'CSI d’Ekounou', 'category': 'CSI', 'sync_status': 'failed', 'sync_label': 'Échec de synchro'},
    ],
  };

  @override
  Future<Map<String, dynamic>> standardList({String? facilityId}) async => {
    'title': 'ONG · Bailleur A GFFO5',
    'facilities': [
      {'id': 'f1', 'name': 'CSI de Nkolndongo'},
    ],
    'pathologies': ['Paludisme simple'],
    'products': [
      {'id': 'p1', 'code': 'CODE-003', 'name': 'Artéméther 20/120', 'packaging': 'Plaquette de 24', 'pathology': 'Paludisme simple', 'retained': true},
      {'id': 'p2', 'code': 'CODE-004', 'name': 'Artésunate 60 mg', 'pathology': 'Paludisme grave', 'retained': false},
    ],
  };

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

void main() {
  testWidgets('accueil Admin Projet : indicateurs et synchronisation', (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3.0;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(MaterialApp(home: ProjectAdminHomePage(service: _FakeService())));
    await tester.pumpAndSettle();

    expect(find.text('Projet GFFO5'), findsOneWidget);
    expect(find.text('Synchronisé il y a 2 min'), findsOneWidget);
    expect(find.text('12 / 14'), findsOneWidget);
    expect(find.text('248'), findsOneWidget);
    expect(find.text('2 FOSA à surveiller'), findsOneWidget);
    expect(find.text('Échec de synchro'), findsOneWidget);
  });

  testWidgets('Liste Standard en consultation : retenus et filtre par pathologie', (tester) async {
    await tester.pumpWidget(MaterialApp(home: ProjectAdminStandardListPage(service: _FakeService())));
    await tester.pumpAndSettle();

    expect(find.text('Consultation seule · gérée par la Coordination'), findsOneWidget);
    expect(find.text('Retenu'), findsOneWidget);
    expect(find.text('Non retenu'), findsOneWidget);

    await tester.tap(find.widgetWithText(ChoiceChip, 'Paludisme simple'));
    await tester.pumpAndSettle();
    expect(find.text('Artésunate 60 mg'), findsNothing);
    expect(find.text('Artéméther 20/120'), findsOneWidget);
  });
}
