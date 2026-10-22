import 'package:pos_core/pos_core.dart';

import '../json.dart';

/// Onlayn kanaldan (Telegram Mini App, mobil ilova) kelgan va kassir qabul qilishini kutayotgan buyurtma
/// (`GET /staff/orders?status=new`, docs/API.md 9.1).
class PosOnlineOrder {
  const PosOnlineOrder({
    required this.uuid,
    required this.status,
    required this.channel,
    this.shortCode,
    this.statusLabel,
    this.type = '',
    this.branchId,
    this.paymentMethod,
    this.paymentStatus,
    this.subtotal = PosMoney.zero,
    this.deliveryPrice = PosMoney.zero,
    this.totalPrice = PosMoney.zero,
    this.notes,
    this.customerName,
    this.customerPhone,
    this.deliveryAddress,
    this.items = const <PosOnlineOrderItem>[],
    this.createdAt,
    this.waitingSeconds,
    this.raw = const <String, Object?>{},
  });

  factory PosOnlineOrder.fromJson(PosJson j) {
    final PosJson? customer = j.objOrNull('customer');
    final PosJson? delivery = j.objOrNull('delivery');
    return PosOnlineOrder(
      uuid: j.str('uuid'),
      shortCode: j.strOrNull('short_code'),
      status: j.str('status'),
      statusLabel: j.strOrNull('status_label'),
      type: j.strOrNull('type') ?? '',
      channel: j.strOrNull('channel') ?? '',
      branchId: j.intOrNull('branch_id'),
      paymentMethod: j.strOrNull('payment_method'),
      paymentStatus: j.strOrNull('payment_status'),
      subtotal: j.money('subtotal'),
      deliveryPrice: j.money('delivery_price'),
      totalPrice: j.money('total_price'),
      notes: j.strOrNull('notes'),
      customerName: customer?.strOrNull('name'),
      customerPhone: customer?.strOrNull('phone'),
      deliveryAddress: delivery?.strOrNull('address'),
      items: j.objects('items', PosOnlineOrderItem.fromJson),
      createdAt: j.dateOrNull('created_at'),
      waitingSeconds: j.intOrNull('waiting_seconds'),
      raw: j.map,
    );
  }

  final String uuid;
  final String? shortCode;

  /// `new` (qabul kutmoqda), `accepted`, ...
  final String status;
  final String? statusLabel;

  /// `delivery`, `pickup`, `dine_in`.
  final String type;

  /// `telegram`, `mobile`, `aggregator`.
  final String channel;
  final int? branchId;

  /// `cash`, `click`, `payme`, ...
  final String? paymentMethod;
  final String? paymentStatus;
  final PosMoney subtotal;
  final PosMoney deliveryPrice;
  final PosMoney totalPrice;
  final String? notes;
  final String? customerName;
  final String? customerPhone;
  final String? deliveryAddress;
  final List<PosOnlineOrderItem> items;
  final DateTime? createdAt;
  final int? waitingSeconds;
  final Map<String, Object?> raw;

  bool get isNew => status == 'new';

  /// Onlayn to'langan (Click, Payme): server o'zi qabul qiladi.
  bool get isPaid => paymentStatus == 'paid';
}

class PosOnlineOrderItem {
  const PosOnlineOrderItem({
    required this.name,
    this.variantName,
    this.quantityLabel,
    this.totalPrice,
    this.note,
  });

  factory PosOnlineOrderItem.fromJson(PosJson j) => PosOnlineOrderItem(
    name: j.strOrNull('product_name') ?? j.strOrNull('name') ?? '',
    variantName: j.strOrNull('variant_name'),
    quantityLabel: j.strOrNull('quantity_label') ?? j.strOrNull('quantity'),
    totalPrice: j.moneyOrNull('total_price'),
    note: j.strOrNull('note'),
  );

  final String name;
  final String? variantName;

  /// "2", "1.5 kg".
  final String? quantityLabel;
  final PosMoney? totalPrice;
  final String? note;

  String get displayName => variantName == null || variantName!.isEmpty
      ? name
      : '$name ($variantName)';
}

/// Rad etish sababi (`POST /staff/orders/{uuid}/status` `reason`, backend `CancelReason`).
enum PosRejectReason {
  outOfStock('out_of_stock', 'Mahsulot qolmagan'),
  storeClosed('store_closed', "Do'kon yopiq"),
  customerRequest('customer_request', "Mijoz so'rovi"),
  courierUnavailable('courier_unavailable', "Kuryer yo'q"),
  other('other', 'Boshqa sabab');

  const PosRejectReason(this.wire, this.label);

  final String wire;
  final String label;
}
