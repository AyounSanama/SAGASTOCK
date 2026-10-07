// Captures des écrans mobiles « Ma Coordination » avec la vraie police Inter
// (taille Galaxy A15). Hors suite : flutter test test_captures --update-goldens
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/connectivity/connectivity_service.dart';
import 'package:sagastock_mobile/src/core/theme/app_theme.dart';
import 'package:sagastock_mobile/src/core/theme/app_tokens.dart';
import 'package:sagastock_mobile/src/features/coordination/data/coordination_service.dart';
import 'package:sagastock_mobile/src/features/coordination/presentation/coordination_dashboard_page.dart';
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
  _FakeService({this.canAct = true});
  final bool canAct;
  final validated = <String>[];
  String pendingStatus = 'pending';

  @override
  Future<Map<String, dynamic>> overview() async => {
    'mission': {
      'id': 'm1',
      'name': 'Coordination Yaoundé',
      'country': 'Cameroun',
    },
    'can_act': canAct,
    'stats': {'projects': 1, 'pending': pendingStatus == 'pending' ? 1 : 0},
    'projects': [
      {
        'id': 'p1',
        'code': 'GFFO5',
        'name': 'Appui aux soins de santé primaire',
        'type': 'donor_project',
        'type_label': 'Projet bailleur',
        'status': 'active',
        'status_label': 'Actif',
        'donor_name': 'Bailleur A',
        'admin_name': 'M. Atangana',
        'validated_facilities_count': 6,
        'pending_facilities_count': 1,
      },
    ],
    'facilities': [
      {
        'id': 'f1',
        'code': 'FOSA-015',
        'name': 'CSI d’Ekoumdoum',
        'validation_status': pendingStatus,
        'category': 'Centre de santé intégré (CSI)',
        'care_level': 'Soins de santé primaire',
        'declared_by': 'M. Atangana',
        'created_at': '2026-09-28T10:00:00Z',
        'standard_list_count': 186,
        'projects': [
          {'id': 'p1', 'code': 'GFFO5'},
        ],
        'accounts': [],
        'target_populations': ['Adultes'],
        'pathologies': ['Paludisme simple'],
      },
    ],
    'accounts': [
      {
        'id': 'u1',
        'name': 'M. Atangana Joseph',
        'username': 'j.atangana',
        'is_active': true,
        'role': 'Admin Projet, GFFO5',
        'status': 'Actif',
      },
    ],
  };

  @override
  Future<Map<String, dynamic>> dashboard({String? donorId}) async => {
    'mission': {
      'id': 'm1',
      'name': 'Coordination Yaoundé',
      'country': 'Cameroun',
    },
    'generated_at': '2026-10-05T10:45:00Z',
    'stats': {
      'validated': 14,
      'total': 17,
      'pending': 3,
      'sync_failed': 1,
      'sync_late': 1,
    },
    'todo': [
      {
        'tone': 'info',
        'action': 'validate',
        'title': '3 FOSA attendent votre validation',
        'detail': 'Projets GFFO5 et FH4',
      },
      {
        'tone': 'danger',
        'action': 'facility',
        'title': 'CSI d’Ekounou ne s’est pas synchronisée depuis 6 jours',
        'detail': 'Projet GFFO5',
      },
    ],
    'facilities': [
      {
        'name': 'CSI de Nkolndongo',
        'category': 'CSI',
        'last_contact_days': 0,
        'sync_status': 'ok',
        'sync_label': 'À jour',
      },
      {
        'name': 'CSI d’Ekounou',
        'category': 'CSI',
        'last_contact_days': 6,
        'sync_status': 'late',
        'sync_label': 'À surveiller',
      },
      {
        'name': 'CMA de Mvog-Ada',
        'category': 'CMA',
        'last_contact_days': 1,
        'sync_status': 'failed',
        'sync_label': 'Échec de synchro',
      },
    ],
    'analyses_available': false,
  };

  @override
  Future<void> validateFacility(String id) async {
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
}

Future<void> _fonts() async {
  ByteData bytes(String path) =>
      ByteData.view(Uint8List.fromList(File(path).readAsBytesSync()).buffer);
  final inter = FontLoader('Inter');
  for (final weight in ['Regular', 'Medium', 'SemiBold', 'Bold']) {
    inter.addFont(Future.value(bytes('assets/fonts/Inter-$weight.ttf')));
  }
  await inter.load();
  final flutterRoot = Platform.environment['FLUTTER_ROOT'] ?? 'C:/flutter';
  final icons = FontLoader('MaterialIcons')
    ..addFont(
      Future.value(
        bytes(
          '$flutterRoot/bin/cache/artifacts/material_fonts/MaterialIcons-Regular.otf',
        ),
      ),
    );
  await icons.load();
}

void main() {
  // Niveau 4 : chaque capture en mode clair et en mode sombre.
  for (final dark in [false, true]) {
    for (final (name, tab, canAct) in [
      ('01-projets', 0, true),
      ('02-a-valider', 1, true),
      ('03-fosa', 2, true),
      ('04-comptes', 3, true),
      ('05-lecture-seule-a-valider', 1, false),
    ]) {
      testWidgets('capture $name${dark ? '-sombre' : ''}', (tester) async {
        await _fonts();
        AppColors.useDark(dark);
        addTearDown(() => AppColors.useDark(false));
        tester.view.physicalSize = const Size(1080, 2340);
        tester.view.devicePixelRatio = 2.77;
        addTearDown(tester.view.reset);
        await tester.pumpWidget(
          MaterialApp(
            debugShowCheckedModeBanner: false,
            theme: AppTheme.light,
            home: RepaintBoundary(
              child: CoordinationPage(
                organizationId: 'o1',
                service: _FakeService(canAct: canAct),
                connectivity: _FakeConnectivity(true),
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();
        if (tab > 0) {
          await tester.tap(
            find.text(['Projets', 'À valider', 'FOSA', 'Comptes'][tab]),
          );
          await tester.pumpAndSettle();
        }
        await expectLater(
          find.byType(RepaintBoundary).first,
          matchesGoldenFile('captures/$name${dark ? '-sombre' : ''}.png'),
        );
      });
    }
  }

  testWidgets('capture 06-tableau-de-bord', (tester) async {
    await _fonts();
    tester.view.physicalSize = const Size(1080, 2340);
    tester.view.devicePixelRatio = 2.77;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light,
        home: RepaintBoundary(
          child: CoordinationDashboardPage(
            service: _FakeService(),
            connectivity: _FakeConnectivity(true),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await expectLater(
      find.byType(RepaintBoundary).first,
      matchesGoldenFile('captures/06-tableau-de-bord.png'),
    );
  });
}
