import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// P6 lokal ko'prik: Android'da `WifiManager.MulticastLock` — ofitsiant, KDS va mijoz ekrani markaz kassaning UDP
/// beacon'ini tinglayotgan bir necha soniya davomida ushlanadi (ko'p telefonlar quvvatni tejash uchun Wi-Fi'dagi
/// broadcast'ni tashlab yuboradi). Boshqa platformalarda (Windows, Linux, testlar) hech narsa qilmaydi.
///
/// Kanal `uz.rizqim.pos_hardware/multicast` (`WifiMulticast.kt`): `acquire` → `bool`, `release`, `held` → `bool`.
class WifiMulticastLock {
  WifiMulticastLock({MethodChannel? channel, bool? android})
    : _channel = channel ?? defaultChannel,
      _android = android ?? defaultTargetPlatform == TargetPlatform.android;

  static const MethodChannel defaultChannel = MethodChannel(
    'uz.rizqim.pos_hardware/multicast',
  );

  final MethodChannel _channel;
  final bool _android;

  /// `true`: ushlandi (Android'dan boshqa joyda yoki plagin yo'q bo'lsa `false`, tinglash baribir ishlaydi).
  Future<bool> acquire() async {
    if (!_android) return false;
    try {
      return await _channel.invokeMethod<bool>('acquire') ?? false;
    } on MissingPluginException {
      return false;
    } on PlatformException {
      return false;
    }
  }

  Future<void> release() async {
    if (!_android) return;
    try {
      await _channel.invokeMethod<void>('release');
    } on MissingPluginException {
      // No plugin (tests, another platform)
    } on PlatformException {
      // Already released
    }
  }
}
