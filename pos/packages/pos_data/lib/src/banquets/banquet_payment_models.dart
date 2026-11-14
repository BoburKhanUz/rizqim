import 'package:pos_core/pos_core.dart';

import '../billing/billing_models.dart';
import '../json.dart';
import '../shift/shift_models.dart';
import 'banquet_models.dart';

/// P6-53: to'lov jadvali qatorining turi (`kind`): zaklad, oraliq to'lov, yakuniy.
enum PosBanquetPaymentKind {
  deposit('deposit', 'Zaklad'),
  interim('interim', "Oraliq to'lov"),
  finalPayment('final', "Yakuniy to'lov"),
  unknown('', "To'lov");

  const PosBanquetPaymentKind(this.wire, this.label);

  final String wire;
  final String label;

  static PosBanquetPaymentKind fromWire(String? wire) {
    for (final PosBanquetPaymentKind k in values) {
      if (k.wire == wire && k != unknown) return k;
    }
    return unknown;
  }
}

/// Qator holati (`status`): kutilmoqda, to'langan, qaytarilgan, ushlab qolingan (bekor qilishda).
enum PosBanquetPaymentStatus {
  due('due', "To'lanmagan"),
  paid('paid', "To'langan"),
  refunded('refunded', 'Qaytarilgan'),
  retained('retained', 'Ushlab qolingan'),
  unknown('', "Noma'lum");

  const PosBanquetPaymentStatus(this.wire, this.label);

  final String wire;
  final String label;

  static PosBanquetPaymentStatus fromWire(String? wire) {
    for (final PosBanquetPaymentStatus s in values) {
      if (s.wire == wire && s != unknown) return s;
    }
    return unknown;
  }
}

/// `GET /pos/banquets/{uuid}/payments` qatori: sana, summa, holat, muddati o'tganmi, to'lov turi, bo'nak cheki.
class PosBanquetPaymentRow {
  const PosBanquetPaymentRow({
    required this.uuid,
    required this.kind,
    required this.amount,
    required this.status,
    required this.overdue,
    this.kindLabel,
    this.statusLabel,
    this.dueDate,
    this.tender,
    this.cardType,
    this.transactionId,
    this.reference,
    this.shiftId,
    this.paidAt,
    this.paidByName,
    this.fiscalReceiptUuid,
    this.fiscalStatus,
    this.refundedAmount = PosMoney.zero,
    this.retainedAmount = PosMoney.zero,
    this.note,
  });

  factory PosBanquetPaymentRow.fromJson(PosJson j) {
    final PosJson? fiscal = j.objOrNull('fiscal');
    return PosBanquetPaymentRow(
      uuid: j.str('uuid'),
      kind: PosBanquetPaymentKind.fromWire(j.strOrNull('kind')),
      kindLabel: j.strOrNull('kind_label'),
      dueDate: j.strOrNull('due_date'),
      amount: j.money('amount'),
      status: PosBanquetPaymentStatus.fromWire(j.strOrNull('status')),
      statusLabel: j.strOrNull('status_label'),
      overdue: j.boolean('overdue'),
      tender: j.strOrNull('tender'),
      cardType: j.strOrNull('card_type'),
      transactionId: j.strOrNull('transaction_id'),
      reference: j.strOrNull('reference'),
      shiftId: j.intOrNull('shift_id'),
      paidAt: j.dateOrNull('paid_at'),
      paidByName: j.objOrNull('paid_by')?.strOrNull('name'),
      fiscalReceiptUuid:
          fiscal?.strOrNull('receipt_uuid') ??
          j.strOrNull('fiscal_receipt_uuid'),
      fiscalStatus: fiscal?.strOrNull('status'),
      refundedAmount: j.money('refunded_amount'),
      retainedAmount: j.money('retained_amount'),
      note: j.strOrNull('note'),
    );
  }

  final String uuid;
  final PosBanquetPaymentKind kind;
  final String? kindLabel;

  /// Mahalliy sana `YYYY-MM-DD`.
  final String? dueDate;
  final PosMoney amount;
  final PosBanquetPaymentStatus status;
  final String? statusLabel;

  /// To'lanmagan va sanasi o'tgan.
  final bool overdue;

  /// `cash`, `card`, `qr`, `bank_transfer`.
  final String? tender;
  final String? cardType;
  final String? transactionId;
  final String? reference;
  final int? shiftId;
  final DateTime? paidAt;
  final String? paidByName;
  final String? fiscalReceiptUuid;
  final String? fiscalStatus;
  final PosMoney refundedAmount;
  final PosMoney retainedAmount;
  final String? note;

  bool get isDue => status == PosBanquetPaymentStatus.due;

  /// Ekrandagi nomi (server nomi bo'lsa — o'sha).
  String get label => kindLabel ?? kind.label;
}

/// Jadvaldagi pul yozuvi (`entries`): to'lov, bank o'tkazmasi, qaytarish, ushlab qolish — har biri o'z cheki bilan.
class PosBanquetPaymentEntry {
  const PosBanquetPaymentEntry({
    required this.uuid,
    required this.type,
    required this.amount,
    required this.tender,
    this.typeLabel,
    this.paymentUuid,
    this.cardType,
    this.reference,
    this.businessDate,
    this.shiftId,
    this.userName,
    this.reason,
    this.transferPending = false,
    this.fiscalReceiptUuid,
    this.fiscalStatus,
    this.originalReceiptUuid,
    this.createdAt,
  });

  factory PosBanquetPaymentEntry.fromJson(PosJson j) {
    final PosJson? fiscal = j.objOrNull('fiscal');
    return PosBanquetPaymentEntry(
      uuid: j.str('uuid'),
      type: j.str('type'),
      typeLabel: j.strOrNull('type_label'),
      paymentUuid: j.strOrNull('payment_uuid'),
      amount: j.money('amount'),
      tender: j.strOrNull('tender') ?? 'cash',
      cardType: j.strOrNull('card_type'),
      reference: j.strOrNull('reference'),
      businessDate: j.strOrNull('business_date'),
      shiftId: j.intOrNull('shift_id'),
      userName: j.objOrNull('user')?.strOrNull('name'),
      reason: j.strOrNull('reason'),
      transferPending: j.boolean('transfer_pending'),
      fiscalReceiptUuid: fiscal?.strOrNull('receipt_uuid'),
      fiscalStatus: fiscal?.strOrNull('status'),
      originalReceiptUuid: j.strOrNull('original_receipt_uuid'),
      createdAt: j.dateOrNull('created_at'),
    );
  }

  final String uuid;

  /// `payment`, `transfer`, `refund`, `retain`.
  final String type;
  final String? typeLabel;
  final String? paymentUuid;
  final PosMoney amount;
  final String tender;
  final String? cardType;
  final String? reference;
  final String? businessDate;
  final int? shiftId;
  final String? userName;
  final String? reason;

  /// Bank o'tkazmasi bilan qaytarilishi kerak (kassadan emas).
  final bool transferPending;
  final String? fiscalReceiptUuid;
  final String? fiscalStatus;
  final String? originalReceiptUuid;
  final DateTime? createdAt;
}

/// Tadbir kartasi uchun jadval jami (`summary`).
class PosBanquetScheduleSummary {
  const PosBanquetScheduleSummary({
    required this.total,
    required this.paid,
    required this.outstanding,
    required this.overdue,
    required this.overdueAmount,
    required this.overdueCount,
    this.nextDueDate,
    this.nextDueAmount,
    this.nextDueKind,
  });

  factory PosBanquetScheduleSummary.fromJson(PosJson j) {
    final PosJson? next = j.objOrNull('next_due');
    return PosBanquetScheduleSummary(
      total: j.money('total'),
      paid: j.money('paid'),
      outstanding: j.money('outstanding'),
      overdue: j.boolean('overdue'),
      overdueAmount: j.money('overdue_amount'),
      overdueCount: j.intOrNull('overdue_count') ?? 0,
      nextDueDate: next?.strOrNull('date'),
      nextDueAmount: next?.moneyOrNull('amount'),
      nextDueKind: next == null
          ? null
          : PosBanquetPaymentKind.fromWire(next.strOrNull('kind')),
    );
  }

  final PosMoney total;
  final PosMoney paid;
  final PosMoney outstanding;
  final bool overdue;
  final PosMoney overdueAmount;
  final int overdueCount;
  final String? nextDueDate;
  final PosMoney? nextDueAmount;
  final PosBanquetPaymentKind? nextDueKind;
}

/// Bekor qilingan tadbir: nima qaror qilingani.
class PosBanquetCancellationRecord {
  const PosBanquetCancellationRecord({
    required this.daysBefore,
    required this.rulePercent,
    required this.paid,
    required this.retained,
    required this.refund,
    this.overrideReason,
    this.reason,
  });

  factory PosBanquetCancellationRecord.fromJson(PosJson j) =>
      PosBanquetCancellationRecord(
        daysBefore: j.intOrNull('days_before') ?? 0,
        rulePercent: j.strOrNull('rule_percent') ?? '0',
        paid: j.money('paid'),
        retained: j.money('retained'),
        refund: j.money('refund'),
        overrideReason: j.strOrNull('override_reason'),
        reason: j.strOrNull('reason'),
      );

  final int daysBefore;
  final String rulePercent;
  final PosMoney paid;
  final PosMoney retained;
  final PosMoney refund;
  final String? overrideReason;
  final String? reason;
}

/// `GET /pos/banquets/{uuid}/payments`: tadbir (qisqa), jami, qatorlar, pul yozuvlari, bekor qilish (bo'lsa).
class PosBanquetSchedule {
  const PosBanquetSchedule({
    required this.eventUuid,
    required this.eventStatus,
    required this.eventDate,
    required this.total,
    required this.paidTotal,
    required this.version,
    required this.summary,
    required this.rows,
    required this.entries,
    this.cancellation,
  });

  factory PosBanquetSchedule.fromJson(PosJson j) {
    final PosJson event = j.obj('event');
    final PosJson? cancellation = j.objOrNull('cancellation');
    return PosBanquetSchedule(
      eventUuid: event.str('uuid'),
      eventStatus: PosBanquetStatus.fromWire(event.strOrNull('status')),
      eventDate: event.str('event_date'),
      total: event.money('total'),
      paidTotal: event.money('paid_total'),
      version: event.intOrNull('version') ?? 1,
      summary: PosBanquetScheduleSummary.fromJson(j.obj('summary')),
      rows: j.objects('rows', PosBanquetPaymentRow.fromJson),
      entries: j.has('entries')
          ? j.objects('entries', PosBanquetPaymentEntry.fromJson)
          : const <PosBanquetPaymentEntry>[],
      cancellation: cancellation == null
          ? null
          : PosBanquetCancellationRecord.fromJson(cancellation),
    );
  }

  final String eventUuid;
  final PosBanquetStatus eventStatus;
  final String eventDate;
  final PosMoney total;
  final PosMoney paidTotal;
  final int version;
  final PosBanquetScheduleSummary summary;
  final List<PosBanquetPaymentRow> rows;
  final List<PosBanquetPaymentEntry> entries;
  final PosBanquetCancellationRecord? cancellation;

  /// Kassada to'lanadigan qatorlar (to'lanmagan).
  List<PosBanquetPaymentRow> get dueRows => <PosBanquetPaymentRow>[
    for (final PosBanquetPaymentRow r in rows)
      if (r.isDue) r,
  ];
}

/// Jadval qatorini kassada to'lash (`POST …/payments/{row}/pay`): naqd, karta (turi va tranzaksiya raqami) yoki
/// statik QR (ixtiyoriy `reference`). [amount] — qatorning bir qismi (qolgani o'sha sanali yangi qator).
class PosBanquetPayInput {
  const PosBanquetPayInput({
    required this.tender,
    this.amount,
    this.cardType,
    this.transactionId,
    this.reference,
  });

  final PosTender tender;
  final PosMoney? amount;
  final String? cardType;
  final String? transactionId;
  final String? reference;

  Map<String, Object?> toJson({String? shiftUuid}) => <String, Object?>{
    'tender': tender.wire,
    'amount': ?amount?.toApiString(),
    'card_type': ?cardType,
    'transaction_id': ?transactionId,
    'reference': ?reference,
    'shift_uuid': ?shiftUuid,
  };
}

/// `POST …/payments/{row}/pay` javobi: yangilangan jadval, pul yozuvi va bo'nak cheki (`fiscal`).
class PosBanquetPaymentResult {
  const PosBanquetPaymentResult({
    required this.schedule,
    required this.entry,
    required this.fiscal,
  });

  factory PosBanquetPaymentResult.fromJson(PosJson j) =>
      PosBanquetPaymentResult(
        schedule: PosBanquetSchedule.fromJson(j.obj('schedule')),
        entry: PosBanquetPaymentEntry.fromJson(j.obj('entry')),
        fiscal: j.has('fiscal')
            ? PosFiscalState.fromJson(j.obj('fiscal'))
            : const PosFiscalState(),
      );

  final PosBanquetSchedule schedule;
  final PosBanquetPaymentEntry entry;
  final PosFiscalState fiscal;
}

/// Bekor qilish hisobidagi to'lov qatori: qancha ushlanadi, qancha va qaysi yo'l bilan qaytadi.
class PosBanquetCancellationPart {
  const PosBanquetCancellationPart({
    required this.paymentUuid,
    required this.kind,
    required this.amount,
    required this.retain,
    required this.refund,
    this.tender,
    this.refundPath,
  });

  factory PosBanquetCancellationPart.fromJson(PosJson j) =>
      PosBanquetCancellationPart(
        paymentUuid: j.str('payment_uuid'),
        kind: PosBanquetPaymentKind.fromWire(j.strOrNull('kind')),
        tender: j.strOrNull('tender'),
        amount: j.money('amount'),
        retain: j.money('retain'),
        refund: j.money('refund'),
        refundPath: j.strOrNull('refund_path'),
      );

  final String paymentUuid;
  final PosBanquetPaymentKind kind;
  final String? tender;
  final PosMoney amount;
  final PosMoney retain;
  final PosMoney refund;

  /// `cash_drawer`, `card`, `qr`, `bank_transfer`.
  final String? refundPath;
}

/// `GET /pos/banquets/{uuid}/cancellation`: necha kun qoldi, qoida foizi, to'langan, ushlanadigan va qaytariladigan
/// qism (naqd — shu kassaning ochiq smenasidan).
class PosBanquetCancellationQuote {
  const PosBanquetCancellationQuote({
    required this.daysBefore,
    required this.rulePercent,
    required this.paid,
    required this.computedRetained,
    required this.retained,
    required this.refund,
    required this.overridden,
    required this.cashRefund,
    required this.transferRefund,
    this.rows = const <PosBanquetCancellationPart>[],
  });

  factory PosBanquetCancellationQuote.fromJson(PosJson j) =>
      PosBanquetCancellationQuote(
        daysBefore: j.intOrNull('days_before') ?? 0,
        rulePercent: j.strOrNull('rule_percent') ?? '0',
        paid: j.money('paid'),
        computedRetained: j.money('computed_retained'),
        retained: j.money('retained'),
        refund: j.money('refund'),
        overridden: j.boolean('overridden'),
        cashRefund: j.money('cash_refund'),
        transferRefund: j.money('transfer_refund'),
        rows: j.has('rows')
            ? j.objects('rows', PosBanquetCancellationPart.fromJson)
            : const <PosBanquetCancellationPart>[],
      );

  final int daysBefore;
  final String rulePercent;
  final PosMoney paid;
  final PosMoney computedRetained;
  final PosMoney retained;
  final PosMoney refund;
  final bool overridden;
  final PosMoney cashRefund;
  final PosMoney transferRefund;
  final List<PosBanquetCancellationPart> rows;
}

/// X/Z dagi `banquets` qismi (P6-53): smenada olingan tadbir avanslari (bo'nak — sotuvga kirmaydi; naqdi kutilgan
/// naqdga qo'shiladi) va bekor qilingan tadbirlar qaytarishlari (naqdi kutilgan naqddan ayiriladi).
class PosShiftBanquets {
  const PosShiftBanquets({
    required this.takenCount,
    required this.takenAmount,
    required this.takenCash,
    required this.takenCard,
    required this.takenQr,
    required this.refundedCount,
    required this.refundedAmount,
    required this.refundedCash,
    required this.drawerCash,
    required this.drawerRefunds,
  });

  static const PosShiftBanquets none = PosShiftBanquets(
    takenCount: 0,
    takenAmount: PosMoney.zero,
    takenCash: PosMoney.zero,
    takenCard: PosMoney.zero,
    takenQr: PosMoney.zero,
    refundedCount: 0,
    refundedAmount: PosMoney.zero,
    refundedCash: PosMoney.zero,
    drawerCash: PosMoney.zero,
    drawerRefunds: PosMoney.zero,
  );

  final int takenCount;
  final PosMoney takenAmount;
  final PosMoney takenCash;
  final PosMoney takenCard;
  final PosMoney takenQr;
  final int refundedCount;
  final PosMoney refundedAmount;
  final PosMoney refundedCash;

  /// `cash.banquets`: kassaga tushgan naqd avans.
  final PosMoney drawerCash;

  /// `cash.banquet_refunds`: kassadan qaytarilgan naqd.
  final PosMoney drawerRefunds;

  bool get isEmpty => takenCount == 0 && refundedCount == 0;
}

/// X/Z ning `banquets` qismi (eski server: [PosShiftBanquets.none]).
extension PosShiftReportBanquets on PosShiftReport {
  PosShiftBanquets get banquets {
    final Object? block = raw['banquets'];
    if (block is! Map) return PosShiftBanquets.none;
    const PosJson empty = PosJson(<String, Object?>{});
    final PosJson b = PosJson(block.cast<String, Object?>());
    final PosJson taken = b.objOrNull('taken') ?? empty;
    final PosJson refunded = b.objOrNull('refunded') ?? empty;
    final Object? cashBlock = raw['cash'];
    final PosJson cash = cashBlock is Map
        ? PosJson(cashBlock.cast<String, Object?>())
        : empty;
    return PosShiftBanquets(
      takenCount: taken.intOrNull('count') ?? 0,
      takenAmount: taken.money('amount'),
      takenCash: taken.money('cash'),
      takenCard: taken.money('card'),
      takenQr: taken.money('qr'),
      refundedCount: refunded.intOrNull('count') ?? 0,
      refundedAmount: refunded.money('amount'),
      refundedCash: refunded.money('cash'),
      drawerCash: cash.money('banquets'),
      drawerRefunds: cash.money('banquet_refunds'),
    );
  }
}
