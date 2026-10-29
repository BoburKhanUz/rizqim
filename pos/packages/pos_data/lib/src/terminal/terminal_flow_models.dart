import 'package:pos_core/pos_core.dart';

import '../bootstrap/bootstrap_models.dart';
import '../json.dart';

/// Filial kartani qanday oladi (`terminal_flow.card_mode`, docs/POS_SPEC.md 4.10).
enum PosCardMode {
  /// Bank terminali "faqat terminal" rejimida: kassir chekdagi RRN'ni Rizqim'ga teradi, chekni Rizqim chiqaradi.
  terminalOnly('terminal_only'),

  /// Rizqim Android smart-kassada: karta qurilmaning o'zida.
  smartKassa('smart_kassa'),

  /// Karta yo'q: faqat naqd va QR.
  none('none');

  const PosCardMode(this.wire);

  final String wire;

  static PosCardMode? fromWire(String? wire) {
    for (final PosCardMode m in values) {
      if (m.wire == wire) return m;
    }
    return null;
  }
}

/// `GET /pos/bootstrap` → `terminal_flow` (P6-16; `terminal_card_flow` moduli yoqilgan filialda, bo'lmasa `null`).
/// To'lov, schyot va smenani yopish hech qachon bunga bog'liq emas: bu faqat qo'shimcha maydonlarni ko'rsatadi.
class PosTerminalFlow {
  const PosTerminalFlow({
    this.cardMode,
    this.smartKassa = false,
    this.fiscalKassaId,
    this.askBankTotal = false,
    this.differenceThreshold = PosMoney.zero,
    this.checklistComplete = false,
  });

  factory PosTerminalFlow.fromJson(PosJson j) => PosTerminalFlow(
    cardMode: PosCardMode.fromWire(j.strOrNull('card_mode')),
    smartKassa: j.boolean('smart_kassa'),
    fiscalKassaId: j.strOrNull('fiscal_kassa_id'),
    askBankTotal: j.boolean('ask_bank_total'),
    differenceThreshold: j.money('difference_threshold'),
    checklistComplete: j.boolean('checklist_complete'),
  );

  /// Karta rejimi (`null` — egasi hali tanlamagan).
  final PosCardMode? cardMode;

  /// Shu kassa Android smart-kassa (fiskal yo'nalish provayderning virtual kassasi orqali).
  final bool smartKassa;
  final String? fiscalKassaId;

  /// Smenani yopishda bank terminalining kunlik jamini so'rash.
  final bool askBankTotal;

  /// Shu farqdan katta bo'lsa egaga ogohlantirish boradi (so'm; 0 — har qanday farq).
  final PosMoney differenceThreshold;

  /// O'rnatish ro'yxati to'liq (to'liq bo'lmasa ham hech narsa bloklanmaydi: faqat eslatma).
  final bool checklistComplete;

  /// Karta to'lovida RRN va oxirgi 4 raqam maydonlari ko'rsatiladi (bank terminali alohida).
  bool get asksCardReference => cardMode == PosCardMode.terminalOnly;

  /// Smart-kassada karta jami provayderdan olinadi (sandbox).
  bool get providerTotals => smartKassa || cardMode == PosCardMode.smartKassa;
}

/// Bootstrap'ning `terminal_flow` qismi: konstruktorga tegmasdan [PosBootstrap.raw] dan o'qiladi.
extension PosBootstrapTerminalFlow on PosBootstrap {
  /// Karta oqimi; modul o'chiq, eski server yoki o'qib bo'lmasa — `null` (hech qanday qo'shimcha maydon).
  PosTerminalFlow? get terminalFlow {
    final Object? block = raw['terminal_flow'];
    if (block is! Map) return null;
    try {
      return PosTerminalFlow.fromJson(PosJson(block.cast<String, Object?>()));
    } on FormatException {
      return null;
    }
  }

  /// Yoqilgan modul (`terminal_flow` bloki bor).
  bool get hasTerminalFlow => terminalFlow != null;
}

/// Bank terminali kunlik jami kiritish manbai (`bank-report` `source`).
enum PosBankReportSource {
  manual('manual'),
  import('import'),
  provider('provider');

  const PosBankReportSource(this.wire);

  final String wire;

  static PosBankReportSource? fromWire(String? wire) {
    for (final PosBankReportSource s in values) {
      if (s.wire == wire) return s;
    }
    return null;
  }
}

/// Smenani yopishda yuboriladigan bank terminali jami (`POST /pos/shifts/{uuid}/close` `bank_terminal`). Hech qachon
/// smena yopilishiga to'sqinlik qilmaydi.
class PosBankTerminalInput {
  const PosBankTerminalInput({
    required this.total,
    this.count,
    this.terminalRef,
    this.note,
  });

  /// Terminalning kunlik (settlement) jami.
  final PosMoney total;

  /// Terminal hisobotidagi to'lovlar soni.
  final int? count;
  final String? terminalRef;
  final String? note;

  Map<String, Object?> toJson() => <String, Object?>{
    'total': total.toApiString(),
    'count': ?count,
    if (terminalRef != null && terminalRef!.trim().isNotEmpty)
      'terminal_ref': terminalRef!.trim(),
    if (note != null && note!.trim().isNotEmpty) 'note': note!.trim(),
  };
}

/// Smenaning bank terminali hisoboti (`bank_terminal` yopish javobida va `GET /pos/shifts/{uuid}` da): terminal jami
/// Rizqim'dagi karta pul bilan solishtirilgan.
class PosBankTerminalReport {
  const PosBankTerminalReport({
    required this.bankTotal,
    required this.rizqimTotal,
    required this.difference,
    this.uuid,
    this.source = PosBankReportSource.manual,
    this.bankCount,
    this.rizqimCount = 0,
    this.threshold = PosMoney.zero,
    this.overThreshold = false,
    this.terminalRef,
    this.note,
    this.unmatchedBank = 0,
    this.unmatchedRizqim = 0,
  });

  factory PosBankTerminalReport.fromJson(PosJson j) {
    final PosJson? unmatched = j.objOrNull('unmatched');
    return PosBankTerminalReport(
      uuid: j.strOrNull('uuid'),
      source:
          PosBankReportSource.fromWire(j.strOrNull('source')) ??
          PosBankReportSource.manual,
      terminalRef: j.strOrNull('terminal_ref'),
      bankTotal: j.money('bank_total'),
      bankCount: j.intOrNull('bank_count'),
      rizqimTotal: j.money('rizqim_total'),
      rizqimCount: j.intOrNull('rizqim_count') ?? 0,
      difference: j.money('difference'),
      threshold: j.money('threshold'),
      overThreshold: j.boolean('over_threshold'),
      note: j.strOrNull('note'),
      unmatchedBank: unmatched?.list('bank').length ?? 0,
      unmatchedRizqim: unmatched?.list('rizqim').length ?? 0,
    );
  }

  static PosBankTerminalReport? maybe(Object? raw) {
    if (raw is! Map) return null;
    try {
      return PosBankTerminalReport.fromJson(
        PosJson(raw.cast<String, Object?>()),
      );
    } on FormatException {
      return null;
    }
  }

  final String? uuid;
  final PosBankReportSource source;
  final String? terminalRef;

  /// Bank terminali jami.
  final PosMoney bankTotal;
  final int? bankCount;

  /// Rizqim'dagi karta pul (smena to'lovlari + avanslar + nasiya − qaytarishlar).
  final PosMoney rizqimTotal;
  final int rizqimCount;

  /// Bank − Rizqim.
  final PosMoney difference;
  final PosMoney threshold;

  /// |farq| chegaradan katta: egaga ogohlantirish ketdi.
  final bool overThreshold;
  final String? note;

  /// RRN bo'yicha juftlanmaganlar soni (`import` va `provider` manbalarida).
  final int unmatchedBank;
  final int unmatchedRizqim;

  bool get hasDifference => !difference.isZero;
}
