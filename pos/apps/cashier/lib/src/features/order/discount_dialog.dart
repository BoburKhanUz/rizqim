import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Chegirma oynasi natijasi: tur, API qiymati (`"10"`, `"50000"`) va sabab.
class DiscountInput {
  const DiscountInput({
    required this.kind,
    required this.value,
    required this.reason,
  });

  final PosDiscountKind kind;
  final String value;
  final String reason;
}

/// Yuzdan birlar → API satri: 1000 → `"10.00"`, 1250 → `"12.50"`.
String hundredthsText(int h) =>
    '${h ~/ 100}.${(h % 100).toString().padLeft(2, '0')}';

/// Tez sabablar (egasi qarori, 24.10.2026).
const List<String> discountReasons = <String>[
  'Doimiy mijoz',
  'Ega ruxsati',
  'Kechikish uchun uzr',
];

/// Tez foizlar.
const List<int> discountQuickPercents = <int>[5, 10, 15];

/// Kassirning chegirma chegarasi haqida bitta jumla: `orders.discount` egasi — "istalgan", aks holda filial
/// sozlamasi (`cashier_discount_max_percent`); sozlanmagan — har chegirma menejer PIN bilan.
String discountLimitText({
  required bool unlimited,
  required int limitHundredths,
}) {
  if (unlimited) return "Siz istalgan chegirmani o'zingiz bera olasiz.";
  if (limitHundredths <= 0) {
    return "Chegirma menejer PIN'i bilan beriladi.";
  }
  return "Siz ${formatPercent(hundredthsText(limitHundredths))}% gacha o'zingiz bera olasiz. Ko'prog'i — menejer PIN'i bilan.";
}

/// Buyurtmaga chegirma (P3-37): foiz yoki so'm, tez tugmalar (5%, 10%, 15%), o'z qiymati, majburiy sabab (tez sabablar
/// yoki matn) va kassirning chegarasi. Chegaradan ko'pi — server menejer tasdig'ini so'raydi (oynadan keyin
/// `withApproval`). Yopilsa — `null`.
Future<DiscountInput?> showDiscountDialog(
  BuildContext context, {
  required PosOrder order,
  required bool unlimited,
  required int limitHundredths,
}) => showDialog<DiscountInput>(
  context: context,
  builder: (BuildContext context) => DiscountDialog(
    order: order,
    unlimited: unlimited,
    limitHundredths: limitHundredths,
  ),
);

class DiscountDialog extends StatefulWidget {
  const DiscountDialog({
    super.key,
    required this.order,
    required this.unlimited,
    required this.limitHundredths,
  });

  final PosOrder order;

  /// Xodimda `orders.discount` bor: chegara yo'q.
  final bool unlimited;

  /// Kassir o'zi beradigan eng katta foiz (yuzdan birlarda; 0 — har chegirma tasdiq bilan).
  final int limitHundredths;

  @override
  State<DiscountDialog> createState() => _DiscountDialogState();
}

class _DiscountDialogState extends State<DiscountDialog> {
  late PosDiscountKind _kind;
  final TextEditingController _value = TextEditingController();
  final TextEditingController _reason = TextEditingController();

  @override
  void initState() {
    super.initState();
    final PosOrder o = widget.order;
    _kind = o.discountKind ?? PosDiscountKind.percent;
    final String? v = o.discountValue;
    if (v != null) _value.text = _plain(v);
    _reason.text = o.discountReason ?? '';
    _value.addListener(_changed);
    _reason.addListener(_changed);
  }

  void _changed() => setState(() {});

  @override
  void dispose() {
    _value.dispose();
    _reason.dispose();
    super.dispose();
  }

  /// `"10.00"` → `"10"`, `"50000.00"` → `"50000"`.
  static String _plain(String raw) {
    try {
      return formatPercent(raw);
    } on FormatException {
      return raw;
    }
  }

  /// Taomlar jami (chegirma shundan olinadi).
  PosMoney get _items {
    final PosMoney s = widget.order.charges.itemsSubtotal;
    return s.isZero ? widget.order.subtotal : s;
  }

  /// Kiritilgan qiymat yuzdan birlarda (foiz) yoki tiyinda (summa); noto'g'ri — `null`.
  int? get _entered {
    final String text = _value.text
        .trim()
        .replaceAll(' ', '')
        .replaceAll(',', '.');
    if (text.isEmpty) return null;
    try {
      return percentHundredths(text);
    } on FormatException {
      return null;
    }
  }

  /// Taxminiy chegirma summasi (server aniq hisoblaydi).
  PosMoney? get _estimate {
    final int? v = _entered;
    if (v == null) return null;
    return _kind == PosDiscountKind.percent
        ? _items.percent(hundredthsText(v)).roundToSom()
        : PosMoney(v);
  }

  String? get _valueError {
    final int? v = _entered;
    if (_value.text.trim().isEmpty) return null;
    if (v == null || v <= 0) return "Qiymatni to'g'ri kiriting.";
    if (_kind == PosDiscountKind.percent && v > 10000) {
      return '100% dan oshmaydi.';
    }
    if (_kind == PosDiscountKind.amount && v > _items.tiyin) {
      return 'Taomlar summasidan (${_items.format()}) oshmaydi.';
    }
    return null;
  }

  /// Kiritilgan chegirma kassir chegarasidan ko'p (menejer PIN'i kerak bo'ladi).
  bool get _aboveLimit {
    if (widget.unlimited) return false;
    final int? v = _entered;
    if (v == null) return false;
    final int limit = widget.limitHundredths;
    if (limit <= 0) return true;
    return _kind == PosDiscountKind.percent
        ? v > limit
        : v * 10000 > limit * _items.tiyin;
  }

  bool get _valid =>
      _entered != null &&
      _entered! > 0 &&
      _valueError == null &&
      _reason.text.trim().isNotEmpty;

  void _setKind(PosDiscountKind k) {
    if (k == _kind) return;
    setState(() {
      _kind = k;
      _value.clear();
    });
  }

  void _submit() {
    if (!_valid) return;
    final int v = _entered!;
    final String value = v % 100 == 0 ? '${v ~/ 100}' : hundredthsText(v);
    Navigator.of(context).pop(
      DiscountInput(kind: _kind, value: value, reason: _reason.text.trim()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final String reason = _reason.text.trim();
    final PosMoney? estimate = _estimate;
    final String? error = _valueError;
    return AlertDialog(
      title: const Text('Chegirma'),
      content: SizedBox(
        width: 480,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                discountLimitText(
                  unlimited: widget.unlimited,
                  limitHundredths: widget.limitHundredths,
                ),
                key: const ValueKey<String>('discount-limit'),
                style: RzType.body.copyWith(color: c.fgMuted),
              ),
              const SizedBox(height: 12),
              SegmentedButton<PosDiscountKind>(
                showSelectedIcon: false,
                segments: const <ButtonSegment<PosDiscountKind>>[
                  ButtonSegment<PosDiscountKind>(
                    value: PosDiscountKind.percent,
                    label: Text('Foiz (%)'),
                    icon: Icon(Icons.percent_rounded),
                  ),
                  ButtonSegment<PosDiscountKind>(
                    value: PosDiscountKind.amount,
                    label: Text("Summa (so'm)"),
                    icon: Icon(Icons.payments_outlined),
                  ),
                ],
                selected: <PosDiscountKind>{_kind},
                onSelectionChanged: (Set<PosDiscountKind> s) =>
                    _setKind(s.first),
              ),
              const SizedBox(height: 12),
              if (_kind == PosDiscountKind.percent) ...<Widget>[
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: <Widget>[
                    for (final int p in discountQuickPercents)
                      ChoiceChip(
                        key: ValueKey<String>('discount-quick-$p'),
                        label: Text('$p%'),
                        labelStyle: RzType.headline,
                        selected: _entered == p * 100,
                        materialTapTargetSize: MaterialTapTargetSize.padded,
                        onSelected: (_) => _value.text = '$p',
                      ),
                  ],
                ),
                const SizedBox(height: 12),
              ],
              TextField(
                key: const ValueKey<String>('discount-value'),
                controller: _value,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                inputFormatters: <TextInputFormatter>[
                  FilteringTextInputFormatter.allow(RegExp(r'[0-9., ]')),
                ],
                decoration: InputDecoration(
                  labelText: _kind == PosDiscountKind.percent
                      ? 'Boshqa foiz'
                      : "Summa (so'm)",
                  suffixText: _kind == PosDiscountKind.percent ? '%' : "so'm",
                  errorText: error,
                  border: const OutlineInputBorder(),
                ),
              ),
              if (estimate != null && error == null) ...<Widget>[
                const SizedBox(height: 8),
                Text(
                  'Chegirma: ${estimate.format()} (taomlar ${_items.format()} dan)',
                  style: RzType.body.copyWith(color: c.fg),
                ),
              ],
              if (_aboveLimit && error == null) ...<Widget>[
                const SizedBox(height: 8),
                Semantics(
                  liveRegion: true,
                  child: Text(
                    "Bu chegaradan ko'p: menejer PIN'i so'raladi.",
                    key: const ValueKey<String>('discount-above-limit'),
                    style: RzType.body.copyWith(color: c.warningSoftFg),
                  ),
                ),
              ],
              const SizedBox(height: 16),
              Text('Sabab', style: RzType.headline.copyWith(color: c.fg)),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  for (final String r in discountReasons)
                    ChoiceChip(
                      label: Text(r),
                      selected: reason == r,
                      materialTapTargetSize: MaterialTapTargetSize.padded,
                      onSelected: (_) => _reason.text = r,
                    ),
                ],
              ),
              const SizedBox(height: 12),
              TextField(
                key: const ValueKey<String>('discount-reason'),
                controller: _reason,
                maxLength: 200,
                decoration: const InputDecoration(
                  labelText: 'Boshqa sabab',
                  border: OutlineInputBorder(),
                ),
              ),
            ],
          ),
        ),
      ),
      actions: <Widget>[
        RzButton(
          label: 'Bekor qilish',
          variant: RzButtonVariant.ghost,
          onPressed: () => Navigator.of(context).pop(),
        ),
        RzButton(
          key: const ValueKey<String>('discount-submit'),
          label: 'Chegirma berish',
          icon: Icons.check_rounded,
          onPressed: _valid ? _submit : null,
        ),
      ],
    );
  }
}

/// Chegirma qatorining yorlig'i: "Chegirma 10%" yoki "Chegirma".
String discountLabel(PosOrder? order) {
  final String? p = order?.discountPercent;
  if (p == null) return 'Chegirma';
  try {
    return 'Chegirma ${formatPercent(p)}%';
  } on FormatException {
    return 'Chegirma';
  }
}

/// Kassirning chegirma qoidasi (P3-37) joriy xodim va filial sozlamasidan.
class DiscountRules {
  const DiscountRules({
    required this.allowed,
    required this.unlimited,
    required this.limitHundredths,
  });

  /// Boshqa amallar kabi: ruxsat bor yoki menejer tasdig'i bilan bo'ladi.
  factory DiscountRules.of(PosSession session) => DiscountRules(
    allowed:
        session.can(PosPermissions.ordersDiscount) ||
        session.needsApproval(PosPermissions.ordersDiscount),
    unlimited: session.can(PosPermissions.ordersDiscount),
    // The own limit is the cashier's (pos.take_payment); anyone else asks a manager
    limitHundredths: session.can(PosPermissions.takePayment)
        ? session.bootstrap?.branch.settings.cashierDiscountMaxHundredths ?? 0
        : 0,
  );

  final bool allowed;
  final bool unlimited;
  final int limitHundredths;
}

/// Internetsiz rejimda chegirma tugmasining izohi.
const String discountOfflineReason = 'Chegirma uchun internet kerak.';
