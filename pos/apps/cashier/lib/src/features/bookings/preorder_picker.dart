import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/pos_scope.dart';
import '../billing/cashier_ui.dart';

/// Oldindan buyurtma qatori (hali yuborilmagan): variant, miqdor (kg ham), tayyor bo'lish vaqti.
class PreorderDraft {
  const PreorderDraft({
    required this.product,
    required this.variant,
    required this.quantity,
    this.dueAt,
    this.note,
  });

  final PosProduct product;
  final PosVariant variant;
  final PosQuantity quantity;
  final DateTime? dueAt;
  final String? note;

  String get name => product.variants.length > 1
      ? '${product.name} (${variant.name})'
      : product.name;

  PosMoney get amount => variant.price.timesQuantity(quantity);
}

/// Menyudan taom tanlash (kategoriya, qidiruv), miqdor (kg uchun kasr klaviatura) va tayyor bo'lish vaqti. [start] —
/// bron boshlanishi (standart vaqt).
Future<PreorderDraft?> showPreorderPicker(
  BuildContext context, {
  required DateTime start,
}) => showScopedDialog<PreorderDraft>(
  context,
  (BuildContext _) => _PreorderPicker(start: start),
);

class _PreorderPicker extends StatefulWidget {
  const _PreorderPicker({required this.start});

  final DateTime start;

  @override
  State<_PreorderPicker> createState() => _PreorderPickerState();
}

class _PreorderPickerState extends State<_PreorderPicker> {
  PosCatalog? _catalog;
  PosException? _error;
  int? _category;
  String _query = '';
  PosProduct? _product;
  PosVariant? _variant;
  PosQuantity? _quantity;
  late DateTime _due = widget.start;
  bool _started = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      _load();
    }
  }

  Future<void> _load() async {
    final PosScope scope = PosScope.of(context);
    final PosCatalog? cached = scope.data.catalog.cached(PosChannel.hall);
    if (cached != null) {
      setState(() => _catalog = cached);
      return;
    }
    try {
      final PosCatalog c = await scope.data.catalog.load(
        PosChannel.hall,
        menuVersion: scope.session.bootstrap?.menuVersion,
      );
      if (mounted) setState(() => _catalog = c);
    } on PosException catch (e) {
      if (mounted) setState(() => _error = e);
    }
  }

  void _pick(PosProduct p) => setState(() {
    _product = p;
    _variant = p.variants.isEmpty ? null : p.variants.first;
    _quantity = _variant == null || _variant!.unit.isMeasured
        ? null
        : PosQuantity.one;
  });

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return AlertDialog(
      title: Text(
        _product == null ? 'Oldindan buyurtma: taom tanlang' : _product!.name,
      ),
      content: SizedBox(
        width: 640,
        height: 520,
        child: _catalog == null
            ? (_error == null
                  ? const LoadingBox()
                  : InlineError(message: _error!.message, onRetry: _load))
            : (_product == null ? _menu(c, _catalog!) : _details(c)),
      ),
      actions: <Widget>[
        RzButton(
          label: _product == null ? 'Bekor qilish' : 'Orqaga',
          variant: RzButtonVariant.ghost,
          onPressed: () => _product == null
              ? Navigator.pop(context)
              : setState(() => _product = null),
        ),
        if (_product != null)
          RzButton(
            key: const ValueKey<String>('preorder-add'),
            label: "Qo'shish",
            onPressed:
                _variant != null && _quantity != null && _quantity!.isPositive
                ? () => Navigator.pop(
                    context,
                    PreorderDraft(
                      product: _product!,
                      variant: _variant!,
                      quantity: _quantity!,
                      dueAt: _due,
                    ),
                  )
                : null,
          ),
      ],
    );
  }

  Widget _menu(RzColors c, PosCatalog catalog) {
    final String q = _query.toLowerCase();
    final List<PosProduct> products = <PosProduct>[
      for (final PosProduct p in catalog.products)
        if (p.variants.isNotEmpty &&
            (q.isEmpty
                ? (_category == null || p.categoryId == _category)
                : p.name.toLowerCase().contains(q)))
          p,
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        TextField(
          key: const ValueKey<String>('preorder-search'),
          decoration: const InputDecoration(
            labelText: 'Qidirish',
            prefixIcon: Icon(Icons.search_rounded),
          ),
          onChanged: (String v) => setState(() => _query = v.trim()),
        ),
        const SizedBox(height: 8),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: ChoiceRow<int?>(
            options: <int?, String>{
              null: 'Hammasi',
              for (final PosCategory cat in catalog.categories)
                cat.id: cat.name,
            },
            selected: _category,
            onSelected: (int? v) => setState(() => _category = v),
          ),
        ),
        const SizedBox(height: 8),
        Expanded(
          child: GridView.count(
            crossAxisCount: 3,
            childAspectRatio: 2.2,
            mainAxisSpacing: 8,
            crossAxisSpacing: 8,
            children: <Widget>[
              for (final PosProduct p in products)
                PosTileButton(
                  label: p.name,
                  subtitle: p.variants.first.price.format(),
                  minHeight: 72,
                  onTap: () => _pick(p),
                ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _details(RzColors c) {
    final PosProduct p = _product!;
    final PosVariant? v = _variant;
    final DateTime start = widget.start;
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          if (p.variants.length > 1) ...<Widget>[
            ChoiceRow<PosVariant>(
              options: <PosVariant, String>{
                for (final PosVariant x in p.variants)
                  x: '${x.name} · ${x.price.formatAmount()}',
              },
              selected: v,
              onSelected: (PosVariant x) => setState(() {
                _variant = x;
                _quantity = x.unit.isMeasured ? null : PosQuantity.one;
              }),
            ),
            const SizedBox(height: 12),
          ],
          if (v != null && v.unit.isMeasured)
            PosQuantityKeypad(
              key: ValueKey<int>(v.id),
              unit: v.unit,
              unitLabel: v.unitLabel,
              initial: _quantity,
              min: v.minQuantity,
              step: v.quantityStep,
              keySize: 52,
              submitLabel: 'Belgilash',
              onSubmit: (PosQuantity q) => setState(() => _quantity = q),
            )
          else if (v != null)
            Row(
              children: <Widget>[
                Text('Soni', style: RzType.headline.copyWith(color: c.fg)),
                const Spacer(),
                PosQuantityStepper(
                  value: _quantity ?? PosQuantity.one,
                  onChanged: (PosQuantity q) => setState(() => _quantity = q),
                ),
              ],
            ),
          if (_quantity != null && v != null) ...<Widget>[
            const SizedBox(height: 12),
            AmountRow(
              label:
                  '${_quantity!.label(v.unit, unitLabel: v.unitLabel)} × ${v.price.formatAmount()}',
              amount: v.price.timesQuantity(_quantity!),
              bold: true,
            ),
          ],
          const SizedBox(height: 16),
          Text(
            "Tayyor bo'lish vaqti",
            style: RzType.footnote.copyWith(color: c.fgMuted),
          ),
          const SizedBox(height: 6),
          ChoiceRow<int>(
            options: const <int, String>{
              0: 'Kelishida',
              30: '+30 daqiqa',
              60: '+1 soat',
              120: '+2 soat',
            },
            selected: _due.difference(start).inMinutes,
            onSelected: (int m) =>
                setState(() => _due = start.add(Duration(minutes: m))),
          ),
          const SizedBox(height: 6),
          Text(
            '${dm(_due)} ${hm(_due)}',
            style: RzType.body.copyWith(color: c.fg),
          ),
        ],
      ),
    );
  }
}
