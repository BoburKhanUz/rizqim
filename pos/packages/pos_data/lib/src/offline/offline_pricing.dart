import 'package:pos_core/pos_core.dart';

import 'offline_settings.dart';

/// Internetsiz rejimdagi oddiy narx qoidalari (docs/POS_SPEC.md 5.2) — backend'dagi bilan bir xil hisob, `pos_core`
/// pulida (tiyin, half-up): qator narxi va qo'shimchalar, xizmat haqi, joy narxi, xona puli, minimal summa farqi.
/// Server sinxronlashda baribir o'zi hisoblaydi; farq bo'lsa amal "farq bilan" belgilanadi.
abstract final class OfflinePricing {
  /// `narx × miqdor + qo'shimchalar × (dona bo'lsa miqdor, vaznda 1)`, bir marta butun so'mga yaxlitlanadi
  /// (backend `OrderLineBuilder::lineTotal`).
  static PosMoney lineTotal(
    PosMoney unitPrice,
    PosQuantity quantity,
    PosMoney modifiersPerUnit,
    PosUnit unit,
  ) {
    final int numerator =
        unitPrice.tiyin * quantity.milli +
        modifiersPerUnit.tiyin *
            (unit.isMeasured ? PosQuantity.scaleFactor : quantity.milli);
    return PosMoney(
      divideHalfUp(numerator, PosQuantity.scaleFactor * 100) * 100,
    );
  }

  /// `summa × foiz / 100`, butun so'mga (backend `Money::percent($rate, 0)`).
  static PosMoney percentSom(PosMoney amount, String percent) {
    final int hundredths = percentHundredths(percent);
    if (hundredths == 0 || !amount.isPositive) return PosMoney.zero;
    return PosMoney(divideHalfUp(amount.tiyin * hundredths, 10000 * 100) * 100);
  }

  /// Joy narxi: kishi boshiga (kamida 1 mehmon) yoki stol uchun, butun so'mga.
  static PosMoney placeFee(PosMoney amount, String mode, int? guests) {
    if (!amount.isPositive) return PosMoney.zero;
    final int times = mode == 'per_table'
        ? 1
        : ((guests ?? 0) < 1 ? 1 : guests!);
    return (amount * times).roundToSom();
  }

  /// P6-22 (C1): buyurtma turi (backend `ServiceOrderType::of`): zal — `hall`; kassa buyurtmasi stol raqami bilan —
  /// `table_service`; boshqasi — `takeaway`.
  static String orderType(String channel, String? tableLabel) {
    if (channel == 'pos_hall') return 'hall';
    return tableLabel != null && tableLabel.trim().isNotEmpty
        ? 'table_service'
        : 'takeaway';
  }

  /// P6-22 (C1): [fee] qatorining xizmat haqi qoidalari shu buyurtma uchun (backend `ServiceChargeRules::percentFor`
  /// va `resolve`): foiz va qaysi qoida (`base`, `order_type`, `large_group`, `none`), joy narxi qatori (kuver bo'lsa —
  /// mehmon boshiga kuver). Qoidasiz qator — o'z foizi va joy narxi. [locked] — stolovaya qulfi: hamma foiz 0.
  static OfflineServiceCharge serviceCharge(
    PosOfflineFee fee, {
    required String orderType,
    int? guests,
    bool locked = false,
  }) {
    final PosServiceChargeRules? rules = fee.rules;
    if (rules == null || rules.isEmpty) {
      return OfflineServiceCharge(
        percent: fee.serviceChargePercent,
        rule: 'base',
        placeFeeAmount: fee.placeFeeAmount,
        placeFeeMode: fee.placeFeeMode,
      );
    }
    final bool dineIn = orderType != 'takeaway';
    var percent = '0.00';
    var rule = 'none';
    if (!locked) {
      final String? byType = rules.byOrderType[orderType];
      if (byType != null) {
        percent = byType;
        rule = 'order_type';
      } else {
        percent = fee.serviceChargePercent;
        rule = 'base';
      }
      final int? min = rules.largeGroupMinGuests;
      final String? group = rules.largeGroupPercent;
      if (dineIn && min != null && group != null && (guests ?? 0) >= min) {
        percent = group;
        rule = 'large_group';
      }
      if (percentHundredths(percent) <= 0) {
        percent = '0.00';
        rule = 'none';
      }
    }
    final PosMoney? cover = dineIn ? rules.coverAmount : null;
    return OfflineServiceCharge(
      percent: percent,
      rule: rule,
      placeFeeAmount: cover ?? fee.placeFeeAmount,
      placeFeeMode: cover != null ? 'per_person' : fee.placeFeeMode,
      cover: cover != null,
    );
  }

  /// Minimal summa farqi: `max(0, minimal − taomlar)`, butun so'mga.
  static PosMoney minSpendTopup(
    PosMoney minSpend,
    String mode,
    int? guests,
    PosMoney itemsAfterDiscount,
  ) {
    if (!minSpend.isPositive) return PosMoney.zero;
    final PosMoney min = mode == 'per_person'
        ? minSpend * ((guests ?? 0) < 1 ? 1 : guests!)
        : minSpend;
    final PosMoney gap = min - itemsAfterDiscount;
    return gap.isPositive ? gap.roundToSom() : PosMoney.zero;
  }

  /// Choy-non: mehmon boshiga miqdor × mehmonlar; donada yuqoriga butun songa.
  static PosQuantity guestQuantity(
    PosQuantity perGuest,
    int guests,
    PosUnit unit,
  ) {
    final int milli = perGuest.milli * guests;
    if (unit.isMeasured) return PosQuantity.milli(milli);
    final int whole =
        (milli + PosQuantity.scaleFactor - 1) ~/ PosQuantity.scaleFactor;
    return PosQuantity.whole(whole);
  }
}

/// [OfflinePricing.serviceCharge] natijasi: foiz, qoida va joy narxi qatori (kuver yoki qatorniki).
class OfflineServiceCharge {
  const OfflineServiceCharge({
    required this.percent,
    required this.rule,
    required this.placeFeeAmount,
    required this.placeFeeMode,
    this.cover = false,
  });

  final String percent;

  /// `base`, `order_type`, `large_group`, `none` (summa 0 bo'lsa `none` — buyurtmada).
  final String rule;
  final PosMoney placeFeeAmount;
  final String placeFeeMode;

  /// Joy narxi qatori restoran kuveri.
  final bool cover;
}
