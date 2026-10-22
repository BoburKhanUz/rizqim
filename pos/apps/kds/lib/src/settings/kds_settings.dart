import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:rizqim_api/rizqim_api.dart' show StationKind;
import 'package:shared_preferences/shared_preferences.dart';

/// Oshxona ekrani sozlamalari (qurilmada saqlanadi, maxfiy emas).
@immutable
class KdsSettings {
  const KdsSettings({
    this.idleTimeout = const Duration(minutes: 15),
    this.warnAfter = const Duration(minutes: 10),
    this.lateAfter = const Duration(minutes: 20),
    this.muted = false,
    this.dark = true,
    this.fontScale = 1.0,
    this.leadOsh = const Duration(minutes: 120),
    this.leadMangal = const Duration(minutes: 30),
    this.leadOther = const Duration(minutes: 30),
  });

  /// Shuncha vaqt ekranga tegilmasa oshpaz chiqariladi (PIN qayta so'raladi). `null` — o'chirilgan.
  final Duration? idleTimeout;

  /// Kutish vaqti shundan oshsa chipta sariq ("Shoshiling").
  final Duration warnAfter;

  /// Shundan oshsa qizil ("Kechikdi").
  final Duration lateAfter;

  /// Yangi chipta ovozi o'chirilgan.
  final bool muted;

  /// Qorong'i mavzu (oshxona uchun standart).
  final bool dark;

  /// Shrift kattaligi: 1.0, 1.2, 1.4 (televizor uchun).
  final double fontScale;

  /// Oldindan buyurtma (`due_at`) muddatidan shuncha oldin faol doskaga chiqadi: osh (qozon) uzoq pishadi, mangal
  /// qisqa. Sex turiga qarab ([leadFor]).
  final Duration leadOsh;
  final Duration leadMangal;
  final Duration leadOther;

  /// Sex turi ([StationKind]) uchun oldindan chiqish vaqti; sexsiz yoki boshqa tur — [leadOther].
  Duration leadFor(String? stationKind) => switch (stationKind) {
    StationKind.osh => leadOsh,
    StationKind.mangal => leadMangal,
    _ => leadOther,
  };

  static const List<int> leadOshChoices = <int>[60, 90, 120, 150, 180, 240];
  static const List<int> leadMangalChoices = <int>[10, 15, 20, 30, 45, 60];
  static const List<int> leadOtherChoices = <int>[10, 15, 30, 45, 60, 90];

  static const List<Duration?> idleChoices = <Duration?>[
    null,
    Duration(minutes: 5),
    Duration(minutes: 10),
    Duration(minutes: 15),
    Duration(minutes: 30),
    Duration(minutes: 60),
  ];
  static const List<int> warnChoices = <int>[5, 8, 10, 12, 15, 20, 25, 30];
  static const List<int> lateChoices = <int>[10, 15, 20, 25, 30, 40, 45, 60];
  static const List<double> fontChoices = <double>[1.0, 1.2, 1.4];

  KdsSettings copyWith({
    Duration? Function()? idleTimeout,
    Duration? warnAfter,
    Duration? lateAfter,
    bool? muted,
    bool? dark,
    double? fontScale,
    Duration? leadOsh,
    Duration? leadMangal,
    Duration? leadOther,
  }) {
    final Duration warn = warnAfter ?? this.warnAfter;
    Duration late = lateAfter ?? this.lateAfter;
    // "Kechikdi" chegarasi har doim "shoshiling"dan keyin
    if (late <= warn) late = warn + const Duration(minutes: 5);
    return KdsSettings(
      idleTimeout: idleTimeout != null ? idleTimeout() : this.idleTimeout,
      warnAfter: warn,
      lateAfter: late,
      muted: muted ?? this.muted,
      dark: dark ?? this.dark,
      fontScale: fontScale ?? this.fontScale,
      leadOsh: leadOsh ?? this.leadOsh,
      leadMangal: leadMangal ?? this.leadMangal,
      leadOther: leadOther ?? this.leadOther,
    );
  }

  Map<String, Object> toMap() => <String, Object>{
    'idle_minutes': idleTimeout?.inMinutes ?? 0,
    'warn_minutes': warnAfter.inMinutes,
    'late_minutes': lateAfter.inMinutes,
    'muted': muted,
    'dark': dark,
    'font_scale': fontScale,
    'lead_osh_minutes': leadOsh.inMinutes,
    'lead_mangal_minutes': leadMangal.inMinutes,
    'lead_other_minutes': leadOther.inMinutes,
  };

  /// Saqlangan qiymatlardan; yo'q yoki buzilgan qiymat o'rniga standart.
  factory KdsSettings.fromMap(Map<String, Object?> map) {
    const KdsSettings d = KdsSettings();
    int? minutes(String key) {
      final Object? v = map[key];
      return v is int && v >= 0 && v <= 24 * 60 ? v : null;
    }

    final int? idle = minutes('idle_minutes');
    final int? warn = minutes('warn_minutes');
    final int? late = minutes('late_minutes');
    final Object? scale = map['font_scale'];
    return d.copyWith(
      idleTimeout: idle == null
          ? null
          : () => idle == 0 ? null : Duration(minutes: idle),
      warnAfter: warn != null && warn > 0 ? Duration(minutes: warn) : null,
      lateAfter: late != null && late > 0 ? Duration(minutes: late) : null,
      muted: map['muted'] is bool ? map['muted']! as bool : null,
      dark: map['dark'] is bool ? map['dark']! as bool : null,
      fontScale: scale is double && scale >= 0.8 && scale <= 2 ? scale : null,
      leadOsh: _lead(minutes('lead_osh_minutes')),
      leadMangal: _lead(minutes('lead_mangal_minutes')),
      leadOther: _lead(minutes('lead_other_minutes')),
    );
  }

  static Duration? _lead(int? m) =>
      m == null || m <= 0 ? null : Duration(minutes: m);

  @override
  bool operator ==(Object other) =>
      other is KdsSettings &&
      other.idleTimeout == idleTimeout &&
      other.warnAfter == warnAfter &&
      other.lateAfter == lateAfter &&
      other.muted == muted &&
      other.dark == dark &&
      other.fontScale == fontScale &&
      other.leadOsh == leadOsh &&
      other.leadMangal == leadMangal &&
      other.leadOther == leadOther;

  @override
  int get hashCode => Object.hash(
    idleTimeout,
    warnAfter,
    lateAfter,
    muted,
    dark,
    fontScale,
    leadOsh,
    leadMangal,
    leadOther,
  );
}

/// Sozlamalar ombori.
abstract interface class KdsSettingsStore {
  Future<Map<String, Object?>> load();

  Future<void> save(Map<String, Object> values);
}

/// `shared_preferences` (Android SharedPreferences, Windows/Linux JSON fayl). Token emas, faqat ekran sozlamalari.
class PrefsSettingsStore implements KdsSettingsStore {
  static const String _prefix = 'kds.settings.';

  @override
  Future<Map<String, Object?>> load() async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    return <String, Object?>{
      for (final String key in prefs.getKeys())
        if (key.startsWith(_prefix))
          key.substring(_prefix.length): prefs.get(key),
    };
  }

  @override
  Future<void> save(Map<String, Object> values) async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    for (final MapEntry<String, Object> e in values.entries) {
      final String key = '$_prefix${e.key}';
      switch (e.value) {
        case final bool v:
          await prefs.setBool(key, v);
        case final int v:
          await prefs.setInt(key, v);
        case final double v:
          await prefs.setDouble(key, v);
        case final Object v:
          await prefs.setString(key, '$v');
      }
    }
  }
}

/// Testlar uchun.
class MemorySettingsStore implements KdsSettingsStore {
  MemorySettingsStore([Map<String, Object?>? values])
    : values = values ?? <String, Object?>{};

  final Map<String, Object?> values;

  @override
  Future<Map<String, Object?>> load() async => Map<String, Object?>.of(values);

  @override
  Future<void> save(Map<String, Object> v) async => values.addAll(v);
}

/// Joriy sozlamalar; o'zgarsa ekranlar qayta chiziladi va qiymat saqlanadi.
class KdsSettingsController extends ValueNotifier<KdsSettings> {
  KdsSettingsController(this._store, [super.initial = const KdsSettings()]);

  final KdsSettingsStore _store;

  /// Saqlangan sozlamalarni o'qiydi; o'qib bo'lmasa standartlar qoladi.
  Future<void> load() async {
    try {
      value = KdsSettings.fromMap(await _store.load());
    } on Object {
      // Standart sozlamalar bilan ishlayveradi
    }
  }

  void update(KdsSettings next) {
    if (next == value) return;
    value = next;
    unawaited(_save(next));
  }

  Future<void> _save(KdsSettings s) async {
    try {
      await _store.save(s.toMap());
    } on Object {
      // Saqlanmasa ham joriy ishga tushirishda amal qiladi
    }
  }
}
