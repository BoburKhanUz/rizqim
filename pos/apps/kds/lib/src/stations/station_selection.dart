import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:rizqim_api/rizqim_api.dart';

import '../settings/kds_settings.dart';

/// Doska ko'rinishi: oddiy chiptalar, mangal (six sanog'i) yoki ixcham (ko'p mayda chipta: ichimlik, barista).
/// `auto` — faqat mangal sexlari tanlangan bo'lsa mangal, faqat ichimlik/barista sexlari tanlangan bo'lsa ixcham.
enum KitchenViewMode {
  auto,
  standard,
  mangal,
  compact;

  static KitchenViewMode fromWire(Object? raw) => KitchenViewMode.values
      .firstWhere((KitchenViewMode m) => m.name == raw, orElse: () => auto);
}

/// Oshpaz shu ekranda ko'rsatishni tanlagan sexlar. `general` — sexsiz qatorlar ("Umumiy": onlayn buyurtmalar, yo'naltirish
/// topilmagan taomlar). Tanlov qurilmada saqlanadi, shuning uchun ekran qayta yoqilganda qayta so'ralmaydi.
@immutable
class StationSelection {
  const StationSelection({
    this.chosen = false,
    this.stationIds = const <int>{},
    this.general = false,
    this.mode = KitchenViewMode.auto,
  });

  /// Oshpaz tanlovni tasdiqlagan (aks holda sexlar bor filialda tanlash ekrani chiqadi).
  final bool chosen;
  final Set<int> stationIds;
  final bool general;
  final KitchenViewMode mode;

  /// Qator ([stationId], sexsiz bo'lsa `null`) tanlangan sexga kiradimi.
  bool includes(int? stationId) =>
      stationId == null ? general : stationIds.contains(stationId);

  /// Tanlov ma'lum [stations] ro'yxatiga mos: kamida bitta tanlangan sex bor yoki "Umumiy" tanlangan.
  bool validFor(List<KitchenStation> stations) =>
      chosen &&
      (general ||
          stations.any((KitchenStation s) => stationIds.contains(s.id)));

  StationSelection copyWith({
    bool? chosen,
    Set<int>? stationIds,
    bool? general,
    KitchenViewMode? mode,
  }) => StationSelection(
    chosen: chosen ?? this.chosen,
    stationIds: stationIds ?? this.stationIds,
    general: general ?? this.general,
    mode: mode ?? this.mode,
  );

  Map<String, Object> toMap() => <String, Object>{
    'stations_chosen': chosen,
    'stations_ids': (stationIds.toList()..sort()).join(','),
    'stations_general': general,
    'view_mode': mode.name,
  };

  factory StationSelection.fromMap(Map<String, Object?> map) {
    final Object? ids = map['stations_ids'];
    return StationSelection(
      chosen: map['stations_chosen'] == true,
      stationIds: <int>{
        if (ids is String)
          for (final String part in ids.split(','))
            if (int.tryParse(part.trim()) case final int id) id,
      },
      general: map['stations_general'] == true,
      mode: KitchenViewMode.fromWire(map['view_mode']),
    );
  }

  @override
  bool operator ==(Object other) =>
      other is StationSelection &&
      other.chosen == chosen &&
      other.general == general &&
      other.mode == mode &&
      setEquals(other.stationIds, stationIds);

  @override
  int get hashCode =>
      Object.hash(chosen, general, mode, Object.hashAllUnordered(stationIds));
}

/// Joriy sexlar tanlovi; o'zgarsa ekran qayta chiziladi va qiymat saqlanadi (sozlamalar ombori bilan bir joyda).
class StationSelectionController extends ValueNotifier<StationSelection> {
  StationSelectionController(
    this._store, [
    super.initial = const StationSelection(),
  ]);

  final KdsSettingsStore _store;

  Future<void> load() async {
    try {
      value = StationSelection.fromMap(await _store.load());
    } on Object {
      // Tanlov yo'q: oshpazdan so'raladi
    }
  }

  /// Oshpaz sexlarni tanladi.
  void choose({required Set<int> stationIds, required bool general}) => _update(
    value.copyWith(
      chosen: true,
      stationIds: Set<int>.unmodifiable(stationIds),
      general: general,
    ),
  );

  void setMode(KitchenViewMode mode) => _update(value.copyWith(mode: mode));

  void _update(StationSelection next) {
    if (next == value) return;
    value = next;
    unawaited(_save(next));
  }

  Future<void> _save(StationSelection s) async {
    try {
      await _store.save(s.toMap());
    } on Object {
      // Saqlanmasa ham joriy ishga tushirishda amal qiladi
    }
  }
}

/// Doska mangal ko'rinishidami: oshpaz tanlagan bo'lsa shu, `auto` bo'lsa faqat mangal sexlari tanlangan bo'lsa.
bool isMangalView(StationSelection sel, List<KitchenStation>? stations) {
  switch (sel.mode) {
    case KitchenViewMode.mangal:
      return true;
    case KitchenViewMode.standard:
    case KitchenViewMode.compact:
      return false;
    case KitchenViewMode.auto:
      return _allChosen(sel, stations, (KitchenStation s) => s.isMangal);
  }
}

/// P6-33: doska ixcham ko'rinishdami (barista va ichimlik sexlari: ko'p mayda chipta). Oshpaz tanlagan bo'lsa shu,
/// `auto` bo'lsa faqat ichimlik/barista sexlari tanlangan bo'lsa.
bool isCompactView(StationSelection sel, List<KitchenStation>? stations) {
  switch (sel.mode) {
    case KitchenViewMode.compact:
      return true;
    case KitchenViewMode.standard:
    case KitchenViewMode.mangal:
      return false;
    case KitchenViewMode.auto:
      return _allChosen(sel, stations, (KitchenStation s) => s.isDrink);
  }
}

/// P6-33: tanlangan sexlar orasidagi yig'ish (expo) sexlari id si; sexlar ma'lum bo'lmasa bo'sh.
Set<int> chosenExpoIds(StationSelection sel, List<KitchenStation>? stations) =>
    <int>{
      for (final KitchenStation s in stations ?? const <KitchenStation>[])
        if (s.isExpo && sel.stationIds.contains(s.id)) s.id,
    };

/// Faqat yig'ish sexi tanlangan: oddiy doska (sexlarning o'z qatorlari) bo'sh, faqat yig'ish ekrani bor.
bool isExpoOnly(StationSelection sel, List<KitchenStation>? stations) =>
    _allChosen(sel, stations, (KitchenStation s) => s.isExpo);

bool _allChosen(
  StationSelection sel,
  List<KitchenStation>? stations,
  bool Function(KitchenStation) test,
) {
  if (stations == null || sel.general || sel.stationIds.isEmpty) return false;
  final List<KitchenStation> chosen = stations
      .where((KitchenStation s) => sel.stationIds.contains(s.id))
      .toList();
  return chosen.isNotEmpty && chosen.every(test);
}
