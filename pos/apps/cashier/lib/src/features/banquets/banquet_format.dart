import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';

import '../billing/cashier_ui.dart';

/// P6-51…P6-54: tadbirlar bo'limining umumiy matn va rang yordamchilari.

/// Tadbir holati rangi.
StatusTone banquetStatusTone(PosBanquetStatus s) => switch (s) {
  PosBanquetStatus.hold => StatusTone.warning,
  PosBanquetStatus.confirmed => StatusTone.info,
  PosBanquetStatus.inProgress => StatusTone.success,
  PosBanquetStatus.settled => StatusTone.neutral,
  PosBanquetStatus.cancelled => StatusTone.danger,
  PosBanquetStatus.unknown => StatusTone.neutral,
};

IconData banquetStatusIcon(PosBanquetStatus s) => switch (s) {
  PosBanquetStatus.hold => Icons.hourglass_top_rounded,
  PosBanquetStatus.confirmed => Icons.verified_outlined,
  PosBanquetStatus.inProgress => Icons.celebration_outlined,
  PosBanquetStatus.settled => Icons.task_alt_rounded,
  PosBanquetStatus.cancelled => Icons.block_rounded,
  PosBanquetStatus.unknown => Icons.help_outline_rounded,
};

/// Tadbir holati yorlig'i (rang + ikonka + matn).
class BanquetStatusPill extends StatelessWidget {
  const BanquetStatusPill(this.status, {super.key});

  final PosBanquetStatus status;

  @override
  Widget build(BuildContext context) => TonePill(
    label: status.label,
    tone: banquetStatusTone(status),
    icon: banquetStatusIcon(status),
  );
}

/// "Muddati o'tgan" belgisi.
class BanquetOverduePill extends StatelessWidget {
  const BanquetOverduePill({super.key});

  @override
  Widget build(BuildContext context) => const TonePill(
    key: ValueKey<String>('banquet-overdue'),
    label: "Muddati o'tgan",
    tone: StatusTone.danger,
    icon: Icons.warning_amber_rounded,
  );
}

/// Jadval qatori holati: to'langan, kutilmoqda, muddati o'tgan, qaytarilgan, ushlab qolingan.
(String, StatusTone, IconData) paymentRowState(PosBanquetPaymentRow r) {
  if (r.overdue) {
    return ("Muddati o'tgan", StatusTone.danger, Icons.warning_amber_rounded);
  }
  return switch (r.status) {
    PosBanquetPaymentStatus.paid => (
      "To'langan",
      StatusTone.success,
      Icons.check_circle_outline_rounded,
    ),
    PosBanquetPaymentStatus.due => (
      'Kutilmoqda',
      StatusTone.warning,
      Icons.schedule_rounded,
    ),
    PosBanquetPaymentStatus.refunded => (
      'Qaytarilgan',
      StatusTone.neutral,
      Icons.undo_rounded,
    ),
    PosBanquetPaymentStatus.retained => (
      'Ushlab qolingan',
      StatusTone.neutral,
      Icons.lock_outline_rounded,
    ),
    PosBanquetPaymentStatus.unknown => (
      "Noma'lum",
      StatusTone.neutral,
      Icons.help_outline_rounded,
    ),
  };
}

/// `YYYY-MM-DD` → mahalliy kun (soat 00:00).
DateTime banquetDay(String iso) {
  final DateTime? d = DateTime.tryParse(iso);
  if (d == null) return DateTime(2000);
  return DateTime(d.year, d.month, d.day);
}

/// `YYYY-MM-DD` → "20.12.2026".
String banquetDate(String? iso) {
  if (iso == null || iso.length < 10) return iso ?? '';
  return '${iso.substring(8, 10)}.${iso.substring(5, 7)}.${iso.substring(0, 4)}';
}

/// Tadbir vaqti: "Kechqurun 18:00–23:00".
String banquetTime(PosBanquetEvent e) =>
    '${e.slot.label} ${e.startTime}–${e.endTime}';

/// Zallar: "Oq zal, Ko'k zal".
String banquetHalls(PosBanquetEvent e) =>
    e.halls.map((PosBanquetHall h) => h.name).join(', ');

/// To'lov turi nomi (bank o'tkazmasi ham).
String banquetTenderLabel(String? tender) => switch (tender) {
  null => '',
  'bank_transfer' => "Bank o'tkazmasi",
  _ => tenderLabel(tender),
};

/// Qaytarish yo'li.
String refundPathLabel(String? path) => switch (path) {
  'cash_drawer' => 'naqd, shu kassadan',
  'card' => 'karta, to\'langan yo\'li bilan',
  'qr' => 'QR, to\'langan yo\'li bilan',
  'bank_transfer' => "bank o'tkazmasi bilan (panelda)",
  _ => path ?? '',
};

/// Internetsiz tadbirlar ishlamaydi: oddiy xabar.
const String banquetsOfflineText =
    "Internet yo'q: tadbirlar faqat server bilan ishlaydi. Aloqa tiklangach qayta oching.";

/// A4 hujjatlar panelda.
const String banquetsA4Note =
    "Chek printerida chiqadi. A4 (shartnoma, varaq) — boshqaruv panelidagi Tadbirlar bo'limidan chop eting.";
