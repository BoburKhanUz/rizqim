import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../counter/counter_format.dart';
import '../../menu_day/menu_day.dart';
import '../../shell/pos_scope.dart';
import 'discount_dialog.dart';
import 'order_controller.dart';
import 'order_draft.dart';
import 'order_format.dart';
import 'quick_modifiers.dart';
import 'restaurant_widgets.dart';
import 'takeaway_widgets.dart';

/// Buyurtmaning o'ng paneli: bosqichlar (raqami, vaqti, kim qo'shgani, qatorlar holati bilan), yangi bosqich
/// qoralamasi, choy-non tez tugmalari, jami (xizmat qatorlari bilan) va amallar.
class OrderPanel extends StatefulWidget {
  const OrderPanel({
    super.key,
    required this.controller,
    required this.quickProducts,
    required this.onQuickAdd,
    required this.onItemTap,
    required this.onDraftTap,
    required this.onSubmit,
    required this.onSendHeld,
    required this.onBill,
    required this.onCharge,
    this.onRemoveDiscount,
    this.onSendCourse,
    this.onPackaging,
    this.onPickup,
  });

  final OrderController controller;

  /// Choy-non tez tugmalari.
  final List<PosProduct> quickProducts;
  final ValueChanged<PosProduct> onQuickAdd;
  final ValueChanged<PosOrderItem> onItemTap;
  final ValueChanged<DraftLine> onDraftTap;

  /// `true` — "Yuborish", `false` — "Saqlash".
  final ValueChanged<bool> onSubmit;
  final VoidCallback onSendHeld;
  final VoidCallback onBill;

  /// Xizmat qatorini olib tashlash yoki qaytarish.
  final void Function(PosChargeKind kind, bool waived) onCharge;

  /// P3-37: chegirmani olib tashlash (`null` — amal yo'q: internetsiz yoki ruxsat yo'q).
  final VoidCallback? onRemoveDiscount;

  /// P6-21: "N-kursni yuborish" (`null` — kurslar paneli yo'q).
  final ValueChanged<int>? onSendCourse;

  /// P6-34: qadoq qatorini olib tashlash (yoki olib tashlanganini qaytarish).
  final ValueChanged<PosPackagingLine>? onPackaging;

  /// P6-34: "Vaqtga" (olib ketish vaqti); `null` — tugma yo'q.
  final VoidCallback? onPickup;

  @override
  State<OrderPanel> createState() => _OrderPanelState();
}

class _OrderPanelState extends State<OrderPanel> {
  final ScrollController _scroll = ScrollController();
  int _lastCount = -1;

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  void _scrollToEnd(int count) {
    if (count == _lastCount) return;
    _lastCount = count;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) {
        _scroll.jumpTo(_scroll.position.maxScrollExtent);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final OrderController ctl = widget.controller;
    final PosOrder? order = ctl.order;
    final bool open = ctl.isOpen;
    final List<DraftLine> draft = ctl.draft;
    final Map<int, List<PosOrderItem>> rounds =
        order?.itemsByRound ?? const <int, List<PosOrderItem>>{};
    final List<int> roundNos = rounds.keys.toList()..sort();
    _scrollToEnd((order?.items.length ?? 0) * 1000 + draft.length);

    final List<Widget> children = <Widget>[
      for (final int n in roundNos)
        _RoundSection(round: n, items: rounds[n]!, onItemTap: widget.onItemTap),
      if (open && draft.isNotEmpty)
        _DraftSection(
          controller: ctl,
          round: (order?.rounds ?? 0) + 1,
          onTap: widget.onDraftTap,
        ),
      if (roundNos.isEmpty && draft.isEmpty)
        const Padding(
          padding: EdgeInsets.only(top: 48),
          child: EmptyState(
            icon: Icons.restaurant_menu_outlined,
            title: "Hali taom yo'q",
            message: 'Menyudan taom tanlang: u yangi bosqichga tushadi.',
          ),
        ),
    ];

    final List<PosOrderItem> held = ctl.held;
    final int unsent = ctl.unsentNow.length;
    final bool canSend = open && (draft.isNotEmpty || unsent > 0);
    final String sendLabel = draft.isNotEmpty
        ? (ctl.holdRound ? 'Saqlash (ushlab)' : 'Yuborish (${draft.length})')
        : (unsent > 0 ? 'Yuborish ($unsent)' : 'Yuborish');

    return ColoredBox(
      color: c.surface,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          if (open && widget.quickProducts.isNotEmpty)
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
              child: Row(
                children: <Widget>[
                  Text(
                    'Choy-non:',
                    style: RzType.footnote.copyWith(color: c.fgMuted),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Wrap(
                      spacing: 8,
                      runSpacing: 4,
                      children: <Widget>[
                        for (final PosProduct p in widget.quickProducts)
                          ActionChip(
                            key: ValueKey<String>('quick-${p.id}'),
                            avatar: const Icon(Icons.add, size: 18),
                            label: Text(p.name),
                            labelStyle: RzType.headline,
                            materialTapTargetSize: MaterialTapTargetSize.padded,
                            onPressed: ctl.busy
                                ? null
                                : () => widget.onQuickAdd(p),
                          ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          Divider(height: 1, color: c.line),
          Expanded(
            child: ListView(
              controller: _scroll,
              padding: const EdgeInsets.only(bottom: 12),
              children: children,
            ),
          ),
          // P6-21: the order's courses and the next one to fire
          if (open &&
              widget.onSendCourse != null &&
              (order?.courses.isNotEmpty ?? false))
            CourseBar(
              courses: order!.courses,
              enabled: !ctl.busy,
              onSend: widget.onSendCourse!,
            ),
          if (open && held.isNotEmpty)
            Material(
              color: Color.alphaBlend(c.warningSoft, c.surface),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 4, 8, 4),
                child: Row(
                  children: <Widget>[
                    Icon(Icons.pause_circle_outline, color: c.warningSoftFg),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Ushlab turilgan: ${held.length} ta taom',
                        style: RzType.body.copyWith(color: c.fg),
                      ),
                    ),
                    TextButton.icon(
                      onPressed: ctl.busy ? null : widget.onSendHeld,
                      icon: const Icon(Icons.send_outlined),
                      label: const Text('Hozir yuborish'),
                    ),
                  ],
                ),
              ),
            ),
          Divider(height: 1, color: c.line),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
            child: _Totals(
              controller: ctl,
              onCharge: widget.onCharge,
              onRemoveDiscount: widget.onRemoveDiscount,
              onPackaging: widget.onPackaging,
              onPickup: widget.onPickup,
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 4, 12, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                if (open && draft.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Row(
                      children: <Widget>[
                        Text(
                          'Oshxonaga:',
                          style: RzType.footnote.copyWith(color: c.fgMuted),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: SegmentedButton<bool>(
                            showSelectedIcon: false,
                            segments: const <ButtonSegment<bool>>[
                              ButtonSegment<bool>(
                                value: false,
                                label: Text('Hozir'),
                                icon: Icon(Icons.send_outlined),
                              ),
                              ButtonSegment<bool>(
                                value: true,
                                label: Text('Ushlab turish'),
                                icon: Icon(Icons.pause_circle_outline),
                              ),
                            ],
                            selected: <bool>{ctl.holdRound},
                            onSelectionChanged: (Set<bool> s) =>
                                ctl.setHoldRound(s.first),
                          ),
                        ),
                      ],
                    ),
                  ),
                Row(
                  children: <Widget>[
                    Expanded(
                      child: RzButton(
                        label: 'Saqlash',
                        size: RzButtonSize.lg,
                        variant: RzButtonVariant.secondary,
                        onPressed: open && draft.isNotEmpty && !ctl.busy
                            ? () => widget.onSubmit(false)
                            : null,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      flex: 2,
                      child: RzButton(
                        key: const ValueKey<String>('send'),
                        label: sendLabel,
                        icon: Icons.send,
                        size: RzButtonSize.lg,
                        loading: ctl.submitting,
                        onPressed: canSend ? () => widget.onSubmit(true) : null,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                // P6-25: no bill while a dish waits for its weight (the server refuses it: weight_pending)
                RzButton(
                  key: const ValueKey<String>('order-bill'),
                  label: (order?.weightPending ?? 0) > 0
                      ? weightPendingLabel(order!.weightPending)
                      : 'Schyot',
                  icon: (order?.weightPending ?? 0) > 0
                      ? Icons.scale_outlined
                      : Icons.receipt_long_outlined,
                  size: RzButtonSize.lg,
                  variant: RzButtonVariant.success,
                  fullWidth: true,
                  onPressed:
                      order != null &&
                          !ctl.busy &&
                          (order.weightPending == 0 || !order.isOpen)
                      ? widget.onBill
                      : null,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RoundSection extends StatelessWidget {
  const _RoundSection({
    required this.round,
    required this.items,
    required this.onItemTap,
  });

  final int round;
  final List<PosOrderItem> items;
  final ValueChanged<PosOrderItem> onItemTap;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final DateTime? at = items
        .map((PosOrderItem i) => i.addedAt)
        .whereType<DateTime>()
        .fold<DateTime?>(
          null,
          (DateTime? a, DateTime b) => a == null || b.isBefore(a) ? b : a,
        );
    final String? who = items
        .map((PosOrderItem i) => i.addedBy?.name)
        .whereType<String>()
        .firstOrNull;
    final PosMoney sum = PosMoney.sum(
      items
          .where((PosOrderItem i) => !i.isVoid)
          .map((PosOrderItem i) => i.totalPrice),
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Container(
          color: Color.alphaBlend(c.surface2, c.surface),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          child: Row(
            children: <Widget>[
              Text(
                round == 0 ? 'Bosqichsiz' : '$round-bosqich',
                style: RzType.headline.copyWith(color: c.fg),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  <String>[
                    if (at != null) formatReceiptTime(at.toLocal()),
                    ?who,
                  ].join(' · '),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: RzType.footnote.copyWith(color: c.fgMuted),
                ),
              ),
              Text(sum.format(), style: RzType.footnote.copyWith(color: c.fg)),
            ],
          ),
        ),
        for (final PosOrderItem i in items)
          PosOrderLineTile(
            key: ValueKey<String>('item-${i.id}'),
            quantityLabel: itemQuantityLabel(i),
            name: i.displayName,
            // P6-31: a combo's parts, without prices
            parts: i.isCombo
                ? comboPartLines(i.comboParts)
                : setPartLines(i.setParts),
            modifiers: itemModifierLabels(i),
            note: i.note,
            seat: i.seatNo,
            amount: i.totalPrice,
            voided: i.isVoid,
            voidReason: i.voidReason,
            status: restaurantStatusChip(i),
            onTap: () => onItemTap(i),
          ),
      ],
    );
  }
}

class _DraftSection extends StatelessWidget {
  const _DraftSection({
    required this.controller,
    required this.round,
    required this.onTap,
  });

  final OrderController controller;
  final int round;
  final ValueChanged<DraftLine> onTap;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final OrderController ctl = controller;
    return Container(
      margin: const EdgeInsets.fromLTRB(8, 8, 8, 0),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(RzRadius.card),
        border: Border.all(color: c.brand, width: 1.5),
        color: Color.alphaBlend(c.brandSoft.withValues(alpha: 0.35), c.surface),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 8, 8, 4),
            child: Text(
              'Yangi bosqich ($round) · ${ctl.draftEstimate.format()}',
              style: RzType.headline.copyWith(color: c.brandSoftFg),
            ),
          ),
          for (final DraftLine l in ctl.draft) ...<Widget>[
            PosOrderLineTile(
              key: ValueKey<String>('draft-${l.key}'),
              quantityLabel: l.quantity.label(
                l.variant.unit,
                unitLabel: l.variant.unitLabel,
              ),
              name: l.name,
              // P6-31: a combo's picks; P6-32: quick groups are on the line's buttons below
              parts: l.combo != null
                  ? l.comboParts
                  : setPartLines(l.product.setParts),
              modifiers: l.combo != null || quickGroupsOf(l.product).isEmpty
                  ? l.modifierLabels
                  : l.dialogModifierLabels,
              note: l.note,
              seat: l.seatNo,
              amount: l.estimate,
              status: l.fireMode == PosFireMode.hold
                  ? const PosToneChip(
                      label: 'Ushlab turiladi',
                      tone: PosChipTone.warning,
                      icon: Icons.pause_circle_outline,
                    )
                  : (l.fireMode == PosFireMode.now && ctl.holdRound
                        ? const PosToneChip(
                            label: 'Hozir yuboriladi',
                            tone: PosChipTone.info,
                          )
                        : null),
              leading: l.variant.unit.isMeasured
                  ? null
                  : PosQuantityStepper(
                      value: l.quantity,
                      step: l.variant.step,
                      min: l.variant.minimum,
                      buttonSize: 48,
                      onChanged: (PosQuantity q) => ctl.setQuantity(l.key, q),
                      onRemove: () => ctl.removeLine(l.key),
                    ),
              trailing: l.variant.unit.isMeasured
                  ? IconButton(
                      tooltip: 'Olib tashlash',
                      onPressed: () => ctl.removeLine(l.key),
                      icon: const Icon(Icons.delete_outline),
                    )
                  : null,
              onTap: () => onTap(l),
            ),
            if (l.combo == null && quickGroupsOf(l.product).isNotEmpty)
              QuickModifierBar(
                product: l.product,
                selected: l.modifiers,
                availability: ctl.catalog?.availability,
                keyPrefix: 'draft-${l.key}-mod',
                onChanged: (Map<String, int> m) =>
                    ctl.setDraftModifiers(l.key, m),
              ),
          ],
          const SizedBox(height: 6),
        ],
      ),
    );
  }
}

class _Totals extends StatelessWidget {
  const _Totals({
    required this.controller,
    required this.onCharge,
    this.onRemoveDiscount,
    this.onPackaging,
    this.onPickup,
  });

  final OrderController controller;
  final void Function(PosChargeKind kind, bool waived) onCharge;
  final VoidCallback? onRemoveDiscount;
  final ValueChanged<PosPackagingLine>? onPackaging;
  final VoidCallback? onPickup;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosOrder? o = controller.order;
    final PosMoney draft = controller.draftEstimate;
    if (o == null) {
      return PosTotalsPanel(
        lines: const <PosTotalsLine>[],
        totalLabel: 'Yangi bosqich (taxminan)',
        total: draft,
      );
    }
    final PosOrderCharges ch = o.charges;
    final bool open = o.isOpen;
    final bool packagingOffline =
        PosScope.maybeOf(context)?.data.offline?.active ?? false;
    final PosEntitlements plan =
        PosScope.maybeOf(context)?.session.entitlements ?? PosEntitlements.open;
    final PosFormat? format = PosScope.maybeOf(context)?.session.posFormat;
    final bool serviceChargeOn =
        format == null ||
        (format.has(PosModule.serviceCharge) && !format.serviceChargeZero);
    PosTotalsLine? charge(PosChargeKind kind, String label, PosMoney amount) {
      final bool waived = ch.waived.contains(kind);
      if (!waived && amount.isZero) return null;
      final PosChargeWaiver? w = ch.waivers
          .where((PosChargeWaiver x) => x.charge == kind.wire)
          .lastOrNull;
      // P4-21: taking a waived service charge back is the plan's service_charge; taking it off is always allowed
      // P6-11: nor where the format has no service charge (or the law keeps it at 0%, stolovaya)
      final bool restoreLocked =
          waived &&
          kind == PosChargeKind.serviceCharge &&
          (!plan.has(PosFeature.serviceCharge) || !serviceChargeOn);
      final String waivedText =
          'Olib tashlandi${w == null || w.reason.isEmpty ? '' : ': ${w.reason}'}';
      return PosTotalsLine(
        label: label,
        amount: amount,
        waived: waived,
        detail: restoreLocked
            ? '$waivedText · ${serviceChargeOn ? plan.lockLabel(PosFeature.serviceCharge) : 'formatda yo\'q'}'
            : waived
            ? waivedText
            : null,
        actionLabel: open && !restoreLocked
            ? (waived ? 'Qaytarish' : 'Olib tashlash')
            : null,
        onAction: open && !restoreLocked ? () => onCharge(kind, waived) : null,
      );
    }

    final List<PosTotalsLine> lines = <PosTotalsLine>[
      PosTotalsLine(
        label: 'Taomlar',
        amount: ch.itemsSubtotal.isZero ? o.subtotal : ch.itemsSubtotal,
      ),
      if (o.hasDiscount)
        PosTotalsLine(
          label: discountLabel(o),
          amount: o.discount,
          negative: true,
          detail: o.discountReason,
          actionLabel: open && onRemoveDiscount != null
              ? 'Olib tashlash'
              : null,
          onAction: open ? onRemoveDiscount : null,
        ),
      ?charge(PosChargeKind.roomFee, 'Xona puli', o.roomFeeAmount),
      ?charge(
        PosChargeKind.minSpendTopup,
        ch.minSpend.isZero
            ? "Minimal summaga qo'shimcha"
            : "Minimal summa (${ch.minSpend.format()}) gacha",
        o.minSpendTopupAmount,
      ),
      ?charge(
        PosChargeKind.serviceCharge,
        'Xizmat haqi ${formatPercent(ch.serviceChargePercent)}%',
        o.serviceChargeAmount,
      ),
      ?charge(
        PosChargeKind.placeFee,
        o.placeFeeIsCover ? 'Kuver' : 'Joy narxi',
        o.placeFeeAmount,
      ),
      // P6-34: each packaging line, removable with a reason (online only)
      for (final PosPackagingLine l in o.packagingLines)
        PosTotalsLine(
          label: l.quantity > 1 ? '${l.name} × ${l.quantity}' : l.name,
          amount: l.removed ? l.unitPrice * l.quantity : l.amount,
          waived: l.removed,
          detail: l.removed
              ? 'Olib tashlandi${(l.removedReason ?? '').isEmpty ? '' : ': ${l.removedReason}'}'
              : (packagingOffline && open
                    ? "Internet yo'q: olib tashlab bo'lmaydi"
                    : null),
          actionLabel: open && onPackaging != null && !packagingOffline
              ? (l.removed ? 'Qaytarish' : 'Olib tashlash')
              : null,
          onAction: open && onPackaging != null && !packagingOffline
              ? () => onPackaging!(l)
              : null,
        ),
    ];
    final PosPickupTime? pickup = o.pickup;
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        if (onPickup != null || pickup != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Align(
              alignment: Alignment.centerLeft,
              child: PickupTimeButton(
                label: pickupTimeLabel(pickup),
                waiting: pickup?.waiting ?? true,
                onPressed: onPickup,
              ),
            ),
          ),
        PosTotalsPanel(lines: lines, total: o.totalPrice),
        if (!draft.isZero)
          Text(
            '+ yangi bosqich ≈ ${draft.format()}',
            textAlign: TextAlign.right,
            style: RzType.footnote.copyWith(color: c.brandSoftFg),
          ),
      ],
    );
  }
}
