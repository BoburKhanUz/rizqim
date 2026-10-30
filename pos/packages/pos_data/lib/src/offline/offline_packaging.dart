import 'package:pos_core/pos_core.dart';

import '../takeaway/takeaway_models.dart';

/// P6-34 (B8): internetsiz rejimda qadoq qatorlari — backend `PackagingRules::linesFor` bilan bir xil hisob:
///
/// * `order` qoidasi — buyurtmada kamida bitta tirik taom bo'lsa, `quantity` × 1;
/// * `item` qoidasi — o'z kategoriyasi yoki taomining porsiyalari × `quantity` (dona qator — miqdori yuqoriga butun
///   songa, kg/l qator — 1);
/// * summa = soni × narx; olib tashlangan qoida qatori ro'yxatda qoladi, summasi 0;
/// * faqat qoida aytgan buyurtma turida (`takeaway` — kassa olib ketishi, stol raqamisiz); zalda hech qachon.
///
/// Qadoq chegirmadan keyin qo'shiladi va chegirma olmaydi.
abstract final class OfflinePackaging {
  /// Buyurtmaning qadoq turi (backend `PackagingOrderType::ofOrder`): stol raqamisiz kassa olib ketishi — `takeaway`,
  /// boshqasi (zal, stolga olib kelish) — `null`.
  static String? orderType(String channel, String? tableLabel) {
    if (channel != 'pos_takeaway') return null;
    return tableLabel != null && tableLabel.trim().isNotEmpty
        ? null
        : 'takeaway';
  }

  /// Taom qatorining porsiyalari: dona — miqdori yuqoriga yaxlitlangan, tortiladigan — 1, nol — 0.
  static int portions(PosQuantity quantity, {required bool measured}) {
    if (quantity.milli <= 0) return 0;
    if (measured) return 1;
    return (quantity.milli + PosQuantity.scaleFactor - 1) ~/
        PosQuantity.scaleFactor;
  }

  /// Qoidalar bo'yicha qadoq qatorlari ([removed] — olib tashlangan qoida id'lari va sababi).
  static List<PosPackagingLine> lines(
    List<PosPackagingRule> rules,
    String? orderType,
    List<OfflineDish> dishes, {
    Map<int, String> removed = const <int, String>{},
  }) {
    if (orderType == null || dishes.isEmpty) return const <PosPackagingLine>[];
    final List<PosPackagingRule> ordered =
        rules.where((PosPackagingRule r) => r.isActive).toList()..sort(
          (PosPackagingRule a, PosPackagingRule b) => a.sortOrder != b.sortOrder
              ? a.sortOrder.compareTo(b.sortOrder)
              : a.id.compareTo(b.id),
        );
    final List<PosPackagingLine> lines = <PosPackagingLine>[];
    for (final PosPackagingRule rule in ordered) {
      if (!rule.orderTypes.contains(orderType)) continue;
      var count = rule.perOrder ? 1 : 0;
      if (!rule.perOrder) {
        for (final OfflineDish dish in dishes) {
          final bool matches = rule.productId != null
              ? dish.productId == rule.productId
              : rule.categoryId != null && dish.categoryId == rule.categoryId;
          if (matches) {
            count += portions(dish.quantity, measured: dish.measured);
          }
        }
      }
      if (count < 1) continue;
      final int quantity = count * (rule.quantity < 1 ? 1 : rule.quantity);
      final bool off = removed.containsKey(rule.id);
      lines.add(
        PosPackagingLine(
          ruleId: rule.id,
          name: rule.name,
          basis: rule.basis,
          quantity: quantity,
          unit: rule.unit,
          unitPrice: rule.unitPrice,
          amount: off ? PosMoney.zero : rule.unitPrice * quantity,
          mxik: rule.mxik,
          packageCode: rule.packageCode,
          removed: off,
          removedReason: off ? removed[rule.id] : null,
        ),
      );
    }
    return lines;
  }

  /// Olib tashlanmagan qatorlar jami.
  static PosMoney total(List<PosPackagingLine> lines) => PosMoney.sum(
    <PosMoney>[for (final PosPackagingLine l in lines) l.amount],
  );
}

/// Qadoq hisobi uchun buyurtmadagi taom qatori: mahsulot, kategoriya, miqdor, tortiladimi.
class OfflineDish {
  const OfflineDish({
    required this.quantity,
    this.productId,
    this.categoryId,
    this.measured = false,
  });

  final int? productId;
  final int? categoryId;
  final PosQuantity quantity;
  final bool measured;
}
