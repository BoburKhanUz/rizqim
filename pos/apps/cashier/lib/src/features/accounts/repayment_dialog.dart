import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/pos_scope.dart';
import '../billing/cashier_ui.dart';
import '../billing/receipts.dart';
import '../billing/tender_form.dart';

/// Nasiya qarzini to'lash: naqd, karta yoki QR; qarzdan ko'p emas. Har to'lovga kredit cheki chop etiladi (QR va
/// fiskal belgisiz), naqdda kassa qutisi ochiladi.
Future<PosAccountWithEntries?> showRepaymentDialog(
  BuildContext context,
  PosCustomerAccount account,
) => showScopedDialog<PosAccountWithEntries>(
  context,
  (BuildContext _) => _RepaymentDialog(account: account),
);

class _RepaymentDialog extends StatefulWidget {
  const _RepaymentDialog({required this.account});

  final PosCustomerAccount account;

  @override
  State<_RepaymentDialog> createState() => _RepaymentDialogState();
}

class _RepaymentDialogState extends State<_RepaymentDialog> {
  late final TenderDraft _draft = TenderDraft(amount: widget.account.balance);
  String? _error;
  bool _busy = false;

  /// Javob kelmagan to'lov: o'sha kalit bilan qayta yuboriladi.
  String? _key;

  Future<void> _submit() async {
    final String? error = _draft.validate(
      max: widget.account.balance,
      maxLabel: 'qarzdan',
    );
    setState(() => _error = error);
    if (error != null) return;
    setState(() => _busy = true);
    final PosScope scope = PosScope.of(context);
    try {
      final PosAccountWithEntries r = await scope.data.accounts.repay(
        widget.account.uuid,
        amount: _draft.amount!,
        tender: _draft.tender.wire,
        cardType: _draft.tender == PosTender.card ? _draft.cardType : null,
        transactionId: _draft.tender == PosTender.card
            ? _draft.transactionId.trim()
            : null,
        idempotencyKey: _key,
      );
      final PosFiscalState? fiscal = r.fiscal;
      final PosFiscalReceiptDetail? detail = fiscal == null
          ? null
          : await receiptOf(scope.data, fiscal);
      if (!mounted) return;
      final ReceiptPrinter printer = ReceiptPrinter(scope);
      final FiscalReceipt doc = creditReceiptOf(
        account: r.account,
        entry: r.entries.first,
        detail: detail,
        header: printer.header,
        now: scope.services.now(),
        cashier: printer.cashier,
      );
      await printer.print(
        context,
        (PaperWidth paper) => layoutFiscalReceipt(doc, paper),
        openDrawer: _draft.tender == PosTender.cash,
      );
      if (!mounted) return;
      showPosMessage(
        context,
        "Qarz to'lovi qabul qilindi. Qolgan qarz: ${r.account.balance.format()}",
      );
      Navigator.pop(context, r);
    } on PosNetworkException catch (e) {
      _key = e.idempotencyKey;
      if (mounted) {
        setState(() {
          _busy = false;
          _error = "Aloqa yo'q: to'lov holati noma'lum. Qayta yuborish xavfsiz — ikki marta yozilmaydi.";
        });
      }
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
      title: Text("Qarz to'lovi · ${widget.account.name}"),
      content: SizedBox(
        width: 480,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              AmountRow(
                label: 'Qarz',
                amount: widget.account.balance,
                bold: true,
                tone: StatusTone.danger,
              ),
              Text(
                "Kredit cheki chiqadi (QR va fiskal belgisiz). Qisman to'lash mumkin.",
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
              const SizedBox(height: 16),
              TenderForm(
                draft: _draft,
                amountLabel: "To'lanadigan summa",
                error: _error,
                onChanged: () => setState(() => _error = null),
              ),
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
          key: const ValueKey<String>('repay-submit'),
          label: 'Qabul qilish',
          loading: _busy,
          onPressed: _submit,
        ),
      ],
    );
  }
}
