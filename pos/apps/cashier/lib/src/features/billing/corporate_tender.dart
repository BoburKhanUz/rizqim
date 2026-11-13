import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'cashier_ui.dart';
import 'payment_messages.dart';

/// P6-44: "Nasiya" ichidagi "Korxona" rejimi: xodimning talon kodi (skaner yoki terish) yoki telefoni → qidirish
/// (korxona, xodim, bugun va shu oy qancha qolgani) → summa korxona hisobiga xodim kodi bilan yoziladi.
class CorporateTenderController extends ChangeNotifier {
  final TextEditingController query = TextEditingController();
  final TextEditingController amount = TextEditingController();
  PosCorporateLookup? found;
  bool busy = false;
  String? error;

  /// The phone the member was found by (review P6 wave 4, S5: then the lookup gives the talon code masked and the
  /// charge goes by the phone); `null` — found by the talon code.
  String? foundByPhone;

  /// Telefon raqamimi (9+ raqam, harfsiz) — aks holda talon kodi.
  static bool _isPhone(String q) {
    final String digits = q.replaceAll(RegExp(r'\D'), '');
    return digits.length >= 9 && !RegExp(r'[A-Za-z]').hasMatch(q);
  }

  Future<void> lookup(PosData data, PosMoney due, {String? scanned}) async {
    if (scanned != null) query.text = scanned.trim();
    final String q = query.text.trim();
    if (q.isEmpty) {
      error = 'Talon kodini skanerlang yoki xodim telefonini kiriting.';
      notifyListeners();
      return;
    }
    busy = true;
    error = null;
    notifyListeners();
    try {
      final bool phone = _isPhone(q);
      final PosCorporateLookup r = phone
          ? await data.accounts.lookupMember(phone: q)
          : await data.accounts.lookupMember(code: q);
      found = r;
      foundByPhone = phone ? q : null;
      PosMoney a = due;
      for (final PosMoney? cap in <PosMoney?>[
        r.dailyAvailable,
        r.monthAvailable,
        r.creditAvailable,
      ]) {
        if (cap != null && cap < a) a = cap;
      }
      amount.text = moneyText(a);
      error = refusalOf(r, a);
    } on PosException catch (e) {
      found = null;
      foundByPhone = null;
      error = paymentRefusalText(e) ?? e.message;
    } finally {
      busy = false;
      notifyListeners();
    }
  }

  /// Kassada oldindan aytiladigan rad sababi ([PosCorporateLookup.allows] bilan bir xil; oxirgi so'z — serverniki).
  static String? refusalOf(PosCorporateLookup r, PosMoney amount) {
    const String rest = 'Naqd yoki karta bilan oling.';
    if (!r.memberActive) return "Xodimning taloni to'xtatilgan. $rest";
    if (r.accountBlocked) return "Bu hisobga yozish to'xtatilgan. $rest";
    if (r.mealsLeft != null && r.mealsLeft! <= 0) {
      return "Bu xodim bugungi ovqatini olgan (abonement). $rest";
    }
    if (r.dailyAvailable != null && !r.dailyAvailable!.isPositive) {
      return "Xodimning bugungi limiti tugagan. $rest";
    }
    if (r.monthAvailable != null && !r.monthAvailable!.isPositive) {
      return "Korxonaning oylik limiti tugagan. $rest";
    }
    if (r.creditAvailable != null && !r.creditAvailable!.isPositive) {
      return "Korxona hisobining qarz limiti tugagan. $rest";
    }
    if (amount.isPositive && !r.allows(amount)) {
      return 'Bu summa limitdan ko\'p: ko\'pi bilan ${_max(r).format()}. Qolganini naqd yoki karta bilan oling.';
    }
    return null;
  }

  static PosMoney _max(PosCorporateLookup r) {
    PosMoney? m;
    for (final PosMoney? cap in <PosMoney?>[
      r.dailyAvailable,
      r.monthAvailable,
      r.creditAvailable,
    ]) {
      if (cap != null && (m == null || cap < m)) m = cap;
    }
    return m ?? PosMoney.zero;
  }

  /// Summa o'zgardi: limitga sig'adimi darhol ko'rinadi.
  void amountChanged(PosMoney? m) {
    final PosCorporateLookup? f = found;
    error = m == null || f == null ? null : refusalOf(f, m);
    notifyListeners();
  }

  void clear() {
    found = null;
    foundByPhone = null;
    error = null;
    query.clear();
    amount.clear();
    notifyListeners();
  }

  @override
  void dispose() {
    query.dispose();
    amount.dispose();
    super.dispose();
  }
}

/// Korxona xodimi formasi.
class CorporateTenderForm extends StatelessWidget {
  const CorporateTenderForm({
    super.key,
    required this.controller,
    required this.onLookup,
    this.formError,
  });

  final CorporateTenderController controller;
  final VoidCallback onLookup;
  final String? formError;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return ListenableBuilder(
      listenable: controller,
      builder: (BuildContext context, _) {
        final PosCorporateLookup? f = controller.found;
        final PosMoney? amount = parseMoney(controller.amount.text);
        final bool ok =
            f != null &&
            amount != null &&
            amount.isPositive &&
            f.allows(amount);
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(
                  child: TextField(
                    key: const ValueKey<String>('member-code'),
                    controller: controller.query,
                    decoration: const InputDecoration(
                      labelText: 'Talon kodi yoki telefon',
                      prefixIcon: Icon(Icons.badge_outlined),
                    ),
                    onSubmitted: (_) => onLookup(),
                  ),
                ),
                const SizedBox(width: 8),
                RzButton(
                  key: const ValueKey<String>('member-lookup'),
                  label: 'Topish',
                  variant: RzButtonVariant.secondary,
                  loading: controller.busy,
                  onPressed: controller.busy ? null : onLookup,
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              'Xodimning talonini (QR) skanerlang yoki kodini teriting.',
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
            if (f != null) ...<Widget>[
              const SizedBox(height: 12),
              PosCard(
                key: const ValueKey<String>('member-found'),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Icon(Icons.apartment_rounded, color: c.fgMuted),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            f.companyName ?? f.accountName,
                            style: RzType.body.copyWith(color: c.fgMuted),
                          ),
                        ),
                        TonePill(
                          label: ok ? 'Yozish mumkin' : 'Limit yetmaydi',
                          tone: ok ? StatusTone.success : StatusTone.danger,
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      f.memberName,
                      style: RzType.title.copyWith(
                        color: c.fg,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      <String>[
                        'Talon ${f.code}',
                        ?f.memberPhone,
                        if (!f.memberActive) "to'xtatilgan",
                      ].join(' · '),
                      style: RzType.footnote.copyWith(color: c.fgMuted),
                    ),
                    const Divider(height: 20),
                    if (f.dailyAvailable != null)
                      AmountRow(
                        key: const ValueKey<String>('member-today-left'),
                        label: 'Bugun qoldi',
                        amount: f.dailyAvailable!,
                        bold: true,
                        tone: f.dailyAvailable!.isPositive
                            ? StatusTone.success
                            : StatusTone.danger,
                      ),
                    if (f.dailyLimit != null)
                      AmountRow(
                        label:
                            'Kunlik limit (ishlatildi ${f.usedToday.formatAmount()})',
                        amount: f.dailyLimit!,
                        muted: true,
                      ),
                    if (f.mealsPerDay != null)
                      Padding(
                        key: const ValueKey<String>('member-meals'),
                        padding: const EdgeInsets.symmetric(vertical: 3),
                        child: Row(
                          children: <Widget>[
                            Expanded(
                              child: Text(
                                'Abonement: bugun ${f.mealsToday}/${f.mealsPerDay} ovqat',
                                style: RzType.body.copyWith(color: c.fg),
                              ),
                            ),
                            Text(
                              f.mealsLeft != null && f.mealsLeft! > 0
                                  ? 'yana ${f.mealsLeft}'
                                  : 'tugadi',
                              style: RzType.body.copyWith(
                                color: f.mealsLeft != null && f.mealsLeft! > 0
                                    ? c.successSoftFg
                                    : c.dangerSoftFg,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ),
                    if (f.monthAvailable != null)
                      AmountRow(
                        label: 'Korxona: shu oy qoldi',
                        amount: f.monthAvailable!,
                        muted: true,
                      ),
                    if (f.dailyAvailable == null &&
                        f.mealsPerDay == null &&
                        f.monthAvailable == null)
                      Text(
                        'Limitsiz',
                        style: RzType.body.copyWith(color: c.fgMuted),
                      ),
                    if (f.chargeApproval)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(
                          "Bu korxonaga yozish menejer tasdig'i bilan.",
                          style: RzType.footnote.copyWith(color: c.fgMuted),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              MoneyField(
                fieldKey: const ValueKey<String>('member-amount'),
                controller: controller.amount,
                label: 'Korxona hisobiga',
                helperText: "Qolgani boshqa usulda (aralash)",
                onChanged: controller.amountChanged,
              ),
            ],
            if ((controller.error ?? formError) != null) ...<Widget>[
              const SizedBox(height: 12),
              Text(
                (controller.error ?? formError)!,
                key: const ValueKey<String>('member-error'),
                style: RzType.body.copyWith(color: c.dangerSoftFg),
              ),
            ],
          ],
        );
      },
    );
  }
}

/// Korxona to'lovi qismi.
({PosTenderInput? input, String? label, String? error}) corporatePart(
  CorporateTenderController ctl,
  PosMoney remaining,
) {
  final PosCorporateLookup? f = ctl.found;
  if (f == null) {
    return (
      input: null,
      label: null,
      error: 'Avval xodimni toping (talon kodi yoki telefon).',
    );
  }
  final PosMoney? amount = parseMoney(ctl.amount.text);
  if (amount == null || !amount.isPositive) {
    return (input: null, label: null, error: 'Summani kiriting.');
  }
  if (amount > remaining) {
    return (
      input: null,
      label: null,
      error: "Summa qoldiqdan ko'p: ${remaining.format()}.",
    );
  }
  final String? refused = CorporateTenderController.refusalOf(f, amount);
  if (refused != null) return (input: null, label: null, error: refused);
  return (
    input: PosTenderInput.account(
      amount,
      accountUuid: f.accountUuid,
      // Found by phone the code is masked: the server finds the member by the same phone
      memberCode: ctl.foundByPhone == null ? f.code : null,
      memberPhone: ctl.foundByPhone,
    ),
    label: 'Korxona · ${f.companyName ?? f.accountName} · ${f.memberName}',
    error: null,
  );
}

/// Skaner kodini korxona formasiga beradi.
void scanIntoCorporate(
  CorporateTenderController ctl,
  PosData data,
  PosMoney due,
  String code,
) => unawaited(ctl.lookup(data, due, scanned: code));
