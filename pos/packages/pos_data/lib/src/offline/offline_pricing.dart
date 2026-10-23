import 'package:pos_core/pos_core.dart';

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
