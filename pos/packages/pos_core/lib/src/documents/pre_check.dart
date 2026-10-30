import '../money.dart';
import 'common.dart';

/// Schyotdagi qo'shimcha qator turi. Har biri taomlardan alohida qatorda chiqadi (research R3, R4, R6).
enum BillChargeKind {
  roomFee('Xona puli'),
  placeFee('Joy puli'),
  serviceCharge('Xizmat haqi'),
  minSpendTopUp("Minimal summaga to'ldirish"),
  discount('Chegirma');

  const BillChargeKind(this.label);

  final String label;
}

/// Schyotdagi qo'shimcha qator: xona puli, xizmat haqi, minimal summaga to'ldirish, chegirma (manfiy).
final class BillCharge {
  const BillCharge(
    this.kind,
    this.amount, {
    this.percent,
    this.label,
    this.note,
  });

  final BillChargeKind kind;

  /// Chegirma uchun manfiy.
  final PosMoney amount;

  /// Xizmat haqi yoki chegirma foizi (`"10"`).
  final String? percent;

  /// Turdan boshqa nom kerak bo'lsa ("Xona puli (dam olish kuni)").
  final String? label;

  /// Qator ostidagi izoh (P6-22: xizmat haqi e'loni, "Xizmat haqi: zalda 15%...").
  final String? note;

  String get title {
    final String base = label ?? kind.label;
    return percent == null ? base : '$base ${formatPercent(percent!)}%';
  }
}

/// Schyotdagi taom qatori.
final class PreCheckItem {
  const PreCheckItem({
    required this.name,
    required this.quantity,
    required this.unitPrice,
    required this.amount,
    this.modifiers = const <String>[],
  });

  final String name;
  final ItemQuantity quantity;
  final PosMoney unitPrice;

  /// Qator jami (modifikatorlar bilan).
  final PosMoney amount;

  /// "Qazi", "Bedana tuxumi x2".
  final List<String> modifiers;
}

/// Schyot (pre-check): mehmonga to'lovdan oldin beriladi, **fiskal chek emas**. Qayta chop etilsa "NUSXA".
final class PreCheck {
  const PreCheck({
    required this.header,
    required this.printedAt,
    required this.items,
    this.tableLabel,
    this.waiter,
    this.guests,
    this.openedAt,
    this.orderCode,
    this.billLabel,
    this.charges = const <BillCharge>[],
    this.prepaid = PosMoney.zero,
    this.splitCount,
    this.isCopy = false,
  });

  final ReceiptHeader header;
  final DateTime printedAt;
  final List<PreCheckItem> items;
  final String? tableLabel;
  final String? waiter;
  final int? guests;
  final DateTime? openedAt;
  final String? orderCode;

  /// Bo'lingan schyot: "Schyot 2/3".
  final String? billLabel;
  final List<BillCharge> charges;

  /// Avans (bron bo'nagi) — to'lanadigan summadan ayiriladi.
  final PosMoney prepaid;

  /// Kishi boshiga summa uchun ulushlar soni; `null` bo'lsa [guests] (1 dan katta bo'lsa).
  final int? splitCount;
  final bool isCopy;

  PosMoney get itemsTotal =>
      PosMoney.sum(items.map((PreCheckItem i) => i.amount));

  PosMoney get total =>
      itemsTotal + PosMoney.sum(charges.map((BillCharge c) => c.amount));

  /// To'lanadigan summa (avansdan keyin, manfiy bo'lmaydi).
  PosMoney get toPay {
    final PosMoney rest = total - prepaid;
    return rest.isNegative ? PosMoney.zero : rest;
  }

  /// Kishi boshiga necha ulush ([splitCount] yoki mehmonlar soni); 1 yoki noma'lum — `null`.
  int? get shares {
    final int? n = splitCount ?? guests;
    return (n != null && n > 1) ? n : null;
  }

  /// Bir kishiga (butun so'mda, eng katta ulush): to'lanadigan summa teng bo'linadi.
  PosMoney? get perPerson {
    final int? n = shares;
    if (n == null) return null;
    return toPay.splitEqually(n).first;
  }
}
