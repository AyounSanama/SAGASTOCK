import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/features/structures/presentation/facilities_page.dart';

void main() {
  test('a completed facility draft can be submitted from the summary step', () {
    expect(
      facilityFormFirstInvalidStep(
        code: 'FOSA_001',
        name: 'Centre de santé de Nkoldongo',
        careLevel: 'primary',
        facilityType: 'health_center',
      ),
      isNull,
    );
  });

  test('facility validation points to the actual incomplete step', () {
    expect(
      facilityFormFirstInvalidStep(
        code: '',
        name: 'Centre de santé',
        careLevel: 'primary',
        facilityType: 'health_center',
      ),
      0,
    );
    expect(
      facilityFormFirstInvalidStep(
        code: 'FOSA_001',
        name: 'Centre de santé',
        careLevel: null,
        facilityType: 'health_center',
      ),
      1,
    );
  });
}
