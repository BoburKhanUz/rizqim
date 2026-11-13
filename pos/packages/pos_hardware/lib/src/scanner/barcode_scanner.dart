import 'dart:async';

import 'package:flutter/services.dart';

/// Kod qayerdan keldi.
enum ScanSource {
  /// Klaviatura sifatida ulangan skaner (USB/Bluetooth HID): `pos_ui` dagi `KeyboardScanListener`.
  keyboard,

  /// Qurilmaning ichki skaneri (Sunmi va boshqalar) — tizim xabari (broadcast) orqali.
  broadcast,
}

/// O'qilgan shtrix-kod yoki QR.
class BarcodeScan {
  const BarcodeScan(this.code, {required this.source, this.action});

  final String code;
  final ScanSource source;

  /// Broadcast nomi (qaysi skaner yubordi), klaviaturada `null`.
  final String? action;

  @override
  String toString() => 'BarcodeScan($code, ${source.name})';
}

/// Ichki skaner kodni qaysi tizim xabari (Android broadcast) bilan yuboradi.
class ScanBroadcast {
  const ScanBroadcast(this.action, {this.extra = 'data'});

  /// Sunmi (V2, P2, T2, L2 ...): `com.sunmi.scanner.ACTION_DATA_CODE_RECEIVED`, kod `data` da.
  static const ScanBroadcast sunmi = ScanBroadcast(
    'com.sunmi.scanner.ACTION_DATA_CODE_RECEIVED',
  );

  final String action;

  /// Kod yozilgan qo'shimcha (String).
  final String extra;

  Map<String, Object?> toMap() => <String, Object?>{
    'action': action,
    'extra': extra,
  };
}

/// Qurilmaning ichki skaneri: Android plagini tizim xabarlarini (broadcast) tinglaydi va kodni oqimga beradi.
///
/// ### Kanal: `uz.rizqim.pos_hardware/scanner` (EventChannel)
///
/// Tinglash argumenti `{'actions': [{'action': String, 'extra': String}]}`; har hodisa —
/// `{'code': String, 'action': String}`. Android bo'lmagan platformada [scans] bo'sh qoladi (plagin yo'q).
///
/// Boshqa ishlab chiqaruvchi skaneri (iMin, Urovo, Honeywell "broadcast" rejimi) uchun uning xabar nomini
/// [broadcasts] ga qo'shing.
class BroadcastScanner {
  BroadcastScanner({
    EventChannel? channel,
    this.broadcasts = const <ScanBroadcast>[ScanBroadcast.sunmi],
  }) : _channel = channel ?? defaultChannel;

  static const EventChannel defaultChannel = EventChannel(
    'uz.rizqim.pos_hardware/scanner',
  );

  final EventChannel _channel;
  final List<ScanBroadcast> broadcasts;

  /// O'qilgan kodlar (bo'sh kodlar tashlanadi; oxiridagi bo'sh joy va qator oxiri olib tashlanadi).
  Stream<BarcodeScan> get scans => _channel
      .receiveBroadcastStream(<String, Object?>{
        'actions': <Map<String, Object?>>[
          for (final ScanBroadcast b in broadcasts) b.toMap(),
        ],
      })
      .map(parseScanEvent)
      .where((BarcodeScan? s) => s != null)
      .cast<BarcodeScan>();
}

/// Kanal hodisasidan kod; noto'g'ri yoki bo'sh — `null`.
BarcodeScan? parseScanEvent(Object? event) {
  if (event is! Map) return null;
  final Object? raw = event['code'];
  if (raw is! String) return null;
  final String code = raw.trim();
  if (code.isEmpty) return null;
  final Object? action = event['action'];
  return BarcodeScan(
    code,
    source: ScanSource.broadcast,
    action: action is String ? action : null,
  );
}
