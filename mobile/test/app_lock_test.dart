import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/security/app_lock.dart';

void main() {
  late DateTime now;
  late AppLock lock;

  setUp(() {
    FlutterSecureStorage.setMockInitialValues({});
    now = DateTime(2026, 10, 2, 9);
    lock = AppLock(clock: () => now);
  });

  test('S-03 : une session ouverte sans PIN exige sa création', () async {
    await lock.evaluate(hasSession: true);
    expect(lock.state.value, AppLockState.setupRequired);

    expect(() => lock.setPin('12'), throwsArgumentError);
    await lock.setPin('4826');
    expect(lock.state.value, AppLockState.unlocked);
    expect(await lock.hasPin(), isTrue);

    // Le PIN n'est jamais stocké en clair.
    final stored = await const FlutterSecureStorage().readAll();
    expect(stored.values, isNot(contains('4826')));
  });

  test('S-03 : verrouillage après 5 minutes d’inactivité, pas avant', () async {
    await lock.setPin('4826');
    now = now.add(const Duration(minutes: 4, seconds: 59));
    await lock.evaluate(hasSession: true);
    expect(lock.state.value, AppLockState.unlocked);

    lock.touch();
    now = now.add(const Duration(minutes: 5));
    await lock.evaluate(hasSession: true);
    expect(lock.state.value, AppLockState.locked);

    expect(await lock.unlock('0000'), isFalse);
    expect(lock.state.value, AppLockState.locked);
    expect(await lock.unlock('4826'), isTrue);
    expect(lock.state.value, AppLockState.unlocked);
  });

  test('S-03 : démarrage à froid avec un PIN existant → verrouillé', () async {
    await lock.setPin('4826');
    final restarted = AppLock(clock: () => now);
    await restarted.evaluate(hasSession: true);
    expect(restarted.state.value, AppLockState.locked);
  });

  test('S-03 : 5 codes faux → session à fermer', () async {
    await lock.setPin('4826');
    now = now.add(const Duration(minutes: 6));
    await lock.evaluate(hasSession: true);
    for (var i = 0; i < 4; i++) {
      expect(await lock.unlock('1111'), isFalse);
      expect(lock.lockedOut, isFalse);
    }
    expect(await lock.unlock('1111'), isFalse);
    expect(lock.lockedOut, isTrue);
    expect(lock.remainingAttempts, 0);
  });

  test('sans session, l’application n’est jamais verrouillée', () async {
    await lock.setPin('4826');
    now = now.add(const Duration(hours: 1));
    await lock.evaluate(hasSession: false);
    expect(lock.state.value, AppLockState.unlocked);
  });
}
