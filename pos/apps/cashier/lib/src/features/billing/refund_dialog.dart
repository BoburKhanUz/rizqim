import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/pos_scope.dart';
import 'cashier_ui.dart';
import 'receipts.dart';

/// Smena ichida pul qaytarish (POS_SPEC 2.10): qaysi to'lovdan (naqd, karta, QR), qancha, sabab; `pos.refund` yoki
/// menejer tasdig'i. Qaytarish cheki chop etiladi, naqdda kassa qutisi ochiladi.
Future<PosRefund?> showRefundDialog(
  BuildContext context, {
  required PosBill bill,
  PosOrder? order,
}) => showScopedDialog<PosRefund>(
  context,
  (BuildContext _) => _RefundDialog(bill: bill, order: order),
);

class _RefundDialog extends StatefulWidget {
  const _RefundDialog({required this.bill, this.order});

  final PosBill bill;
  final PosOrder? order;

  @override
  State<_RefundDialog> createState() => _RefundDialogState();
}

class _RefundDialogState extends State<_RefundDialog> {
  late final List<PosPayment> _payments = <PosPayment>[
    for (final PosPayment p in widget.bill.payments)
      if (<String>['cash', 'card', 'qr'].contains(p.tender) &&
          p.status != 'refunded')
        p,
  ];
  late PosPayment? _payment = _payments.isEmpty ? null : _payments.first;
  late final TextEditingController _amount = TextEditingController(
    text: moneyText(_payment?.amount),
  );
  final TextEditingController _reason = TextEditingController();
  String? _error;
  bool _busy = false;

  static const List<String> _presets = <String>[
    'Taom sifatsiz',
    "Xato to'lov",
    'Mijoz voz kechdi',
  ];

  @override
  void dispose() {
    _amount.dispose();
    _reason.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final PosPayment? p = _payment;
    final PosMoney? amount = parseMoney(_amount.text);
    final String reason = _reason.text.trim();
    setState(() {
      _error = p == null
          ? "To'lovni tanlang."
          : (amount == null || !amount.isPositive)
          ? 'Summani kiriting.'
          : amount > p.amount
          ? "Qaytarish to'lovdan ko'p bo'lmaydi."
          : reason.isEmpty
          ? 'Sababini yozing.'
          : null;
    });
    if (_error != null) return;
    setState(() => _busy = true);
    final PosScope scope = PosScope.of(context);
    try {
      final PosRefund refund = await withApproval<PosRefund>(
        context,
        (String? token) => scope.data.billing.refund(
          paymentUuid: p!.uuid,
          amount: amount!,
          reason: reason,
          shiftUuid: scope.session.shift?.uuid,
          approvalToken: token,
        ),
      );
      if (!mounted) return;
      final PosFiscalReceiptDetail? detail = await receiptOf(
        scope.data,
        refund.fiscal,
      );
      if (!mounted) return;
      final ReceiptPrinter printer = ReceiptPrinter(scope);
      final FiscalReceipt doc = refundReceiptOf(
        refund: refund,
        detail: detail,
        order: widget.order,
        header: printer.header,
        now: scope.services.now(),
        cashier: printer.cashier,
      );
      await printer.print(
        context,
        (PaperWidth paper) => layoutFiscalReceipt(doc, paper),
        openDrawer: refund.method == 'cash',
      );
      if (!mounted) return;
      showPosMessage(context, '${refund.amount.format()} qaytarildi.');
      Navigator.pop(context, refund);
    } on Object catch (e) {
      if (mounted) {
        setState(() => _busy = false);
        showPosError(context, e);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return AlertDialog(
      title: const Text('Pulni qaytarish'),
      content: SizedBox(
        width: 460,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                "Faqat shu smenada olingan naqd, karta yoki QR to'lov qaytariladi. Qaytarish cheki asl sotuv chekiga "
                "bog'lanadi.",
                style: RzType.body.copyWith(color: c.fgMuted),
              ),
              const SizedBox(height: 12),
              if (_payments.isEmpty)
                Text(
                  "Qaytariladigan to'lov yo'q.",
                  style: RzType.body.copyWith(color: c.fg),
                )
              else
                ChoiceRow<PosPayment>(
                  options: <PosPayment, String>{
                    for (final PosPayment p in _payments)
                      p: '${tenderLabel(p.tender)} · ${p.amount.formatAmount()}',
                  },
                  selected: _payment,
                  onSelected: (PosPayment p) => setState(() {
                    _payment = p;
                    _amount.text = moneyText(p.amount);
                  }),
                ),
              const SizedBox(height: 12),
              MoneyField(controller: _amount, label: 'Qaytariladigan summa'),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  for (final String p in _presets)
                    ChoiceChip(
                      label: Text(p),
                      selected: _reason.text == p,
                      materialTapTargetSize: MaterialTapTargetSize.padded,
                      onSelected: (_) => setState(() => _reason.text = p),
                    ),
                ],
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _reason,
                decoration: const InputDecoration(labelText: 'Sabab'),
                onChanged: (_) => setState(() {}),
              ),
              if (_error != null) ...<Widget>[
                const SizedBox(height: 12),
                Text(
                  _error!,
                  style: RzType.body.copyWith(color: c.dangerSoftFg),
                ),
              ],
            ],
          ),
        ),
      ),
      actions: <Widget>[
        RzButton(
          label: 'Bekor qilish',
          variant: RzButtonVariant.ghost,
          onPressed: () => Navigator.pop(context),
        ),
        RzButton(
          label: 'Qaytarish',
          variant: RzButtonVariant.danger,
          onPressed: _payments.isEmpty || _busy ? null : _submit,
        ),
      ],
    );
  }
}
