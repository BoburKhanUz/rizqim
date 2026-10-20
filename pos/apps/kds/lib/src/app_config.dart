/// Ilova sozlamalari. Qiymatlar `--dart-define` bilan beriladi:
/// `flutter run -d linux --dart-define=RIZQIM_API_URL=https://staging.example.uz/api`.
abstract final class AppConfig {
  /// Backend manzili, `/api` bilan tugaydi (yo'llar `/v1/...` dan boshlanadi).
  static const String apiBaseUrl = String.fromEnvironment(
    'RIZQIM_API_URL',
    defaultValue: 'http://localhost/api',
  );

  /// pubspec.yaml'dagi versiya bilan bir xil bo'lishi kerak; ulashda backend'ga yuboriladi.
  static const String version = '0.1.0';
}
