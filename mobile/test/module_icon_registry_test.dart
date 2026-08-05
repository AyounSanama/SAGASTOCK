import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/theme/module_icon_registry.dart';

void main() {
  test('les modules utilisent le registre Material unique', () {
    expect(ModuleIconRegistry.resolve('dashboard'), Icons.dashboard_outlined);
    expect(ModuleIconRegistry.resolve('organizations'), Icons.business_outlined);
    expect(ModuleIconRegistry.resolve('dispensing'), Icons.local_pharmacy_outlined);
    expect(ModuleIconRegistry.resolve('unknown'), Icons.extension_outlined);
  });
}
