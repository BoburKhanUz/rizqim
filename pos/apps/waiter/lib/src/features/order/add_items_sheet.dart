import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../common/modules.dart';
import '../../common/sheet.dart';
import 'cart_sheet.dart';
import 'draft_cart.dart';
import 'options_sheet.dart';
import 'order_controller.dart';
import 'quantity_sheet.dart';
import 'slot_pickers.dart';

/// "Taom qo'shish": toifalar + qidiruv + taomlar ro'yxati, savat, "hozir / ushlab turish" va "Yuborish".
/// Bosqich yuborilsa `true` bilan yopiladi.
Future<bool?> showAddItemsSheet(
  BuildContext context, {
  required OrderController controller,
  required PosCatalog catalog,
  required Approving approving,
  required String placeTitle,
}) => showWaiterSheet<bool>(
  context,
  fullHeight: true,
  builder: (BuildContext sheetContext) => AddItemsSheet(
    controller: controller,
    catalog: catalog,
    approving: approving,
    placeTitle: placeTitle,
  ),
);

class AddItemsSheet extends StatefulWidget {
  const AddItemsSheet({
    super.key,
    required this.controller,
    required this.catalog,
    required this.approving,
    required this.placeTitle,
  });

  final OrderController controller;
  final PosCatalog catalog;

  /// Foydalanuvchi menejer tasdig'ini shu oyna ustida ko'radi (sahifa kontekstidan emas).
  final Approving approving;
  final String placeTitle;

  @override
  State<AddItemsSheet> createState() => _AddItemsSheetState();
}

class _AddItemsSheetState extends State<AddItemsSheet> {
  final DraftCart _cart = DraftCart();
  final TextEditingController _search = TextEditingController();
  late PosCatalog _catalog = widget.catalog;
  int? _categoryId;
  PosFireMode _mode = PosFireMode.now;
  bool _sending = false;
  String? _error;
  StreamSubscription<PosCatalog>? _sub;
  StreamSubscription<PosEvent>? _events;

  OrderController get _ctl => widget.controller;

  PosSession get _session => _ctl.session;

  /// P6-25: shu taom og'irligi keyin tortiladigan qator bo'ladi (modul yoqilgan).
  bool _weighedLater(PosProduct p) => _session.weightLaterOn && p.weighedLater;

  /// P6-25: ×N tez tugmalari (`skewer_buttons` moduli).
  List<int> _multipliers(PosProduct p) =>
      _session.skewersOn ? p.quickMultipliers : const <int>[];

  @override
  void initState() {
    super.initState();
    _categoryId = _categories.isEmpty ? null : _categories.first.id;
    _sub = _ctl.data.catalog.changes.listen((PosCatalog c) {
      if (c.channel == PosChannel.hall && mounted) setState(() => _catalog = c);
    });
    _events = _ctl.realtime?.events.listen((PosEvent e) {
      if (e is PosMenuAvailabilityChanged) {
        unawaited(_ctl.refreshAvailability());
      }
    });
    unawaited(_ctl.refreshAvailability());
  }

  @override
  void dispose() {
    unawaited(_sub?.cancel());
    unawaited(_events?.cancel());
    _search.dispose();
    _cart.dispose();
    super.dispose();
  }

  /// Taomi bor toifalar (menyu tartibida).
  List<PosCategory> get _categories => <PosCategory>[
    for (final PosCategory c in _catalog.categories)
      if (_catalog.productsIn(c.id).isNotEmpty) c,
  ];

  List<PosProduct> get _products {
    final String q = _search.text.trim();
    if (q.isNotEmpty) return _catalog.search(q);
    final int? id = _categoryId;
    return id == null ? _catalog.products : _catalog.productsIn(id);
  }

  /// Tez qo'shiladigan taomlar (P3-26): bootstrap'dagi ro'yxat (menyuda "tez qo'shish" belgilangan va choy-non
  /// qatorlari, shu zalniki); ro'yxat bo'sh bo'lsa — nom bo'yicha (choy, non; bitta dona variant, qo'shimchasiz).
  List<PosProduct> get _quick => quickProducts(
    _catalog,
    _ctl.session.bootstrap?.quickItems ?? const <PosQuickItem>[],
    hallId: _ctl.order?.hall?.id,
    limit: 4,
    fallback: _isQuick,
  );

  bool _isQuick(PosProduct p) {
    final String n = p.name.toLowerCase();
    final PosVariant? v = p.singleVariant;
    return v != null &&
        !v.unit.isMeasured &&
        !p.hasModifiers &&
        (n.startsWith('choy') || n == 'non' || n.startsWith('non '));
  }

  Future<void> _tap(PosProduct p) async {
    if (!_catalog.isProductAvailable(p)) return;
    final PosVariant? single = p.singleVariant;
    if (single != null && !p.hasModifiers) {
      if (single.unit.isMeasured) {
        final PosQuantity? q = await showQuantitySheet(
          context,
          product: p,
          variant: single,
          initial: _cart.plainQuantityOf(single).isPositive
              ? _cart.plainQuantityOf(single)
              : null,
          weighedLater: _weighedLater(p),
        );
        if (q != null) {
          _cart.setPlainQuantity(
            p,
            single,
            q,
            awaitingWeight: _weighedLater(p),
          );
        }
      } else {
        _cart.setPlainQuantity(
          p,
          single,
          _cart.plainQuantityOf(single) + PosQuantity.one,
        );
      }
      return;
    }
    await _chooseOptions(p);
  }

  /// P6-25: "×N" — oddiy taomga N dona qo'shiladi, variantli yoki qo'shimchali taomda tanlovdan keyin N dona.
  Future<void> _multiply(PosProduct p, int times) async {
    if (!_catalog.isProductAvailable(p)) return;
    final PosQuantity n = PosQuantity.whole(times);
    final PosVariant? single = p.singleVariant;
    if (single != null && !p.hasModifiers && !single.unit.isMeasured) {
      _cart.setPlainQuantity(p, single, _cart.plainQuantityOf(single) + n);
      return;
    }
    await _chooseOptions(p, quantity: n);
  }

  Future<void> _chooseOptions(PosProduct p, {PosQuantity? quantity}) async {
    final ItemChoice? choice = await showOptionsSheet(
      context,
      product: p,
      catalog: _catalog,
    );
    if (choice == null || !mounted) return;
    PosQuantity count = quantity ?? PosQuantity.one;
    if (choice.variant.unit.isMeasured) {
      final PosQuantity? q = await showQuantitySheet(
        context,
        product: p,
        variant: choice.variant,
        weighedLater: _weighedLater(p),
      );
      if (q == null) return;
      count = q;
    }
    _cart.add(
      DraftLine(
        product: p,
        variant: choice.variant,
        quantity: count,
        modifiers: choice.modifiers,
        note: (choice.note ?? '').isEmpty ? null : choice.note,
        courseNo: _cart.course,
        seatNo: _cart.seat,
        awaitingWeight: _weighedLater(p) && choice.variant.unit.isMeasured,
      ),
    );
  }

  Future<void> _send() async {
    if (_cart.isEmpty || _sending) return;
    setState(() {
      _sending = true;
      _error = null;
    });
    final bool ok = await _ctl.addRound(
      widget.approving,
      _cart.toInputs(),
      _mode,
    );
    if (!mounted) return;
    if (ok) {
      Navigator.of(context).pop(true);
      return;
    }
    setState(() {
      _sending = false;
      _error = _ctl.lastError;
    });
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return SafeArea(
      child: ListenableBuilder(
        listenable: Listenable.merge(<Listenable>[_cart, _ctl, _search]),
        builder: (BuildContext context, _) {
          final List<PosProduct> products = _products;
          final bool searching = _search.text.trim().isNotEmpty;
          return Column(
            children: <Widget>[
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                child: Row(
                  children: <Widget>[
                    Expanded(
                      child: SheetHeader(
                        title: "Taom qo'shish",
                        subtitle: widget.placeTitle,
                      ),
                    ),
                    IconButton(
                      tooltip: 'Yopish',
                      onPressed: () => Navigator.of(context).pop(false),
                      icon: const Icon(Icons.close),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: TextField(
                  controller: _search,
                  textInputAction: TextInputAction.search,
                  decoration: InputDecoration(
                    hintText: 'Taom qidirish',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: searching
                        ? IconButton(
                            tooltip: 'Tozalash',
                            onPressed: _search.clear,
                            icon: const Icon(Icons.close),
                          )
                        : null,
                    border: const OutlineInputBorder(),
                    isDense: false,
                  ),
                ),
              ),
              // P6-21 / P6-24: the course and the guest the next dishes go to (only with the modules on)
              if (_session.coursesOn)
                CourseChips(
                  value: _cart.course,
                  onChanged: (int? v) => _cart.course = v,
                ),
              if (_session.guestSplitOn)
                SeatChips(
                  guests: _ctl.order?.guestsCount,
                  value: _cart.seat,
                  onChanged: (int? v) => _cart.seat = v,
                ),
              if (!searching) ...<Widget>[
                _QuickRow(products: _quick, cart: _cart, onTap: _tap),
                SizedBox(
                  height: 56,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 4,
                    ),
                    children: <Widget>[
                      for (final PosCategory cat in _categories)
                        Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: ChoiceChip(
                            label: Text(cat.name),
                            selected: _categoryId == cat.id,
                            materialTapTargetSize: MaterialTapTargetSize.padded,
                            visualDensity: VisualDensity.comfortable,
                            labelStyle: RzType.body,
                            onSelected: (_) =>
                                setState(() => _categoryId = cat.id),
                          ),
                        ),
                    ],
                  ),
                ),
              ],
              Expanded(
                child: products.isEmpty
                    ? const EmptyState(
                        icon: Icons.search_off,
                        title: 'Taom topilmadi',
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
                        itemCount: products.length,
                        separatorBuilder: (_, _) =>
                            Divider(height: 1, color: c.line),
                        itemBuilder: (BuildContext context, int i) =>
                            _ProductRow(
                              product: products[i],
                              catalog: _catalog,
                              cart: _cart,
                              multipliers: _multipliers(products[i]),
                              showWait: _session.skewersOn,
                              weighedLater: _weighedLater(products[i]),
                              onMultiply: (int n) =>
                                  unawaited(_multiply(products[i], n)),
                              onTap: () => unawaited(_tap(products[i])),
                              onOptions: () =>
                                  unawaited(_chooseOptions(products[i])),
                              onRemoveOne: (PosVariant v) =>
                                  _cart.setPlainQuantity(
                                    products[i],
                                    v,
                                    _cart.plainQuantityOf(v) - PosQuantity.one,
                                  ),
                            ),
                      ),
              ),
              if (!_cart.isEmpty) _bottomBar(context),
            ],
          );
        },
      ),
    );
  }

  Widget _bottomBar(BuildContext context) {
    final RzColors c = context.rz;
    final bool online = _ctl.online;
    final String sendLabel = _mode == PosFireMode.now ? 'Yuborish' : 'Saqlash';
    return Material(
      color: c.surface,
      elevation: 8,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            if (_error != null) ...<Widget>[
              InlineError(message: _error!),
              const SizedBox(height: 8),
            ],
            if (!online) ...<Widget>[
              Text(
                "Aloqa yo'q: aloqa qaytguncha yuborib bo'lmaydi.",
                style: RzType.footnote.copyWith(color: c.dangerSoftFg),
              ),
              const SizedBox(height: 6),
            ],
            SizedBox(
              width: double.infinity,
              child: SegmentedButton<PosFireMode>(
                showSelectedIcon: false,
                style: SegmentedButton.styleFrom(
                  minimumSize: const Size.fromHeight(48),
                ),
                segments: const <ButtonSegment<PosFireMode>>[
                  ButtonSegment<PosFireMode>(
                    value: PosFireMode.now,
                    label: Text('Hozir yuborish'),
                    icon: Icon(Icons.bolt_outlined),
                  ),
                  ButtonSegment<PosFireMode>(
                    value: PosFireMode.hold,
                    label: Text('Ushlab turish'),
                    icon: Icon(Icons.pause_circle_outline),
                  ),
                ],
                selected: <PosFireMode>{_mode},
                onSelectionChanged: (Set<PosFireMode> s) =>
                    setState(() => _mode = s.first),
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: <Widget>[
                Expanded(
                  child: Semantics(
                    button: true,
                    label: 'Savat, ${_cart.length} qator',
                    excludeSemantics: true,
                    child: InkWell(
                      borderRadius: BorderRadius.circular(RzRadius.card),
                      onTap: () => unawaited(
                        showCartSheet(
                          context,
                          _cart,
                          courses: _session.coursesOn,
                          seats: _session.guestSplitOn,
                          guests: _ctl.order?.guestsCount,
                        ),
                      ),
                      child: SizedBox(
                        height: 52,
                        child: Row(
                          children: <Widget>[
                            Badge(
                              label: Text('${_cart.length}'),
                              child: const Icon(
                                Icons.shopping_basket_outlined,
                                size: 28,
                              ),
                            ),
                            const SizedBox(width: 14),
                            Expanded(
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: <Widget>[
                                  Text(
                                    'Savat',
                                    style: RzType.footnote.copyWith(
                                      color: c.fgMuted,
                                    ),
                                  ),
                                  Text(
                                    _cart.total.format(),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: RzType.headline.copyWith(
                                      color: c.fg,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: RzButton(
                    label: sendLabel,
                    icon: Icons.send_rounded,
                    size: RzButtonSize.lg,
                    fullWidth: true,
                    loading: _sending,
                    onPressed: online && !_ctl.busy ? _send : null,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// Choy-non tez tugmalari: bir bosish — bitta qo'shiladi.
class _QuickRow extends StatelessWidget {
  const _QuickRow({
    required this.products,
    required this.cart,
    required this.onTap,
  });

  final List<PosProduct> products;
  final DraftCart cart;
  final Future<void> Function(PosProduct) onTap;

  @override
  Widget build(BuildContext context) {
    if (products.isEmpty) return const SizedBox.shrink();
    final RzColors c = context.rz;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 2),
      child: Row(
        children: <Widget>[
          for (final PosProduct p in products) ...<Widget>[
            Expanded(
              child: Semantics(
                button: true,
                label: "${p.name} qo'shish",
                excludeSemantics: true,
                child: FilledButton.tonal(
                  onPressed: () => unawaited(onTap(p)),
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(52),
                    padding: const EdgeInsets.symmetric(horizontal: 8),
                    backgroundColor: Color.alphaBlend(c.brandSoft, c.surface),
                    foregroundColor: c.brandSoftFg,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(RzRadius.card),
                    ),
                  ),
                  child: Builder(
                    builder: (BuildContext context) {
                      final int n = cart
                          .plainQuantityOf(p.variants.first)
                          .wholePart;
                      return Text(
                        n > 0 ? '+ ${p.name}  x$n' : '+ ${p.name}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: RzType.headline,
                      );
                    },
                  ),
                ),
              ),
            ),
            if (p != products.last) const SizedBox(width: 8),
          ],
        ],
      ),
    );
  }
}

/// Taom qatori: nom, narx, qoldiq/stop-list belgisi; oxirida "+" yoki stepper (kg uchun miqdor tugmasi).
class _ProductRow extends StatelessWidget {
  const _ProductRow({
    required this.product,
    required this.catalog,
    required this.cart,
    required this.onTap,
    required this.onOptions,
    required this.onRemoveOne,
    this.multipliers = const <int>[],
    this.showWait = false,
    this.weighedLater = false,
    this.onMultiply,
  });

  final PosProduct product;
  final PosCatalog catalog;
  final DraftCart cart;
  final VoidCallback onTap;
  final VoidCallback onOptions;
  final ValueChanged<PosVariant> onRemoveOne;

  /// P6-25: ×N tugmalari (bo'sh — yo'q).
  final List<int> multipliers;

  /// P6-25: mangal vaqti ("~15 daq") ko'rsatiladi.
  final bool showWait;

  /// P6-25: og'irligi keyin tortiladi.
  final bool weighedLater;
  final ValueChanged<int>? onMultiply;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final bool available = catalog.isProductAvailable(product);
    final PosVariant? single = product.singleVariant;
    final bool plain = single != null && !product.hasModifiers;
    final PosVariant first = product.variants.first;
    final PosQuantity inCart = plain
        ? cart.plainQuantityOf(single)
        : PosQuantity.zero;
    final int lines = cart.linesOf(product);

    String subtitle;
    if (product.variants.length > 1) {
      final PosMoney cheapest = product.variants
          .map((PosVariant v) => v.price)
          .reduce((PosMoney a, PosMoney b) => a < b ? a : b);
      subtitle = "${product.variants.length} xil · ${cheapest.format()} dan";
    } else if (first.unit.isMeasured) {
      subtitle =
          '${first.price.format()} / ${first.unitLabel ?? first.unit.label}';
    } else {
      subtitle = first.price.format();
    }
    final PosBatch? batch = product.batchTracked && single != null
        ? catalog.batchFor(single)
        : null;
    final List<String> extras = <String>[
      if (showWait && (product.waitMinutes ?? 0) > 0)
        '~${product.waitMinutes} daq',
      if (weighedLater)
        "og'irligi keyin tortiladi (${product.weightMode!.label})",
    ];
    String? reason;
    if (!available) {
      reason = catalog.availability.stoppedProducts.contains(product.id)
          ? PosUnavailableReason.stopped.label
          : (single == null
                    ? null
                    : catalog.unavailableReasonOf(single)?.label) ??
                PosUnavailableReason.soldOut.label;
    }

    Widget trailing;
    if (!available) {
      trailing = Text(
        reason ?? 'Tugadi',
        style: RzType.body.copyWith(color: c.dangerSoftFg),
      );
    } else if (plain && !single.unit.isMeasured) {
      trailing = inCart.isPositive
          ? PosQuantityStepper(
              value: inCart,
              onChanged: (PosQuantity q) =>
                  cart.setPlainQuantity(product, single, q),
              onRemove: () => onRemoveOne(single),
              buttonSize: 48,
            )
          : _RoundIcon(
              icon: Icons.add,
              tooltip: "${product.name} qo'shish",
              onTap: onTap,
            );
    } else if (plain && single.unit.isMeasured) {
      trailing = SizedBox(
        height: 48,
        child: FilledButton.tonal(
          onPressed: onTap,
          style: FilledButton.styleFrom(
            minimumSize: const Size(72, 48),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(RzRadius.control),
            ),
          ),
          child: Text(
            inCart.isPositive
                ? inCart.label(single.unit, unitLabel: single.unitLabel)
                : 'Miqdor',
          ),
        ),
      );
    } else {
      trailing = Badge(
        isLabelVisible: lines > 0,
        label: Text('$lines'),
        child: _RoundIcon(
          icon: Icons.tune,
          tooltip: '${product.name}: tanlash',
          onTap: onOptions,
        ),
      );
    }

    final Widget row = Semantics(
      label: <String>[product.name, subtitle, ...extras, ?reason].join(', '),
      container: true,
      child: InkWell(
        onTap: available ? onTap : null,
        onLongPress: available ? onOptions : null,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 68),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        product.name,
                        style: RzType.headline.copyWith(
                          color: available ? c.fg : c.fgSubtle,
                          decoration: available
                              ? null
                              : TextDecoration.lineThrough,
                        ),
                      ),
                      Text(
                        subtitle,
                        style: RzType.footnote.copyWith(color: c.fgMuted),
                      ),
                      if (extras.isNotEmpty)
                        Text(
                          extras.join(' · '),
                          style: RzType.footnote.copyWith(
                            color: weighedLater ? c.infoSoftFg : c.fgMuted,
                          ),
                        ),
                      if (batch != null && available)
                        Text(
                          'Qoldi: ${batch.quantityLeft.format()}',
                          style: RzType.footnote.copyWith(
                            color: batch.quantityLeft.milli <= 2000
                                ? c.warningSoftFg
                                : c.successSoftFg,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                trailing,
              ],
            ),
          ),
        ),
      ),
    );
    if (multipliers.isEmpty || !available || onMultiply == null) return row;
    // P6-25: skewers by the dozen in one tap ("×10"), under the dish
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        row,
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Wrap(
            spacing: 8,
            runSpacing: 6,
            children: <Widget>[
              for (final int n in multipliers)
                Semantics(
                  button: true,
                  label: "${product.name}: $n dona qo'shish",
                  excludeSemantics: true,
                  child: OutlinedButton(
                    onPressed: () => onMultiply!(n),
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size(56, 48),
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      foregroundColor: c.brandSoftFg,
                      side: BorderSide(color: c.line),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(RzRadius.control),
                      ),
                    ),
                    child: Text('×$n', style: RzType.headline),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _RoundIcon extends StatelessWidget {
  const _RoundIcon({
    required this.icon,
    required this.tooltip,
    required this.onTap,
  });

  final IconData icon;
  final String tooltip;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => IconButton.filledTonal(
    tooltip: tooltip,
    onPressed: onTap,
    iconSize: 26,
    style: IconButton.styleFrom(
      minimumSize: const Size.square(48),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(RzRadius.control),
      ),
    ),
    icon: Icon(icon),
  );
}
