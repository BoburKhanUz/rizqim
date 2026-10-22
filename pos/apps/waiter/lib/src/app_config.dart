import 'package:flutter/material.dart' show ThemeMode;

/// Ilova sozlamalari. Qiymatlar `--dart-define` bilan beriladi:
/// `flutter run -d linux --dart-define=RIZQIM_API_URL=https://staging.example.uz/api --dart-define=RIZQIM_REVERB_KEY=...`.
abstract final class AppConfig {
  /// Backend manzili, `/api` bilan tugaydi (yo'llar `/v1/...` dan boshlanadi).
  static const String apiBaseUrl = String.fromEnvironment(
    'RIZQIM_API_URL',
    defaultValue: 'http://localhost/api',
  );

  /// Reverb ilova kaliti (`REVERB_APP_KEY`). Bo'sh bo'lsa real-time o'chiq: ekranlar polling bilan yangilanadi.
  static const String reverbKey = String.fromEnvironment('RIZQIM_REVERB_KEY');

  /// Reverb manzili, agar backend bilan bir xostda bo'lmasa (`wss://ws.example.uz` yoki `ws://127.0.0.1:8080`).
  static const String reverbUrl = String.fromEnvironment('RIZQIM_REVERB_URL');

  /// WebSocket'ga yuboriladigan `Origin` (bo'sh — backend manzilining origin'i).
  static const String reverbOrigin = String.fromEnvironment(
    'RIZQIM_REVERB_ORIGIN',
  );

  /// Mavzu: `light` yoki `dark` (suratga olish uchun); bo'sh — tizimniki.
  static const String theme = String.fromEnvironment('RIZQIM_THEME');

  /// [theme] ni [ThemeMode] ga aylantiradi.
  static ThemeMode get themeMode => switch (theme) {
    'light' => ThemeMode.light,
    'dark' => ThemeMode.dark,
    _ => ThemeMode.system,
  };

  /// pubspec.yaml'dagi versiya bilan bir xil; ulashda backend'ga yuboriladi.
  static const String version = '0.1.0';

  /// Ulashda qurilma nomi (panelda Sozlamalar → Qurilmalar ro'yxatida ko'rinadi).
  static const String deviceName = 'Ofitsiant';

  /// Real-time jonli bo'lmaganda ro'yxatlar shu oraliqda qayta so'raladi.
  static const Duration pollInterval = Duration(seconds: 15);

  /// Real-time ulangan bo'lsa ham ehtiyot uchun (hodisa yo'qolishi mumkin: docs/API.md, 8-bo'lim).
  static const Duration livePollInterval = Duration(seconds: 60);
}
