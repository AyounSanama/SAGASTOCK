import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/data/local_first_repository.dart';
import '../../../core/database/app_database.dart';
import '../../../core/network/api_client.dart';

/// Action de la Coordination refusée ou impossible (message affichable).
class CoordinationActionException implements Exception {
  const CoordinationActionException(this.message);
  final String message;

  @override
  String toString() => message;
}

/// AM-172 — « Ma Coordination » (mobile). La lecture reste disponible hors
/// ligne (dernière synchronisation) ; valider, refuser, suspendre exigent le
/// réseau, car la décision doit être appliquée tout de suite par le serveur.
class CoordinationService {
  CoordinationService({
    ApiClient? client,
    FlutterSecureStorage? storage,
    AppDatabase? database,
  }) : _client = client ?? ApiClient(),
       _storage = storage ?? const FlutterSecureStorage() {
    _repository = LocalFirstRepository(
      client: _client,
      storage: _storage,
      database: database,
    );
  }

  final ApiClient _client;
  final FlutterSecureStorage _storage;
  late final LocalFirstRepository _repository;

  static const offlineMessage =
      'La validation nécessite une connexion internet. Hors ligne, la liste reste consultable.';

  Future<Map<String, dynamic>> overview() => _repository.document(
    collection: 'coordination_overview',
    endpoint: '/coordination/overview',
  );

  /// Tableau de bord (maquette 08), gardé sur le téléphone pour le hors ligne.
  /// Niveau 3 : filtre « couple ONG/Bailleur » (maquette 08) ; chaque filtre
  /// est gardé séparément sur le téléphone pour le hors ligne.
  Future<Map<String, dynamic>> dashboard({String? donorId}) => _repository.document(
    collection: 'coordination_dashboard',
    endpoint: '/coordination/dashboard',
    query: {'donor_id': ?donorId},
  );

  Future<void> validateFacility(String id) =>
      _post('/coordination/facilities/$id/validate');

  Future<void> refuseFacility(String id, String reason) =>
      _post('/coordination/facilities/$id/refuse', {'reason': reason});

  Future<void> suspendFacility(String id, String reason) =>
      _post('/coordination/facilities/$id/suspend', {'reason': reason});

  Future<void> reactivateFacility(String id) =>
      _post('/coordination/facilities/$id/reactivate');

  Future<void> setAccountActive(String userId, {required bool active}) => _post(
    '/coordination/accounts/$userId/${active ? 'reactivate' : 'suspend'}',
  );

  /// Niveau 3 — « Modifier » un compte de la coordination (identité, contact,
  /// projet d'un Admin Projet). Exige le réseau, comme les autres décisions.
  Future<void> updateAccount(String userId, Map<String, dynamic> data) =>
      _post('/coordination/accounts/$userId', data, 'PUT');

  /// Niveau 6 — Liste Standard d'un projet, ou d'une FOSA ([facilityId]).
  /// Gardée sur le téléphone pour la consultation hors ligne.
  Future<Map<String, dynamic>> standardList({String? projectId, String? facilityId}) =>
      _repository.document(
        collection: 'coordination_standard_list',
        endpoint: '/coordination/standard-list',
        query: {'project': ?projectId, 'facility': ?facilityId},
      );

  /// Décochage par FOSA : seuls [retainedIds] restent dans la liste de la FOSA.
  Future<void> saveFacilityList(String projectId, String facilityId, List<String> retainedIds) => _post(
    '/coordination/standard-list/$projectId/facilities/$facilityId',
    {'retained': retainedIds},
    'PUT',
  );

  /// Lien code-barres ↔ produit (vide : lien retiré).
  Future<void> saveBarcode(String projectId, String productId, String barcode) => _post(
    '/coordination/standard-list/$projectId/products/$productId/barcode',
    {'barcode': barcode},
    'PUT',
  );

  Future<void> _post(
    String path, [
    Map<String, dynamic>? data,
    String method = 'POST',
  ]) async {
    try {
      await _client.dio.request<Map<String, dynamic>>(
        path,
        data: data,
        options: Options(
          method: method,
          headers: {
            'Authorization': 'Bearer ${await _storage.read(key: 'auth_token')}',
          },
        ),
      );
    } on DioException catch (error) {
      final response = error.response;
      if (response == null) {
        throw const CoordinationActionException(offlineMessage);
      }
      final body = response.data;
      final errors = body is Map ? body['errors'] : null;
      final first = errors is Map && errors.isNotEmpty
          ? (errors.values.first as List?)?.first
          : null;
      throw CoordinationActionException(
        '${first ?? (body is Map ? body['message'] : null) ?? 'Action impossible (${response.statusCode}).'}',
      );
    }
  }
}
