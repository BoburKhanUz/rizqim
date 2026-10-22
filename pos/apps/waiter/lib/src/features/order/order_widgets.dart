import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Qator holati belgisi (rang + ikonka + yorliq): holat faqat rang bilan emas.
class LineStatusChip extends StatelessWidget {
  const LineStatusChip({super.key, required this.item});

  final PosOrderItem item;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final (
      String label,
      IconData icon,
      Color bg,
      Color fg,
    ) = switch (item.kitchenStatus) {
      PosKitchenStatus.voided => (
        "O'chirildi",
        Icons.block,
        c.dangerSoft,
        c.dangerSoftFg,
      ),
      PosKitchenStatus.pending when item.fireMode == PosFireMode.hold => (
        'Ushlab turilgan',
        Icons.pause_circle_outline,
        c.infoSoft,
        c.infoSoftFg,
      ),
      PosKitchenStatus.pending => (
        'Yuborilmagan',
        Icons.hourglass_empty,
        c.warningSoft,
        c.warningSoftFg,
      ),
      PosKitchenStatus.sent => (
        'Oshxonada',
        Icons.soup_kitchen_outlined,
        c.infoSoft,
        c.infoSoftFg,
      ),
      PosKitchenStatus.ready => (
        'Tayyor',
        Icons.room_service_outlined,
        c.successSoft,
        c.successSoftFg,
      ),
      PosKitchenStatus.served => (
        'Berildi',
        Icons.check_circle_outline,
        c.surface2,
        c.fgMuted,
      ),
      PosKitchenStatus.unknown => (
        "Noma'lum",
        Icons.help_outline,
        c.surface2,
        c.fgMuted,
      ),
    };
    return Semantics(
      label: label,
      excludeSemantics: true,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(RzRadius.control),
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(icon, size: 14, color: fg),
              const SizedBox(width: 5),
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: RzType.caption.copyWith(color: fg),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Buyurtmadagi bitta qator: nom, miqdor, qo'shimchalar, izoh, holat, summa. Tayyor qatorda katta "Berildi" tugmasi
/// (qatorning o'zi ajratib ko'rsatiladi), yuborilmaganida — o'chirish, ushlab turilganida — "Yuborish".
class OrderLineTile extends StatelessWidget {
  const OrderLineTile({
    super.key,
    required this.item,
    this.editable = false,
    this.enabled = true,
    this.onServed,
    this.onSend,
    this.onVoid,
  });

  final PosOrderItem item;

  /// Amal tugmalari ko'rinadimi (boshqa ofitsiantning buyurtmasida — yo'q).
  final bool editable;
  final bool enabled;
  final VoidCallback? onServed;
  final VoidCallback? onSend;
  final VoidCallback? onVoid;

  String get _quantityText =>
      item.quantityLabel ?? item.quantity.label(item.unit);

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final bool ready = item.kitchenStatus == PosKitchenStatus.ready;
    final bool voided = item.isVoid;
    final bool pending = item.kitchenStatus == PosKitchenStatus.pending;
    final TextStyle nameStyle = RzType.headline.copyWith(
      color: voided ? c.fgSubtle : c.fg,
      decoration: voided ? TextDecoration.lineThrough : null,
    );
    final List<String> mods = <String>[
      for (final PosOrderItemModifier m in item.modifiers)
        m.quantity > 1 ? '${m.name} x${m.quantity}' : m.name,
    ];
    return DecoratedBox(
      decoration: BoxDecoration(
        color: ready ? Color.alphaBlend(c.successSoft, c.surface) : null,
        border: ready ? Border.all(color: c.success, width: 1.5) : null,
        borderRadius: BorderRadius.circular(RzRadius.control),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(10, 10, 10, 10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(item.displayName, style: nameStyle),
                      const SizedBox(height: 2),
                      Text(
                        _quantityText,
                        style: RzType.body.copyWith(color: c.fgMuted),
                      ),
                      if (mods.isNotEmpty)
                        Text(
                          '+ ${mods.join(', ')}',
                          style: RzType.footnote.copyWith(color: c.fgMuted),
                        ),
                      if (item.note != null && item.note!.trim().isNotEmpty)
                        Text(
                          'Izoh: ${item.note}',
                          style: RzType.footnote.copyWith(
                            color: c.fgMuted,
                            fontStyle: FontStyle.italic,
                          ),
                        ),
                      if (voided && (item.voidReason ?? '').isNotEmpty)
                        Text(
                          'Sabab: ${item.voidReason}',
                          style: RzType.footnote.copyWith(
                            color: c.dangerSoftFg,
                          ),
                        ),
                      const SizedBox(height: 6),
                      LineStatusChip(item: item),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  item.totalPrice.format(),
                  style: RzType.body.copyWith(
                    color: voided ? c.fgSubtle : c.fg,
                    decoration: voided ? TextDecoration.lineThrough : null,
                  ),
                ),
              ],
            ),
            if (editable && ready) ...<Widget>[
              const SizedBox(height: 10),
              RzButton(
                label: 'Berildi',
                icon: Icons.check_rounded,
                variant: RzButtonVariant.success,
                size: RzButtonSize.lg,
                fullWidth: true,
                onPressed: enabled ? onServed : null,
              ),
            ],
            if (editable && pending) ...<Widget>[
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: <Widget>[
                  if (item.fireMode == PosFireMode.hold && onSend != null)
                    RzButton(
                      label: 'Yuborish',
                      icon: Icons.send_rounded,
                      variant: RzButtonVariant.secondary,
                      size: RzButtonSize.lg,
                      onPressed: enabled ? onSend : null,
                    ),
                  if (onVoid != null) ...<Widget>[
                    const SizedBox(width: 8),
                    SizedBox.square(
                      dimension: 52,
                      child: IconButton(
                        tooltip: "Olib tashlash",
                        onPressed: enabled ? onVoid : null,
                        icon: Icon(
                          Icons.delete_outline,
                          color: enabled ? c.dangerSoftFg : c.fgSubtle,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Bir bosqich: sarlavha ("1-bosqich · 20:15") va qatorlar.
class RoundSection extends StatelessWidget {
  const RoundSection({
    super.key,
    required this.roundNo,
    required this.items,
    required this.tileBuilder,
  });

  final int roundNo;
  final List<PosOrderItem> items;
  final Widget Function(PosOrderItem item) tileBuilder;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    DateTime? at;
    for (final PosOrderItem i in items) {
      final DateTime? t = i.addedAt;
      if (t != null && (at == null || t.isBefore(at))) at = t;
    }
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(8, 12, 8, 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.fromLTRB(8, 0, 8, 8),
              child: Row(
                children: <Widget>[
                  Icon(Icons.layers_outlined, size: 18, color: c.fgMuted),
                  const SizedBox(width: 6),
                  Text(
                    roundNo > 0 ? '$roundNo-bosqich' : 'Boshqa qatorlar',
                    style: RzType.headline.copyWith(color: c.fg),
                  ),
                  if (at != null) ...<Widget>[
                    const SizedBox(width: 8),
                    Text(
                      formatReceiptTime(at.toLocal()),
                      style: RzType.body.copyWith(color: c.fgMuted),
                    ),
                  ],
                ],
              ),
            ),
            for (int i = 0; i < items.length; i++) ...<Widget>[
              if (i > 0) Divider(height: 1, color: c.line),
              tileBuilder(items[i]),
            ],
          ],
        ),
      ),
    );
  }
}

/// Buyurtma hisobi (serverdan): taomlar, xizmat haqi, xona puli, minimal summaga qo'shimcha, jami.
class OrderTotals extends StatelessWidget {
  const OrderTotals({super.key, required this.order});

  final PosOrder order;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    Widget row(String label, PosMoney value, {bool strong = false}) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: <Widget>[
          Expanded(
            child: Text(
              label,
              style: (strong ? RzType.headline : RzType.body).copyWith(
                color: strong ? c.fg : c.fgMuted,
              ),
            ),
          ),
          Text(
            value.format(),
            style: (strong ? RzType.title : RzType.body).copyWith(color: c.fg),
          ),
        ],
      ),
    );
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: <Widget>[
            row('Taomlar', order.subtotal),
            if (order.discount.isPositive) row('Chegirma', -order.discount),
            if (order.serviceChargeAmount.isPositive)
              row(
                'Xizmat haqi (${formatPercent(order.charges.serviceChargePercent)}%)',
                order.serviceChargeAmount,
              ),
            if (order.placeFeeAmount.isPositive)
              row('Joy narxi', order.placeFeeAmount),
            if (order.roomFeeAmount.isPositive)
              row('Xona puli', order.roomFeeAmount),
            if (order.minSpendTopupAmount.isPositive)
              row("Minimal summaga qo'shimcha", order.minSpendTopupAmount),
            const SizedBox(height: 4),
            Divider(color: c.line),
            row('Jami', order.totalPrice, strong: true),
          ],
        ),
      ),
    );
  }
}
