import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/access/session_scope.dart';

void main() {
  test('uses the direct project context from the authenticated user', () {
    final user = <String, dynamic>{
      'organization_id': 'org-1',
      'project_id': 'project-1',
      'access_scope': <String, dynamic>{
        'project_ids': <String>['project-2'],
      },
    };

    expect(SessionScope.organizationId(user), 'org-1');
    expect(SessionScope.projectId(user), 'project-1');
  });

  test(
    'falls back to the scoped project without selecting an organization',
    () {
      final user = <String, dynamic>{
        'organization': <String, dynamic>{'id': 'org-1'},
        'scopes': <Map<String, dynamic>>[
          <String, dynamic>{'type': 'project', 'id': 'project-1'},
        ],
      };

      expect(SessionScope.organizationId(user), 'org-1');
      expect(SessionScope.projectId(user), 'project-1');
    },
  );
}
