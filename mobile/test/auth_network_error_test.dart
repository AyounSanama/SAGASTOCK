import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/features/auth/data/auth_service.dart';

void main() {
  test('le diagnostic distingue une erreur de connexion', () {
    final dioError = DioException(
      requestOptions: RequestOptions(
        path: '/auth/login',
        baseUrl: 'http://192.168.137.234:8000/api/v1',
      ),
      type: DioExceptionType.connectionError,
      error: const OSError('Connection refused'),
    );

    final exception = ServerUnavailableException.fromDio(dioError);
    final message = AuthService.messageFor(exception);

    expect(exception.host, '192.168.137.234:8000');
    expect(message, contains('Connexion au serveur impossible'));
    expect(message, contains('port 8000'));
  });
}
