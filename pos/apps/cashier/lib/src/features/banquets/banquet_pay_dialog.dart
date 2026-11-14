import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/pos_scope.dart';
import '../billing/cashier_ui.dart';
import '../billing/payment_messages.dart';
import '../billing/tender_form.dart';
import 'banquet_format.dart';
import 'banquet_printing.dart';

/// P6-53: jadval qatorini kassada olish (docs/API.md "Tadbirlar: to'lov jadvali"): naqd (qaytim), karta (turi va
/// tranzaksiya raqami) yoki statik QR; summa — qator yoki uning qismi (qolgani o'sha sanaga qoladi). Pul ochiq smenaga
/// tushadi, server BO'NAK chekini chiqaradi — kassa uni chop etadi (naqdda quti ochiladi). Javobsiz qolsa — o'sha
/// so'rov qayta yuboriladi (ikki marta yozilmaydi). Natija — yangilangan jadval.
Future<PosBanquetSchedule?> showBanquetPayDialog(
  BuildContext context, {
  required PosBanquetEvent event,
  required PosBanquetPaymentRow row,
}) => showScopedDialog<PosBanquetSchedule>(
  context,
  (BuildContext context) => BanquetPayDialog(event: event, row: row),
  barrierDismissible: false,
);

class BanquetPayDialog extends StatefulWidget {
  const BanquetPayDialog({super.key, required this.event, required this.row});

  final PosBanquetEvent event;
  final PosBanquetPaymentRow row;

  @override
  State<BanquetPayDialog> createState() => _BanquetPayDialogState();
}

class _BanquetPayDialogState extends State<BanquetPayDialog> {
  late final TenderDraft _draft = TenderDraft(amount: widget.row.amount);
  final LostRequest _lost = LostRequest();
  String? _error;
  bool _busy = false;

  Future<void> _pay() async {
    final String? error = _draft.validate(
      max: widget.row.amount,
      maxLabel: 'qatordan',
    );
    if (error != null) {
      setState(() => _error = error);
      return;
    }
    final PosScope scope = PosScope.of(context);
    final PosMoney amount = _draft.amount!;
    final PosBanquetPayInput input = PosBanquetPayInput(
      tender: _draft.tender,
      amount: amount == widget.row.amount ? null : amount,
      cardType: _draft.tender == PosTender.card ? _draft.cardType : null,
      transactionId: _draft.tender == PosTender.card
          ? _draft.transactionId.trim()
          : null,
      reference:
          _draft.tender == PosTender.qr && _draft.reference.trim().isNotEmpty
          ? _draft.reference.trim()
          : null,
    );
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final PosBanquetPaymentResult r = await _lost
          .run<PosBanquetPaymentResult>(
            context,
            '${widget.row.uuid}|${input.toJson()}',
            (String? _, String? key) => scope.data.banquetPayments.pay(
              widget.event.uuid,
              widget.row.uuid,
              input,
              shiftUuid: scope.session.shift?.uuid,
              idempotencyKey: key,
            ),
          );
      if (!mounted) return;
      await printBanquetEntry(
        context,
        event: widget.event,
        entry: r.entry,
        kind: widget.row.kind,
        detail: r.fiscal.receipt,
        openDrawer: _draft.tender == PosTender.cash,
      );
      if (!mounted) return;
      final PosMoney change = _draft.change;
      showPosMessage(
        context,
        change.isPositive
            ? "To'lov qabul qilindi. Qaytim: ${change.format()}"
            : "To'lov qabul qilindi, bo'nak cheki chop etildi.",
      );
      Navigator.pop(context, r.schedule);
    } on PosNetworkException {
      if (mounted) {
        setState(
          () => _error = "Aloqa yo'q: to'lov holati noma'lum. Xuddi shu to'lovni qayta yuborish xavfsiz — ikki marta yozilmaydi.",
        );
      }
    } on PosApprovalCancelled {
      // nothing
    } on PosException catch (e) {
      if (mounted) {
        setState(
          () => _error = switch (e) {
            final PosOfflineUnavailable _ => banquetsOfflineText,
            final PosValidationException v => v.firstError,
            _ => paymentRefusalText(e) ?? e.message,
          },
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosBanquetPaymentRow row = widget.row;
    return AlertDialog(
      title: Text("${row.label}: to'lov"),
      content: SizedBox(
        width: 480,
        child: SingleChildScrollView(
          child: Column(
            key: const ValueKey<String>('banquet-pay-dialog'),
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                widget.event.displayName,
                style: RzType.headline.copyWith(color: c.fg),
              ),
              Text(
                '${banquetDate(widget.event.eventDate)} · ${banquetHalls(widget.event)}',
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
              const SizedBox(height: 12),
              AmountRow(
                label: '${row.label} · muddati ${banquetDate(row.dueDate)}',
                amount: row.amount,
                bold: true,
                tone: row.overdue ? StatusTone.danger : null,
              ),
              const SizedBox(height: 4),
              Text(
                "Bo'nak (avans) cheki chiqadi. Qisman to'lash mumkin: qolgani o'sha sanaga yangi qator bo'lib qoladi.",
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
              const SizedBox(height: 12),
              TenderForm(
                draft: _draft,
                amountLabel: "To'lov summasi",
                onChanged: () => setState(() => _error = null),
              ),
              if (_error != null) ...<Widget>[
                const SizedBox(height: 12),
                Text(
                  _error!,
                  key: const ValueKey<String>('banquet-pay-error'),
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
          onPressed: _busy ? null : () => Navigator.pop(context),
        ),
        RzButton(
          key: const ValueKey<String>('banquet-pay-submit'),
          label: "To'lovni olish",
          icon: Icons.payments_outlined,
          loading: _busy,
          onPressed: _busy ? null : _pay,
        ),
      ],
    );
  }
}
