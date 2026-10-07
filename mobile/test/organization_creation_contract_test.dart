import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/features/organizations/data/organization_service.dart';
import 'package:sagastock_mobile/src/features/organizations/presentation/organization_creation_page.dart';

void main() {
  test('unipays est encode comme un tableau multipart PHP', () {
    final data = buildOrganizationCreationFormData({
      'geographic_access_type': 'single_country',
      'country_ids': ['country-cm'],
    });
    expect(
      data.fields.any(
        (field) => field.key == 'country_ids[]' && field.value == 'country-cm',
      ),
      isTrue,
    );
    expect(
      data.fields.where((field) => field.key == 'country_ids[]'),
      hasLength(1),
    );
    expect(data.fields.where((field) => field.key == 'country_ids'), isEmpty);
  });

  test('multipays conserve chaque pays dans le meme tableau', () {
    final data = buildOrganizationCreationFormData({
      'country_ids': ['country-cm', 'country-td'],
    });
    expect(
      data.fields
          .where((field) => field.key == 'country_ids[]')
          .map((field) => field.value),
      ['country-cm', 'country-td'],
    );
  });

  test('validation locale de l identifiant admin', () {
    expect(validateOrganizationAdminUsername('jean_coordination'), isNull);
    expect(validateOrganizationAdminUsername('jean-coordination'), isNull);
    expect(validateOrganizationAdminUsername('JeanCoordination01'), isNull);
    expect(validateOrganizationAdminUsername('hélène_coordination'), isNull, reason: 'accents acceptés comme sur le serveur');
    expect(validateOrganizationAdminUsername('jean coordination'), isNotNull);
    expect(validateOrganizationAdminUsername('jean@coordination'), isNotNull);
  });

  test('les erreurs API connues sont localisees', () {
    final request = RequestOptions(path: '/organizations');
    final error = DioException(
      requestOptions: request,
      response: Response(
        requestOptions: request,
        statusCode: 422,
        data: {
          'errors': {
            'country_ids': ['The country ids field must be an array.'],
          },
        },
      ),
    );
    expect(
      organizationCreationErrorMessage(error),
      'Veuillez sélectionner un pays valide.',
    );
  });
}
