import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/sync/offline_request_data.dart';

void main() {
  test('prescription attachment stays a file during deferred sync', () async {
    final directory = await Directory.systemTemp.createTemp('pharmacare-rx-');
    addTearDown(() => directory.delete(recursive: true));
    final file = File('${directory.path}${Platform.pathSeparator}rx.jpg');
    await file.writeAsBytes(const [0xFF, 0xD8, 0xFF, 0xD9]);

    final request = await prepareOfflineRequestData({
      '_attachment_path': file.path,
      '_attachment_name': 'ordonnance.jpg',
      'patient_id': 'patient-1',
      'items': [
        {'product_id': 'product-1', 'quantity': 2},
      ],
    });

    expect(request, isA<FormData>());
    final form = request as FormData;
    expect(form.files.single.key, 'attachment');
    expect(form.files.single.value.filename, 'ordonnance.jpg');
    expect(form.fields.any((entry) => entry.key == 'patient_id'), isTrue);
  });

  test('ordinary offline payload remains JSON', () async {
    final request = await prepareOfflineRequestData({'name': 'Patient'});
    expect(request, {'name': 'Patient'});
  });
}
