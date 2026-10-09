import 'package:dio/dio.dart';

/// Converts an offline-safe JSON payload into the request body expected by Dio.
/// File paths remain local-only metadata until the operation is actually sent.
Future<Object> prepareOfflineRequestData(Map<String, dynamic> source) async {
  final payload = Map<String, dynamic>.from(source);
  final attachmentPath = payload.remove('_attachment_path')?.toString();
  final attachmentName = payload.remove('_attachment_name')?.toString();
  if (attachmentPath == null || attachmentPath.isEmpty) return payload;

  return FormData.fromMap({
    ...(_formValue(payload) as Map<String, dynamic>),
    'attachment': await MultipartFile.fromFile(
      attachmentPath,
      filename: attachmentName ?? 'ordonnance.jpg',
    ),
  });
}

/// Un formulaire multipart n'a que du texte : « true » n'est pas un booléen
/// pour le serveur, « 1 » et « 0 » le sont.
Object? _formValue(Object? value) => switch (value) {
  bool() => value ? '1' : '0',
  Map() => {
    for (final entry in value.entries) '${entry.key}': _formValue(entry.value),
  },
  List() => [for (final item in value) _formValue(item)],
  _ => value,
};
