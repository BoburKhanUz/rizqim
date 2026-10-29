import 'package:pos_data/pos_data.dart';

/// P6-11/P6-12: filial formati kassada (docs/POS_SPEC.md 2.15). Ekran va tugmalar faqat samarali modullarga qarab
/// ko'rsatiladi; eski server (`format` yo'q) — choyxona ([PosFormat.legacy]), 3-bosqich oqimi o'zgarmaydi.
extension CashierFormat on PosSession {
  /// Filial formati (bootstrap yuklanmagan bo'lsa — eski choyxona).
  PosFormat get posFormat =>
      bootstrap?.format ?? PosFormat.legacy(PosMode.hybrid);

  /// Samarali modullar.
  PosModules get posModules => posFormat.modules;

  bool hasModule(PosModule module) => posModules.has(module);

  /// "Tez kassa" bo'limi ko'rinadimi (P6-12, A1): `quick_counter` moduli yoqilgan va rejim `table` emas.
  ///
  /// Choyxona (`hybrid`, zal xaritasidan) bunga kirmaydi: uning tez kassasi zal xaritasidagi «Tez kassa» tugmasi
  /// (3-bosqich oqimi o'zgarmaydi). Boshqa `hybrid` profillar (lag'monxona, kafe) — zal va tez kassa yonma-yon bo'limlar.
  bool get showsCounter {
    final PosFormat f = posFormat;
    if (!f.has(PosModule.quickCounter) || f.mode == PosMode.table) {
      return false;
    }
    return f.mode == PosMode.counter ||
        f.firstScreen == PosFirstScreen.quickCounter ||
        f.profile != 'choyxona';
  }

  /// Zal xaritasi ko'rinadimi: `counter` rejimida zal (stol, xona) bo'lmasa — yo'q (stolsiz buyurtmalar «Navbat» da).
  bool get showsFloor {
    final PosFormat f = posFormat;
    if (f.mode != PosMode.counter) return true;
    return (bootstrap?.tables ?? const <PosTable>[]).isNotEmpty;
  }

  /// Ish ekrani qaysi bo'limdan boshlanadi (`format.first_screen`; tez kassa yopiq bo'lsa — zal).
  bool get startsAtCounter =>
      showsCounter &&
      (posFormat.firstScreen == PosFirstScreen.quickCounter ||
          posFormat.mode == PosMode.counter ||
          !showsFloor);

  /// P6-12 (A5): telefon rejimi — ega o'zi kassir: smena bir bosishda, printer ixtiyoriy.
  bool get phoneMode => hasModule(PosModule.phoneMode);

  /// P6-13: kassa buyurtmalari navbat raqami oladi (modul va bootstrap `queue.numbers`).
  bool get queueNumbers => hasModule(PosModule.queueNumbers);

  /// P6-13: tayyor raqam chaqiriladi ("Tayyor" ro'yxati, ovoz).
  bool get queueCalling => hasModule(PosModule.queueCalling);
}

/// Tez tugmalar ro'yxati modul bo'yicha: `guest_items` (choy-non) o'chiq filialda choy-non qoidalari va nom bo'yicha
/// taxmin chiqmaydi — faqat menyudagi `quick_add` belgisi.
List<PosQuickItem> quickItemsFor(PosSession session) {
  final List<PosQuickItem> all =
      session.bootstrap?.quickItems ?? const <PosQuickItem>[];
  if (session.hasModule(PosModule.guestItems)) return all;
  return <PosQuickItem>[
    for (final PosQuickItem i in all)
      if (i.source != 'auto_guest') i,
  ];
}
