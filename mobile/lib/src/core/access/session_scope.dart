class SessionScope {
  const SessionScope._();

  static String organizationId(Map<String, dynamic>? user) =>
      '${user?['organization_id'] ?? (user?['organization'] as Map?)?['id'] ?? ''}';

  static String projectId(Map<String, dynamic>? user) {
    final direct =
        '${user?['project_id'] ?? (user?['project'] as Map?)?['id'] ?? ''}';
    if (direct.isNotEmpty) return direct;
    final scope = user?['access_scope'];
    if (scope is Map) {
      final ids = scope['project_ids'];
      if (ids is List && ids.isNotEmpty) return '${ids.first}';
    }
    final scopes = user?['scopes'];
    if (scopes is List) {
      for (final item in scopes.whereType<Map>()) {
        if ('${item['type']}' == 'project') return '${item['id'] ?? ''}';
      }
    }
    return '';
  }
}
