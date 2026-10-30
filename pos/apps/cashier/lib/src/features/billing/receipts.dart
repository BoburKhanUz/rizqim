import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart';

import '../../counter/counter_format.dart';
import '../../shell/pos_scope.dart';
import '../order/kitchen_tickets.dart';
import '../settings/cashier_prefs.dart';
import 'cashier_ui.dart';

/// Kassa hujjatlari (`pos_core`) va ularni chek printeriga yuborish: schyot (pre-check), sotuv, qaytarish, bo'nak va
/// kredit cheklari, X/Z hisoboti. Chop etishni terminal o'zi bajaradi (POS_SPEC 2.11): printer — bootstrap'dagi
/// `receipt` printeri, qog'oz eni va rasm rejimi qurilmadagi sozlama bilan ([CashierPrefs]).
class ReceiptPrinter {
  ReceiptPrinter(this.scope);

  factory ReceiptPrinter.of(BuildContext context) =>
      ReceiptPrinter(PosScope.of(context));

  final PosScope scope;

  /// Chek printeri (qurilma sozlamalari bilan); yo'q — `null`.
  PrinterConfig? get config {
    final PrinterConfig? c = scope.printing.receiptPrinter;
    return c == null ? null : CashierPrefs.instance.apply(c);
  }

  /// Do'kon sarlavhasi (bootstrap).
  ReceiptHeader get header {
    final PosBootstrap? b = scope.session.bootstrap;
    return ReceiptHeader(
      storeName: b?.store.name ?? scope.session.storeName ?? 'Kassa',
      address: b?.branch.address,
      phone: b?.branch.phone,
    );
  }

  String? get cashier => scope.session.staffName;

  /// Hujjatni chop etish navbatiga qo'yadi (navbatga tushdi — `true`). Printer yo'q yoki chizib bo'lmadi —
  /// [context] da xabar, `false`. Chiqmasa (qog'oz tugadi, printer javob bermadi) ish ekranlarida banner
  /// ([KitchenPrintIssues.receiptsOf]) "Qayta urinish" bilan.
  Future<bool> print(
    BuildContext context,
    PrintLayout Function(PaperWidth paper) layout, {
    bool openDrawer = false,
  }) async => await submit(context, layout, openDrawer: openDrawer) != null;

  /// [print] kabi, navbatdagi ishni qaytaradi (chiqqanini kutish uchun: `job.done`).
  Future<PrintJob?> submit(
    BuildContext context,
    PrintLayout Function(PaperWidth paper) layout, {
    bool openDrawer = false,
  }) async {
    await CashierPrefs.instance.load();
    final PrinterConfig? printer = config;
    if (printer == null) {
      // P6-12 phone mode: the printer is optional (the receipt and the number are on the screen)
      if (scope.session.phoneMode) return null;
      if (context.mounted) {
        showPosMessage(
          context,
          "Chek printeri sozlanmagan: boshqaruv panelida Printerlar bo'limidan qo'shing.",
        );
      }
      return null;
    }
    try {
      final PrintJob job = await scope.printing.printers.print(
        printer,
        layout(printer.paper),
        openDrawer: openDrawer,
      );
      KitchenPrintIssues.receiptsOf(scope.printing)
          .watch(scope.printing.printers.queue, job);
      return job;
    } on Object catch (e) {
      if (context.mounted) showPosMessage(context, 'Chop etilmadi: $e');
      return null;
    }
  }

  /// Kassa qutisini ochadi (naqd to'lov, kirim/chiqim).
  void openDrawer() {
    final PrinterConfig? printer = config;
    if (printer != null) scope.printing.printers.openDrawer(printer);
  }
}

// --- schyot ----------------------------------------------------------------------------------------------------------

/// Joy nomi: "3-xona", "Tapchan 2" yoki "Olib ketish".
String? placeLabel(PosOrder? order) {
  if (order == null) return null;
  if (order.table != null) return order.table!.name;
  if (order.tableRef != null) return order.tableRef;
  // P6-25: a counter order carried to the guest's table plate
  final String? plate = order.tableLabel?.trim();
  if (plate != null && plate.isNotEmpty) return 'Stol $plate';
  return order.posChannel == PosChannel.takeaway ? 'Olib ketish' : 'Zal';
}

/// Qator birlik narxi (summa ÷ miqdor).
PosMoney unitPriceOf(PosMoney amount, PosQuantity quantity) => quantity.isZero
    ? amount
    : PosMoney(
        (amount.tiyin * PosQuantity.scaleFactor / quantity.milli).round(),
      );

/// Schyot (pre-check) hujjati.
PreCheck preCheckOf({
  required PosBill bill,
  required ReceiptHeader header,
  required DateTime now,
  PosOrder? order,
  int billsCount = 1,
  PosMoney prepaid = PosMoney.zero,
  bool copy = false,
}) => PreCheck(
  header: header,
  printedAt: now.toLocal(),
  tableLabel: placeLabel(order),
  waiter: order?.waiter?.name,
  guests: order?.guestsCount,
  openedAt: order?.openedAt?.toLocal(),
  orderCode: order?.shortCode == null ? null : '#${order!.shortCode}',
  billLabel: billsCount > 1 ? 'Schyot ${bill.number}/$billsCount' : null,
  items: <PreCheckItem>[
    for (final PosBillItem i in bill.items)
      PreCheckItem(
        name: i.variantName == null || i.variantName!.isEmpty
            ? i.productName
            : '${i.productName} (${i.variantName})',
        quantity: ItemQuantity(i.quantity, unit: i.unit),
        unitPrice: unitPriceOf(i.amount, i.quantity),
        amount: i.amount,
      ),
  ],
  charges: <BillCharge>[
    if (!bill.roomFeeAmount.isZero)
      BillCharge(BillChargeKind.roomFee, bill.roomFeeAmount),
    if (!bill.placeFeeAmount.isZero)
      BillCharge(
        BillChargeKind.placeFee,
        bill.placeFeeAmount,
        // P6-22: the restaurant cover per guest
        label: (order?.placeFeeIsCover ?? false) ? 'Kuver' : null,
        note: bill.serviceChargeAmount.isZero ? bill.serviceChargeNotice : null,
      ),
    if (!bill.minSpendTopupAmount.isZero)
      BillCharge(BillChargeKind.minSpendTopUp, bill.minSpendTopupAmount),
    if (!bill.serviceChargeAmount.isZero)
      BillCharge(
        BillChargeKind.serviceCharge,
        bill.serviceChargeAmount,
        percent: _percent(order?.charges.serviceChargePercent),
        // P6-22: the public notice under the service charge line
        note: bill.serviceChargeNotice,
      ),
    // P3-37: a percent discount shows its percent ("Chegirma 10%")
    if (!bill.discount.isZero)
      BillCharge(
        BillChargeKind.discount,
        -bill.discount.abs(),
        percent: _percent(order?.discountPercent),
      ),
  ],
  prepaid: prepaid,
  splitCount: bill.guestsShare,
  isCopy: copy,
);

String? _percent(String? raw) {
  if (raw == null) return null;
  try {
    return percentHundredths(raw) == 0 ? null : raw;
  } on FormatException {
    return null;
  }
}

// --- cheklar ---------------------------------------------------------------------------------------------------------

ReceiptPayment _payment(
  String tender,
  PosMoney amount, {
  String? cardType,
  String? reference,
  String? cardLast4,
}) => ReceiptPayment(
  PosTender.fromWire(tender) ?? PosTender.cash,
  amount,
  cardType: cardType == null ? null : (cardTypes[cardType] ?? cardType),
  // P6-16: the RRN of the bank terminal's slip and (optional) the card's last four digits
  reference: cardLast4 == null || cardLast4.isEmpty
      ? reference
      : '${reference == null ? '' : '$reference '}**** $cardLast4',
);

ReceiptLine _lineOf(PosFiscalLine l) => ReceiptLine(
  name: l.name,
  quantity: ItemQuantity(
    l.quantity,
    unit: l.unit == 'kg' ? PosUnit.kg : PosUnit.piece,
    unitLabel: l.unit,
  ),
  unitPrice: l.unitPrice,
  amount: l.amount,
  vatRate: l.noVat ? null : l.vatRate,
  vatAmount: l.noVat ? null : l.vatAmount,
  mxikCode: l.mxikCode,
  packageCode: l.packageCode,
);

/// Ro'yxatdan o'tgan chekdagi fiskal ma'lumot (P3-26: to'lov javobi chekni olib keladi). Sotuv va qaytarishda fiskal
/// belgi bo'lmasa — chekda "kutilmoqda"; bo'nak va kreditda belgi yo'q, lekin kassa (fiskal modul) va raqam bor.
FiscalInfo? _fiscalOf(PosFiscalReceiptDetail? d) =>
    d == null ||
        (d.fiscalSign == null &&
            d.terminalId == null &&
            d.receiptNumber == null)
    ? null
    : FiscalInfo(
        fiscalSign: d.fiscalSign,
        fiscalNumber: d.receiptNumber,
        qrUrl: d.qrUrl,
        terminalId: d.terminalId,
      );

/// Chek qatorlari serverdagi chekdan (bo'sh bo'lsa `null`).
List<ReceiptLine>? _linesOf(PosFiscalReceiptDetail? d) =>
    d == null || d.lines.isEmpty
    ? null
    : <ReceiptLine>[for (final PosFiscalLine l in d.lines) _lineOf(l)];

/// Chekdagi to'lovlar serverdagi chekdan (avans va nasiya qismi o'z belgisi bilan); bo'sh bo'lsa `null`.
List<ReceiptPayment>? _paymentsOf(PosFiscalReceiptDetail? d) =>
    d == null || d.payments.isEmpty
    ? null
    : <ReceiptPayment>[
        for (final PosFiscalPaymentPart p in d.payments)
          _payment(
            p.prepayment ? 'prepayment' : (p.credit ? 'account' : p.form),
            p.amount,
            cardType: p.cardType,
            reference: p.transactionId,
            cardLast4: p.cardLast4,
          ),
      ];

/// To'lov, qaytarish, avans yoki qarz to'lovi javobidagi chek; bo'lmasa (eski server) — chekni o'qib ko'radi.
Future<PosFiscalReceiptDetail?> receiptOf(
  PosData data,
  PosFiscalState fiscal,
) async {
  if (fiscal.receipt != null) return fiscal.receipt;
  final String? uuid = fiscal.receiptUuid;
  if (uuid == null) return null;
  try {
    return await FiscalReceiptRepository(data.http).get(uuid);
  } on PosException {
    // O'qib bo'lmadi: chek kassa ma'lumotidan tuziladi, fiskal belgi o'rniga "kutilmoqda"
    return null;
  }
}

String? _nonFiscalNote(PosFiscalReceiptDetail? d) =>
    d != null && d.nonFiscal ? 'Bu fiskal chek emas (sinov rejimi).' : null;

/// Sotuv cheki: schyot qatorlari, xizmatlar va to'lovlar. [detail] — serverdagi chek (MXIK, QQS, fiskal belgi, QR,
/// raqam, kassa; P3-26: to'lov javobidagi `fiscal.receipt`); bo'lmasa chek schyotdan tuziladi va fiskal belgi o'rniga
/// "kutilmoqda" yoziladi.
FiscalReceipt saleReceiptOf({
  required PosBill bill,
  required ReceiptHeader header,
  required DateTime now,
  PosFiscalReceiptDetail? detail,
  PosOrder? order,
  String? cashier,
  bool copy = false,
}) {
  final List<ReceiptLine> lines = detail != null && detail.lines.isNotEmpty
      ? <ReceiptLine>[for (final PosFiscalLine l in detail.lines) _lineOf(l)]
      : <ReceiptLine>[
          for (final PosBillItem i in bill.items)
            ReceiptLine(
              name: i.variantName == null || i.variantName!.isEmpty
                  ? i.productName
                  : '${i.productName} (${i.variantName})',
              quantity: ItemQuantity(i.quantity, unit: i.unit),
              unitPrice: unitPriceOf(i.amount, i.quantity),
              amount: i.amount - i.discountAmount,
              discount: i.discountAmount,
            ),
          for (final (PosMoney amount, String name) in <(PosMoney, String)>[
            (bill.roomFeeAmount, 'Xona puli'),
            (bill.placeFeeAmount, 'Joy puli'),
            (bill.minSpendTopupAmount, "Minimal summaga qo'shimcha"),
            (bill.serviceChargeAmount, 'Xizmat haqi'),
          ])
            if (!amount.isZero)
              ReceiptLine(
                name: name,
                quantity: const ItemQuantity(PosQuantity.one),
                unitPrice: amount,
                amount: amount,
              ),
        ];
  return FiscalReceipt(
    type: FiscalReceiptType.sale,
    header: header,
    number: detail?.receiptNumber ?? '${order?.shortCode ?? ''}-${bill.number}',
    issuedAt: (detail?.issuedAt ?? bill.paidAt ?? now).toLocal(),
    lines: lines,
    payments:
        _paymentsOf(detail) ??
        <ReceiptPayment>[
          for (final PosPayment p in bill.payments)
            _payment(
              p.tender,
              p.amount,
              cardType: p.cardType,
              reference: p.transactionId,
              cardLast4: p.cardLast4,
            ),
        ],
    cashier: cashier,
    tableLabel: placeLabel(order),
    orderCode: order?.shortCode == null ? null : '#${order!.shortCode}',
    guests: order?.guestsCount,
    change: PosMoney.sum(
      bill.payments.map((PosPayment p) => p.changeAmount ?? PosMoney.zero),
    ),
    fiscal: _fiscalOf(detail),
    note: _nonFiscalNote(detail),
    isCopy: copy,
    // P3-37: the server's lines are after the discount — the receipt tells it once at the end
    discount: bill.discount.abs(),
    discountPercent: _percent(order?.discountPercent),
  );
}

/// Qaytarish cheki.
FiscalReceipt refundReceiptOf({
  required PosRefund refund,
  required ReceiptHeader header,
  required DateTime now,
  PosFiscalReceiptDetail? detail,
  PosOrder? order,
  String? cashier,
}) => FiscalReceipt(
  type: FiscalReceiptType.refund,
  header: header,
  number: detail?.receiptNumber ?? refund.uuid.substring(0, 8),
  issuedAt: (detail?.issuedAt ?? refund.createdAt ?? now).toLocal(),
  lines: detail != null && detail.lines.isNotEmpty
      ? <ReceiptLine>[for (final PosFiscalLine l in detail.lines) _lineOf(l)]
      : <ReceiptLine>[
          ReceiptLine(
            name: 'Qaytarish',
            quantity: const ItemQuantity(PosQuantity.one),
            unitPrice: refund.amount,
            amount: refund.amount,
          ),
        ],
  payments: <ReceiptPayment>[_payment(refund.method ?? 'cash', refund.amount)],
  cashier: cashier,
  tableLabel: placeLabel(order),
  orderCode: order?.shortCode == null ? null : '#${order!.shortCode}',
  fiscal: _fiscalOf(detail),
  note: <String>[
    if (refund.reason != null) 'Sabab: ${refund.reason}',
    ?_nonFiscalNote(detail),
  ].join('\n').ifEmpty,
);

/// Bo'nak (avans) cheki. [detail] — serverdagi chek (raqam, kassa, qatorlar; P3-26 avans javobi olib keladi).
FiscalReceipt advanceReceiptOf({
  required PosBooking booking,
  required PosBookingPayment payment,
  required ReceiptHeader header,
  required DateTime now,
  PosFiscalReceiptDetail? detail,
  String? room,
  String? cashier,
}) => FiscalReceipt(
  type: FiscalReceiptType.advance,
  header: header,
  number: detail?.receiptNumber ?? payment.uuid.substring(0, 8),
  issuedAt: (detail?.issuedAt ?? payment.createdAt ?? now).toLocal(),
  lines:
      _linesOf(detail) ??
      <ReceiptLine>[
        ReceiptLine(
          name: 'Avans (bron)',
          quantity: const ItemQuantity(PosQuantity.one),
          unitPrice: payment.amount,
          amount: payment.amount,
        ),
      ],
  payments: <ReceiptPayment>[
    _payment(
      payment.tender,
      payment.amount,
      cardType: payment.cardType,
      reference: payment.transactionId,
    ),
  ],
  cashier: cashier,
  customerName: booking.contactName,
  fiscal: _fiscalOf(detail),
  note: <String>[
    'Bron: ${dm(booking.startsAt, year: true)} ${hm(booking.startsAt)}-${hm(booking.endsAt)}'
        '${room == null ? '' : ', $room'}',
    ?_nonFiscalNote(detail),
  ].join('\n'),
);

/// Kredit cheki (nasiya qarzi to'lovi). [detail] — serverdagi chek (P3-26: qarz to'lovi javobi olib keladi).
FiscalReceipt creditReceiptOf({
  required PosCustomerAccount account,
  required PosAccountEntry entry,
  required ReceiptHeader header,
  required DateTime now,
  PosFiscalReceiptDetail? detail,
  String? cashier,
}) {
  final PosMoney amount = entry.amount.abs();
  return FiscalReceipt(
    type: FiscalReceiptType.credit,
    header: header,
    number: detail?.receiptNumber ?? entry.uuid.substring(0, 8),
    issuedAt: (detail?.issuedAt ?? entry.createdAt ?? now).toLocal(),
    lines:
        _linesOf(detail) ??
        <ReceiptLine>[
          ReceiptLine(
            name: "Qarz to'lovi",
            quantity: const ItemQuantity(PosQuantity.one),
            unitPrice: amount,
            amount: amount,
          ),
        ],
    payments: <ReceiptPayment>[_payment(entry.tender ?? 'cash', amount)],
    cashier: cashier,
    customerName: account.name,
    remainingDebt: account.balance,
    fiscal: _fiscalOf(detail),
    note: _nonFiscalNote(detail),
  );
}

extension on String {
  String? get ifEmpty => isEmpty ? null : this;
}

/// Chop etish natijasini kutmasdan yuboradi.
void printLater(Future<bool> job) => unawaited(job);
