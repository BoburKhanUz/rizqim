import 'dart:async';
import 'dart:convert';

import 'package:flutter/services.dart';

/// Qurilmaning ikkinchi ekrani (Sunmi D2, T2 va boshqa ikki ekranli Android kassalar).
class SecondaryDisplay {
  const SecondaryDisplay({
    required this.id,
    required this.name,
    this.width,
    this.height,
  });

  factory SecondaryDisplay.fromMap(Map<Object?, Object?> m) => SecondaryDisplay(
    id: (m['id'] as num?)?.toInt() ?? 0,
    name: '${m['name'] ?? ''}',
    width: (m['width'] as num?)?.toInt(),
    height: (m['height'] as num?)?.toInt(),
  );

  final int id;
  final String name;
  final int? width;
  final int? height;
}

/// Kassaning ikkinchi ekrani (mijozga qaragan ekran) — Android `Presentation`: plagin ikkinchi ekranda alohida
/// Flutter dvigatelini ishga tushiradi (ilovadagi `@pragma('vm:entry-point') customerScreenMain`) va kassa yuborgan
/// xabarlarni unga uzatadi. Internet va server kerak emas: hammasi qurilmaning ichida.
///
/// ### Kanal: `uz.rizqim.pos_hardware/customer_screen` (asosiy dvigatel, MethodChannel)
///
/// | Usul | Argument | Natija |
/// |---|---|---|
/// | `displays` | — | `[{'id', 'name', 'width', 'height'}]` (`Presentation` ekranlari; bitta ekranli qurilmada bo'sh) |
/// | `show` | `{'entrypoint': String}` | `bool`: ikkinchi ekran ko'rsatildi |
/// | `hide` | — | `null` |
/// | `send` | `{'message': String (JSON)}` | `null`; plagin har `type` ning oxirgisini saqlaydi |
///
/// Ikkinchi dvigatel tomoni — [CustomerScreenReceiver]. Xabarlar (JSON):
/// `{'type': 'store', 'name': String?}`, `{'type': 'cart', 'cart': {...}}` (kassa `PosDisplayCart.toJson()`).
class CustomerScreen {
  CustomerScreen({MethodChannel? channel})
    : _channel = channel ?? defaultChannel;

  static const MethodChannel defaultChannel = MethodChannel(
    'uz.rizqim.pos_hardware/customer_screen',
  );

  /// Kassa ilovasidagi ikkinchi ekran funksiyasi (`main.dart` da, `@pragma('vm:entry-point')`).
  static const String defaultEntrypoint = 'customerScreenMain';

  final MethodChannel _channel;
  bool _shown = false;

  /// Ikkinchi ekran ko'rsatilyaptimi.
  bool get isShown => _shown;

  /// Ikkinchi ekranlar (plagin yo'q yoki xato — bo'sh ro'yxat).
  Future<List<SecondaryDisplay>> displays() async {
    try {
      final List<Object?>? raw = await _channel.invokeMethod<List<Object?>>(
        'displays',
      );
      return <SecondaryDisplay>[
        for (final Object? d in raw ?? const <Object?>[])
          if (d is Map) SecondaryDisplay.fromMap(d),
      ];
    } on MissingPluginException {
      return const <SecondaryDisplay>[];
    } on PlatformException {
      return const <SecondaryDisplay>[];
    }
  }

  /// Ikkinchi ekranni ochadi; ekran yo'q bo'lsa `false` (kassa ishi davom etadi).
  Future<bool> show({String entrypoint = defaultEntrypoint}) async {
    try {
      _shown =
          await _channel.invokeMethod<bool>('show', <String, Object?>{
            'entrypoint': entrypoint,
          }) ??
          false;
    } on MissingPluginException {
      _shown = false;
    } on PlatformException {
      _shown = false;
    }
    return _shown;
  }

  Future<void> hide() async {
    _shown = false;
    try {
      await _channel.invokeMethod<void>('hide');
    } on MissingPluginException {
      // Plagin yo'q
    } on PlatformException {
      // Ekran allaqachon yopilgan
    }
  }

  /// Xabarni ikkinchi ekranga yuboradi (kutmaydi, yiqilmaydi).
  Future<void> send(Map<String, Object?> message) async {
    if (!_shown) return;
    try {
      await _channel.invokeMethod<void>('send', <String, Object?>{
        'message': jsonEncode(message),
      });
    } on MissingPluginException {
      _shown = false;
    } on PlatformException {
      // Ikkinchi ekran yopilgan bo'lishi mumkin: keyingi `show` qayta ochadi
    }
  }
}

/// Ikkinchi ekran dvigateli tomoni: kassa yuborgan xabarlarni oladi.
///
/// Kanal `uz.rizqim.pos_hardware/customer_screen/feed` (BasicMessageChannel, satr): plagin xabarni JSON satri qilib
/// yuboradi. [start] plaginga `hello` yuboradi va javobda oxirgi xabarlarni oladi (ekran kech ochilsa ham do'kon nomi
/// va joriy chek darhol ko'rinadi).
class CustomerScreenReceiver {
  CustomerScreenReceiver({BasicMessageChannel<String?>? channel})
    : _channel = channel ?? defaultChannel;

  static const BasicMessageChannel<String?> defaultChannel =
      BasicMessageChannel<String?>(
        'uz.rizqim.pos_hardware/customer_screen/feed',
        StringCodec(),
      );

  final BasicMessageChannel<String?> _channel;
  final StreamController<Map<String, Object?>> _messages =
      StreamController<Map<String, Object?>>.broadcast();
  bool _started = false;

  /// Kassadan kelgan xabarlar (`type` bo'yicha).
  Stream<Map<String, Object?>> get messages => _messages.stream;

  /// Tinglashni boshlaydi va saqlangan xabarlarni so'raydi.
  Future<void> start() async {
    if (_started) return;
    _started = true;
    _channel.setMessageHandler((String? message) async {
      _emit(message);
      return null;
    });
    try {
      final String? replay = await _channel.send('hello');
      if (replay == null) return;
      final Object? list = jsonDecode(replay);
      if (list is List) {
        for (final Object? m in list) {
          if (m is String) _emit(m);
        }
      }
    } on Object {
      // Plagin javob bermadi: keyingi xabarlar baribir keladi
    }
  }

  void _emit(String? message) {
    final Map<String, Object?>? m = decodeScreenMessage(message);
    if (m != null && !_messages.isClosed) _messages.add(m);
  }

  Future<void> dispose() async {
    _channel.setMessageHandler(null);
    await _messages.close();
  }
}

/// JSON satridan xabar (`type` bo'lishi shart); noto'g'ri — `null`.
Map<String, Object?>? decodeScreenMessage(String? message) {
  if (message == null || message.isEmpty) return null;
  try {
    final Object? m = jsonDecode(message);
    if (m is Map<String, Object?> && m['type'] is String) return m;
  } on FormatException {
    return null;
  }
  return null;
}
