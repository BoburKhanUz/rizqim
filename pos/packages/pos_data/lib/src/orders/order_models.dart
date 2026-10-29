import 'package:pos_core/pos_core.dart';

import '../catalog/catalog_models.dart';
import '../json.dart';
import '../menu_day/menu_day_models.dart';
import '../queue/online_order_models.dart';

/// POS qatorining oshxona holati: `pending` (faqat buyurtmada) → `sent` → `ready` → `served`; `void` — o'chirilgan.
enum PosKitchenStatus {
  pending('pending', 'Yuborilmagan'),
  sent('sent', 'Oshxonada'),
  ready('ready', 'Tayyor'),
  served('served', 'Berildi'),
  voided('void', "O'chirildi"),
  unknown('', "Noma'lum");

  const PosKitchenStatus(this.wire, this.label);

  final String wire;
  final String label;

  static PosKitchenStatus fromWire(String? value) {
    for (final PosKitchenStatus s in values) {
      if (s.wire == value && s != unknown) return s;
    }
    return unknown;
  }
}

/// Qator qachon oshxonaga ketadi: `now` — keyingi yuborishda, `hold` — faqat id bilan yuborilganda.
enum PosFireMode {
  now('now', 'Hozir'),
  hold('hold', 'Ushlab turish');

  const PosFireMode(this.wire, this.label);

  final String wire;
  final String label;

  static PosFireMode? fromWire(String? value) {
    for (final PosFireMode m in values) {
      if (m.wire == value) return m;
    }
    return null;
  }
}

/// Olib tashlanadigan xizmat qatorlari (`/charges/{charge}/waive|restore`).
enum PosChargeKind {
  serviceCharge('service_charge', 'Xizmat haqi'),
  placeFee('place_fee', 'Joy narxi'),
  roomFee('room_fee', 'Xona puli'),
  minSpendTopup('min_spend_topup', "Minimal summaga qo'shimcha");

  const PosChargeKind(this.wire, this.label);

  final String wire;
  final String label;

  static PosChargeKind? fromWire(String? value) {
    for (final PosChargeKind k in values) {
      if (k.wire == value) return k;
    }
    return null;
  }
}

/// Buyurtma chegirmasi turi (`discount_kind`): foiz (qatorlar o'zgarsa qayta hisoblanadi) yoki summa.
enum PosDiscountKind {
  percent('percent', 'Foiz'),
  amount('amount', "So'm");

  const PosDiscountKind(this.wire, this.label);

  final String wire;
  final String label;

  static PosDiscountKind? fromWire(String? value) {
    for (final PosDiscountKind k in values) {
      if (k.wire == value) return k;
    }
    return null;
  }
}

/// `{id, name, kind}` — buyurtmadagi joy.
class PosTableRef {
  const PosTableRef({
    required this.id,
    required this.name,
    this.kind = FloorTableKind.table,
  });

  factory PosTableRef.fromJson(PosJson j) => PosTableRef(
    id: j.integer('id'),
    name: j.strOrNull('name') ?? '',
    kind: FloorTableKind.fromWire(j.strOrNull('kind')),
  );

  static PosTableRef? maybe(PosJson j, String key) {
    final PosJson? o = j.objOrNull(key);
    return o == null ? null : PosTableRef.fromJson(o);
  }

  final int id;
  final String name;
  final FloorTableKind kind;
}

/// Bitta POS buyurtma — har o'zgartiruvchi endpoint shu to'liq ko'rinishni qaytaradi. Narx, xizmat haqi va xona
/// pulini **faqat server** hisoblaydi: ilova shu summalarni ko'rsatadi.
class PosOrder {
  const PosOrder({
    required this.uuid,
    required this.status,
    required this.channel,
    required this.type,
    required this.version,
    required this.items,
    required this.charges,
    this.shortCode,
    this.storeId,
    this.branchId,
    this.hall,
    this.table,
    this.hallRef,
    this.tableRef,
    this.guestsCount,
    this.waiter,
    this.openedBy,
    this.deviceId,
    this.bookingId,
    this.bookingUuid,
    this.depositAvailable = PosMoney.zero,
    this.billLocked = false,
    this.billRequestedAt,
    this.notes,
    this.openedAt,
    this.updatedAt,
    this.subtotal = PosMoney.zero,
    this.discount = PosMoney.zero,
    this.discountKind,
    this.discountValue,
    this.discountReason,
    this.discountBy,
    this.serviceChargeAmount = PosMoney.zero,
    this.placeFeeAmount = PosMoney.zero,
    this.roomFeeAmount = PosMoney.zero,
    this.minSpendTopupAmount = PosMoney.zero,
    this.totalPrice = PosMoney.zero,
    this.rounds = 0,
    this.raw = const <String, Object?>{},
  });

  factory PosOrder.fromJson(PosJson j) => PosOrder(
    uuid: j.str('uuid'),
    shortCode: j.strOrNull('short_code'),
    status: j.str('status'),
    channel: j.str('channel'),
    type: j.strOrNull('type') ?? '',
    storeId: j.intOrNull('store_id'),
    branchId: j.intOrNull('branch_id'),
    version: j.integer('version'),
    hall: PosRef.maybe(j, 'hall'),
    table: PosTableRef.maybe(j, 'table'),
    hallRef: j.strOrNull('hall_ref'),
    tableRef: j.strOrNull('table_ref'),
    guestsCount: j.intOrNull('guests_count'),
    waiter: PosRef.maybe(j, 'waiter'),
    openedBy: j.intOrNull('opened_by'),
    deviceId: j.intOrNull('device_id'),
    bookingId: j.intOrNull('booking_id'),
    bookingUuid: j.strOrNull('booking_uuid'),
    depositAvailable: j.money('deposit_available'),
    billLocked: j.boolean('bill_locked'),
    billRequestedAt: j.dateOrNull('bill_requested_at'),
    notes: j.strOrNull('notes'),
    openedAt: j.dateOrNull('opened_at'),
    updatedAt: j.dateOrNull('updated_at'),
    subtotal: j.money('subtotal'),
    discount: j.money('discount'),
    discountKind: PosDiscountKind.fromWire(j.strOrNull('discount_kind')),
    discountValue: j.strOrNull('discount_value'),
    discountReason: j.strOrNull('discount_reason'),
    discountBy: j.intOrNull('discount_by'),
    serviceChargeAmount: j.money('service_charge_amount'),
    placeFeeAmount: j.money('place_fee_amount'),
    roomFeeAmount: j.money('room_fee_amount'),
    minSpendTopupAmount: j.money('min_spend_topup_amount'),
    totalPrice: j.money('total_price'),
    charges: j.has('charges')
        ? PosOrderCharges.fromJson(j.obj('charges'))
        : const PosOrderCharges(),
    rounds: j.intOrNull('rounds') ?? 0,
    items: j.objects('items', PosOrderItem.fromJson),
    raw: j.map,
  );

  final String uuid;
  final String? shortCode;

  /// `accepted`, `preparing`, `ready`, `delivered` (yopildi), `canceled`.
  final String status;

  /// `pos_hall`, `pos_takeaway` (navbatda onlayn kanallar ham).
  final String channel;

  /// `dine_in`, `pickup`.
  final String type;
  final int? storeId;
  final int? branchId;

  /// Har o'zgarishda oshadi: o'zgartiruvchi so'rovga shu qiymat yuboriladi (eskisi — 409 [PosVersionConflict]).
  final int version;

  /// Zal (`{id, name}`).
  final PosRef? hall;
  final PosTableRef? table;
  final String? hallRef;
  final String? tableRef;
  final int? guestsCount;
  final PosRef? waiter;
  final int? openedBy;
  final int? deviceId;
  final int? bookingId;

  /// Buyurtma ochilgan bron (P3-26).
  final String? bookingUuid;

  /// Bron avanslaridan qolgan summa (schyotni `prepayment` bilan to'lash uchun).
  final PosMoney depositAvailable;

  /// Schyot chiqarilgan: o'chirish va chegirma menejer tasdig'i bilan.
  final bool billLocked;

  /// Ofitsiant schyot so'ragan payt ("Schyot so'raldi", P3-26).
  final DateTime? billRequestedAt;
  final String? notes;
  final DateTime? openedAt;
  final DateTime? updatedAt;
  final PosMoney subtotal;

  /// Chegirma summasi (butun so'm; foizli chegirmada server har qayta hisoblashda yangilaydi).
  final PosMoney discount;

  /// Chegirma turi (P3-37); chegirma yo'q — `null`.
  final PosDiscountKind? discountKind;

  /// Kassir kiritgan qiymat: foiz (`"10.00"`) yoki summa (`"50000.00"`).
  final String? discountValue;

  /// Chegirma sababi.
  final String? discountReason;

  /// Chegirmani bergan xodim id'si.
  final int? discountBy;
  final PosMoney serviceChargeAmount;
  final PosMoney placeFeeAmount;
  final PosMoney roomFeeAmount;
  final PosMoney minSpendTopupAmount;
  final PosMoney totalPrice;
  final PosOrderCharges charges;

  /// Nechta bosqich bor.
  final int rounds;
  final List<PosOrderItem> items;
  final Map<String, Object?> raw;

  PosChannel? get posChannel => PosChannel.fromWire(channel);

  bool get isOpen => status != 'delivered' && status != 'canceled';

  /// Buyurtmada chegirma bor.
  bool get hasDiscount => discountKind != null || !discount.isZero;

  /// Foizli chegirmaning foizi (`"10.00"`); summa bo'lsa yoki yo'q bo'lsa `null`.
  String? get discountPercent =>
      discountKind == PosDiscountKind.percent ? discountValue : null;

  /// O'chirilmagan qatorlar.
  Iterable<PosOrderItem> get activeItems =>
      items.where((PosOrderItem i) => !i.isVoid);

  /// Oshxonaga hali yuborilmagan qatorlar (`hold` ham).
  Iterable<PosOrderItem> get unsentItems => items.where(
    (PosOrderItem i) => i.kitchenStatus == PosKitchenStatus.pending,
  );

  /// Bosqich bo'yicha (1, 2, ...).
  Map<int, List<PosOrderItem>> get itemsByRound {
    final Map<int, List<PosOrderItem>> out = <int, List<PosOrderItem>>{};
    for (final PosOrderItem i in items) {
      (out[i.roundNo ?? 0] ??= <PosOrderItem>[]).add(i);
    }
    return out;
  }

  PosOrderItem? item(int id) {
    for (final PosOrderItem i in items) {
      if (i.id == id) return i;
    }
    return null;
  }
}

/// Xizmat qatorlari va ularning hisobi (`charges`).
class PosOrderCharges {
  const PosOrderCharges({
    this.itemsSubtotal = PosMoney.zero,
    this.discount = PosMoney.zero,
    this.serviceChargePercent = '0',
    this.serviceChargeAmount = PosMoney.zero,
    this.placeFeeAmount = PosMoney.zero,
    this.roomFeeAmount = PosMoney.zero,
    this.roomTariffId,
    this.businessDate,
    this.dayType,
    this.minSpend = PosMoney.zero,
    this.minSpendTopupAmount = PosMoney.zero,
    this.waived = const <PosChargeKind>{},
    this.total = PosMoney.zero,
    this.waivers = const <PosChargeWaiver>[],
  });

  factory PosOrderCharges.fromJson(PosJson j) => PosOrderCharges(
    itemsSubtotal: j.money('items_subtotal'),
    discount: j.money('discount'),
    serviceChargePercent: j.strOrNull('service_charge_percent') ?? '0',
    serviceChargeAmount: j.money('service_charge_amount'),
    placeFeeAmount: j.money('place_fee_amount'),
    roomFeeAmount: j.money('room_fee_amount'),
    roomTariffId: j.intOrNull('room_tariff_id'),
    businessDate: j.strOrNull('business_date'),
    dayType: j.strOrNull('day_type'),
    minSpend: j.money('min_spend'),
    minSpendTopupAmount: j.money('min_spend_topup_amount'),
    waived: <PosChargeKind>{
      for (final String w in j.strings('waived')) ?PosChargeKind.fromWire(w),
    },
    total: j.money('total'),
    waivers: j.objects('waivers', PosChargeWaiver.fromJson),
  );

  final PosMoney itemsSubtotal;
  final PosMoney discount;

  /// Masalan `"10.00"`.
  final String serviceChargePercent;
  final PosMoney serviceChargeAmount;
  final PosMoney placeFeeAmount;
  final PosMoney roomFeeAmount;
  final int? roomTariffId;
  final String? businessDate;

  /// `weekday`, `weekend`, `holiday`.
  final String? dayType;
  final PosMoney minSpend;
  final PosMoney minSpendTopupAmount;
  final Set<PosChargeKind> waived;
  final PosMoney total;
  final List<PosChargeWaiver> waivers;
}

class PosChargeWaiver {
  const PosChargeWaiver({
    required this.charge,
    required this.reason,
    this.waivedBy,
    this.approvedBy,
    this.waivedAt,
  });

  factory PosChargeWaiver.fromJson(PosJson j) => PosChargeWaiver(
    charge: j.str('charge'),
    reason: j.strOrNull('reason') ?? '',
    waivedBy: j.intOrNull('waived_by'),
    approvedBy: j.intOrNull('approved_by'),
    waivedAt: j.dateOrNull('waived_at'),
  );

  final String charge;
  final String reason;
  final int? waivedBy;
  final int? approvedBy;
  final DateTime? waivedAt;
}

class PosOrderItem {
  const PosOrderItem({
    required this.id,
    required this.productName,
    required this.quantity,
    required this.kitchenStatus,
    this.roundNo,
    this.productVariantId,
    this.variantName,
    this.unit = PosUnit.piece,
    this.quantityLabel,
    this.unitPrice = PosMoney.zero,
    this.modifiers = const <PosOrderItemModifier>[],
    this.modifiersTotal = PosMoney.zero,
    this.totalPrice = PosMoney.zero,
    this.note,
    this.seatNo,
    this.fireMode,
    this.dueAt,
    this.stationId,
    this.sentAt,
    this.readyAt,
    this.addedBy,
    this.addedAt,
    this.voidedAt,
    this.voidedBy,
    this.voidReason,
    this.setParts,
  });

  factory PosOrderItem.fromJson(PosJson j) => PosOrderItem(
    id: j.integer('id'),
    roundNo: j.intOrNull('round_no'),
    productVariantId: j.intOrNull('product_variant_id'),
    productName: j.str('product_name'),
    variantName: j.strOrNull('variant_name'),
    quantity: j.quantity('quantity'),
    unit: PosUnit.fromWire(j.strOrNull('unit')),
    quantityLabel: j.strOrNull('quantity_label'),
    unitPrice: j.money('unit_price'),
    modifiers: j.objects('modifiers', PosOrderItemModifier.fromJson),
    modifiersTotal: j.money('modifiers_total'),
    totalPrice: j.money('total_price'),
    note: j.strOrNull('note'),
    seatNo: j.intOrNull('seat_no'),
    fireMode: PosFireMode.fromWire(j.strOrNull('fire_mode')),
    dueAt: j.dateOrNull('due_at'),
    kitchenStatus: PosKitchenStatus.fromWire(j.strOrNull('kitchen_status')),
    stationId: j.intOrNull('station_id'),
    sentAt: j.dateOrNull('sent_at'),
    readyAt: j.dateOrNull('ready_at'),
    addedBy: PosRef.maybe(j, 'added_by'),
    addedAt: j.dateOrNull('added_at'),
    voidedAt: j.dateOrNull('voided_at'),
    voidedBy: PosRef.maybe(j, 'voided_by'),
    voidReason: j.strOrNull('void_reason'),
    setParts: PosSetPart.listOf(j['set_parts']),
  );

  final int id;
  final int? roundNo;
  final int? productVariantId;
  final String productName;
  final String? variantName;
  final PosQuantity quantity;
  final PosUnit unit;

  /// Serverdagi yorliq ("1.5 kg (guruch)", "2").
  final String? quantityLabel;
  final PosMoney unitPrice;
  final List<PosOrderItemModifier> modifiers;
  final PosMoney modifiersTotal;
  final PosMoney totalPrice;
  final String? note;
  final int? seatNo;
  final PosFireMode? fireMode;
  final DateTime? dueAt;
  final PosKitchenStatus kitchenStatus;
  final int? stationId;
  final DateTime? sentAt;
  final DateTime? readyAt;
  final PosRef? addedBy;
  final DateTime? addedAt;
  final DateTime? voidedAt;
  final PosRef? voidedBy;
  final String? voidReason;

  /// P6-15: kompleks qatorining tarkibi (sotuv paytidagi nusxa; kompleks emas — `null`).
  final List<PosSetPart>? setParts;

  bool get isVoid => kitchenStatus == PosKitchenStatus.voided;

  /// Yuborilgan (o'chirish uchun sabab va tasdiq kerak bo'ladi).
  bool get wasSent =>
      sentAt != null ||
      kitchenStatus == PosKitchenStatus.sent ||
      kitchenStatus == PosKitchenStatus.ready ||
      kitchenStatus == PosKitchenStatus.served;

  String get displayName => variantName == null || variantName!.isEmpty
      ? productName
      : '$productName ($variantName)';
}

class PosOrderItemModifier {
  const PosOrderItemModifier({
    required this.name,
    this.id,
    this.groupName,
    this.quantity = 1,
    this.priceDelta = PosMoney.zero,
  });

  factory PosOrderItemModifier.fromJson(PosJson j) => PosOrderItemModifier(
    id: j.intOrNull('id'),
    name: j.str('name'),
    groupName: j.strOrNull('group_name'),
    quantity: j.intOrNull('quantity') ?? 1,
    priceDelta: j.money('price_delta'),
  );

  final int? id;
  final String name;
  final String? groupName;
  final int quantity;
  final PosMoney priceDelta;
}

/// `GET /pos/orders?scope=open`: navbatdagi buyurtma (POS va qabul qilingan onlayn buyurtmalar).
class PosOpenOrder {
  const PosOpenOrder({
    required this.uuid,
    required this.status,
    required this.channel,
    required this.version,
    this.shortCode,
    this.type = '',
    this.branchId,
    this.hall,
    this.table,
    this.hallRef,
    this.tableRef,
    this.guestsCount,
    this.waiter,
    this.itemsCount = 0,
    this.subtotal = PosMoney.zero,
    this.totalPrice = PosMoney.zero,
    this.paymentStatus,
    this.billLocked = false,
    this.billRequested = false,
    this.fiscalPending = false,
    this.bookingId,
    this.businessDate,
    this.openedAt,
    this.closedAt,
    this.updatedAt,
    this.online,
    this.raw = const <String, Object?>{},
  });

  factory PosOpenOrder.fromJson(PosJson j) => PosOpenOrder(
    uuid: j.str('uuid'),
    shortCode: j.strOrNull('short_code'),
    status: j.str('status'),
    channel: j.str('channel'),
    type: j.strOrNull('type') ?? '',
    branchId: j.intOrNull('branch_id'),
    version: j.intOrNull('version') ?? 0,
    hall: PosRef.maybe(j, 'hall'),
    table: PosTableRef.maybe(j, 'table'),
    hallRef: j.strOrNull('hall_ref'),
    tableRef: j.strOrNull('table_ref'),
    guestsCount: j.intOrNull('guests_count'),
    waiter: PosRef.maybe(j, 'waiter'),
    itemsCount: j.intOrNull('items_count') ?? 0,
    subtotal: j.money('subtotal'),
    totalPrice: j.money('total_price'),
    paymentStatus: j.strOrNull('payment_status'),
    billLocked: j.boolean('bill_locked'),
    billRequested: j.boolean('bill_requested'),
    fiscalPending: j.boolean('fiscal_pending'),
    bookingId: j.intOrNull('booking_id'),
    businessDate: j.strOrNull('business_date'),
    openedAt: j.dateOrNull('opened_at'),
    closedAt: j.dateOrNull('closed_at'),
    updatedAt: j.dateOrNull('updated_at'),
    online: j.objOrNull('online') == null
        ? null
        : PosOnlineOrder.fromJson(j.obj('online')),
    raw: j.map,
  );

  final String uuid;
  final String? shortCode;
  final String status;

  /// `pos_hall`, `pos_takeaway`, `telegram`, `mobile`, ...
  final String channel;
  final String type;
  final int? branchId;
  final int version;
  final PosRef? hall;
  final PosTableRef? table;
  final String? hallRef;
  final String? tableRef;
  final int? guestsCount;
  final PosRef? waiter;
  final int itemsCount;
  final PosMoney subtotal;
  final PosMoney totalPrice;
  final String? paymentStatus;
  final bool billLocked;

  /// Ofitsiant schyot so'ragan (P3-26).
  final bool billRequested;

  /// To'langan, sotuv cheki kutilmoqda ("fiskal kutmoqda").
  final bool fiscalPending;
  final int? bookingId;

  /// Ish kuni (`YYYY-MM-DD`).
  final String? businessDate;
  final DateTime? openedAt;

  /// Yopilgan payt (`scope=closed`).
  final DateTime? closedAt;
  final DateTime? updatedAt;

  /// Onlayn buyurtma kartasi (mijoz, manzil, qatorlar, to'lov); POS buyurtmasida `null` (P3-26).
  final PosOnlineOrder? online;
  final Map<String, Object?> raw;

  bool get isPos => PosChannel.fromWire(channel) != null;
}

/// Bosqichdagi yangi qator (`items[]`).
class PosLineInput {
  const PosLineInput({
    required this.variantUuid,
    required this.quantity,
    this.modifiers = const <PosModifierChoice>[],
    this.note,
    this.seatNo,
    this.fireMode,
    this.dueAt,
  });

  /// Variant uuid'si (katalogdagi [PosVariant.uuid]).
  final String variantUuid;
  final PosQuantity quantity;
  final List<PosModifierChoice> modifiers;
  final String? note;
  final int? seatNo;

  /// `null` — bosqichning `fire_mode` i (standart `now`).
  final PosFireMode? fireMode;

  /// Oldindan buyurtma vaqti.
  final DateTime? dueAt;

  Map<String, Object?> toJson() => <String, Object?>{
    'variant_id': variantUuid,
    // Satr: miqdor aniq ("1.500"), suzuvchi nuqtasiz
    'quantity': quantity.toApiString(),
    if (modifiers.isNotEmpty)
      'modifiers': <Map<String, Object?>>[
        for (final PosModifierChoice m in modifiers) m.toJson(),
      ],
    if (note != null && note!.trim().isNotEmpty) 'note': note!.trim(),
    'seat_no': ?seatNo,
    'fire_mode': ?fireMode?.wire,
    'due_at': ?dueAt?.toUtc().toIso8601String(),
  };
}

/// Tanlangan qo'shimcha: modifikator uuid'si va soni.
class PosModifierChoice {
  const PosModifierChoice(this.modifierUuid, {this.quantity = 1});

  final String modifierUuid;
  final int quantity;

  Map<String, Object?> toJson() => <String, Object?>{
    'id': modifierUuid,
    'quantity': quantity,
  };
}
