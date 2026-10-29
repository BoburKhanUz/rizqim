import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';

import '../features/billing/cashier_ui.dart';
import '../shell/pos_scope.dart';

/// P6-15: bugungi menyu, keyingi partiya va kompleks kassa menyusida qanday ko'rinadi. Hammasi modulga bog'liq
/// (`daily_menu`, `batches`, `combo_meal`): modul o'chiq bo'lsa taom odatdagidek ko'rinadi. Sotuv hech qachon bunga
/// bog'liq emas: server menyusi belgilanmagan kunda hamma narsani sotadi, kassa faqat ogohlantiradi.

/// Terminaldagi samarali modullar (xodim kirmagan yoki bootstrap yo'q — eski server: [PosModules.legacy]).
PosModules menuModulesOf(BuildContext context) =>
    PosScope.maybeOf(context)?.session.bootstrap?.modules ?? PosModules.legacy;

/// Taom tugmasida ko'rinish: menyuda yo'q, "keyingi partiya", kompleks tarkibi.
class MenuDayItem {
  const MenuDayItem({
    this.offMenu = false,
    this.nextBatch,
    this.parts = const <PosSetPart>[],
  });

  /// Kunlik taom bugungi menyuda emas ("Bugungi menyuda yo'q").
  final bool offMenu;

  /// Tugagan taomning "keyingi partiya 12:30"ligi (faqat `batches` moduli yoqilganda).
  final PosNextBatch? nextBatch;

  /// Kompleks tarkibi (faqat `combo_meal` moduli yoqilganda).
  final List<PosSetPart> parts;

  bool get isSet => parts.isNotEmpty;

  /// Taom tugmasi ostidagi qisqa tarkib: "Mastava · Osh · Salat".
  String? get partsLine =>
      isSet ? parts.map((PosSetPart p) => p.display).join(' · ') : null;

  /// Tugmadagi izoh matni sotuvda bo'lmagan taom uchun ([fallback] — odatdagi "Tugadi" / "Stop-listda").
  String unavailableLabel(String fallback) =>
      offMenu ? posOffMenuReason : fallback;

  static const MenuDayItem none = MenuDayItem();
}

/// [product] bugun kassada qanday ko'rinadi. Modul o'chiq bo'lsa — [MenuDayItem.none].
MenuDayItem menuDayItem(
  PosCatalog catalog,
  PosProduct product,
  PosModules modules,
) {
  final bool offMenu =
      modules.has(PosModule.dailyMenu) && catalog.isOffMenu(product);
  return MenuDayItem(
    offMenu: offMenu,
    nextBatch: modules.has(PosModule.batches)
        ? catalog.nextBatchOf(product)
        : null,
    parts: modules.has(PosModule.comboMeal)
        ? (product.setParts ?? const <PosSetPart>[])
        : const <PosSetPart>[],
  );
}

/// Bugungi menyuda yo'q kunlik taomlar ro'yxatdan yashiriladi (qidiruvda esa xira ko'rinadi: kassir nega yo'qligini
/// ko'radi). [searching] — qidiruv yozilgan.
List<PosProduct> visibleProducts(
  List<PosProduct> products,
  PosCatalog catalog,
  PosModules modules, {
  required bool searching,
}) {
  if (searching || !modules.has(PosModule.dailyMenu)) return products;
  return <PosProduct>[
    for (final PosProduct p in products)
      if (!catalog.isOffMenu(p)) p,
  ];
}

/// Bugungi menyu qo'yilmagan kunda ogohlantirish: hamma taom sotiladi, lekin ega menyuni qo'yishi kerak. Menyu
/// bor, modul o'chiq yoki eski server — hech narsa ko'rsatilmaydi.
class DailyMenuMissingBanner extends StatelessWidget {
  const DailyMenuMissingBanner({super.key, this.compact = false});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    final PosBootstrap? boot = PosScope.maybeOf(context)?.session.bootstrap;
    final PosDailyMenu? menu = boot?.dailyMenu;
    if (boot == null ||
        menu == null ||
        !menu.missing ||
        !boot.modules.has(PosModule.dailyMenu)) {
      return const SizedBox.shrink();
    }
    return Padding(
      padding: EdgeInsets.fromLTRB(12, 0, 12, compact ? 4 : 8),
      child: const NoticeBanner(
        key: ValueKey<String>('daily-menu-missing'),
        message: "Bugungi menyu belgilanmagan: hamma taom sotiladi. Menyuni hamkor panelidagi \"Bugungi menyu\" sahifasida qo'ying.",
        tone: StatusTone.warning,
        icon: Icons.restaurant_menu_rounded,
      ),
    );
  }
}

/// Kompleks qatoridagi tarkib: har bir taom alohida satrda (buyurtma qatorida va schyotda).
List<String> setPartLines(List<PosSetPart>? parts) => <String>[
  if (parts != null)
    for (final PosSetPart p in parts) p.display,
];
