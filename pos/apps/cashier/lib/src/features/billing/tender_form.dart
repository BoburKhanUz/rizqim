import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../terminal/card_reference_fields.dart';
import 'cashier_ui.dart';

/// Bitta to'lov turi bo'yicha kiritilgan qiymatlar (naqd, karta yoki QR): to'lov sahifasi, bron avansi va qarz to'lovi
/// shu formadan foydalanadi.
class TenderDraft {
  TenderDraft({
    this.tender = PosTender.cash,
    this.amount,
    this.tendered,
    this.cardType,
    this.transactionId = '',
    this.cardLast4 = '',
    this.reference = '',
  });

  PosTender tender;
  PosMoney? amount;

  /// Naqd: mijoz bergan pul (qaytim uchun).
  PosMoney? tendered;
  String? cardType;
  String transactionId;

  /// P6-16: kartaning oxirgi 4 raqami (ixtiyoriy).
  String cardLast4;
  String reference;

  /// Naqd qaytim.
  PosMoney get change {
    final PosMoney? a = amount;
    final PosMoney? t = tendered;
    if (a == null || t == null || t <= a) return PosMoney.zero;
    return t - a;
  }

  /// Xato matni (to'g'ri bo'lsa `null`). [max] — eng ko'p summa (qoldiq, qarz).
  String? validate({PosMoney? max, String maxLabel = "qoldiqdan"}) {
    final PosMoney? a = amount;
    if (a == null || !a.isPositive) return 'Summani kiriting.';
    if (max != null && a > max) return "Summa $maxLabel ko'p: ${max.format()}.";
    switch (tender) {
      case PosTender.cash:
        if (tendered != null && tendered! < a) {
          return 'Berilgan pul summadan kam.';
        }
      case PosTender.card:
        if (cardType == null) return 'Karta turini tanlang.';
        if (transactionId.trim().isEmpty) {
          return 'Terminal chekidagi tranzaksiya raqamini kiriting.';
        }
        final String? last4 = cardLast4Error(cardLast4);
        if (last4 != null) return last4;
      default:
        break;
    }
    return null;
  }

  /// API uchun (naqd, karta, QR).
  PosTenderInput toInput() => switch (tender) {
    PosTender.card => PosTenderInput.card(
      amount!,
      cardType: cardType!,
      transactionId: transactionId.trim(),
      cardLast4: cardLast4.trim().isEmpty ? null : cardLast4.trim(),
    ),
    PosTender.qr => PosTenderInput.qr(
      amount!,
      reference: reference.trim().isEmpty ? null : reference.trim(),
    ),
    _ => PosTenderInput.cash(amount!, tendered: tendered),
  };
}

/// Naqd uchun tez summalar: aniq summa va undan katta yaxlit pullar (10 000, 50 000, 100 000 ...).
List<PosMoney> quickCash(PosMoney amount) {
  final int som = amount.wholeSom + (amount.tiyinPart > 0 ? 1 : 0);
  final Set<int> out = <int>{som};
  for (final int step in <int>[10000, 50000, 100000, 200000, 500000]) {
    final int up = ((som + step - 1) ~/ step) * step;
    if (up > som) out.add(up);
    if (out.length >= 5) break;
  }
  return <PosMoney>[for (final int v in out) PosMoney.som(v)];
}

/// Naqd, karta yoki QR formasi: tur, summa, (naqd) berilgan pul va qaytim, (karta) turi va tranzaksiya raqami, (QR)
/// izoh. Har o'zgarishda [onChanged].
class TenderForm extends StatefulWidget {
  const TenderForm({
    super.key,
    required this.draft,
    required this.onChanged,
    this.tenders = const <PosTender>[
      PosTender.cash,
      PosTender.card,
      PosTender.qr,
    ],
    this.showTenderChoice = true,
    this.amountLabel = 'Summa',
    this.error,
  });

  final TenderDraft draft;
  final VoidCallback onChanged;
  final List<PosTender> tenders;
  final bool showTenderChoice;
  final String amountLabel;
  final String? error;

  @override
  State<TenderForm> createState() => _TenderFormState();
}

class _TenderFormState extends State<TenderForm> {
  late final TextEditingController _amount = TextEditingController(
    text: moneyText(widget.draft.amount),
  );
  late final TextEditingController _tendered = TextEditingController(
    text: moneyText(widget.draft.tendered),
  );
  late final TextEditingController _txn = TextEditingController(
    text: widget.draft.transactionId,
  );
  late final TextEditingController _last4 = TextEditingController(
    text: widget.draft.cardLast4,
  );
  late final TextEditingController _ref = TextEditingController(
    text: widget.draft.reference,
  );

  TenderDraft get d => widget.draft;

  @override
  void didUpdateWidget(TenderForm old) {
    super.didUpdateWidget(old);
    // Tashqaridan almashtirilgan summa (masalan, "qoldiq" tugmasi)
    if (parseMoney(_amount.text) != d.amount) {
      _amount.text = moneyText(d.amount);
    }
    if (parseMoney(_tendered.text) != d.tendered) {
      _tendered.text = moneyText(d.tendered);
    }
  }

  @override
  void dispose() {
    _amount.dispose();
    _tendered.dispose();
    _txn.dispose();
    _last4.dispose();
    _ref.dispose();
    super.dispose();
  }

  void _changed() {
    setState(() {});
    widget.onChanged();
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosTerminalFlow? flow = cardFlowOf(context);
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        if (widget.showTenderChoice && widget.tenders.length > 1) ...<Widget>[
          ChoiceRow<PosTender>(
            options: <PosTender, String>{
              for (final PosTender t in widget.tenders) t: t.label,
            },
            selected: d.tender,
            onSelected: (PosTender t) {
              d.tender = t;
              _changed();
            },
          ),
          const SizedBox(height: 16),
        ],
        MoneyField(
          fieldKey: const ValueKey<String>('tender-amount'),
          controller: _amount,
          label: widget.amountLabel,
          onChanged: (PosMoney? m) {
            d.amount = m;
            _changed();
          },
        ),
        const SizedBox(height: 12),
        if (d.tender == PosTender.cash) ...<Widget>[
          MoneyField(
            fieldKey: const ValueKey<String>('tender-given'),
            controller: _tendered,
            label: 'Mijoz bergan pul',
            helperText: "Bo'sh qoldirsangiz — aynan summa",
            onChanged: (PosMoney? m) {
              d.tendered = m;
              _changed();
            },
          ),
          const SizedBox(height: 8),
          if (d.amount != null && d.amount!.isPositive)
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: <Widget>[
                for (final PosMoney m in quickCash(d.amount!))
                  ActionChip(
                    label: Text(m.formatAmount()),
                    materialTapTargetSize: MaterialTapTargetSize.padded,
                    onPressed: () {
                      d.tendered = m;
                      _tendered.text = moneyText(m);
                      _changed();
                    },
                  ),
              ],
            ),
          const SizedBox(height: 12),
          Row(
            children: <Widget>[
              Text('Qaytim', style: RzType.headline.copyWith(color: c.fgMuted)),
              const Spacer(),
              Text(
                d.change.format(),
                key: const ValueKey<String>('tender-change'),
                style: RzType.display.copyWith(
                  color: d.change.isZero ? c.fgMuted : c.successSoftFg,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ],
        if (d.tender == PosTender.card) ...<Widget>[
          if (flow?.providerTotals ?? false) ...<Widget>[
            const SmartKassaCardHint(),
            const SizedBox(height: 12),
          ],
          Text('Karta turi', style: RzType.footnote.copyWith(color: c.fgMuted)),
          const SizedBox(height: 6),
          ChoiceRow<String>(
            options: cardTypes,
            selected: d.cardType,
            onSelected: (String t) {
              d.cardType = t;
              _changed();
            },
          ),
          const SizedBox(height: 12),
          TextField(
            key: const ValueKey<String>('tender-txn'),
            controller: _txn,
            decoration: InputDecoration(labelText: cardReferenceLabel(flow)),
            onChanged: (String t) {
              d.transactionId = t;
              _changed();
            },
          ),
          // P6-16: the bank terminal is separate ("faqat terminal"): the last four digits go on the receipt too
          if (flow?.asksCardReference ?? false) ...<Widget>[
            const SizedBox(height: 12),
            CardLast4Field(
              controller: _last4,
              onChanged: (String t) {
                d.cardLast4 = t;
                _changed();
              },
            ),
          ],
        ],
        if (d.tender == PosTender.qr)
          TextField(
            controller: _ref,
            decoration: const InputDecoration(
              labelText: "To'lov raqami (ixtiyoriy)",
            ),
            onChanged: (String t) {
              d.reference = t;
              _changed();
            },
          ),
        if (widget.error != null) ...<Widget>[
          const SizedBox(height: 12),
          Text(
            widget.error!,
            style: RzType.body.copyWith(color: c.dangerSoftFg),
          ),
        ],
      ],
    );
  }
}
