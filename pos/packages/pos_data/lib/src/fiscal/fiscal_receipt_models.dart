import 'package:pos_core/pos_core.dart';

import '../json.dart';

/// Ro'yxatdan o'tgan chek (`GET /pos/fiscal-receipts/{uuid}` yoki `fiscal.receipt`): qatorlar (MXIK, QQS), to'lovlar,
/// fiskal belgi, chek raqami, QR va fiskal modul (`terminal_id`). Kassa sotuv, qaytarish, avans va kredit chekini shu
/// ma'lumot bilan qog'ozga chiqaradi.
class PosFiscalReceiptDetail {
  const PosFiscalReceiptDetail({
    required this.uuid,
    required this.type,
    required this.status,
    this.paymentForm,
    this.nonFiscal = false,
    this.total = PosMoney.zero,
    this.vatTotal = PosMoney.zero,
    this.fiscalSign,
    this.receiptNumber,
    this.qrUrl,
    this.terminalId,
    this.error,
    this.issuedAt,
    this.createdAt,
    this.originalReceiptUuid,
    this.lines = const <PosFiscalLine>[],
    this.payments = const <PosFiscalPaymentPart>[],
    this.raw = const <String, Object?>{},
  });

  factory PosFiscalReceiptDetail.fromJson(PosJson j) => PosFiscalReceiptDetail(
    uuid: j.str('uuid'),
    type: j.str('type'),
    status: j.str('status'),
    paymentForm: j.strOrNull('payment_form'),
    nonFiscal: j.boolean('non_fiscal'),
    total: j.money('total'),
    vatTotal: j.money('vat_total'),
    fiscalSign: j.strOrNull('fiscal_sign'),
    receiptNumber: j.strOrNull('receipt_number'),
    qrUrl: j.strOrNull('qr_url'),
    terminalId: j.strOrNull('terminal_id'),
    error: j.strOrNull('error'),
    issuedAt: j.dateOrNull('issued_at'),
    createdAt: j.dateOrNull('created_at'),
    originalReceiptUuid: j.strOrNull('original_receipt_uuid'),
    lines: j.objects('lines', PosFiscalLine.fromJson),
    payments: j.objects('payments', PosFiscalPaymentPart.fromJson),
    raw: j.map,
  );

  final String uuid;

  /// `sale`, `refund`, `advance`, `credit`.
  final String type;

  /// `pending`, `issued`, `failed`.
  final String status;

  /// `cash`, `card`, `qr`, `mixed`.
  final String? paymentForm;

  /// Sinov (`sandbox`) yoki fiskal rejim o'chiq (`null` drayver): chek fiskal emas.
  final bool nonFiscal;
  final PosMoney total;
  final PosMoney vatTotal;
  final String? fiscalSign;
  final String? receiptNumber;
  final String? qrUrl;
  final String? terminalId;
  final String? error;
  final DateTime? issuedAt;
  final DateTime? createdAt;

  /// Qaytarish chekining sotuv cheki.
  final String? originalReceiptUuid;
  final List<PosFiscalLine> lines;
  final List<PosFiscalPaymentPart> payments;
  final Map<String, Object?> raw;

  bool get isIssued => status == 'issued';

  FiscalReceiptType? get receiptType => FiscalReceiptType.fromWire(type);
}

/// Chek qatori (backend `FiscalLine`).
class PosFiscalLine {
  const PosFiscalLine({
    required this.name,
    required this.quantity,
    this.mxikCode,
    this.packageCode,
    this.unit,
    this.unitPrice = PosMoney.zero,
    this.amount = PosMoney.zero,
    this.vatRate,
    this.vatAmount = PosMoney.zero,
    this.noVat = false,
  });

  factory PosFiscalLine.fromJson(PosJson j) {
    final String? mxik = j.strOrNull('mxik_code');
    return PosFiscalLine(
      name: j.str('name'),
      mxikCode: mxik == null || mxik.isEmpty ? null : mxik,
      packageCode: j.strOrNull('package_code'),
      unit: j.strOrNull('unit'),
      quantity: j.quantity('quantity'),
      unitPrice: j.money('unit_price'),
      amount: j.money('amount'),
      vatRate: j.strOrNull('vat_rate'),
      vatAmount: j.money('vat_amount'),
      noVat: j.boolean('no_vat'),
    );
  }

  final String name;
  final String? mxikCode;
  final String? packageCode;

  /// Chekdagi birlik ("dona", "kg (guruch)", "xizmat").
  final String? unit;
  final PosQuantity quantity;
  final PosMoney unitPrice;
  final PosMoney amount;

  /// `"12"`, `"0"`.
  final String? vatRate;
  final PosMoney vatAmount;
  final bool noVat;
}

/// Chekdagi to'lov qismi: shakl, summa, avans (`prepayment`) yoki nasiya (`credit`) belgisi.
class PosFiscalPaymentPart {
  const PosFiscalPaymentPart({
    required this.form,
    required this.amount,
    this.prepayment = false,
    this.credit = false,
    this.cardType,
    this.transactionId,
    this.cardLast4,
  });

  factory PosFiscalPaymentPart.fromJson(PosJson j) => PosFiscalPaymentPart(
    form: j.strOrNull('form') ?? 'cash',
    amount: j.money('amount'),
    prepayment: j.boolean('prepayment'),
    credit: j.boolean('credit'),
    cardType: j.strOrNull('card_type'),
    transactionId: j.strOrNull('transaction_id'),
    cardLast4: j.strOrNull('card_last4'),
  );

  /// `cash`, `card`, `qr`.
  final String form;
  final PosMoney amount;
  final bool prepayment;
  final bool credit;
  final String? cardType;
  final String? transactionId;

  /// P6-16: kartaning oxirgi 4 raqami (ixtiyoriy).
  final String? cardLast4;
}
