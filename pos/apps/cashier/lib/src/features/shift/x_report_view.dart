import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../menu_day/write_off_dialog.dart';
import '../../shell/pos_scope.dart';
import '../billing/cashier_ui.dart';
import '../billing/receipts.dart';
import 'shift_report_mapping.dart';

/// X (yoki Z) hisobotning to'liq ko'rinishi: savdo, to'lov turlari (avans va nasiya alohida — ular bugungi pul emas),
/// karta turlari, kanallar, ofitsiantlar, chegirma, o'chirish, qaytarish, avans, nasiya, kassa va kutilgan naqd.
class ShiftReportBody extends StatelessWidget {
  const ShiftReportBody({super.key, required this.view});

  final ShiftReportView view;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final ShiftReportView v = view;
    Widget title(String t) => Padding(
      padding: const EdgeInsets.only(top: 14, bottom: 4),
      child: Text(t, style: RzType.headline.copyWith(color: c.fg)),
    );
    final List<({String tender, int count, PosMoney amount})> money =
        <({String tender, int count, PosMoney amount})>[
          for (final ({String tender, int count, PosMoney amount}) t
              in v.byTender)
            if (PosTender.fromWire(t.tender)?.isMoneyReceived ?? true) t,
        ];
    final List<({String tender, int count, PosMoney amount})> notMoney =
        <({String tender, int count, PosMoney amount})>[
          for (final ({String tender, int count, PosMoney amount}) t
              in v.byTender)
            if (!(PosTender.fromWire(t.tender)?.isMoneyReceived ?? true)) t,
        ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        title('Savdo'),
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 3),
          child: Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  'Cheklar soni',
                  style: RzType.body.copyWith(color: c.fg),
                ),
              ),
              Text('${v.bills}', style: RzType.body.copyWith(color: c.fg)),
            ],
          ),
        ),
        AmountRow(label: 'Sotuv (cheklar jami)', amount: v.billsTotal),
        AmountRow(label: "Olingan to'lovlar", amount: v.received),
        if (v.refunded.isPositive)
          AmountRow(label: 'Qaytarilgan', amount: v.refunded, negative: true),
        AmountRow(label: 'Sof tushum', amount: v.net, bold: true),
        if (v.averageBill.isPositive)
          AmountRow(label: "O'rtacha chek", amount: v.averageBill, muted: true),
        if (v.roomFee.isPositive)
          AmountRow(label: 'Xona puli', amount: v.roomFee, muted: true),
        if (v.serviceCharge.isPositive)
          AmountRow(label: 'Xizmat haqi', amount: v.serviceCharge, muted: true),
        // P6-34: the packaging lines sold (their own receipt lines)
        if (v.packaging.isPositive)
          AmountRow(label: 'Qadoq', amount: v.packaging, muted: true),
        if (v.discount.isPositive)
          AmountRow(label: 'Chegirma', amount: v.discount, muted: true),
        if (v.voidsCount > 0)
          AmountRow(
            label: "O'chirilgan taomlar (${v.voidsCount})",
            amount: v.voidsAmount,
            muted: true,
          ),
        title("To'lov turlari"),
        for (final ({String tender, int count, PosMoney amount}) t in money)
          AmountRow(
            label: '${tenderLabel(t.tender)} (${t.count})',
            amount: t.amount,
          ),
        for (final ({String type, PosMoney amount}) card in v.cards)
          AmountRow(
            label: '   ${cardTypes[card.type] ?? card.type}',
            amount: card.amount,
            muted: true,
          ),
        if (notMoney.isNotEmpty) ...<Widget>[
          title("Pul emas (bugun kassaga tushmagan)"),
          for (final ({String tender, int count, PosMoney amount}) t
              in notMoney)
            AmountRow(
              label: '${tenderLabel(t.tender)} (${t.count})',
              amount: t.amount,
              muted: true,
            ),
        ],
        title('Avans va nasiya'),
        AmountRow(label: 'Olingan avanslar', amount: v.depositsTaken),
        AmountRow(label: 'Sotuvda ayirilgan avans', amount: v.depositsApplied),
        if (v.depositsRefunded.isPositive)
          AmountRow(label: 'Qaytarilgan avans', amount: v.depositsRefunded),
        AmountRow(label: 'Nasiyaga sotuv', amount: v.creditSales),
        AmountRow(label: "Qarz to'lovlari", amount: v.creditRepayments),
        if (v.byChannel.isNotEmpty) ...<Widget>[
          title('Kanallar'),
          for (final ({String channel, int orders, PosMoney amount}) ch
              in v.byChannel)
            AmountRow(
              label: '${channelLabel(ch.channel)} (${ch.orders})',
              amount: ch.amount,
            ),
        ],
        if (v.byWaiter.isNotEmpty) ...<Widget>[
          title('Ofitsiantlar'),
          for (final ({String name, PosMoney amount, PosMoney service}) w
              in v.byWaiter)
            AmountRow(
              label: w.name,
              amount: w.amount,
              trailing: 'xizmat ${w.service.formatAmount()}',
            ),
        ],
        // P6-22: tips are not revenue — their own section, never in the sales above
        if (v.tipLines.isNotEmpty) ...<Widget>[
          title('Choy puli (tushum emas)'),
          for (final ReportAmount t in v.tipLines)
            AmountRow(
              label: t.count == null ? t.label : '${t.label} (${t.count})',
              amount: t.amount,
              bold: t.label == 'Jami',
              muted: t.label.startsWith('  '),
            ),
        ],
        if (v.report.batchWriteoffs.items.isNotEmpty) ...<Widget>[
          title('Hisobdan chiqarilgan taomlar'),
          ShiftWriteOffsList(writeOffs: v.report.batchWriteoffs),
        ],
        title('Kassa (naqd)'),
        AmountRow(label: "Boshlang'ich", amount: v.openingCash),
        AmountRow(label: 'Naqd sotuv', amount: v.cashSales),
        if (v.cashDeposits.isPositive)
          AmountRow(label: 'Naqd avans', amount: v.cashDeposits),
        if (v.cashRepayments.isPositive)
          AmountRow(label: "Naqd qarz to'lovi", amount: v.cashRepayments),
        if (v.cashIn.isPositive) AmountRow(label: 'Kirim', amount: v.cashIn),
        if (v.cashTips.isPositive)
          AmountRow(
            key: const ValueKey<String>('x-cash-tips'),
            label: 'Choy puli (naqd)',
            amount: v.cashTips,
          ),
        if (v.cashRefunds.isPositive)
          AmountRow(
            label: 'Naqd qaytarish',
            amount: v.cashRefunds,
            negative: true,
          ),
        if (v.cashDepositRefunds.isPositive)
          AmountRow(
            label: 'Avans qaytarish',
            amount: v.cashDepositRefunds,
            negative: true,
          ),
        if (v.purchases.isPositive)
          AmountRow(label: 'Bozorlik', amount: v.purchases, negative: true),
        if (v.wages.isPositive)
          AmountRow(label: 'Ish haqi', amount: v.wages, negative: true),
        if (v.tipPayouts.isPositive)
          AmountRow(
            key: const ValueKey<String>('x-tip-payouts'),
            label: 'Choy puli berildi',
            amount: v.tipPayouts,
            negative: true,
          ),
        if (v.cashOut.isPositive)
          AmountRow(label: 'Chiqim', amount: v.cashOut, negative: true),
        const Divider(height: 20),
        AmountRow(
          label: 'Kassada bo\'lishi kerak',
          amount: v.expectedCash,
          bold: true,
          large: true,
        ),
      ],
    );
  }
}

/// X hisobot oynasi: ko'rish va chop etish.
Future<void> showXReport(
  BuildContext context,
  PosShift shift,
  PosShiftReport report,
) => showScopedDialog<void>(context, (BuildContext context) {
  final ShiftReportView view = ShiftReportView(report);
  final PosScope scope = PosScope.of(context);
  return AlertDialog(
    title: Text(
      'X-hisobot · ${report.generatedAt == null ? '' : '${dm(report.generatedAt!)} ${hm(report.generatedAt!)}'}',
    ),
    content: SizedBox(
      width: 520,
      child: SingleChildScrollView(child: ShiftReportBody(view: view)),
    ),
    actions: <Widget>[
      RzButton(
        label: 'Yopish',
        variant: RzButtonVariant.ghost,
        onPressed: () => Navigator.pop(context),
      ),
      RzButton(
        label: 'Chop etish',
        icon: Icons.print_outlined,
        onPressed: () async {
          final ReceiptPrinter printer = ReceiptPrinter(scope);
          final ShiftReport doc = view.toDocument(
            kind: ShiftReportKind.x,
            header: printer.header,
            shift: shift,
            printedAt: scope.services.now(),
          );
          final bool ok = await printer.print(
            context,
            (PaperWidth paper) => layoutShiftReport(doc, paper),
          );
          if (ok && context.mounted) {
            showPosMessage(context, 'X-hisobot chop etildi.');
          }
        },
      ),
    ],
  );
});

/// Hisobot karta: katta raqam va yorliq.
class StatTile extends StatelessWidget {
  const StatTile({
    super.key,
    required this.label,
    required this.value,
    this.tone,
  });

  final String label;
  final String value;
  final StatusTone? tone;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return ConstrainedBox(
      constraints: const BoxConstraints(minWidth: 180),
      child: PosCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text(label, style: RzType.footnote.copyWith(color: c.fgMuted)),
            const SizedBox(height: 4),
            Text(
              value,
              style: RzType.title.copyWith(
                color: tone == null ? c.fg : toneColors(c, tone!).$2,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
