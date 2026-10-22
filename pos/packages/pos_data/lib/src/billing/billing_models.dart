import 'package:pos_core/pos_core.dart';

import '../json.dart';

/// Schyot endpointlaridagi buyurtma hisob holati.
class PosBillingOrder {
  const PosBillingOrder({
    required this.uuid,
    required this.status,
    required this.version,
    this.paymentStatus,
    this.queueNumber,
    this.businessDate,
    this.billLockedAt,
    this.closedAt,
    this.totalPrice = PosMoney.zero,
    this.billedAmount = PosMoney.zero,
    this.paidAmount = PosMoney.zero,
    this.unbilledAmount = PosMoney.zero,
    this.fiscalPending = false,
    this.billsOutOfDate = false,
  });

  factory PosBillingOrder.fromJson(PosJson j) => PosBillingOrder(
    uuid: j.str('uuid'),
    status: j.str('status'),
    paymentStatus: j.strOrNull('payment_status'),
    version: j.intOrNull('version') ?? 0,
    queueNumber: j.intOrNull('queue_number'),
    businessDate: j.strOrNull('business_date'),
    billLockedAt: j.dateOrNull('bill_locked_at'),
    closedAt: j.dateOrNull('closed_at'),
    totalPrice: j.money('total_price'),
    billedAmount: j.money('billed_amount'),
    paidAmount: j.money('paid_amount'),
    unbilledAmount: j.money('unbilled_amount'),
    fiscalPending: j.boolean('fiscal_pending'),
    billsOutOfDate: j.boolean('bills_out_of_date'),
  );

  final String uuid;
  final String status;
  final String? paymentStatus;
  final int version;
  final int? queueNumber;
  final String? businessDate;
  final DateTime? billLockedAt;
  final DateTime? closedAt;
  final PosMoney totalPrice;
  final PosMoney billedAmount;
  final PosMoney paidAmount;
  final PosMoney unbilledAmount;

  /// Schyot to'langan, sotuv cheki kutilmoqda.
  final bool fiscalPending;

  /// Ochiq schyotdan keyin buyurtma o'zgargan: schyotni qayta tuzing.
  final bool billsOutOfDate;

  bool get isClosed => closedAt != null;
}

/// Schyot (pre-check) va uning to'lovlari.
class PosBill {
  const PosBill({
    required this.uuid,
    required this.orderUuid,
    required this.number,
    required this.status,
    this.splitMode,
    this.subtotal = PosMoney.zero,
    this.discount = PosMoney.zero,
    this.roomFeeAmount = PosMoney.zero,
    this.minSpendTopupAmount = PosMoney.zero,
    this.serviceChargeAmount = PosMoney.zero,
    this.placeFeeAmount = PosMoney.zero,
    this.total = PosMoney.zero,
    this.paidAmount = PosMoney.zero,
    this.dueAmount = PosMoney.zero,
    this.guestsShare,
    this.perGuestAmount,
    this.printedCount = 0,
    this.printedAt,
    this.paidAt,
    this.fiscal = const PosFiscalState(),
    this.items = const <PosBillItem>[],
    this.payments = const <PosPayment>[],
    this.createdAt,
    this.raw = const <String, Object?>{},
  });

  factory PosBill.fromJson(PosJson j) => PosBill(
    uuid: j.str('uuid'),
    orderUuid: j.strOrNull('order_uuid') ?? '',
    number: j.intOrNull('number') ?? 1,
    status: j.str('status'),
    splitMode: j.strOrNull('split_mode'),
    subtotal: j.money('subtotal'),
    discount: j.money('discount'),
    roomFeeAmount: j.money('room_fee_amount'),
    minSpendTopupAmount: j.money('min_spend_topup_amount'),
    serviceChargeAmount: j.money('service_charge_amount'),
    placeFeeAmount: j.money('place_fee_amount'),
    total: j.money('total'),
    paidAmount: j.money('paid_amount'),
    dueAmount: j.money('due_amount'),
    guestsShare: j.intOrNull('guests_share'),
    perGuestAmount: j.moneyOrNull('per_guest_amount'),
    printedCount: j.intOrNull('printed_count') ?? 0,
    printedAt: j.dateOrNull('printed_at'),
    paidAt: j.dateOrNull('paid_at'),
    fiscal: j.has('fiscal')
        ? PosFiscalState.fromJson(j.obj('fiscal'))
        : const PosFiscalState(),
    items: j.objects('items', PosBillItem.fromJson),
    payments: j.objects('payments', PosPayment.fromJson),
    createdAt: j.dateOrNull('created_at'),
    raw: j.map,
  );

  final String uuid;
  final String orderUuid;
  final int number;

  /// `open`, `printed`, `paying`, `paid`, `void`.
  final String status;

  /// `single`, `equal`, `items`.
  final String? splitMode;
  final PosMoney subtotal;
  final PosMoney discount;
  final PosMoney roomFeeAmount;
  final PosMoney minSpendTopupAmount;
  final PosMoney serviceChargeAmount;
  final PosMoney placeFeeAmount;
  final PosMoney total;
  final PosMoney paidAmount;
  final PosMoney dueAmount;
  final int? guestsShare;

  /// "Kishi boshiga" (butun so'mga yuqoriga yaxlitlangan).
  final PosMoney? perGuestAmount;
  final int printedCount;
  final DateTime? printedAt;
  final DateTime? paidAt;
  final PosFiscalState fiscal;
  final List<PosBillItem> items;
  final List<PosPayment> payments;
  final DateTime? createdAt;
  final Map<String, Object?> raw;

  bool get isPaid => status == 'paid';
}

/// Schyot yoki qaytarishning fiskal cheki holati: `pending`, `issued`, `failed` (`null` — hali so'ralmagan).
class PosFiscalState {
  const PosFiscalState({this.status, this.receiptUuid, this.error});

  factory PosFiscalState.fromJson(PosJson j) => PosFiscalState(
    status: j.strOrNull('status'),
    receiptUuid: j.strOrNull('receipt_uuid'),
    error: j.strOrNull('error'),
  );

  final String? status;
  final String? receiptUuid;
  final String? error;

  bool get isPending => status == 'pending' || status == 'failed';
}

class PosBillItem {
  const PosBillItem({
    required this.orderItemId,
    required this.productName,
    required this.quantity,
    this.variantName,
    this.unit = PosUnit.piece,
    this.amount = PosMoney.zero,
    this.discountAmount = PosMoney.zero,
  });

  factory PosBillItem.fromJson(PosJson j) => PosBillItem(
    orderItemId: j.integer('order_item_id'),
    productName: j.str('product_name'),
    variantName: j.strOrNull('variant_name'),
    unit: PosUnit.fromWire(j.strOrNull('unit')),
    quantity: j.quantity('quantity'),
    amount: j.money('amount'),
    discountAmount: j.money('discount_amount'),
  );

  final int orderItemId;
  final String productName;
  final String? variantName;
  final PosUnit unit;
  final PosQuantity quantity;
  final PosMoney amount;
  final PosMoney discountAmount;
}

class PosPayment {
  const PosPayment({
    required this.uuid,
    required this.tender,
    required this.amount,
    this.tenderedAmount,
    this.changeAmount,
    this.cardType,
    this.transactionId,
    this.reference,
    this.status,
    this.paidAt,
  });

  factory PosPayment.fromJson(PosJson j) => PosPayment(
    uuid: j.str('uuid'),
    tender: j.str('tender'),
    amount: j.money('amount'),
    tenderedAmount: j.moneyOrNull('tendered_amount'),
    changeAmount: j.moneyOrNull('change_amount'),
    cardType: j.strOrNull('card_type'),
    transactionId: j.strOrNull('transaction_id'),
    reference: j.strOrNull('reference'),
    status: j.strOrNull('status'),
    paidAt: j.dateOrNull('paid_at'),
  );

  final String uuid;

  /// `cash`, `card`, `qr`, `prepayment`, `account`.
  final String tender;
  final PosMoney amount;
  final PosMoney? tenderedAmount;

  /// [tender] `pos_core` turida (noma'lum — `null`).
  PosTender? get tenderKind => PosTender.fromWire(tender);

  /// Qaytim.
  final PosMoney? changeAmount;
  final String? cardType;
  final String? transactionId;
  final String? reference;
  final String? status;
  final DateTime? paidAt;
}

/// Smena ichida qaytarilgan pul (`POST /pos/refunds` javobi, schyotlar ro'yxatidagi `refunds`).
class PosRefund {
  const PosRefund({
    required this.uuid,
    required this.amount,
    this.orderUuid,
    this.billUuid,
    this.paymentUuid,
    this.method,
    this.reason,
    this.approvedBy,
    this.fiscal = const PosFiscalState(),
    this.createdAt,
  });

  factory PosRefund.fromJson(PosJson j) => PosRefund(
    uuid: j.str('uuid'),
    orderUuid: j.strOrNull('order_uuid'),
    billUuid: j.strOrNull('bill_uuid'),
    paymentUuid: j.strOrNull('payment_uuid'),
    amount: j.money('amount'),
    method: j.strOrNull('method'),
    reason: j.strOrNull('reason'),
    approvedBy: PosRef.maybe(j, 'approved_by'),
    fiscal: j.has('fiscal')
        ? PosFiscalState.fromJson(j.obj('fiscal'))
        : const PosFiscalState(),
    createdAt: j.dateOrNull('created_at'),
  );

  final String uuid;
  final String? orderUuid;
  final String? billUuid;
  final String? paymentUuid;
  final PosMoney amount;
  final String? method;
  final String? reason;
  final PosRef? approvedBy;
  final PosFiscalState fiscal;
  final DateTime? createdAt;
}

/// `GET|POST /pos/orders/{uuid}/bills`: hisob holati, schyotlar va qaytarishlar.
class PosBilling {
  const PosBilling({
    required this.order,
    required this.bills,
    this.refunds = const <PosRefund>[],
  });

  factory PosBilling.fromJson(PosJson j) => PosBilling(
    order: PosBillingOrder.fromJson(j.obj('order')),
    bills: j.objects('bills', PosBill.fromJson),
    refunds: j.objects('refunds', PosRefund.fromJson),
  );

  final PosBillingOrder order;
  final List<PosBill> bills;
  final List<PosRefund> refunds;
}

/// Bitta schyot ustidagi amal natijasi (chiqarish, to'lov, fiskal qayta urinish).
class PosBillResult {
  const PosBillResult({
    required this.bill,
    required this.order,
    this.copy = false,
  });

  factory PosBillResult.fromJson(PosJson j) => PosBillResult(
    bill: PosBill.fromJson(j.obj('bill')),
    order: PosBillingOrder.fromJson(j.obj('order')),
    copy: j.boolean('copy'),
  );

  final PosBill bill;
  final PosBillingOrder order;

  /// Qayta chiqarildi ("NUSXA").
  final bool copy;
}

/// Schyotni qanday tuzish (`mode`).
sealed class PosSplit {
  const PosSplit();

  /// Bitta schyot.
  const factory PosSplit.single() = _SingleSplit;

  /// [parts] (2–30) teng summali schyot; qoldiq tiyin birinchisiga.
  const factory PosSplit.equal(int parts) = _EqualSplit;

  /// Taom bo'yicha: har schyotga qatorlar va miqdori (kasr ham).
  const factory PosSplit.items(List<PosSplitBill> bills) = _ItemsSplit;

  Map<String, Object?> toJson();
}

class _SingleSplit extends PosSplit {
  const _SingleSplit();

  @override
  Map<String, Object?> toJson() => <String, Object?>{'mode': 'single'};
}

class _EqualSplit extends PosSplit {
  const _EqualSplit(this.parts);

  final int parts;

  @override
  Map<String, Object?> toJson() => <String, Object?>{
    'mode': 'equal',
    'parts': parts,
  };
}

class _ItemsSplit extends PosSplit {
  const _ItemsSplit(this.bills);

  final List<PosSplitBill> bills;

  @override
  Map<String, Object?> toJson() => <String, Object?>{
    'mode': 'items',
    'bills': <Map<String, Object?>>[
      for (final PosSplitBill b in bills) b.toJson(),
    ],
  };
}

class PosSplitBill {
  const PosSplitBill({required this.items, this.guests});

  /// Buyurtma qatori id'si → miqdor.
  final Map<int, PosQuantity> items;
  final int? guests;

  Map<String, Object?> toJson() => <String, Object?>{
    'items': <Map<String, Object?>>[
      for (final MapEntry<int, PosQuantity> e in items.entries)
        <String, Object?>{
          'order_item_id': e.key,
          'quantity': e.value.toApiString(),
        },
    ],
    'guests': ?guests,
  };
}

/// To'lov turi bilan summa (`tenders[]`). Tur — `pos_core` [PosTender].
sealed class PosTenderInput {
  const PosTenderInput(this.tender, this.amount);

  final PosTender tender;
  final PosMoney amount;

  /// Naqd; [tendered] — mijoz bergan pul (qaytim server hisoblaydi).
  const factory PosTenderInput.cash(PosMoney amount, {PosMoney? tendered}) =
      _Cash;

  /// Karta (alohida bank terminali): [cardType] (`uzcard`, `humo`, `visa`, `mastercard`, `unionpay`, `mir`,
  /// `other`) va [transactionId] majburiy.
  const factory PosTenderInput.card(
    PosMoney amount, {
    required String cardType,
    required String transactionId,
  }) = _Card;

  const factory PosTenderInput.qr(PosMoney amount, {String? reference}) = _Qr;

  /// Bron avansidan.
  const factory PosTenderInput.prepayment(PosMoney amount) = _Prepayment;

  /// Nasiyaga ([accountUuid] — mijoz hisobi; menejer tasdig'i kerak bo'lishi mumkin).
  const factory PosTenderInput.account(
    PosMoney amount, {
    required String accountUuid,
  }) = _Account;

  Map<String, Object?> toJson() => <String, Object?>{
    'tender': tender.wire,
    'amount': amount.toApiString(),
    ..._extra(),
  };

  Map<String, Object?> _extra();
}

class _Cash extends PosTenderInput {
  const _Cash(PosMoney amount, {this.tendered}) : super(PosTender.cash, amount);

  final PosMoney? tendered;

  @override
  Map<String, Object?> _extra() => <String, Object?>{
    'tendered_amount': ?tendered?.toApiString(),
  };
}

class _Card extends PosTenderInput {
  const _Card(
    PosMoney amount, {
    required this.cardType,
    required this.transactionId,
  }) : super(PosTender.card, amount);

  final String cardType;
  final String transactionId;

  @override
  Map<String, Object?> _extra() => <String, Object?>{
    'card_type': cardType,
    'transaction_id': transactionId,
  };
}

class _Qr extends PosTenderInput {
  const _Qr(PosMoney amount, {this.reference}) : super(PosTender.qr, amount);

  final String? reference;

  @override
  Map<String, Object?> _extra() => <String, Object?>{'reference': ?reference};
}

class _Prepayment extends PosTenderInput {
  const _Prepayment(PosMoney amount) : super(PosTender.prepayment, amount);

  @override
  Map<String, Object?> _extra() => const <String, Object?>{};
}

class _Account extends PosTenderInput {
  const _Account(PosMoney amount, {required this.accountUuid})
    : super(PosTender.account, amount);

  final String accountUuid;

  @override
  Map<String, Object?> _extra() => <String, Object?>{
    'account_uuid': accountUuid,
  };
}
