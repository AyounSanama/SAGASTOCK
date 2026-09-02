import 'package:dio/dio.dart';

/// Converts an offline-safe JSON payload into the request body expected by Dio.
/// File paths remain local-only metadata until the operation is actually sent.
Future<Object> prepareOfflineRequestData(Map<String, dynamic> source) async {
  final payload = Map<String, dynamic>.from(source);
  final attachmentPath = payload.remove('_attachment_path')?.toString();
  final attachmentName = payload.remove('_attachment_name')?.toString();
  if (attachmentPath == null || attachmentPath.isEmpty) return payload;

  return FormData.fromMap({
    ...payload,
    'attachment': await MultipartFile.fromFile(
      attachmentPath,
      filename: attachmentName ?? 'ordonnance.jpg',
    ),
  });
}
