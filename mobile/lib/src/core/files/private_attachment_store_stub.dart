import 'package:image_picker/image_picker.dart';

Future<String> persistPrivateAttachment(XFile source) async => source.path;
