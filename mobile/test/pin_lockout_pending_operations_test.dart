import 'dart:convert';

import 'package:drift/native.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/connectivity/connectivity_service.dart';
import 'package:sagastock_mobile/src/core/database/app_database.dart';
import 'package:sagastock_mobile/src/core/security/app_lock.dart';
import 'package:sagastock_mobile/src/core/sync/sync_service.dart';
import 'package:sagastock_mobile/src/features/auth/data/auth_service.dart';

class _OnlineConnectivity extends ConnectivityService {
  @override
  Future<bool> get hasNetwork async => true;

  @override
  Stream<bool> get networkChanges => const Stream.empty();
}

/// S-02 / S-03 : PIN oublié ou 5 codes faux → la session est fermée, mais les
/// opérations en attente restent dans la base (chiffrée au lot d), la clé de
/// la base et l'identifiant de l'installation sont conservés, et tout part au
/// serveur après une nouvelle connexion en ligne du même utilisateur.
void main() {
  late AppDatabase database;

  setUp(() {
    database = AppDatabase.forTesting(NativeDatabase.memory());
    FlutterSecureStorage.setMockInitialValues({
      'auth_token': 'jeton-a',
      'cached_user': jsonEncode({'id': 'user-a'}),
      'device_id': 'installation-1',
      AuthService.localDatabaseKeyName: 'cle-de-la-base',
      'app_lock_pin_hash': 'x',
      'app_lock_pin_salt': 'y',
    });
  });
  tearDown(() => database.close());

  Future<void> enqueue(String id) => database.enqueueOperation(
    OfflineOperationsCompanion.insert(
      operationId: id,
      idempotencyKey: 'idem-$id',
      ownerUserId: 'user-a',
      entityType: 'dispensations',
      method: 'POST',
      endpoint: '/dispensations',
      payloadJson: jsonEncode({'reference': id}),
      createdAt: DateTime.now().toUtc(),
      updatedAt: DateTime.now().toUtc(),
    ),
  );

  for (final scenario in ['5 codes faux', 'code oublié']) {
    test(
      '$scenario : opérations conservées, clé conservée, envoi après reconnexion',
      () async {
        await enqueue('dispensation-1');
        await enqueue('dispensation-2');

        if (scenario == '5 codes faux') {
          final lock = AppLock();
          await lock.setPin('4826');
          for (var i = 0; i < AppLock.maxAttempts; i++) {
            await lock.unlock('0000');
          }
          expect(lock.lockedOut, isTrue);
        }
        // Ce que fait l'écran PIN dans les deux cas.
        await AuthService().clearAuthenticatedSession();

        const storage = FlutterSecureStorage();
        final remaining = await storage.readAll();
        expect(remaining.containsKey('auth_token'), isFalse);
        expect(remaining.containsKey('app_lock_pin_hash'), isFalse);
        expect(remaining['device_id'], 'installation-1');
        expect(remaining[AuthService.localDatabaseKeyName], 'cle-de-la-base');
        expect(
          (await database.pendingOperations(ownerUserId: 'user-a')).length,
          2,
        );

        // Nouvelle connexion en ligne du même utilisateur : tout est envoyé.
        final sent = <String>[];
        final report = await SyncService(
          database: database,
          ownerUserId: 'user-a',
          connectivity: _OnlineConnectivity(),
          sender: (operation) async {
            sent.add(operation.operationId);
            return null;
          },
        ).syncNow();
        expect(report.succeeded, 2);
        expect(sent, ['dispensation-1', 'dispensation-2']);
        expect(
          await database.pendingOperations(ownerUserId: 'user-a'),
          isEmpty,
        );
      },
    );
  }
}
