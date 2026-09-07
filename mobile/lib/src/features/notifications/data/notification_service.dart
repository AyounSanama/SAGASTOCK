import '../../../core/data/local_first_repository.dart';
import '../../../core/network/api_client.dart';
import '../../../core/sync/offline_operation_service.dart';

class NotificationService {
  NotificationService({ApiClient? client}) {
    final api = client ?? ApiClient();
    _repository = LocalFirstRepository(client: api);
    _operations = OfflineOperationService(client: api);
  }
  late final LocalFirstRepository _repository;
  late final OfflineOperationService _operations;

  Future<List<Map<String, dynamic>>> list() =>
      _repository.list(collection: 'notifications', endpoint: '/notifications');
  Future<List<Map<String, dynamic>>> localList() =>
      _repository.localList(collection: 'notifications');
  Future<bool> markRead(String id) => _operations.execute(
    method: 'post',
    endpoint: '/notifications/$id/read',
    payload: const {},
    entityType: 'notifications',
  );
  Future<bool> markAllRead() => _operations.execute(
    method: 'post',
    endpoint: '/notifications/read-all',
    payload: const {},
    entityType: 'notifications',
  );
}
