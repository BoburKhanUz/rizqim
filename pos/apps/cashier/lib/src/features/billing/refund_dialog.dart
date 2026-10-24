import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/pos_scope.dart';
import 'cashier_ui.dart';
import 'receipts.dart';

/// Qaytarib bo'ladigan to'lov turlari (P3-35: avans va nasiya qismlari ham).
const List<String> refundableTenders = <String>[
  'cash',
  'card',
  'qr',
  'prepayment',
  'account',
];

/// To'lovdan hali qaytarilmagan summa ([refunds] — schyotlar ro'yxatidagi qaytarishlar).
PosMoney refundLeft(PosPayment p, List<PosRefund> refunds) {
  if (p.status == 'refunded') return PosMoney.zero;
  final PosMoney left =
      p.amount -
      PosMoney.sum(
        refunds
            .where((PosRefund r) => r.paymentUuid == p.uuid)
            .map((PosRefund r) => r.amount),
      );
  return left.isNegative ? PosMoney.zero : left;
}

/// Schyotda qaytariladigan to'lov bormi.
bool hasRefundable(PosBill bill, List<PosRefund> refunds) => bill.payments.any(
  (PosPayment p) =>
      refundableTenders.contains(p.tender) && refundLeft(p, refunds).isPositive,
);

/// Qaytarilganda pul qayerga boradi (dialogdagi izoh): naqd kassadan chiqadi, karta va QR o'z yo'li bilan, avans
/// bronga, nasiya qarzdan ayiriladi (oxirgi ikkisida kassadan pul chiqmaydi).
String refundEffect(String tender) => switch (tender) {
  'cash' => 'Naqd pul kassadan chiqadi (kassa qutisi ochiladi).',
  'card' || 'qr' => "Pul karta yoki QR to'lovi yo'li bilan mijozga qaytadi: kassadan naqd chiqmaydi.",
  'prepayment' =>
    "Kassadan pul chiqmaydi: summa bron avansiga qaytadi. Keyin uni boshqa schyotga ishlatish, mijozga qaytarish "
        "yoki ushlab qolish mumkin (Bronlar bo'limida).",
  'account' =>
    "Kassadan pul chiqmaydi: summa mijozning nasiya qarzidan ayiriladi.",
  _ => '',
};

/// Smena ichida pul qaytarish (POS_SPEC 2.10): qaysi to'lovdan (naqd, karta, QR, avans, nasiya), qancha, sabab;
/// `pos.refund` yoki menejer tasdig'i. Qaytarish cheki chop etiladi, naqdda kassa qutisi ochiladi. [refunds] — shu
/// buyurtmaning avvalgi qaytarishlari (to'lovdan qancha qolganini bilish uchun).
Future<PosRefund?> showRefundDialog(
  BuildContext context, {
  required PosBill bill,
  PosOrder? order,
  List<PosRefund> refunds = const <PosRefund>[],
}) => showScopedDialog<PosRefund>(
  context,
  (BuildContext _) => _RefundDialog(bill: bill, order: order, refunds: refunds),
);

class _RefundDialog extends StatefulWidget {
  const _RefundDialog({
    required this.bill,
    this.order,
    this.refunds = const <PosRefund>[],
  });

  final PosBill bill;
  final PosOrder? order;
  final List<PosRefund> refunds;

  @override
  State<_RefundDialog> createState() => _RefundDialogState();
}

class _RefundDialogState extends State<_RefundDialog> {
  late final List<PosPayment> _payments = <PosPayment>[
    for (final PosPayment p in widget.bill.payments)
      if (refundableTenders.contains(p.tender) && _left(p).isPositive) p,
  ];
  late PosPayment? _payment = _payments.isEmpty ? null : _payments.first;
  late final TextEditingController _amount = TextEditingController(
    text: moneyText(_payment == null ? null : _left(_payment!)),
  );
  final TextEditingController _reason = TextEditingController();
  String? _error;
  bool _busy = false;

  /// Javobsiz qolgan qaytarish: aynan o'zi (kalit, tasdiq) qayta yuboriladi, ikki marta qaytarilmaydi.
  final LostRequest _lost = LostRequest();

  static const List<String> _presets = <String>[
    'Taom sifatsiz',
    "Xato to'lov",
    'Mijoz voz kechdi',
  ];

  PosMoney _left(PosPayment p) => refundLeft(p, widget.refunds);

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
          : amount > _left(p)
          ? "Qaytarish to'lovdan qolganidan (${_left(p).format()}) ko'p bo'lmaydi."
          : reason.isEmpty
          ? 'Sababini yozing.'
          : null;
    });
    if (_error != null) return;
    setState(() => _busy = true);
    final PosScope scope = PosScope.of(context);
    try {
      final PosRefund refund = await _lost.run<PosRefund>(
        context,
        '${p!.uuid}|${amount!.toApiString()}|$reason',
        (String? token, String? key) => scope.data.billing.refund(
          paymentUuid: p.uuid,
          amount: amount,
          reason: reason,
          shiftUuid: scope.session.shift?.uuid,
          approvalToken: token,
          idempotencyKey: key,
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
      showPosMessage(context, switch (refund.method ?? p.tender) {
        'prepayment' =>
          '${refund.amount.format()} bron avansiga qaytdi: kassadan pul chiqmadi.',
        'account' =>
          'Mijoz qarzi ${refund.amount.format()} ga kamaydi: kassadan pul chiqmadi.',
        _ => '${refund.amount.format()} qaytarildi.',
      });
      Navigator.pop(context, refund);
    } on PosNetworkException {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = "Aloqa yo'q: qaytarish holati noma'lum. Qayta yuborish xavfsiz — ikki marta qaytarilmaydi.";
        });
      }
    } on PosApprovalCancelled {
      if (mounted) setState(() => _busy = false);
    } on Object catch (e) {
      // P3-35: the shift closed meanwhile (nothing refunded): the top bar shows the shift as it is now
      if (e is PosConflict && e.reason == PosReasons.shiftClosed) {
        unawaited(reloadShift(scope));
      }
      if (mounted) {
        setState(() {
          _busy = false;
          // Kept in the dialog: the cashier reads why (more_than_debt, reverse_exceeds_*...) and picks another amount
          _error = switch (e) {
            final PosValidationException v => v.firstError,
            final PosException x => x.message,
            _ => null,
          };
        });
        if (e is! PosException) showPosError(context, e);
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
                "Faqat shu smenada olingan to'lov qaytariladi: naqd, karta, QR, bron avansi yoki nasiya. Qaytarish "
                "cheki asl sotuv chekiga bog'lanadi.",
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
                      p: '${_tenderName(p.tender)} · ${_left(p).formatAmount()}',
                  },
                  selected: _payment,
                  onSelected: (PosPayment p) => setState(() {
                    _payment = p;
                    _amount.text = moneyText(_left(p));
                    _error = null;
                  }),
                ),
              if (_payment != null) ...<Widget>[
                const SizedBox(height: 12),
                NoticeBanner(
                  key: const ValueKey<String>('refund-effect'),
                  message: refundEffect(_payment!.tender),
                  icon: _payment!.tenderKind?.isMoneyReceived ?? true
                      ? Icons.info_outline_rounded
                      : Icons.money_off_rounded,
                  tone: StatusTone.info,
                ),
              ],
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

/// To'lov turining dialogdagi nomi (avans va nasiya — qaytarishda ma'nosi bilan).
String _tenderName(String tender) => switch (tender) {
  'prepayment' => 'Bron avansi',
  'account' => 'Nasiya',
  _ => tenderLabel(tender),
};
