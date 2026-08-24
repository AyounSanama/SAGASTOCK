import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/data/local_first_repository.dart';
import 'package:sagastock_mobile/src/core/database/app_database.dart';
import 'package:sagastock_mobile/src/core/network/api_client.dart';

void main() {
  late AppDatabase database;

  setUp(() => database = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => database.close());

  test(
    'enregistre la réponse API dans Drift puis la relit localement',
    () async {
      final dio = Dio(BaseOptions(baseUrl: 'https://example.test'))
        ..interceptors.add(
          InterceptorsWrapper(
            onRequest: (options, handler) => handler.resolve(
              Response<Map<String, dynamic>>(
                requestOptions: options,
                data: {
                  'data': [
                    {'id': 'p1', 'name': 'Paracétamol'},
                  ],
                },
                statusCode: 200,
              ),
            ),
          ),
        );
      final repository = LocalFirstRepository(
        database: database,
        client: ApiClient(dio: dio),
        readSecureValue: (key) async => {
          'auth_token': 'token',
          'cached_user': jsonEncode({'id': 'user-a'}),
        }[key],
      );

      final online = await repository.list(
        collection: 'catalog.products',
        endpoint: '/products',
        organizationId: 'org-a',
      );
      final local = await repository.localList(
        collection: 'catalog.products',
        organizationId: 'org-a',
      );

      expect(online.single['name'], 'Paracétamol');
      expect(local.single['id'], 'p1');

      final offlineDio = Dio(BaseOptions(baseUrl: 'https://example.test'))
        ..interceptors.add(
          InterceptorsWrapper(
            onRequest: (options, handler) => handler.reject(
              DioException(
                requestOptions: options,
                type: DioExceptionType.connectionError,
                error: 'offline',
              ),
            ),
          ),
        );
      final offlineRepository = LocalFirstRepository(
        database: database,
        client: ApiClient(dio: offlineDio),
        readSecureValue: (key) async => {
          'auth_token': 'token',
          'cached_user': jsonEncode({'id': 'user-a'}),
        }[key],
      );
      final fallback = await offlineRepository.list(
        collection: 'catalog.products',
        endpoint: '/products',
        organizationId: 'org-a',
      );
      expect(fallback.single['id'], 'p1');
    },
  );

  test('conserve une création offline dans Drift et dans l outbox', () async {
    final dio = Dio(BaseOptions(baseUrl: 'https://example.test'))
      ..interceptors.add(
        InterceptorsWrapper(
          onRequest: (options, handler) => handler.reject(
            DioException(
              requestOptions: options,
              type: DioExceptionType.connectionError,
              error: 'offline',
            ),
          ),
        ),
      );
    final repository = LocalFirstRepository(
      database: database,
      client: ApiClient(dio: dio),
      readSecureValue: (key) async => {
        'auth_token': 'token',
        'cached_user': jsonEncode({'id': 'user-a'}),
      }[key],
    );

    final synchronized = await repository.mutate(
      collection: 'catalog.products',
      organizationId: 'org-a',
      endpoint: '/organizations/org-a/catalog/products',
      method: 'POST',
      payload: const {'code': 'MED-001', 'name': 'Paracetamol'},
      collectionQuery: const {'status': 'active'},
    );
    final local = await repository.localList(
      collection: 'catalog.products',
      organizationId: 'org-a',
      query: const {'status': 'active'},
    );
    final pending = await database.pendingOperations(ownerUserId: 'user-a');

    expect(synchronized, isFalse);
    expect(local.single['name'], 'Paracetamol');
    expect(local.single['_sync_status'], 'pending');
    expect(pending, hasLength(1));
    expect(pending.single.method, 'POST');
  });

  test(
    'une actualisation serveur conserve une mission locale en attente',
    () async {
      final offlineDio = Dio(BaseOptions(baseUrl: 'https://example.test'))
        ..interceptors.add(
          InterceptorsWrapper(
            onRequest: (options, handler) => handler.reject(
              DioException(
                requestOptions: options,
                type: DioExceptionType.connectionError,
              ),
            ),
          ),
        );
      Future<String?> secure(String key) async => {
        'auth_token': 'token',
        'cached_user': jsonEncode({'id': 'coordination-a'}),
      }[key];
      final offline = LocalFirstRepository(
        database: database,
        client: ApiClient(dio: offlineDio),
        readSecureValue: secure,
      );
      await offline.mutate(
        collection: 'missions',
        organizationId: 'org-a',
        endpoint: '/organizations/org-a/missions',
        method: 'POST',
        payload: const {
          'country_id': 'country-a',
          'code': 'OFFLINE',
          'name': 'Mission hors connexion',
        },
      );

      final onlineDio = Dio(BaseOptions(baseUrl: 'https://example.test'))
        ..interceptors.add(
          InterceptorsWrapper(
            onRequest: (options, handler) => handler.resolve(
              Response<Map<String, dynamic>>(
                requestOptions: options,
                statusCode: 200,
                data: {
                  'data': [
                    {
                      'id': 'mission-server',
                      'code': 'SERVER',
                      'name': 'Mission serveur',
                    },
                  ],
                },
              ),
            ),
          ),
        );
      final online = LocalFirstRepository(
        database: database,
        client: ApiClient(dio: onlineDio),
        readSecureValue: secure,
      );
      final values = await online.list(
        collection: 'missions',
        organizationId: 'org-a',
        endpoint: '/organizations/org-a/missions',
      );

      expect(
        values.map((item) => item['code']),
        containsAll(['OFFLINE', 'SERVER']),
      );
      expect(
        values.firstWhere((item) => item['code'] == 'OFFLINE')['_sync_status'],
        'pending',
      );
    },
  );
}
