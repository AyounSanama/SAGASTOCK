import 'dart:io';

import 'package:image_picker/image_picker.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:uuid/uuid.dart';

Future<String> persistPrivateAttachment(XFile source) async {
  final root = await getApplicationDocumentsDirectory();
  final directory = Directory(p.join(root.path, 'private', 'prescriptions'));
  await directory.create(recursive: true);
  final extension = p.extension(source.path).toLowerCase();
  final target = p.join(
    directory.path,
    '${const Uuid().v4()}${extension.isEmpty ? '.jpg' : extension}',
  );
  await source.saveTo(target);
  return target;
}
