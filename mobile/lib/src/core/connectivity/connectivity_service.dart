import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';

class ConnectivityService {
  ConnectivityService({Connectivity? connectivity})
    : _connectivity = connectivity ?? Connectivity();

  final Connectivity _connectivity;

  Future<bool> get hasNetwork async =>
      _hasUsableNetwork(await _connectivity.checkConnectivity());

  Stream<bool> get networkChanges =>
      _connectivity.onConnectivityChanged.map(_hasUsableNetwork).distinct();

  bool _hasUsableNetwork(List<ConnectivityResult> results) =>
      results.any((result) => result != ConnectivityResult.none);
}
