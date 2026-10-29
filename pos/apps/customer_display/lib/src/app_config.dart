/// Ilova sozlamalari. Qiymatlar `--dart-define` bilan beriladi:
/// `flutter run -d linux --dart-define=RIZQIM_API_URL=https://staging.example.uz/api --dart-define=RIZQIM_REVERB_KEY=...`.
abstract final class AppConfig {
  /// Backend manzili, `/api` bilan tugaydi (yo'llar `/v1/...` dan boshlanadi).
  static const String apiBaseUrl = String.fromEnvironment(
    'RIZQIM_API_URL',
    defaultValue: 'http://localhost/api',
  );

  /// Reverb ilova kaliti (`REVERB_APP_KEY`). Bo'sh bo'lsa real-time o'chiq: ekran faqat har 5 soniyada yangilanadi.
  static const String reverbKey = String.fromEnvironment('RIZQIM_REVERB_KEY');

  /// Reverb manzili, agar backend bilan bir xostda bo'lmasa (`wss://ws.example.uz` yoki `ws://127.0.0.1:8080`).
  static const String reverbUrl = String.fromEnvironment('RIZQIM_REVERB_URL');

  /// WebSocket'ga yuboriladigan `Origin` (Reverb `REVERB_ALLOWED_ORIGINS` ni tekshiradi). Bo'sh — backend manzilining
  /// origin'i.
  static const String reverbOrigin = String.fromEnvironment(
    'RIZQIM_REVERB_ORIGIN',
  );

  /// pubspec.yaml'dagi versiya bilan bir xil bo'lishi kerak; ulashda backend'ga yuboriladi.
  static const String version = '0.1.0';

  /// Real-time ishlamayotganda chek va raqamlar shu oraliqda qayta so'raladi (mijoz kutib o'tirgan ekran: tez).
  static const Duration pollInterval = Duration(seconds: 5);

  /// Real-time ulangan bo'lsa ham ehtiyot uchun (hodisa yo'qolishi mumkin: docs/API.md, 8-bo'lim).
  static const Duration livePollInterval = Duration(seconds: 30);

  /// "Rahmat" ekrani shuncha turadi (kassa "Keyingi mijoz"ni bosmasa ham).
  static const Duration paidHold = Duration(seconds: 8);

  /// Yangilanmagan chek (kassa o'chib qolgan, tugatilmagan) shuncha vaqtdan keyin salomlashishga qaytadi.
  static const Duration staleCart = Duration(minutes: 30);

  /// Yangi tayyor raqam shuncha vaqt yirik ko'rsatiladi va ajratib turadi.
  static const Duration announceHold = Duration(seconds: 8);
}
