import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';
import '../../../core/network/api_client.dart';
import '../../../core/sync/offline_operation_service.dart';
import '../../../core/data/local_first_repository.dart';

class OrderService {
  OrderService({ApiClient? client}) {
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
  Future<List<Map<String, dynamic>>> orders(String id) async {
    await sync();
    return _list('/organizations/$id/orders', 'offline_orders_$id');
  }

  Future<Map<String, dynamic>> options(String id) async {
    return _repository.document(
      collection: 'order.options',
      organizationId: id,
      endpoint: '/organizations/$id/orders/options',
    );
  }

  Future<bool> create(String id, Map<String, dynamic> data) {
    data['offline_uuid'] ??= const Uuid().v4();
    return _send('post', '/organizations/$id/orders', data);
  }

  Future<bool> submit(String id, String order) =>
      _send('post', '/organizations/$id/orders/$order/submit', {});
  Future<bool> decide(
    String id,
    String order,
    String decision, {
    String? comment,
  }) => _send('post', '/organizations/$id/orders/$order/decision', {
    'decision': decision,
    'comment': comment,
  });
  Future<bool> prepare(
    String id,
    String order,
    List<Map<String, dynamic>> lines, {
    bool complete = false,
  }) => _send('post', '/organizations/$id/orders/$order/prepare', {
    'lines': lines,
    'complete': complete,
  });
  Future<int> pendingCount() => _operations.pendingCount(entityType: 'orders');
  Future<bool> _send(String method, String path, Map<String, dynamic> data) =>
      _operations.execute(
        method: method,
        endpoint: path,
        payload: data,
        entityType: 'orders',
        organizationId: RegExp(
          r'/organizations/([^/]+)',
        ).firstMatch(path)?.group(1),
      );
  Future<void> sync() => _operations.synchronize();
  Future<List<Map<String, dynamic>>> _list(String path, String key) async {
    return _repository.list(
      collection: key,
      organizationId: RegExp(
        r'/organizations/([^/]+)',
      ).firstMatch(path)?.group(1),
      endpoint: path,
    );
  }
}
