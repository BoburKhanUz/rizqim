import 'package:pos_core/pos_core.dart';

import '../bootstrap/bootstrap_models.dart';
import '../json.dart';

/// `GET /pos/floor`: zallar → joylar, har birida holat va ochiq buyurtmalar; `untabled_orders` — stolsiz va olib
/// ketish buyurtmalari.
class PosFloor {
  const PosFloor({
    required this.branchId,
    required this.halls,
    required this.untabledOrders,
    this.generatedAt,
    this.overdueAfterHours,
  });

  factory PosFloor.fromJson(PosJson j) => PosFloor(
    branchId: j.integer('branch_id'),
    generatedAt: j.dateOrNull('generated_at'),
    overdueAfterHours: j.intOrNull('overdue_after_hours'),
    halls: j.objects('halls', FloorHall.fromJson),
    untabledOrders: j.objects('untabled_orders', FloorOrder.fromJson),
  );

  final int branchId;
  final DateTime? generatedAt;
  final int? overdueAfterHours;
  final List<FloorHall> halls;
  final List<FloorOrder> untabledOrders;

  Iterable<FloorTable> get tables => halls.expand((FloorHall h) => h.tables);

  FloorTable? table(int id) {
    for (final FloorTable t in tables) {
      if (t.id == id) return t;
    }
    return null;
  }

  /// Hamma ochiq buyurtmalar (joylardagi va stolsiz).
  Iterable<FloorOrder> get orders => <FloorOrder>[
    for (final FloorTable t in tables) ...t.orders,
    ...untabledOrders,
  ];
}

class FloorHall {
  const FloorHall({
    required this.id,
    required this.name,
    required this.tables,
    this.sortOrder = 0,
    this.isActive = true,
  });

  factory FloorHall.fromJson(PosJson j) => FloorHall(
    id: j.integer('id'),
    name: j.str('name'),
    sortOrder: j.intOrNull('sort_order') ?? 0,
    isActive: j.boolean('is_active', fallback: true),
    tables: j.objects('tables', FloorTable.fromJson),
  );

  final int id;
  final String name;
  final int sortOrder;

  /// Yopiq mavsumiy zona (ochiq buyurtmasi bo'lgani uchun ko'rinadi).
  final bool isActive;
  final List<FloorTable> tables;
}

class FloorTable {
  const FloorTable({
    required this.id,
    required this.name,
    required this.kind,
    required this.statusWire,
    required this.orders,
    this.seats,
    this.layout,
    this.isActive = true,
    this.minSpend,
    this.minSpendMode = 'per_table',
    this.reservation,
    this.billRequested = false,
  });

  factory FloorTable.fromJson(PosJson j) => FloorTable(
    id: j.integer('id'),
    name: j.str('name'),
    kind: FloorTableKind.fromWire(j.strOrNull('kind')),
    seats: j.intOrNull('seats'),
    layout: PosLayout.maybe(j, 'layout'),
    isActive: j.boolean('is_active', fallback: true),
    minSpend: j.moneyOrNull('min_spend'),
    minSpendMode: j.strOrNull('min_spend_mode') ?? 'per_table',
    statusWire: j.strOrNull('status') ?? 'free',
    billRequested: j.boolean('bill_requested'),
    reservation: j.has('reservation')
        ? FloorReservation.fromJson(j.obj('reservation'))
        : null,
    orders: j.objects('orders', FloorOrder.fromJson),
  );

  final int id;
  final String name;
  final FloorTableKind kind;
  final int? seats;
  final PosLayout? layout;
  final bool isActive;
  final PosMoney? minSpend;
  final String minSpendMode;

  /// Serverdagi holat: `free`, `reserved_soon`, `occupied`, `overdue`, `bill_printed`.
  final String statusWire;

  /// Bir soat ichida boshlanadigan bron.
  final FloorReservation? reservation;
  final List<FloorOrder> orders;

  /// Ofitsiant schyot so'ragan, schyot hali chiqmagan ("Schyot so'raldi", P3-26).
  final bool billRequested;

  /// `pos_core` holati ([FloorTableStatus]): faol bo'lmagan bo'sh joy — `inactive`.
  FloorTableStatus get status {
    final FloorTableStatus mapped = floorStatusFromWire(statusWire);
    if (!isActive && mapped == FloorTableStatus.free) {
      return FloorTableStatus.inactive;
    }
    return mapped;
  }

  bool get isFree => orders.isEmpty;
}

/// Server holati (`free`, `reserved_soon`, `occupied`, `overdue`, `bill_printed`) → [FloorTableStatus].
FloorTableStatus floorStatusFromWire(String? wire) => switch (wire) {
  'reserved_soon' => FloorTableStatus.booked,
  'occupied' => FloorTableStatus.occupied,
  'overdue' => FloorTableStatus.overdue,
  'bill_printed' => FloorTableStatus.billPrinted,
  _ => FloorTableStatus.free,
};

class FloorReservation {
  const FloorReservation({
    required this.bookingUuid,
    this.startsAt,
    this.guests,
    this.contactName,
    this.contactPhone,
  });

  factory FloorReservation.fromJson(PosJson j) => FloorReservation(
    bookingUuid: j.str('booking_uuid'),
    startsAt: j.dateOrNull('starts_at'),
    guests: j.intOrNull('guests'),
    contactName: j.strOrNull('contact_name'),
    contactPhone: j.strOrNull('contact_phone'),
  );

  final String bookingUuid;
  final DateTime? startsAt;
  final int? guests;

  /// Mehmonning ismi va telefoni (P3-26; buyurtma yoki bron bilan ishlovchi xodimga, oshxona ekraniga `null`).
  final String? contactName;
  final String? contactPhone;
}

/// Zal xaritasidagi ochiq buyurtma (qisqa).
class FloorOrder {
  const FloorOrder({
    required this.uuid,
    required this.status,
    required this.channel,
    required this.version,
    this.shortCode,
    this.openedAt,
    this.openMinutes = 0,
    this.overdue = false,
    this.billPrinted = false,
    this.guestsCount,
    this.waiter,
    this.totalPrice = PosMoney.zero,
    this.itemsCount = 0,
    this.unsentCount = 0,
    this.readyCount = 0,
    this.billRequested = false,
    this.billRequestedAt,
  });

  factory FloorOrder.fromJson(PosJson j) => FloorOrder(
    uuid: j.str('uuid'),
    shortCode: j.strOrNull('short_code'),
    status: j.str('status'),
    channel: j.str('channel'),
    version: j.intOrNull('version') ?? 0,
    openedAt: j.dateOrNull('opened_at'),
    openMinutes: j.intOrNull('open_minutes') ?? 0,
    overdue: j.boolean('overdue'),
    billPrinted: j.boolean('bill_printed'),
    guestsCount: j.intOrNull('guests_count'),
    waiter: PosRef.maybe(j, 'waiter'),
    totalPrice: j.money('total_price'),
    itemsCount: j.intOrNull('items_count') ?? 0,
    unsentCount: j.intOrNull('unsent_count') ?? 0,
    readyCount: j.intOrNull('ready_count') ?? 0,
    billRequested: j.boolean('bill_requested'),
    billRequestedAt: j.dateOrNull('bill_requested_at'),
  );

  final String uuid;
  final String? shortCode;
  final String status;
  final String channel;
  final int version;
  final DateTime? openedAt;
  final int openMinutes;
  final bool overdue;
  final bool billPrinted;
  final int? guestsCount;
  final PosRef? waiter;
  final PosMoney totalPrice;
  final int itemsCount;

  /// Oshxonaga hali yuborilmagan qatorlar.
  final int unsentCount;

  /// Oshxona tayyor deb belgilagan, hali berilmagan qatorlar (P3-26).
  final int readyCount;

  /// Ofitsiant schyot so'ragan (P3-26).
  final bool billRequested;
  final DateTime? billRequestedAt;
}
