import '../../../core/data/local_first_repository.dart';

class OperationalReportService {
  final LocalFirstRepository _repository = LocalFirstRepository();

  Future<Map<String, dynamic>> load(String organizationId) =>
      _repository.document(
        collection: 'operational-report',
        organizationId: organizationId,
        endpoint: '/organizations/$organizationId/operational-report',
      );
}
