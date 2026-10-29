import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';

import '../features/billing/cashier_ui.dart';

/// Bank terminali jami bilan Rizqim'dagi karta pulning farqi: ton va yozuv. Farq hech qachon smenani to'xtatmaydi.
({String label, StatusTone tone}) bankDifferenceTone(
  PosMoney difference,
  PosMoney threshold,
) {
  if (difference.isZero) return (label: "Farq yo'q", tone: StatusTone.success);
  return difference.abs() > threshold
      ? (label: 'Farq chegaradan katta', tone: StatusTone.danger)
      : (label: 'Farq bor', tone: StatusTone.warning);
}

String _signed(PosMoney m) =>
    m.isNegative ? '−${m.abs().format()}' : m.format();

/// Yopilgan smenaning bank terminali hisoboti (`bank_terminal`): terminal jami, Rizqim'dagi karta pul va farq. `null`
/// (kiritilmagan yoki modul o'chiq) bo'lsa hech narsa ko'rsatilmaydi.
class BankTerminalSummary extends StatelessWidget {
  const BankTerminalSummary({super.key, required this.report});

  final PosBankTerminalReport? report;

  @override
  Widget build(BuildContext context) {
    final PosBankTerminalReport? r = report;
    if (r == null) return const SizedBox.shrink();
    final ({String label, StatusTone tone}) verdict = bankDifferenceTone(
      r.difference,
      r.threshold,
    );
    return Column(
      key: const ValueKey<String>('bank-terminal-summary'),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        const Divider(height: 24),
        AmountRow(label: 'Bank terminali', amount: r.bankTotal),
        AmountRow(label: 'Rizqim (karta)', amount: r.rizqimTotal),
        AmountRow(
          label: 'Farq (bank − Rizqim)',
          amount: r.difference,
          bold: true,
          tone: verdict.tone,
        ),
        if (r.overThreshold) ...<Widget>[
          const SizedBox(height: 8),
          NoticeBanner(
            key: const ValueKey<String>('bank-terminal-alert'),
            message:
                "Farq ${_signed(r.difference)}: chegaradan katta, egaga ogohlantirish yuborildi.",
            tone: StatusTone.danger,
          ),
        ] else if (r.hasDifference) ...<Widget>[
          const SizedBox(height: 8),
          const NoticeBanner(
            message: "Bank terminali va Rizqim'dagi karta pul mos kelmadi (chegaradan kichik).",
            tone: StatusTone.warning,
          ),
        ],
      ],
    );
  }
}
