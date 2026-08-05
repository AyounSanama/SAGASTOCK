abstract final class AppConfig {
  // Default base URL for Android emulator.
  // On a physical device, override with the host machine IP:
  // flutter run --dart-define=API_BASE_URL=http://192.168.x.x:8000/api/v1
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://192.168.137.234:8000/api/v1',
  );
}
