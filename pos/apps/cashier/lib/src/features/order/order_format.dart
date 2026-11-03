import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';

/// Xatoni foydalanuvchiga ko'rsatiladigan matnga aylantiradi (tarmoq, maydon xatosi, server sababi).
String posErrorText(Object error) => switch (error) {
  PosNetworkException() =>
    "Aloqa yo'q: server javob bermadi. Qayta urinib ko'ring.",
  PosValidationException() => error.firstError,
  PosException() => error.message,
  _ => "Kutilmagan xato. Qayta urinib ko'ring.",
};

/// Qator miqdori yorlig'i: serverdagi ("1.5 kg (guruch)") yoki o'zimiz.
String itemQuantityLabel(PosOrderItem i) =>
    i.quantityLabel ?? i.quantity.label(i.unit);

/// P6-31: kombo tarkibi (narxsiz): "Ichimlik — 0,4 l Pepsi".
List<String> comboPartLines(List<PosComboPart>? parts) => <String>[
  for (final PosComboPart p in parts ?? const <PosComboPart>[])
    p.label.isNotEmpty ? p.label : p.name,
];

/// Qo'shimchalar matni: "Piyoz", "2 × Qazi".
List<String> itemModifierLabels(PosOrderItem i) => <String>[
  for (final PosOrderItemModifier m in i.modifiers)
    m.quantity > 1 ? '${m.quantity} × ${m.name}' : m.name,
];

/// Marka uchun birlik yorlig'i: "1.5 kg (guruch)" → "kg (guruch)".
String? itemUnitLabel(PosOrderItem i) {
  if (!i.unit.isMeasured) return null;
  final String? label = i.quantityLabel;
  final String prefix = '${i.quantity.format()} ';
  if (label != null && label.startsWith(prefix)) {
    return label.substring(prefix.length);
  }
  return null;
}

/// Qator holati belgisi: yuborilmagan, ushlab turilgan (muddati bilan), oshxonada, tayyor, berildi, o'chirildi.
PosToneChip kitchenStatusChip(PosOrderItem i) {
  final String? due = i.dueAt == null
      ? null
      : formatReceiptTime(i.dueAt!.toLocal());
  return switch (i.kitchenStatus) {
    PosKitchenStatus.pending when i.fireMode == PosFireMode.hold => PosToneChip(
      label: due == null ? 'Ushlab turilgan' : 'Ushlab turilgan · $due',
      tone: PosChipTone.warning,
      icon: Icons.pause_circle_outline,
    ),
    PosKitchenStatus.pending => const PosToneChip(
      label: 'Yuborilmagan',
      tone: PosChipTone.brand,
      icon: Icons.schedule_send_outlined,
    ),
    PosKitchenStatus.sent => PosToneChip(
      label: due == null ? 'Oshxonada' : 'Oshxonada · $due',
      tone: PosChipTone.info,
      icon: Icons.soup_kitchen_outlined,
    ),
    PosKitchenStatus.ready => const PosToneChip(
      label: 'Tayyor',
      tone: PosChipTone.success,
      icon: Icons.room_service_outlined,
    ),
    PosKitchenStatus.served => const PosToneChip(
      label: 'Berildi',
      icon: Icons.check_circle_outline,
    ),
    PosKitchenStatus.voided => const PosToneChip(
      label: "O'chirildi",
      tone: PosChipTone.danger,
      icon: Icons.block,
    ),
    PosKitchenStatus.unknown => const PosToneChip(label: "Noma'lum"),
  };
}

/// Buyurtma joyi: "3-xona", "Olib ketish", "Zalda, stolsiz".
String orderPlaceLabel(PosOrder o) {
  if (o.table != null) return o.table!.name;
  return PosChannel.fromWire(o.channel) == PosChannel.takeaway
      ? 'Olib ketish'
      : 'Zalda, stolsiz';
}

/// Kanal bo'yicha joy (stolsiz buyurtmalar ro'yxati uchun).
String channelPlaceLabel(String channel) =>
    PosChannel.fromWire(channel) == PosChannel.takeaway
    ? 'Olib ketish'
    : 'Zalda, stolsiz';

/// Narx yorlig'i: "120 000 so'm / kg (guruch)".
String variantPriceLabel(PosVariant v) {
  final String price = v.price.format();
  if (!v.unit.isMeasured) return price;
  final String unit = (v.unitLabel == null || v.unitLabel!.trim().isEmpty)
      ? v.unit.label
      : v.unitLabel!.trim();
  return '$price / $unit';
}

/// "1 soat 35 daq", "12 daq".
String formatOpenFor(Duration d) {
  if (d.isNegative) return '0 daq';
  final int h = d.inHours;
  final int m = d.inMinutes % 60;
  return h == 0 ? '$m daq' : '$h soat $m daq';
}
