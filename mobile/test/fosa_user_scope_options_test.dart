import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/features/users/presentation/scoped_users_page.dart';

void main() {
  test('site choices are strictly filtered by the selected facility', () {
    final sites = <Map<String, dynamic>>[
      {'id': 'site-a1', 'health_facility_id': 'facility-a'},
      {'id': 'site-a2', 'health_facility_id': 'facility-a'},
      {'id': 'site-b1', 'health_facility_id': 'facility-b'},
      {'id': 'site-a2', 'health_facility_id': 'facility-a'},
    ];

    expect(sitesForFacility(sites, 'facility-a').map((site) => site['id']), [
      'site-a1',
      'site-a2',
    ]);
    expect(sitesForFacility(sites, 'facility-b').map((site) => site['id']), [
      'site-b1',
    ]);
  });

  test('a facility without a site returns an explicit empty collection', () {
    expect(sitesForFacility(const [], 'facility-a'), isEmpty);
  });

  test('only synchronized UUID sites can be used as account scopes', () {
    final sites = <Map<String, dynamic>>[
      {
        'id': '01991e3e-1480-7f94-8f91-9e944f72fd5b',
        'health_facility_id': 'facility-a',
      },
      {
        'id': 'local-01991e3e-1480-7f94-8f91-9e944f72fd5c',
        'health_facility_id': 'facility-a',
      },
    ];

    expect(
      persistedSitesForFacility(sites, 'facility-a').map((site) => site['id']),
      ['01991e3e-1480-7f94-8f91-9e944f72fd5b'],
    );
    expect(isPersistedScopeId('local-temporary-id'), isFalse);
  });
}
