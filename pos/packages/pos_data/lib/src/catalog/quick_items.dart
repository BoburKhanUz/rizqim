import '../bootstrap/bootstrap_models.dart';
import 'catalog_models.dart';

/// Kassa va ofitsiant telefonidagi "tez qo'shish" tugmalari (choy, non, ...).
///
/// P3-26: ro'yxat serverdan — bootstrap'dagi [PosQuickItem] lar (menyuda `quick_add` belgilangan taomlar va filialning
/// choy-non qatorlari), menyudagi taomga [PosQuickItem.productId] bo'yicha bog'lanadi; sotilmayotganlari tushadi.
/// [hallId] berilsa boshqa zalning choy-non qatori ko'rsatilmaydi. Ro'yxat bo'sh bo'lsa (eski server yoki sozlanmagan
/// menyu) [fallback] bo'yicha taxmin qilinadi (nom: "choy", "non").
List<PosProduct> quickProducts(
  PosCatalog catalog,
  List<PosQuickItem> items, {
  int? hallId,
  int limit = 6,
  bool Function(PosProduct product)? fallback,
}) {
  final List<PosProduct> out = <PosProduct>[];
  final Set<int> seen = <int>{};
  if (items.isNotEmpty) {
    for (final PosQuickItem item in items) {
      if (item.hallId != null && hallId != null && item.hallId != hallId) {
        continue;
      }
      final PosProduct? p = catalog.product(item.productId);
      if (p == null || !seen.add(p.id) || !catalog.isProductAvailable(p)) {
        continue;
      }
      out.add(p);
      if (out.length >= limit) break;
    }
    return out;
  }
  final bool Function(PosProduct product) guess =
      fallback ?? looksLikeTeaOrBread;
  for (final PosProduct p in catalog.products) {
    if (guess(p) && catalog.isProductAvailable(p) && seen.add(p.id)) {
      out.add(p);
      if (out.length >= limit) break;
    }
  }
  return out;
}

/// Tez tugma bosilganda qo'shiladigan variant: serverdagi tugmaning varianti, bo'lmasa taomning yagona varianti.
PosVariant? quickVariantOf(PosProduct product, List<PosQuickItem> items) {
  for (final PosQuickItem item in items) {
    if (item.productId != product.id) continue;
    for (final PosVariant v in product.variants) {
      if (v.uuid == item.variantUuid) return v;
    }
  }
  return product.singleVariant;
}

/// Nom bo'yicha taxmin (server ro'yxati bo'lmasa): "choy", "non" so'zi bor, bitta donali variantli, majburiy
/// qo'shimchasiz taom.
bool looksLikeTeaOrBread(PosProduct p) {
  final PosVariant? v = p.singleVariant;
  return v != null &&
      !v.unit.isMeasured &&
      !p.modifierGroups.any(
        (PosModifierGroup g) => g.isRequired || g.minSelect > 0,
      ) &&
      RegExp(r'\b(choy|non)\b', caseSensitive: false).hasMatch(p.name);
}
