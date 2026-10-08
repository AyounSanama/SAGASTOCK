import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/catalog/care_level_paths.dart';

void main() {
  test('deux catégories du même nom restent distinctes (chemin complet)', () {
    final paths = careLevelPaths([
      {'name': 'Soins de santé primaire', 'depth': 1},
      {'name': 'Programmes de prise en charge', 'depth': 2},
      {'name': 'Programme PEC Paludisme', 'depth': 3},
      {'name': 'Soins de santé secondaire', 'depth': '1'},
      {'name': 'Programmes de prise en charge', 'depth': 2},
    ]);

    expect(paths, [
      'Soins de santé primaire',
      'Soins de santé primaire › Programmes de prise en charge',
      'Soins de santé primaire › Programmes de prise en charge › Programme PEC Paludisme',
      'Soins de santé secondaire',
      'Soins de santé secondaire › Programmes de prise en charge',
    ]);
  });
}
