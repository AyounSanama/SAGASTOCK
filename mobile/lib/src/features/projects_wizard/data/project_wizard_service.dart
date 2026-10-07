import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/network/api_client.dart';

/// Erreur affichable de l'assistant, avec les erreurs par champ du serveur.
class ProjectWizardException implements Exception {
  const ProjectWizardException(this.message, [this.fieldErrors = const {}]);

  final String message;
  final Map<String, String> fieldErrors;

  @override
  String toString() => message;
}

/// Niveau 2 — Assistant « Créer un projet / programme » (mobile).
///
/// Mêmes règles que le Web (ProjectWizardService côté serveur). La création
/// d'un projet exige le réseau : le serveur contrôle le code, le bailleur et la
/// Liste Standard au moment de l'enregistrement.
class ProjectWizardService {
  ProjectWizardService({ApiClient? client, FlutterSecureStorage? storage})
    : _client = client ?? ApiClient(),
      _storage = storage ?? const FlutterSecureStorage();

  final ApiClient _client;
  final FlutterSecureStorage _storage;

  static const offlineMessage =
      'La création d’un projet nécessite une connexion internet.';

  /// Missions, bailleurs, types et valeurs du stock de sécurité.
  Future<Map<String, dynamic>> options() => _send('GET', '/projects/wizard/options');

  Future<Map<String, dynamic>> project(String id) =>
      _send('GET', '/projects/$id/wizard');

  /// Étapes 1-2 : crée le brouillon ou met à jour son identité.
  Future<Map<String, dynamic>> saveIdentity(
    String? id,
    Map<String, dynamic> data, {
    bool draft = false,
  }) => id == null
      ? _send('POST', '/projects/wizard', {...data, if (draft) 'intent': 'draft'})
      : _send('PUT', '/projects/$id/wizard/identity', {
          ...data,
          if (draft) 'intent': 'draft',
        });

  /// Étape 3 : sans [selection], choix et liste enregistrés ; avec, la liste
  /// suit les choix en cours sans rien enregistrer.
  Future<Map<String, dynamic>> standardList(
    String id, {
    Map<String, List<String>>? selection,
  }) => _send(
    'GET',
    '/projects/$id/wizard/standard-list',
    null,
    selection == null
        ? null
        : {
            'refresh': '1',
            for (final entry in selection.entries) '${entry.key}[]': entry.value,
          },
  );

  Future<Map<String, dynamic>> saveStandardList(
    String id,
    Map<String, dynamic> data, {
    bool draft = false,
  }) => _send('PUT', '/projects/$id/wizard/standard-list', {
    ...data,
    if (draft) 'intent': 'draft',
  });

  /// Niveau 6 — « + Nouveau produit » : [product] est créé dans le catalogue
  /// de l'organisation et retenu ; [selection] (choix en cours) est enregistrée
  /// en brouillon avec lui.
  Future<Map<String, dynamic>> createProduct(
    String id,
    Map<String, dynamic> product,
    Map<String, dynamic> selection,
  ) => _send('POST', '/projects/$id/wizard/standard-list/products', {
    ...selection,
    'product': product,
  });

  /// Étape 4 : sans [draft], le projet passe « Actif » et sa Liste Standard est validée.
  Future<Map<String, dynamic>> saveSupply(
    String id,
    Map<String, dynamic> data, {
    bool draft = false,
  }) => _send('PUT', '/projects/$id/wizard/supply', {
    ...data,
    if (draft) 'intent': 'draft',
  });

  /// « + Ajouter un bailleur » : ajouté au référentiel de l'organisation.
  Future<Map<String, dynamic>> addDonor({
    required String missionId,
    required String name,
    required String code,
  }) => _send('POST', '/projects/wizard/donors', {
    'mission_id': missionId,
    'name': name,
    'code': code,
  });

  Future<Map<String, dynamic>> _send(
    String method,
    String path, [
    Map<String, dynamic>? data,
    Map<String, dynamic>? query,
  ]) async {
    try {
      final response = await _client.dio.request<Map<String, dynamic>>(
        path,
        data: data,
        queryParameters: query,
        options: Options(
          method: method,
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer ${await _storage.read(key: 'auth_token')}',
          },
        ),
      );
      return response.data ?? const {};
    } on DioException catch (error) {
      final response = error.response;
      if (response == null) throw const ProjectWizardException(offlineMessage);
      final body = response.data;
      final errors = body is Map ? body['errors'] : null;
      final fields = <String, String>{
        if (errors is Map)
          for (final entry in errors.entries)
            if (entry.value is List && (entry.value as List).isNotEmpty)
              '${entry.key}': '${(entry.value as List).first}',
      };
      throw ProjectWizardException(
        fields.values.firstOrNull ??
            '${(body is Map ? body['message'] : null) ?? 'Enregistrement impossible (${response.statusCode}).'}',
        fields,
      );
    }
  }
}
