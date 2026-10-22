import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'kds_type.dart';
import 'line_marks.dart';

/// Qozon paneli (`batches.manage`): filialning ochiq qozonlari va qolgan porsiyalar; to'ldirish, qayta sanash, yopish va
/// yangi qozon ochish (docs/MENU.md §4). Qoldiq buyurtmalar bilan o'zgaradi: panel `menu.availability_changed` hodisasida
/// va har [pollEvery] da yangilanadi.
class KazanPanel extends StatefulWidget {
  const KazanPanel({
    super.key,
    required this.service,
    this.branchId,
    this.events,
    this.pollEvery = const Duration(seconds: 15),
  });

  final KitchenSetupService service;
  final int? branchId;

  /// Filial kanali hodisalari (`menu.availability_changed` da yangilanadi).
  final Stream<RealtimeEvent>? events;
  final Duration pollEvery;

  @override
  State<KazanPanel> createState() => _KazanPanelState();
}

class _KazanPanelState extends State<KazanPanel> {
  List<KitchenBatch> _batches = const <KitchenBatch>[];
  bool _loaded = false;
  ApiException? _error;
  final Set<int> _busy = <int>{};
  bool _opening = false;
  Timer? _poll;
  Timer? _debounce;
  StreamSubscription<RealtimeEvent>? _sub;
  bool _disposed = false;

  @override
  void initState() {
    super.initState();
    unawaited(_load());
    _poll = Timer.periodic(widget.pollEvery, (_) => unawaited(_load()));
    _sub = widget.events?.listen((RealtimeEvent e) {
      if (e.event != 'menu.availability_changed') return;
      _debounce?.cancel();
      _debounce = Timer(
        const Duration(milliseconds: 300),
        () => unawaited(_load()),
      );
    });
  }

  @override
  void dispose() {
    _disposed = true;
    _poll?.cancel();
    _debounce?.cancel();
    unawaited(_sub?.cancel());
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final List<KitchenBatch> list = await widget.service.batches(
        branchId: widget.branchId,
        includeClosed: true,
      );
      if (_disposed) return;
      setState(() {
        _batches = list;
        _loaded = true;
        _error = null;
      });
    } on ApiException catch (e) {
      if (_disposed) return;
      setState(() {
        _loaded = true;
        _error = e;
      });
    }
  }

  /// Amalni bajaradi; xatoda xabar ko'rsatadi. Keyin ro'yxat yangilanadi.
  Future<void> _run(int? id, Future<Object?> Function() action) async {
    setState(() {
      if (id != null) {
        _busy.add(id);
      } else {
        _opening = true;
      }
    });
    try {
      await action();
    } on ApiException catch (e) {
      if (mounted) _toast(e.message, error: true);
    } finally {
      if (!_disposed) {
        setState(() {
          if (id != null) {
            _busy.remove(id);
          } else {
            _opening = false;
          }
        });
        unawaited(_load());
      }
    }
  }

  void _toast(String message, {bool error = false}) {
    final RzColors c = context.rz;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          backgroundColor: error ? c.danger : null,
          content: Text(
            message,
            style: RzType.headline.copyWith(color: error ? c.onDanger : null),
          ),
        ),
      );
  }

  Future<void> _refill(KitchenBatch b) async {
    final num? add = await showDialog<num>(
      context: context,
      builder: (BuildContext context) => _NumberDialog(
        title: "${b.title}: yana pishirildi",
        label: "Qo'shiladigan miqdor (${_unitLabel(b.unit)})",
        confirm: "Qo'shish",
        quick: const <num>[5, 10, 20],
      ),
    );
    if (add == null) return;
    await _run(b.id, () => widget.service.refill(b.id, add));
  }

  Future<void> _recount(KitchenBatch b) async {
    final num? left = await showDialog<num>(
      context: context,
      builder: (BuildContext context) => _NumberDialog(
        title: '${b.title}: qoldiqni sanash',
        label: 'Hozir qolgan miqdor (${_unitLabel(b.unit)})',
        confirm: 'Saqlash',
        initial: b.quantityLeft,
        allowZero: true,
      ),
    );
    if (left == null) return;
    await _run(
      b.id,
      () => widget.service.setLeft(b.id, left, reason: 'Oshxonada sanaldi'),
    );
  }

  Future<void> _close(KitchenBatch b) async {
    final bool? yes = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: Text('${b.title}: qozonni yopamizmi?'),
        content: Text(
          "Yopilgach bu taom yangi qozon ochilguncha menyuda \"tugadi\" bo'ladi. Qolgan: ${_qty(b.quantityLeft)} ${_unitLabel(b.unit)}.",
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Bekor'),
          ),
          FilledButton(
            key: const ValueKey<String>('kazan-close-confirm'),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Yopish'),
          ),
        ],
      ),
    );
    if (yes != true) return;
    await _run(b.id, () => widget.service.closeBatch(b.id));
  }

  /// Yangi qozon: yopilgan qozondan qayta ochish yoki ro'yxatdan taom tanlash.
  Future<void> _open({KitchenBatch? like}) async {
    List<BatchProduct> products = const <BatchProduct>[];
    if (like == null) {
      try {
        products = await widget.service.batchProducts(
          branchId: widget.branchId,
        );
      } on ApiException catch (e) {
        if (mounted) _toast(e.message, error: true);
        return;
      }
      if (!mounted) return;
    }
    final Set<String> openKeys = <String>{
      for (final KitchenBatch b in _batches)
        if (b.isOpen) b.productKey,
    };
    final _NewBatch? result = await showDialog<_NewBatch>(
      context: context,
      builder: (BuildContext context) =>
          _OpenBatchDialog(like: like, products: products, openKeys: openKeys),
    );
    if (result == null) return;
    await _run(
      null,
      () => widget.service.openBatch(
        branchId: widget.branchId,
        productId: result.productId,
        productVariantId: result.variantId,
        quantity: result.quantity,
        label: result.label,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    if (!_loaded) return const Center(child: CircularProgressIndicator());
    final List<KitchenBatch> open = _batches
        .where((KitchenBatch b) => b.isOpen)
        .toList();
    final List<KitchenBatch> closed = _batches
        .where((KitchenBatch b) => !b.isOpen)
        .take(8)
        .toList();
    return ListView(
      padding: const EdgeInsets.all(12),
      children: <Widget>[
        Row(
          children: <Widget>[
            Expanded(
              child: Text(
                "Qozon qoldig'i",
                style: RzType.title.copyWith(color: c.fg),
              ),
            ),
            IconButton(
              tooltip: 'Yangilash',
              icon: const Icon(Icons.refresh_rounded),
              onPressed: () => unawaited(_load()),
            ),
            const SizedBox(width: 8),
            RzButton(
              key: const ValueKey<String>('kazan-open'),
              label: 'Yangi qozon',
              icon: Icons.add_rounded,
              loading: _opening,
              onPressed: () => unawaited(_open()),
            ),
          ],
        ),
        if (_error != null) ...<Widget>[
          const SizedBox(height: 8),
          InlineError(
            message: _error!.isNetworkError
                ? "Server bilan aloqa yo'q. Qoldiq eskirgan bo'lishi mumkin."
                : _error!.message,
            onRetry: () => unawaited(_load()),
          ),
        ],
        const SizedBox(height: 12),
        if (open.isEmpty)
          const EmptyState(
            icon: Icons.rice_bowl_outlined,
            title: "Ochiq qozon yo'q",
            message: "Qozon bo'yicha sotiladigan taomlar qozon ochilmaguncha menyuda \"tugadi\" turadi. \"Yangi qozon\" tugmasi bilan oching.",
          )
        else
          LayoutBuilder(
            builder: (BuildContext context, BoxConstraints box) {
              // Tor ekranda (telefon) karta butun kenglikni oladi
              final double width = box.maxWidth < 400 ? box.maxWidth : 400;
              return Wrap(
                spacing: 12,
                runSpacing: 12,
                children: <Widget>[
                  for (final KitchenBatch b in open)
                    _BatchCard(
                      width: width,
                      batch: b,
                      busy: _busy.contains(b.id),
                      onRefill: () => unawaited(_refill(b)),
                      onRecount: () => unawaited(_recount(b)),
                      onClose: () => unawaited(_close(b)),
                    ),
                ],
              );
            },
          ),
        if (closed.isNotEmpty) ...<Widget>[
          const SizedBox(height: 24),
          Text(
            'Yaqinda yopilgan',
            style: RzType.headline.copyWith(color: c.fgMuted),
          ),
          const SizedBox(height: 8),
          for (final KitchenBatch b in closed)
            _ClosedRow(
              batch: b,
              onReopen: () => unawaited(_open(like: b)),
            ),
        ],
      ],
    );
  }
}

String _unitLabel(String? unit) => switch (unit) {
  'kg' => 'kg',
  'l' => 'l',
  _ => 'porsiya',
};

String _qty(num? v) => v == null ? '—' : formatCount(v);

class _BatchCard extends StatelessWidget {
  const _BatchCard({
    required this.width,
    required this.batch,
    required this.busy,
    required this.onRefill,
    required this.onRecount,
    required this.onClose,
  });

  final double width;
  final KitchenBatch batch;
  final bool busy;
  final VoidCallback onRefill;
  final VoidCallback onRecount;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final double? fraction = batch.fractionLeft;
    final bool soldOut = batch.isSoldOut;
    final bool low = !soldOut && fraction != null && fraction <= 0.2;
    final Color tone = soldOut
        ? c.danger
        : low
        ? c.warning
        : c.success;
    final String unit = _unitLabel(batch.unit);
    return Semantics(
      container: true,
      label:
          '${batch.title}, qolgan ${_qty(batch.quantityLeft)} $unit${soldOut ? ', tugadi' : ''}',
      child: SizedBox(
        width: width,
        child: Material(
          color: c.surface,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(RzRadius.card),
            side: BorderSide(
              color: soldOut || low ? tone : c.line,
              width: soldOut || low ? 3 : 1,
            ),
          ),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Expanded(
                      child: Text(
                        batch.title,
                        style: KdsType.line.copyWith(color: c.fg),
                      ),
                    ),
                    if (soldOut)
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: c.danger,
                          borderRadius: BorderRadius.circular(RzRadius.control),
                        ),
                        child: Text(
                          'TUGADI',
                          style: RzType.footnote.copyWith(
                            color: c.onDanger,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      )
                    else if (low)
                      Icon(Icons.warning_amber_rounded, color: c.warning),
                  ],
                ),
                if ((batch.label ?? '').isNotEmpty)
                  Text(
                    batch.label!,
                    style: KdsType.meta.copyWith(color: c.fgMuted),
                  ),
                const SizedBox(height: 8),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.baseline,
                    textBaseline: TextBaseline.alphabetic,
                    children: <Widget>[
                      Text(
                        _qty(batch.quantityLeft),
                        style: const TextStyle(
                          fontSize: 48,
                          height: 1.1,
                          fontWeight: FontWeight.w800,
                          fontFeatures: <FontFeature>[
                            FontFeature.tabularFigures(),
                          ],
                        ).copyWith(color: tone),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        '/ ${_qty(batch.quantityProduced)} $unit',
                        style: KdsType.line.copyWith(color: c.fgMuted),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 6),
                ClipRRect(
                  borderRadius: BorderRadius.circular(6),
                  child: LinearProgressIndicator(
                    value: fraction ?? 0,
                    minHeight: 12,
                    color: tone,
                    backgroundColor: Color.alphaBlend(c.surface2, c.surface),
                  ),
                ),
                if (batch.startedAt != null) ...<Widget>[
                  const SizedBox(height: 6),
                  Text(
                    'Ochilgan: ${formatTime(batch.startedAt)}',
                    style: RzType.footnote.copyWith(color: c.fgMuted),
                  ),
                ],
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: <Widget>[
                    RzButton(
                      key: ValueKey<String>('kazan-refill-${batch.id}'),
                      label: "Qo'shish",
                      icon: Icons.add_rounded,
                      variant: RzButtonVariant.success,
                      loading: busy,
                      onPressed: onRefill,
                    ),
                    RzButton(
                      key: ValueKey<String>('kazan-recount-${batch.id}'),
                      label: 'Sanash',
                      icon: Icons.calculate_outlined,
                      variant: RzButtonVariant.secondary,
                      onPressed: busy ? null : onRecount,
                    ),
                    RzButton(
                      key: ValueKey<String>('kazan-close-${batch.id}'),
                      label: 'Yopish',
                      icon: Icons.lock_outline_rounded,
                      variant: RzButtonVariant.ghost,
                      onPressed: busy ? null : onClose,
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ClosedRow extends StatelessWidget {
  const _ClosedRow({required this.batch, required this.onReopen});

  final KitchenBatch batch;
  final VoidCallback onReopen;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 56),
      child: Row(
        children: <Widget>[
          Icon(Icons.lock_outline_rounded, color: c.fgMuted),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              '${batch.title} · yopilgan ${formatTime(batch.closedAt)} · qolgan ${_qty(batch.quantityLeft)} ${_unitLabel(batch.unit)}',
              style: KdsType.meta.copyWith(color: c.fgMuted),
            ),
          ),
          RzButton(
            key: ValueKey<String>('kazan-reopen-${batch.id}'),
            label: 'Qayta ochish',
            variant: RzButtonVariant.ghost,
            onPressed: onReopen,
          ),
        ],
      ),
    );
  }
}

/// Bitta son so'raydi (miqdor). Tez tugmalar (`+5`) maydonga qo'shadi.
class _NumberDialog extends StatefulWidget {
  const _NumberDialog({
    required this.title,
    required this.label,
    required this.confirm,
    this.initial,
    this.quick = const <num>[],
    this.allowZero = false,
  });

  final String title;
  final String label;
  final String confirm;
  final num? initial;
  final List<num> quick;
  final bool allowZero;

  @override
  State<_NumberDialog> createState() => _NumberDialogState();
}

class _NumberDialogState extends State<_NumberDialog> {
  late final TextEditingController _text = TextEditingController(
    text: widget.initial == null ? '' : formatCount(widget.initial!),
  );

  num? get _value {
    final num? v = num.tryParse(_text.text.trim().replaceAll(',', '.'));
    if (v == null || v < 0 || (v == 0 && !widget.allowZero)) return null;
    return v;
  }

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.title),
      content: SizedBox(
        width: 360,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            TextField(
              key: const ValueKey<String>('kazan-number'),
              controller: _text,
              autofocus: true,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              inputFormatters: <TextInputFormatter>[
                FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]')),
              ],
              style: KdsType.line,
              decoration: InputDecoration(labelText: widget.label),
              onChanged: (_) => setState(() {}),
              onSubmitted: (_) {
                final num? v = _value;
                if (v != null) Navigator.pop(context, v);
              },
            ),
            if (widget.quick.isNotEmpty) ...<Widget>[
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  for (final num q in widget.quick)
                    ActionChip(
                      label: Text('+${formatCount(q)}', style: RzType.headline),
                      onPressed: () => setState(() {
                        _text.text = formatCount((_value ?? 0) + q);
                      }),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
      actions: <Widget>[
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Bekor'),
        ),
        FilledButton(
          key: const ValueKey<String>('kazan-number-confirm'),
          onPressed: _value == null
              ? null
              : () => Navigator.pop(context, _value),
          child: Text(widget.confirm),
        ),
      ],
    );
  }
}

class _NewBatch {
  const _NewBatch({
    required this.productId,
    required this.variantId,
    required this.quantity,
    this.label,
  });

  final int productId;
  final int? variantId;
  final num quantity;
  final String? label;
}

/// Yangi qozon: taom (yoki yopilgan qozon taomi), miqdor va ixtiyoriy nom.
class _OpenBatchDialog extends StatefulWidget {
  const _OpenBatchDialog({
    required this.like,
    required this.products,
    required this.openKeys,
  });

  final KitchenBatch? like;
  final List<BatchProduct> products;
  final Set<String> openKeys;

  @override
  State<_OpenBatchDialog> createState() => _OpenBatchDialogState();
}

class _ProductChoice {
  const _ProductChoice(this.productId, this.variantId, this.title);

  final int productId;
  final int? variantId;
  final String title;

  String get key => '$productId/${variantId ?? 0}';
}

class _OpenBatchDialogState extends State<_OpenBatchDialog> {
  late final List<_ProductChoice> _choices = <_ProductChoice>[
    if (widget.like case final KitchenBatch b)
      _ProductChoice(b.productId, b.productVariantId, b.title)
    else
      for (final BatchProduct p in widget.products) ...<_ProductChoice>[
        _ProductChoice(
          p.id,
          p.variants.length == 1 ? p.variants.single.id : null,
          p.name,
        ),
        if (p.variants.length > 1)
          for (final BatchProductVariant v in p.variants)
            _ProductChoice(p.id, v.id, '${p.name} (${v.name})'),
      ],
  ].where((_ProductChoice c) => !widget.openKeys.contains(c.key)).toList();

  _ProductChoice? _picked;
  final TextEditingController _qty = TextEditingController();
  final TextEditingController _label = TextEditingController();

  @override
  void initState() {
    super.initState();
    if (_choices.length == 1) _picked = _choices.single;
  }

  @override
  void dispose() {
    _qty.dispose();
    _label.dispose();
    super.dispose();
  }

  num? get _quantity {
    final num? v = num.tryParse(_qty.text.trim().replaceAll(',', '.'));
    return v != null && v > 0 ? v : null;
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return AlertDialog(
      title: Text(
        widget.like == null
            ? 'Yangi qozon'
            : '${widget.like!.title}: qayta ochish',
      ),
      content: SizedBox(
        width: 400,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            if (_choices.isEmpty)
              Text(
                "Qozon bo'yicha sotiladigan taom topilmadi yoki hammasining qozoni ochiq.",
                style: RzType.body.copyWith(color: c.fgMuted),
              )
            else if (widget.like == null)
              DropdownButtonFormField<_ProductChoice>(
                key: const ValueKey<String>('kazan-product'),
                initialValue: _picked,
                isExpanded: true,
                decoration: const InputDecoration(labelText: 'Taom'),
                items: <DropdownMenuItem<_ProductChoice>>[
                  for (final _ProductChoice p in _choices)
                    DropdownMenuItem<_ProductChoice>(
                      value: p,
                      child: Text(p.title),
                    ),
                ],
                onChanged: (_ProductChoice? v) => setState(() => _picked = v),
              ),
            const SizedBox(height: 12),
            TextField(
              key: const ValueKey<String>('kazan-open-qty'),
              controller: _qty,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              inputFormatters: <TextInputFormatter>[
                FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]')),
              ],
              style: KdsType.line,
              decoration: const InputDecoration(
                labelText: 'Nechta porsiya pishirildi',
              ),
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _label,
              decoration: const InputDecoration(
                labelText: 'Qozon nomi (ixtiyoriy)',
              ),
            ),
          ],
        ),
      ),
      actions: <Widget>[
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Bekor'),
        ),
        FilledButton(
          key: const ValueKey<String>('kazan-open-confirm'),
          onPressed:
              (widget.like != null ? _choices.isNotEmpty : _picked != null) &&
                  _quantity != null
              ? () {
                  final _ProductChoice p = _picked ?? _choices.single;
                  Navigator.pop(
                    context,
                    _NewBatch(
                      productId: p.productId,
                      variantId: p.variantId,
                      quantity: _quantity!,
                      label: _label.text.trim().isEmpty
                          ? null
                          : _label.text.trim(),
                    ),
                  );
                }
              : null,
          child: const Text('Ochish'),
        ),
      ],
    );
  }
}
