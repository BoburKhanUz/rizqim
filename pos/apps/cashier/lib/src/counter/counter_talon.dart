import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../features/billing/cashier_ui.dart';
import '../features/billing/receipts.dart';
import '../shell/pos_scope.dart';
import 'counter_controller.dart';

/// "Olib ketish" / "Zalda" (talon va ekrandagi raqam uchun).
String counterOrderLabel(PosChannel channel) =>
    channel == PosChannel.hall ? 'Zalda' : 'Olib ketish';

/// Navbat talonini chek printeriga chiqaradi (`pos_core` `QueueSlip`, P6-13): serverning talon matni bo'lsa — u, bo'lmasa
/// kassa olgan raqam. Printer yo'q bo'lsa [quiet] da jim (telefon rejimi: raqam ekranda ko'rsatiladi), aks holda xabar.
/// Navbatga tushdi — `true`.
Future<bool> printTalon(
  BuildContext context,
  CounterReceipt r, {
  bool quiet = false,
}) async {
  final int? number = r.number;
  if (number == null) return false;
  final ReceiptPrinter printer = ReceiptPrinter.of(context);
  if (printer.config == null && quiet) return false;
  final PosChannel channel = r.order.posChannel ?? PosChannel.takeaway;
  final QueueSlip slip =
      r.ticket?.slip(
        header: printer.header,
        createdAt: r.paidAt,
        orderCode: r.order.shortCode,
        items: r.lines,
      ) ??
      QueueSlip(
        header: printer.header,
        number: '$number',
        createdAt: r.paidAt,
        orderCode: r.order.shortCode,
        orderLabel: counterOrderLabel(channel),
        items: r.lines,
      );
  return printer.print(
    context,
    (PaperWidth paper) => layoutQueueSlip(slip, paper),
  );
}

/// Ekrandagi chek (printer bo'lmasa yoki mijoz chekni telefonda ko'rmoqchi bo'lsa): taomlar, jami, to'lov va fiskal
/// chek havolasi (soliq tekshiruv havolasi — mijoz o'z telefonida ochadi; nusxa olish tugmasi bilan).
Future<void> showCounterReceipt(BuildContext context, CounterReceipt r) {
  final PosScope scope = PosScope.of(context);
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (BuildContext _) => PosScope(
      services: scope.services,
      realtime: scope.realtime,
      navigate: scope.navigate,
      child: CounterReceiptView(receipt: r),
    ),
  );
}

class CounterReceiptView extends StatelessWidget {
  const CounterReceiptView({super.key, required this.receipt});

  final CounterReceipt receipt;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosBill bill = receipt.bill;
    final PosFiscalReceiptDetail? fiscal = bill.fiscal.receipt;
    final String? link = fiscal?.qrUrl;
    final String store =
        PosScope.maybeOf(context)?.session.storeName ?? 'Kassa';
    return SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * 0.85,
        ),
        child: ListView(
          shrinkWrap: true,
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          children: <Widget>[
            Text(
              store,
              textAlign: TextAlign.center,
              style: RzType.title.copyWith(color: c.fg),
            ),
            Text(
              <String>[
                if (receipt.order.shortCode != null)
                  '#${receipt.order.shortCode}',
                hm(receipt.paidAt.toLocal()),
                dm(receipt.paidAt.toLocal(), year: true),
              ].join(' · '),
              textAlign: TextAlign.center,
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
            if (receipt.number != null) ...<Widget>[
              const SizedBox(height: 12),
              PosQueueNumberCard(
                number: '${receipt.number}',
                orderLabel: receipt.ticket?.orderLabel,
                dense: true,
              ),
            ],
            const Divider(height: 24),
            for (final PosBillItem i in bill.items)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 3),
                child: Row(
                  children: <Widget>[
                    Expanded(
                      child: Text(
                        '${i.quantity.label(i.unit)} × ${i.productName}',
                        style: RzType.body.copyWith(color: c.fg),
                      ),
                    ),
                    Text(
                      i.amount.format(),
                      style: RzType.body.copyWith(color: c.fg),
                    ),
                  ],
                ),
              ),
            const Divider(height: 24),
            AmountRow(label: 'Jami', amount: bill.total, bold: true),
            for (final PosPayment p in bill.payments)
              AmountRow(label: tenderLabel(p.tender), amount: p.amount),
            if (bill.payments.any(
              (PosPayment p) => p.changeAmount?.isPositive ?? false,
            ))
              AmountRow(
                label: 'Qaytim',
                amount: PosMoney.sum(<PosMoney>[
                  for (final PosPayment p in bill.payments) ?p.changeAmount,
                ]),
              ),
            const SizedBox(height: 12),
            if (fiscal?.fiscalSign != null)
              Text(
                'Fiskal belgi: ${fiscal!.fiscalSign}',
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            if (fiscal?.nonFiscal ?? false)
              Text(
                'Bu fiskal chek emas (o\'quv rejimi).',
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            if (link != null) ...<Widget>[
              const SizedBox(height: 8),
              Text(
                'Chek havolasi (mijoz telefonida ochadi):',
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
              SelectableText(link, style: RzType.body.copyWith(color: c.fg)),
              Align(
                alignment: Alignment.centerLeft,
                child: TextButton.icon(
                  onPressed: () {
                    unawaited(Clipboard.setData(ClipboardData(text: link)));
                    showPosMessage(context, 'Havola nusxalandi.');
                  },
                  icon: const Icon(Icons.copy_rounded),
                  label: const Text('Havolani nusxalash'),
                ),
              ),
            ] else if (bill.fiscal.status != 'issued')
              Text(
                "Fiskal chek hali tayyor emas: tayyor bo'lganda «Navbat» bo'limidan chop etiladi.",
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
          ],
        ),
      ),
    );
  }
}
