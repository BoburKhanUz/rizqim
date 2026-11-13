import 'package:pos_core/pos_core.dart';

import '../json.dart';
import '../shift/shift_models.dart';

/// P6-43: sertifikat holati (`status`): `active` — to'lay oladi; `used` — qoldiq qolmagan; `blocked` — menejer
/// to'xtatgan; `expired` — oxirgi biznes kuni o'tgan.
enum PosVoucherStatus {
  active('active', 'Faol'),
  used('used', 'Ishlatilgan'),
  blocked('blocked', 'Bloklangan'),
  expired('expired', "Muddati o'tgan"),
  unknown('', "Noma'lum");

  const PosVoucherStatus(this.wire, this.label);

  final String wire;
  final String label;

  static PosVoucherStatus fromWire(String? wire) {
    for (final PosVoucherStatus s in values) {
      if (s.wire == wire && s != unknown) return s;
    }
    return unknown;
  }

  /// Schyotni to'lay oladimi.
  bool get canPay => this == active;
}

/// Sertifikat varag'ida chop etiladigani (`print`): do'kon, kod, QR matni (kodning o'zi), qiymat, muddat.
class PosVoucherPrint {
  const PosVoucherPrint({
    required this.store,
    required this.code,
    required this.qr,
    required this.value,
    required this.validFrom,
    this.validUntil,
  });

  factory PosVoucherPrint.fromJson(PosJson j) => PosVoucherPrint(
    store: j.strOrNull('store'),
    code: j.str('code'),
    qr: j.str('qr'),
    value: j.money('value'),
    validFrom: j.str('valid_from'),
    validUntil: j.strOrNull('valid_until'),
  );

  final String? store;
  final String code;
  final String qr;
  final PosMoney value;

  /// Biznes kun `YYYY-MM-DD`.
  final String validFrom;
  final String? validUntil;
}

/// `POST /pos/vouchers/sell` va `GET /pos/vouchers/{code}` dagi sovg'a sertifikati. Xaridor telefoni niqoblangan.
class PosVoucher {
  const PosVoucher({
    required this.uuid,
    required this.code,
    required this.faceValue,
    required this.balance,
    required this.validFrom,
    required this.status,
    required this.saleTender,
    required this.print,
    this.validUntil,
    this.buyerName,
    this.buyerPhone,
    this.fiscalStatus,
    this.fiscalReceiptUuid,
    this.blockReason,
  });

  factory PosVoucher.fromJson(PosJson j) {
    final PosJson? buyer = j.objOrNull('buyer');
    final PosJson? fiscal = j.objOrNull('fiscal');
    return PosVoucher(
      uuid: j.str('uuid'),
      code: j.str('code'),
      faceValue: j.money('face_value'),
      balance: j.money('balance'),
      validFrom: j.str('valid_from'),
      validUntil: j.strOrNull('valid_until'),
      status: PosVoucherStatus.fromWire(j.strOrNull('status')),
      saleTender:
          PosTender.fromWire(j.objOrNull('sale')?.strOrNull('tender')) ??
          PosTender.cash,
      buyerName: buyer?.strOrNull('name'),
      buyerPhone: buyer?.strOrNull('phone'),
      fiscalStatus: fiscal?.strOrNull('status'),
      fiscalReceiptUuid: fiscal?.strOrNull('receipt_uuid'),
      blockReason: j.objOrNull('blocked')?.strOrNull('reason'),
      print: PosVoucherPrint.fromJson(j.obj('print')),
    );
  }

  final String uuid;

  /// `XXXX-XXXX-XXXX` (QR — shu matn).
  final String code;
  final PosMoney faceValue;
  final PosMoney balance;
  final String validFrom;

  /// Oxirgi biznes kun (`null` — muddatsiz).
  final String? validUntil;
  final PosVoucherStatus status;

  /// Sotilganda olingan to'lov turi (naqd, karta, QR).
  final PosTender saleTender;
  final String? buyerName;

  /// Niqoblangan (`+99890*****67`).
  final String? buyerPhone;

  /// Bo'nak chekining holati (`issued`, `pending`, `error`).
  final String? fiscalStatus;
  final String? fiscalReceiptUuid;
  final String? blockReason;
  final PosVoucherPrint print;
}

/// Sertifikat tarixining bir qatori (`sale`, `redemption`, `refund`, `block`, `unblock`).
class PosVoucherEntry {
  const PosVoucherEntry({
    required this.uuid,
    required this.type,
    required this.label,
    required this.amount,
    required this.balanceAfter,
    this.billUuid,
    this.businessDate,
  });

  factory PosVoucherEntry.fromJson(PosJson j) => PosVoucherEntry(
    uuid: j.str('uuid'),
    type: j.str('type'),
    label: j.strOrNull('type_label') ?? j.str('type'),
    amount: j.money('amount'),
    balanceAfter: j.money('balance_after'),
    billUuid: j.strOrNull('bill_uuid'),
    businessDate: j.strOrNull('business_date'),
  );

  final String uuid;
  final String type;
  final String label;
  final PosMoney amount;
  final PosMoney balanceAfter;
  final String? billUuid;
  final String? businessDate;
}

/// `GET /pos/vouchers/{code}`: sertifikat va oxirgi 10 harakati.
class PosVoucherLookup {
  const PosVoucherLookup(this.voucher, this.entries);

  factory PosVoucherLookup.fromJson(PosJson j) => PosVoucherLookup(
    PosVoucher.fromJson(j.obj('voucher')),
    j.objects('entries', PosVoucherEntry.fromJson),
  );

  final PosVoucher voucher;
  final List<PosVoucherEntry> entries;
}

/// Kassada sertifikat sotish (`POST /pos/vouchers/sell`). [tender] — naqd, karta ([cardType] va [transactionId]
/// majburiy) yoki QR ([reference] ixtiyoriy). [validUntil] — oxirgi biznes kun (`YYYY-MM-DD`; sukut — 1 yil).
class PosVoucherSale {
  const PosVoucherSale({
    required this.faceValue,
    required this.tender,
    this.cardType,
    this.transactionId,
    this.reference,
    this.buyerName,
    this.buyerPhone,
    this.validUntil,
    this.note,
  });

  final PosMoney faceValue;
  final PosTender tender;
  final String? cardType;
  final String? transactionId;
  final String? reference;
  final String? buyerName;
  final String? buyerPhone;
  final String? validUntil;
  final String? note;

  Map<String, Object?> toJson() => <String, Object?>{
    'face_value': faceValue.toApiString(),
    'tender': tender.wire,
    'card_type': ?cardType,
    'transaction_id': ?transactionId,
    'reference': ?reference,
    'buyer_name': ?buyerName,
    'buyer_phone': ?buyerPhone,
    'valid_until': ?validUntil,
    'note': ?note,
  };
}

/// X/Z dagi `vouchers` qismi (P6-43): smenada sotilgan sertifikatlar (bo'nak — sotuvga kirmaydi; naqdi kutilgan naqdga
/// qo'shiladi) va schyotlarda ishlatilganlari (sotuv chekining "oldindan to'langan" qismi).
class PosShiftVouchers {
  const PosShiftVouchers({
    required this.soldCount,
    required this.soldAmount,
    required this.soldCash,
    required this.soldCard,
    required this.soldQr,
    required this.redeemedCount,
    required this.redeemedAmount,
    required this.drawerCash,
  });

  static const PosShiftVouchers none = PosShiftVouchers(
    soldCount: 0,
    soldAmount: PosMoney.zero,
    soldCash: PosMoney.zero,
    soldCard: PosMoney.zero,
    soldQr: PosMoney.zero,
    redeemedCount: 0,
    redeemedAmount: PosMoney.zero,
    drawerCash: PosMoney.zero,
  );

  final int soldCount;
  final PosMoney soldAmount;
  final PosMoney soldCash;
  final PosMoney soldCard;
  final PosMoney soldQr;
  final int redeemedCount;
  final PosMoney redeemedAmount;

  /// `cash.vouchers`: kassadagi naqd sertifikat puli.
  final PosMoney drawerCash;

  bool get isEmpty => soldCount == 0 && redeemedCount == 0;
}

/// X/Z ning `vouchers` qismi (eski server: [PosShiftVouchers.none]).
extension PosShiftReportVouchers on PosShiftReport {
  PosShiftVouchers get vouchers {
    final Object? block = raw['vouchers'];
    if (block is! Map) return PosShiftVouchers.none;
    final PosJson v = PosJson(block.cast<String, Object?>());
    final PosJson sold =
        v.objOrNull('sold') ?? const PosJson(<String, Object?>{});
    final PosJson redeemed =
        v.objOrNull('redeemed') ?? const PosJson(<String, Object?>{});
    final Object? cash = raw['cash'];
    return PosShiftVouchers(
      soldCount: sold.intOrNull('count') ?? 0,
      soldAmount: sold.money('amount'),
      soldCash: sold.money('cash'),
      soldCard: sold.money('card'),
      soldQr: sold.money('qr'),
      redeemedCount: redeemed.intOrNull('count') ?? 0,
      redeemedAmount: redeemed.money('amount'),
      drawerCash: cash is Map
          ? PosJson(cash.cast<String, Object?>()).money('vouchers')
          : PosMoney.zero,
    );
  }
}
