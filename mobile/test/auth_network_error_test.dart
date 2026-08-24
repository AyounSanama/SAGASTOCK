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

  test('le login affiche le motif fonctionnel retourné par Laravel', () {
    final error = DioException(
      requestOptions: RequestOptions(path: '/auth/login'),
      response: Response<Map<String, dynamic>>(
        requestOptions: RequestOptions(path: '/auth/login'),
        statusCode: 422,
        data: {
          'message': 'The given data was invalid.',
          'errors': {
            'login': ['Compte temporairement verrouillé. Réessayez plus tard.'],
          },
        },
      ),
      type: DioExceptionType.badResponse,
    );

    expect(
      AuthService.messageFor(error),
      'Compte temporairement verrouillé. Réessayez plus tard.',
    );
  });

  test('le login distingue une organisation désactivée', () {
    final error = DioException(
      requestOptions: RequestOptions(path: '/auth/login'),
      response: Response<Map<String, dynamic>>(
        requestOptions: RequestOptions(path: '/auth/login'),
        statusCode: 422,
        data: {
          'errors': {
            'login': ['L’organisation rattachée à ce compte est désactivée.'],
          },
        },
      ),
      type: DioExceptionType.badResponse,
    );

    expect(
      AuthService.messageFor(error),
      'L’organisation rattachée à ce compte est désactivée.',
    );
  });
}
