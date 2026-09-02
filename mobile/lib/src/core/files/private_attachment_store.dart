import 'package:image_picker/image_picker.dart';

import 'private_attachment_store_stub.dart'
    if (dart.library.io) 'private_attachment_store_native.dart'
    as platform;

Future<String> persistPrivateAttachment(XFile source) =>
    platform.persistPrivateAttachment(source);
