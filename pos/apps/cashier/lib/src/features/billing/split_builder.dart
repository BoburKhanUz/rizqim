import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'cashier_ui.dart';

/// Schyotga hali tushmagan qator: qolgan miqdor va uning summasi.
class SplitLine {
  const SplitLine({
    required this.id,
    required this.name,
    required this.quantity,
    required this.amount,
    this.unit = PosUnit.piece,
  });

  final int id;
  final String name;
  final PosQuantity quantity;
  final PosMoney amount;
  final PosUnit unit;

  /// [share] miqdorning taxminiy summasi.
  PosMoney amountOf(PosQuantity share) => quantity.isZero
      ? PosMoney.zero
      : PosMoney((amount.tiyin * share.milli / quantity.milli).round());
}

/// Teng bo'lish: hamma ulush teng, qoldiq tiyin birinchi schyotga (backend qoidasi).
List<PosMoney> equalShares(PosMoney total, int parts) {
  final int base = total.tiyin ~/ parts;
  return <PosMoney>[
    PosMoney(total.tiyin - base * (parts - 1)),
    for (var i = 1; i < parts; i++) PosMoney(base),
  ];
}

/// "Kishi boshiga": butun so'mga yuqoriga yaxlitlangan.
PosMoney perPerson(PosMoney total, int guests) =>
    PosMoney.som((total.tiyin / guests / 100).ceil());

enum SplitMode { single, equal, items }

/// Schyot tuzish: bitta, teng N ulush yoki taom bo'yicha (kg ulushi bilan). Xizmat haqi va xona puli schyotlar
/// orasida summasiga qarab server tomonidan bo'linadi — bu yerda taomlar summasi taxminiy ko'rsatiladi.
class SplitBuilder extends StatefulWidget {
  const SplitBuilder({
    super.key,
    required this.lines,
    required this.total,
    required this.onSubmit,
    this.guests,
    this.busy = false,
    this.onCancel,
  });

  final List<SplitLine> lines;

  /// Schyotga tushadigan jami (xizmatlar bilan).
  final PosMoney total;
  final int? guests;
  final ValueChanged<PosSplit> onSubmit;
  final bool busy;

  /// Mavjud schyotlarga qaytish.
  final VoidCallback? onCancel;

  @override
  State<SplitBuilder> createState() => _SplitBuilderState();
}

class _SplitBuilderState extends State<SplitBuilder> {
  SplitMode _mode = SplitMode.single;
  late int _parts = (widget.guests ?? 0) >= 2 ? widget.guests!.clamp(2, 30) : 2;

  /// Taom bo'yicha: schyot → qator id → miqdor.
  late List<Map<int, PosQuantity>> _bills = _initialBills();
  int _target = 1;

  List<Map<int, PosQuantity>> _initialBills() => <Map<int, PosQuantity>>[
    <int, PosQuantity>{
      for (final SplitLine l in widget.lines) l.id: l.quantity,
    },
    <int, PosQuantity>{},
  ];

  @override
  void didUpdateWidget(SplitBuilder old) {
    super.didUpdateWidget(old);
    if (old.lines.length != widget.lines.length) {
      _bills = _initialBills();
      _target = 1;
    }
  }

  PosQuantity _share(int bill, int id) => _bills[bill][id] ?? PosQuantity.zero;

  PosMoney _billSum(int bill) => PosMoney.sum(
    widget.lines.map((SplitLine l) => l.amountOf(_share(bill, l.id))),
  );

  /// [id] qatorining [bill] dagi ulushini [qty] qiladi, qolgani boshqa schyotlarda qoladi.
  void _assign(int id, int bill, PosQuantity qty) {
    final SplitLine line = widget.lines.firstWhere((SplitLine l) => l.id == id);
    final PosQuantity q = qty > line.quantity ? line.quantity : qty;
    var rest = line.quantity - q;
    final List<Map<int, PosQuantity>> next = <Map<int, PosQuantity>>[
      for (final Map<int, PosQuantity> b in _bills) Map<int, PosQuantity>.of(b),
    ];
    for (var i = 0; i < next.length; i++) {
      if (i == bill) continue;
      final PosQuantity had = next[i][id] ?? PosQuantity.zero;
      final PosQuantity keep = had > rest ? rest : had;
      next[i][id] = keep;
      rest = rest - keep;
    }
    if (rest.isPositive) {
      final int other = bill == 0 ? 1 : 0;
      next[other][id] = (next[other][id] ?? PosQuantity.zero) + rest;
    }
    next[bill][id] = q;
    for (final Map<int, PosQuantity> b in next) {
      b.removeWhere((int _, PosQuantity v) => !v.isPositive);
    }
    setState(() => _bills = next);
  }

  Future<void> _askShare(SplitLine line) async {
    final PosQuantity? q = await showDialog<PosQuantity>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: Text('${line.name}: ${_target + 1}-schyotga qancha?'),
        content: SizedBox(
          width: 360,
          child: SingleChildScrollView(
            child: PosQuantityKeypad(
              unit: line.unit,
              initial: _share(_target, line.id).isPositive
                  ? _share(_target, line.id)
                  : null,
              max: line.quantity,
              min: PosQuantity.zero,
              keySize: 56,
              submitLabel: 'Belgilash',
              onSubmit: (PosQuantity v) => Navigator.pop(context, v),
            ),
          ),
        ),
        actions: <Widget>[
          RzButton(
            label: 'Bekor qilish',
            variant: RzButtonVariant.ghost,
            onPressed: () => Navigator.pop(context),
          ),
        ],
      ),
    );
    if (q != null) _assign(line.id, _target, q);
  }

  bool get _itemsValid =>
      _bills.length >= 2 &&
      _bills.every(
        (Map<int, PosQuantity> b) =>
            b.values.any((PosQuantity q) => q.isPositive),
      );

  void _submit() {
    switch (_mode) {
      case SplitMode.single:
        widget.onSubmit(const PosSplit.single());
      case SplitMode.equal:
        widget.onSubmit(PosSplit.equal(_parts));
      case SplitMode.items:
        widget.onSubmit(
          PosSplit.items(<PosSplitBill>[
            for (final Map<int, PosQuantity> b in _bills)
              PosSplitBill(items: Map<int, PosQuantity>.of(b)),
          ]),
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final bool valid = _mode != SplitMode.items || _itemsValid;
    return PosCard(
      title: 'Schyot tuzish',
      trailing: widget.onCancel == null
          ? null
          : TextButton(
              onPressed: widget.onCancel,
              child: const Text('Schyotlarga qaytish'),
            ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          SegmentedButton<SplitMode>(
            segments: const <ButtonSegment<SplitMode>>[
              ButtonSegment<SplitMode>(
                value: SplitMode.single,
                label: Text('Bitta schyot'),
                icon: Icon(Icons.receipt_outlined),
              ),
              ButtonSegment<SplitMode>(
                value: SplitMode.equal,
                label: Text("Teng bo'lish"),
                icon: Icon(Icons.people_outline_rounded),
              ),
              ButtonSegment<SplitMode>(
                value: SplitMode.items,
                label: Text("Taom bo'yicha"),
                icon: Icon(Icons.call_split_rounded),
              ),
            ],
            selected: <SplitMode>{_mode},
            showSelectedIcon: false,
            style: SegmentedButton.styleFrom(minimumSize: const Size(0, 52)),
            onSelectionChanged: (Set<SplitMode> s) =>
                setState(() => _mode = s.first),
          ),
          const SizedBox(height: 16),
          switch (_mode) {
            SplitMode.single => _single(c),
            SplitMode.equal => _equal(c),
            SplitMode.items => _items(c),
          },
          const SizedBox(height: 16),
          RzButton(
            label: 'Schyot tuzish',
            icon: Icons.receipt_long_rounded,
            size: RzButtonSize.lg,
            onPressed: valid && !widget.busy ? _submit : null,
          ),
        ],
      ),
    );
  }

  Widget _single(RzColors c) {
    final int? g = widget.guests;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        AmountRow(label: 'Jami', amount: widget.total, bold: true, large: true),
        if (g != null && g > 1)
          AmountRow(
            label: 'Kishi boshiga ($g kishi)',
            amount: perPerson(widget.total, g),
            muted: true,
          ),
      ],
    );
  }

  Widget _equal(RzColors c) {
    final List<PosMoney> shares = equalShares(widget.total, _parts);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Row(
          children: <Widget>[
            Expanded(
              child: Text(
                'Necha kishiga?',
                style: RzType.headline.copyWith(color: c.fg),
              ),
            ),
            PosQuantityStepper(
              value: PosQuantity.whole(_parts),
              min: const PosQuantity.whole(2),
              max: const PosQuantity.whole(30),
              onChanged: (PosQuantity q) =>
                  setState(() => _parts = q.milli ~/ PosQuantity.scaleFactor),
            ),
          ],
        ),
        const SizedBox(height: 12),
        for (var i = 0; i < shares.length; i++)
          AmountRow(label: '${i + 1}-schyot', amount: shares[i]),
        const Divider(height: 20),
        AmountRow(label: 'Jami', amount: widget.total, bold: true),
        Text(
          "Qoldiq tiyin birinchi schyotga qo'shiladi. Har schyot alohida to'lanadi va alohida chek oladi.",
          style: RzType.footnote.copyWith(color: c.fgMuted),
        ),
      ],
    );
  }

  Widget _items(RzColors c) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: <Widget>[
      Text(
        "Schyotni tanlang, so'ng taomni bosing — u shu schyotga o'tadi. Kg taomni bo'lish uchun «Ulush».",
        style: RzType.footnote.copyWith(color: c.fgMuted),
      ),
      const SizedBox(height: 10),
      Wrap(
        spacing: 8,
        runSpacing: 8,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: <Widget>[
          for (var i = 0; i < _bills.length; i++)
            ChoiceChip(
              key: ValueKey<String>('split-bill-$i'),
              label: Text('${i + 1}-schyot · ${_billSum(i).formatAmount()}'),
              selected: _target == i,
              materialTapTargetSize: MaterialTapTargetSize.padded,
              onSelected: (_) => setState(() => _target = i),
            ),
          if (_bills.length < 10)
            IconButton(
              tooltip: "Schyot qo'shish",
              icon: const Icon(Icons.add_rounded),
              onPressed: () => setState(() {
                _bills = <Map<int, PosQuantity>>[
                  ..._bills,
                  <int, PosQuantity>{},
                ];
                _target = _bills.length - 1;
              }),
            ),
          if (_bills.length > 2)
            IconButton(
              tooltip: "Oxirgi schyotni olib tashlash",
              icon: const Icon(Icons.remove_rounded),
              onPressed: () {
                final Map<int, PosQuantity> last = _bills.last;
                final List<Map<int, PosQuantity>> next =
                    <Map<int, PosQuantity>>[
                      for (final Map<int, PosQuantity> b in _bills.take(
                        _bills.length - 1,
                      ))
                        Map<int, PosQuantity>.of(b),
                    ];
                last.forEach(
                  (int id, PosQuantity q) =>
                      next[0][id] = (next[0][id] ?? PosQuantity.zero) + q,
                );
                setState(() {
                  _bills = next;
                  _target = _target.clamp(0, next.length - 1);
                });
              },
            ),
        ],
      ),
      const SizedBox(height: 8),
      for (final SplitLine l in widget.lines) _lineTile(c, l),
      if (!_itemsValid)
        Padding(
          padding: const EdgeInsets.only(top: 8),
          child: Text(
            "Har schyotda kamida bitta taom bo'lsin.",
            style: RzType.body.copyWith(color: c.dangerSoftFg),
          ),
        ),
    ],
  );

  Widget _lineTile(RzColors c, SplitLine l) {
    final String where = <String>[
      for (var i = 0; i < _bills.length; i++)
        if (_share(i, l.id).isPositive)
          '${i + 1}-sch: ${_share(i, l.id).label(l.unit)}',
    ].join(' · ');
    final bool here = _share(_target, l.id) == l.quantity;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(RzRadius.control),
        onTap: () => _assign(l.id, _target, l.quantity),
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 56),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Row(
              children: <Widget>[
                Icon(
                  here ? Icons.check_circle_rounded : Icons.circle_outlined,
                  color: here ? c.brand : c.fgSubtle,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        '${l.name} · ${l.quantity.label(l.unit)}',
                        style: RzType.body.copyWith(color: c.fg),
                      ),
                      Text(
                        where,
                        style: RzType.footnote.copyWith(color: c.fgMuted),
                      ),
                    ],
                  ),
                ),
                Text(
                  l.amount.formatAmount(),
                  style: RzType.body.copyWith(color: c.fg),
                ),
                const SizedBox(width: 4),
                if (l.unit.isMeasured || l.quantity > PosQuantity.one)
                  TextButton(
                    onPressed: () => _askShare(l),
                    child: const Text('Ulush'),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
