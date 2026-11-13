import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'cashier_ui.dart';
import 'payment_messages.dart';

/// Sertifikat kodi ko'rinishimi (`XXXX-XXXX-XXXX`: 12 ta lotin harf yoki raqam; bo'shliq va chiziqcha hisobga olinmaydi)
/// — skaner kodi qaysi maydonga ketishini tanlash uchun.
bool looksLikeVoucherCode(String code) {
  final String chars = code.toUpperCase().replaceAll(RegExp(r'[\s\-_]'), '');
  return chars.length == 12 &&
      RegExp(r'^[A-Z0-9]+$').hasMatch(chars) &&
      RegExp(r'[A-Z]').hasMatch(chars);
}

/// P6-43: "Sertifikat" to'lovi holati (to'lov sahifasida): kod (skaner yoki terish) → qidirish (qoldiq, muddat,
/// holat) → summa (ko'pi bilan qoldiq va schyot qarzi).
class VoucherTenderController extends ChangeNotifier {
  final TextEditingController code = TextEditingController();
  final TextEditingController amount = TextEditingController();
  PosVoucherLookup? found;
  bool busy = false;
  String? error;

  PosVoucher? get voucher => found?.voucher;

  /// Kod bo'yicha qidirish; [due] — schyot qoldig'i (summa sukut — qoldiq va balansning kichigi).
  Future<void> lookup(PosData data, PosMoney due, {String? scanned}) async {
    if (scanned != null) code.text = scanned.trim();
    final String c = code.text.trim();
    if (c.isEmpty) {
      error = 'Sertifikat kodini kiriting yoki skanerlang.';
      notifyListeners();
      return;
    }
    busy = true;
    error = null;
    notifyListeners();
    try {
      final PosVoucherLookup r = await data.vouchers.lookup(c);
      found = r;
      code.text = r.voucher.code;
      final PosMoney b = r.voucher.balance;
      amount.text = moneyText(b < due ? b : due);
      if (!r.voucher.status.canPay) {
        error = switch (r.voucher.status) {
          PosVoucherStatus.used => "Sertifikat to'liq ishlatilgan.",
          PosVoucherStatus.blocked =>
            'Sertifikat bloklangan (menejerga murojaat qiling).',
          PosVoucherStatus.expired => "Sertifikat muddati o'tgan.",
          _ => "Bu sertifikat bilan to'lab bo'lmaydi.",
        };
      }
    } on PosException catch (e) {
      found = null;
      error = paymentRefusalText(e) ?? e.message;
    } finally {
      busy = false;
      notifyListeners();
    }
  }

  void clear() {
    found = null;
    error = null;
    code.clear();
    amount.clear();
    notifyListeners();
  }

  @override
  void dispose() {
    code.dispose();
    amount.dispose();
    super.dispose();
  }
}

/// Sertifikat to'lovi formasi: kod, "Tekshirish", topilgan sertifikat (qoldiq, muddat, holat) va summa.
class VoucherTenderForm extends StatelessWidget {
  const VoucherTenderForm({
    super.key,
    required this.controller,
    required this.onLookup,
    this.formError,
  });

  final VoucherTenderController controller;
  final VoidCallback onLookup;
  final String? formError;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return ListenableBuilder(
      listenable: controller,
      builder: (BuildContext context, _) {
        final PosVoucher? v = controller.voucher;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(
                  child: TextField(
                    key: const ValueKey<String>('voucher-code'),
                    controller: controller.code,
                    textCapitalization: TextCapitalization.characters,
                    decoration: const InputDecoration(
                      labelText: 'Sertifikat kodi',
                      hintText: 'XXXX-XXXX-XXXX',
                      prefixIcon: Icon(Icons.qr_code_scanner_rounded),
                    ),
                    onSubmitted: (_) => onLookup(),
                  ),
                ),
                const SizedBox(width: 8),
                RzButton(
                  key: const ValueKey<String>('voucher-lookup'),
                  label: 'Tekshirish',
                  variant: RzButtonVariant.secondary,
                  loading: controller.busy,
                  onPressed: controller.busy ? null : onLookup,
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              'Kodni skanerlang yoki terib kiriting.',
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
            if (v != null) ...<Widget>[
              const SizedBox(height: 12),
              PosCard(
                key: const ValueKey<String>('voucher-found'),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Expanded(
                          child: Text(
                            v.code,
                            style: RzType.headline.copyWith(
                              color: c.fg,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        TonePill(
                          label: v.status.label,
                          tone: v.status.canPay
                              ? StatusTone.success
                              : StatusTone.danger,
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    AmountRow(
                      label: 'Qoldiq',
                      amount: v.balance,
                      bold: true,
                      tone: v.status.canPay
                          ? StatusTone.success
                          : StatusTone.neutral,
                    ),
                    AmountRow(
                      label: 'Qiymati',
                      amount: v.faceValue,
                      muted: true,
                    ),
                    Text(
                      v.validUntil == null
                          ? 'Muddatsiz'
                          : 'Amal qiladi: ${_day(v.validUntil!)} gacha',
                      style: RzType.footnote.copyWith(color: c.fgMuted),
                    ),
                  ],
                ),
              ),
              if (v.status.canPay) ...<Widget>[
                const SizedBox(height: 12),
                MoneyField(
                  fieldKey: const ValueKey<String>('voucher-amount'),
                  controller: controller.amount,
                  label: 'Sertifikatdan',
                  helperText:
                      "Ko'pi bilan qoldiq; qolgani boshqa usulda (aralash)",
                ),
              ],
            ],
            if ((controller.error ?? formError) != null) ...<Widget>[
              const SizedBox(height: 12),
              Text(
                (controller.error ?? formError)!,
                key: const ValueKey<String>('voucher-error'),
                style: RzType.body.copyWith(color: c.dangerSoftFg),
              ),
            ],
          ],
        );
      },
    );
  }

  static String _day(String iso) => iso.length >= 10
      ? '${iso.substring(8, 10)}.${iso.substring(5, 7)}.${iso.substring(0, 4)}'
      : iso;
}

/// Sertifikat to'lovi qismi (xato bo'lsa — matn).
({PosTenderInput? input, String? label, String? error}) voucherPart(
  VoucherTenderController ctl,
  PosMoney remaining,
) {
  final PosVoucher? v = ctl.voucher;
  if (v == null) {
    return (input: null, label: null, error: 'Avval sertifikatni tekshiring.');
  }
  if (!v.status.canPay) {
    return (input: null, label: null, error: ctl.error ?? v.status.label);
  }
  final PosMoney? amount = parseMoney(ctl.amount.text);
  if (amount == null || !amount.isPositive) {
    return (input: null, label: null, error: 'Summani kiriting.');
  }
  if (amount > v.balance) {
    return (
      input: null,
      label: null,
      error: "Sertifikatda faqat ${v.balance.format()} bor.",
    );
  }
  if (amount > remaining) {
    return (
      input: null,
      label: null,
      error: "Summa qoldiqdan ko'p: ${remaining.format()}.",
    );
  }
  return (
    input: PosTenderInput.voucher(amount, code: v.code),
    label: 'Sertifikat · ${v.code}',
    error: null,
  );
}

/// Skaner kodini sertifikat formasiga beradi (topilsa — darhol tekshiradi).
void scanIntoVoucher(
  VoucherTenderController ctl,
  PosData data,
  PosMoney due,
  String code,
) => unawaited(ctl.lookup(data, due, scanned: code));
