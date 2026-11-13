import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/customer_display.dart' show QrView;
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/pos_scope.dart';
import '../billing/cashier_ui.dart';
import '../billing/payment_messages.dart';
import '../billing/receipts.dart';
import '../billing/tender_form.dart';

/// P6-43: "Sertifikat sotish" (menyudan tashqari sotuv, docs/API.md "Sertifikat va vaucher"): qiymat (tez summalar),
/// to'lov turi (naqd — qaytim, karta — turi va tranzaksiya raqami, QR), xaridor ismi va telefoni (ixtiyoriy). Server
/// pulni smenaga yozadi va bo'nak (avans) chekini chiqaradi; kassa bo'nak chekini va sertifikat varag'ini (QR va kod)
/// chop etadi, keyin sertifikatni ko'rsatadi ([showVoucherPreview]). Internetsiz — oddiy xabar.
Future<PosVoucher?> showVoucherSellDialog(BuildContext context) =>
    showScopedDialog<PosVoucher>(
      context,
      (BuildContext context) => const _VoucherSellDialog(),
      barrierDismissible: false,
    );

/// Sertifikat tayyor: QR, kod, qiymat va muddat; "Qayta chop etish" — NUSXA.
Future<void> showVoucherPreview(BuildContext context, PosVoucher voucher) =>
    showScopedDialog<void>(
      context,
      (BuildContext context) => _VoucherPreview(voucher: voucher),
    );

/// Sertifikat varag'ini chop etadi ([copy] — NUSXA).
Future<bool> printGiftCertificate(
  BuildContext context,
  PosVoucher voucher, {
  bool copy = false,
}) {
  final PosScope scope = PosScope.of(context);
  final ReceiptPrinter printer = ReceiptPrinter(scope);
  final GiftCertificate doc = giftCertificateOf(
    voucher: voucher,
    header: printer.header,
    now: scope.services.now(),
    copy: copy,
  );
  return printer.print(
    context,
    (PaperWidth paper) => layoutGiftCertificate(doc, paper),
  );
}

class _VoucherSellDialog extends StatefulWidget {
  const _VoucherSellDialog();

  @override
  State<_VoucherSellDialog> createState() => _VoucherSellDialogState();
}

class _VoucherSellDialogState extends State<_VoucherSellDialog> {
  static const List<int> _quick = <int>[50000, 100000, 200000, 500000];

  final TenderDraft _draft = TenderDraft();
  final TextEditingController _name = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final LostRequest _lost = LostRequest();
  String? _error;
  bool _busy = false;
  int _formKey = 0;

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  String? _phoneValue() {
    final String digits = _phone.text.replaceAll(RegExp(r'\D'), '');
    if (digits.isEmpty) return null;
    if (digits.length == 9) return '+998$digits';
    if (digits.length == 12 && digits.startsWith('998')) return '+$digits';
    return '';
  }

  Future<void> _sell() async {
    final String? error = _draft.validate();
    final String? phone = _phoneValue();
    if (error != null || phone == '') {
      setState(
        () => _error =
            error ?? "Telefon raqamini to'liq kiriting (90 123 45 67).",
      );
      return;
    }
    final PosMoney value = _draft.amount!;
    if (value < const PosMoney.som(1000)) {
      setState(() => _error = "Qiymat kamida 1 000 so'm.");
      return;
    }
    final PosScope scope = PosScope.of(context);
    final PosVoucherSale sale = PosVoucherSale(
      faceValue: value.roundToSom(),
      tender: _draft.tender,
      cardType: _draft.tender == PosTender.card ? _draft.cardType : null,
      transactionId: _draft.tender == PosTender.card
          ? _draft.transactionId.trim()
          : null,
      reference:
          _draft.tender == PosTender.qr && _draft.reference.trim().isNotEmpty
          ? _draft.reference.trim()
          : null,
      buyerName: _name.text.trim().isEmpty ? null : _name.text.trim(),
      buyerPhone: phone,
    );
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final PosVoucher v = await _lost.run<PosVoucher>(
        context,
        sale.toJson().toString(),
        (String? _, String? key) => scope.data.vouchers.sell(
          sale,
          shiftUuid: scope.session.shift?.uuid,
          idempotencyKey: key,
        ),
      );
      if (!mounted) return;
      // The advance receipt (the money is taken now), then the certificate sheet itself
      final PosFiscalReceiptDetail? detail = await receiptOf(
        scope.data,
        PosFiscalState(
          status: v.fiscalStatus,
          receiptUuid: v.fiscalReceiptUuid,
        ),
      );
      if (!mounted) return;
      final ReceiptPrinter printer = ReceiptPrinter(scope);
      final FiscalReceipt advance = voucherAdvanceReceiptOf(
        voucher: v,
        header: printer.header,
        now: scope.services.now(),
        detail: detail,
        cashier: printer.cashier,
        cardType: sale.cardType,
        transactionId: sale.transactionId,
      );
      await printer.print(
        context,
        (PaperWidth paper) => layoutFiscalReceipt(advance, paper),
        openDrawer: sale.tender == PosTender.cash,
      );
      if (!mounted) return;
      await printGiftCertificate(context, v);
      if (mounted) Navigator.pop(context, v);
    } on PosNetworkException {
      if (mounted) {
        setState(
          () => _error = "Aloqa yo'q: sotuv holati noma'lum. Xuddi shu sotuvni qayta yuborish xavfsiz — ikki marta yozilmaydi.",
        );
      }
    } on PosApprovalCancelled {
      // nothing
    } on PosException catch (e) {
      if (mounted) {
        setState(
          () => _error = switch (e) {
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
    return AlertDialog(
      title: const Text('Sertifikat sotish'),
      content: SizedBox(
        width: 480,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                "Sovg'a sertifikati — oldindan to'lov: bo'nak cheki chiqadi, mijoz keyin sertifikat bilan to'laydi.",
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  for (final int v in _quick)
                    ChoiceChip(
                      key: ValueKey<String>('voucher-value-$v'),
                      label: Text(PosMoney.som(v).formatAmount()),
                      selected: _draft.amount == PosMoney.som(v),
                      onSelected: (_) => setState(() {
                        _draft.amount = PosMoney.som(v);
                        _error = null;
                        _formKey++;
                      }),
                    ),
                ],
              ),
              const SizedBox(height: 12),
              TenderForm(
                key: ValueKey<int>(_formKey),
                draft: _draft,
                amountLabel: 'Sertifikat qiymati',
                onChanged: () => setState(() => _error = null),
              ),
              const SizedBox(height: 12),
              TextField(
                key: const ValueKey<String>('voucher-buyer'),
                controller: _name,
                decoration: const InputDecoration(
                  labelText: 'Kimga (ixtiyoriy)',
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                key: const ValueKey<String>('voucher-phone'),
                controller: _phone,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Xaridor telefoni (ixtiyoriy)',
                  prefixText: '+998 ',
                ),
              ),
              if (_error != null) ...<Widget>[
                const SizedBox(height: 12),
                Text(
                  _error!,
                  key: const ValueKey<String>('voucher-sell-error'),
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
          key: const ValueKey<String>('voucher-sell-submit'),
          label: 'Sotish',
          icon: Icons.card_giftcard_rounded,
          loading: _busy,
          onPressed: _busy ? null : _sell,
        ),
      ],
    );
  }
}

class _VoucherPreview extends StatelessWidget {
  const _VoucherPreview({required this.voucher});

  final PosVoucher voucher;

  static String _day(String iso) => iso.length >= 10
      ? '${iso.substring(8, 10)}.${iso.substring(5, 7)}.${iso.substring(0, 4)}'
      : iso;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosVoucherPrint p = voucher.print;
    return AlertDialog(
      title: const Text('Sertifikat tayyor'),
      content: SizedBox(
        width: 360,
        child: SingleChildScrollView(
          child: Column(
            key: const ValueKey<String>('voucher-preview'),
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              if (p.store != null)
                Text(
                  p.store!,
                  textAlign: TextAlign.center,
                  style: RzType.body.copyWith(color: c.fgMuted),
                ),
              Text(
                "SOVG'A SERTIFIKATI",
                textAlign: TextAlign.center,
                style: RzType.headline.copyWith(color: c.fg),
              ),
              const SizedBox(height: 4),
              Text(
                p.value.format(),
                textAlign: TextAlign.center,
                style: RzType.display.copyWith(
                  color: c.fg,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 12),
              Center(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(RzRadius.card),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(10),
                    child: QrView(payload: p.qr, size: 180),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                p.code,
                key: const ValueKey<String>('voucher-preview-code'),
                textAlign: TextAlign.center,
                style: RzType.title.copyWith(
                  color: c.fg,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.5,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                p.validUntil == null
                    ? 'Muddatsiz'
                    : 'Amal qiladi: ${_day(p.validUntil!)} gacha',
                textAlign: TextAlign.center,
                style: RzType.body.copyWith(color: c.fgMuted),
              ),
              if (voucher.buyerName != null)
                Text(
                  'Kimga: ${voucher.buyerName}',
                  textAlign: TextAlign.center,
                  style: RzType.body.copyWith(color: c.fgMuted),
                ),
              const SizedBox(height: 8),
              Text(
                "Bo'nak cheki va sertifikat chop etildi.",
                textAlign: TextAlign.center,
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            ],
          ),
        ),
      ),
      actions: <Widget>[
        RzButton(
          label: 'Qayta chop etish',
          icon: Icons.print_outlined,
          variant: RzButtonVariant.secondary,
          onPressed: () =>
              unawaited(printGiftCertificate(context, voucher, copy: true)),
        ),
        RzButton(label: 'Yopish', onPressed: () => Navigator.pop(context)),
      ],
    );
  }
}
