import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';
import '../../../core/network/api_client.dart';
import '../../../core/sync/offline_operation_service.dart';
import '../../../core/data/local_first_repository.dart';

class InventoryService {
  InventoryService({ApiClient? client}) {
    _client = client ?? ApiClient();
    _operations = OfflineOperationService(client: _client);
    _repository = LocalFirstRepository(client: _client, storage: _storage);
  }
  late final ApiClient _client;
  late final OfflineOperationService _operations;
  late final LocalFirstRepository _repository;
  final _storage = const FlutterSecureStorage();
  Future<List<Map<String, dynamic>>> organizations() =>
      _list('/organizations', 'offline_organizations');
  Future<List<Map<String, dynamic>>> inventories(String id) async {
    await sync();
    return _list('/organizations/$id/inventories', 'offline_inventories_$id');
  }

  Future<List<Map<String, dynamic>>> sites(String id) async {
    return _repository.list(
      collection: 'inventory.sites',
      organizationId: id,
      endpoint: '/organizations/$id/inventories/options',
      responseKey: 'sites',
    );
  }

  Future<bool> create(String id, Map<String, dynamic> data) {
    data['offline_uuid'] ??= const Uuid().v4();
    return _send('post', '/organizations/$id/inventories', data);
  }

  Future<bool> start(String id, String inventory) =>
      _send('post', '/organizations/$id/inventories/$inventory/start', {});
  Future<bool> count(
    String id,
    String inventory,
    List<Map<String, dynamic>> lines,
  ) async {
    final online = await _send(
      'put',
      '/organizations/$id/inventories/$inventory/count',
      {'lines': lines},
    );
    if (!online) {
      final cached = await _repository.localList(
        collection: 'offline_inventories_$id',
        organizationId: id,
      );
      for (final item in cached.where((item) => '${item['id']}' == inventory)) {
        final cachedLines = (item['lines'] as List? ?? [])
            .whereType<Map>()
            .map((line) => Map<String, dynamic>.from(line))
            .toList();
        for (final update in lines) {
          final target = cachedLines
              .where((line) => '${line['id']}' == '${update['id']}')
              .firstOrNull;
          if (target != null) {
            target['physical_quantity'] = update['physical_quantity'];
            target['justification'] = update['justification'];
          }
        }
        item['lines'] = cachedLines;
        item['_sync_status'] = 'pending';
      }
      await _repository.cacheList(
        collection: 'offline_inventories_$id',
        organizationId: id,
        values: cached,
      );
    }
    return online;
  }

  Future<bool> submit(String id, String inventory) =>
      _send('post', '/organizations/$id/inventories/$inventory/submit', {});
  Future<int> pendingCount() =>
      _operations.pendingCount(entityType: 'inventories');
  Future<bool> _send(
    String method,
    String path,
    Map<String, dynamic> data,
  ) async {
    return _operations.execute(
      method: method,
      endpoint: path,
      payload: data,
      entityType: 'inventories',
      organizationId: _organizationFrom(path),
    );
  }

  Future<void> sync() => _operations.synchronize();

  Future<List<Map<String, dynamic>>> _list(String path, String key) async {
    return _repository.list(
      collection: key,
      organizationId: _organizationFrom(path),
      endpoint: path,
    );
  }

  String? _organizationFrom(String path) =>
      RegExp(r'/organizations/([^/]+)').firstMatch(path)?.group(1);
}
