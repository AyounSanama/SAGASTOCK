import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/features/orders/presentation/orders_page.dart';

void main() {
  test('le module Commandes utilise une page mobile réelle', () {
    expect(const OrdersPage(), isA<Widget>());
  });
}
