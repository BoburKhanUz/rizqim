import 'package:rizqim_api/rizqim_api.dart';

import '../json.dart';

/// `private-pos-branch.{id}` kanalidagi hodisa. Hodisada faqat id va holat bor (shaxsiy ma'lumot, narx yo'q): ilova
/// kerakli narsani API'dan qayta so'raydi (docs/API.md, 8-bo'lim).
sealed class PosEvent {
  const PosEvent(this.name, this.data);

  /// O'qiydi; noma'lum hodisa — [PosUnknownEvent], buzilgan tana — [PosUnknownEvent] ham (ilova yiqilmaydi).
  factory PosEvent.of(RealtimeEvent e) => fromWire(e.event, e.data);

  /// Hodisa nomi va tanasidan.
  static PosEvent fromWire(String name, Map<String, Object?> data) {
    final PosJson j = PosJson(data);
    try {
      return switch (name) {
        'order.created' ||
        'order.status_changed' ||
        'order.assigned' => PosOrderEvent._(name, data, _order(j)),
        'order.items_changed' => PosOrderItemsChanged._(
          name,
          data,
          _order(j),
          j,
        ),
        'order.bill_changed' => PosOrderBillChanged._(name, data, _order(j), j),
        'order.bill_requested' => PosOrderBillRequested._(
          name,
          data,
          _order(j),
          j,
        ),
        'order.course_ready' => PosCourseReady._(name, data, _order(j), j),
        'order.transferred' => PosOrderTransferred._(name, data, _order(j), j),
        'waitlist.changed' => PosWaitlistChanged._(name, data, j),
        'table.state_changed' => PosTableStateChanged._(name, data, j),
        'kitchen.item_status_changed' => PosKitchenItemChanged._(
          name,
          data,
          _order(j),
          j,
        ),
        'shift.changed' => PosShiftChanged._(name, data, j),
        'booking.changed' => PosBookingChanged._(name, data, j),
        'fiscal.receipt_updated' => PosFiscalReceiptUpdated._(name, data, j),
        'menu.availability_changed' => PosMenuAvailabilityChanged._(
          name,
          data,
          j,
        ),
        'display.cart' => PosDisplayCartChanged._(name, data, j),
        'queue.preparing' ||
        'queue.ready' ||
        'queue.handed' ||
        'queue.canceled' => PosQueueEvent._(name, data, _order(j), j),
        _ => PosUnknownEvent(name, data),
      };
    } on Object {
      return PosUnknownEvent(name, data);
    }
  }

  static PosEventOrder _order(PosJson j) {
    final PosJson o = j.obj('order');
    return PosEventOrder(
      uuid: o.str('uuid'),
      status: o.strOrNull('status'),
      branchId: o.intOrNull('branch_id'),
      updatedAt: o.dateOrNull('updated_at'),
    );
  }

  /// `order.created`, `shift.changed` ...
  final String name;
  final Map<String, Object?> data;

  /// Buyurtma bilan bog'liq hodisa bo'lsa — buyurtma uuid'si.
  String? get orderUuid => null;

  @override
  String toString() => '$runtimeType($name)';
}

/// Hodisadagi buyurtma: `{uuid, status, store_id, branch_id, courier_id, updated_at}`.
class PosEventOrder {
  const PosEventOrder({
    required this.uuid,
    this.status,
    this.branchId,
    this.updatedAt,
  });

  final String uuid;
  final String? status;
  final int? branchId;
  final DateTime? updatedAt;
}

/// `order.bill_requested` (P3-26): ofitsiant kassadan schyot so'radi ("Schyot so'raldi").
class PosOrderBillRequested extends PosEvent {
  PosOrderBillRequested._(super.name, super.data, this.order, PosJson j)
    : version = j.intOrNull('version'),
      tableId = j.intOrNull('table_id'),
      requestedBy = j.intOrNull('requested_by');

  final PosEventOrder order;
  final int? version;
  final int? tableId;

  /// So'ragan xodim id'si.
  final int? requestedBy;

  @override
  String? get orderUuid => order.uuid;
}

/// `order.course_ready` (P6-21): buyurtma kursining oshxonaga yuborilgan hamma taomi tayyor ("2-kurs tayyor"). Ofitsiant
/// telefoni [waiterId] o'zi bo'lsa (yoki buyurtmada ofitsiant yo'q bo'lsa) xabar ko'rsatadi.
class PosCourseReady extends PosEvent {
  PosCourseReady._(super.name, super.data, this.order, PosJson j)
    : courseNo = j.integer('course_no'),
      version = j.intOrNull('version'),
      tableId = j.intOrNull('table_id'),
      waiterId = j.intOrNull('waiter_id'),
      itemIds = <int>[
        for (final Object? id
            in (j['item_ids'] as List<Object?>?) ?? const <Object?>[])
          if (id is num) id.toInt(),
      ];

  final PosEventOrder order;
  final int courseNo;
  final int? version;
  final int? tableId;
  final int? waiterId;

  /// Kursning taomlari (server id'lari).
  final List<int> itemIds;

  /// Shu ofitsiantga tegishli xabar: buyurtma uniki yoki ofitsiantsiz.
  bool isFor(int staffId) => waiterId == null || waiterId == staffId;

  @override
  String? get orderUuid => order.uuid;
}

/// `order.transferred` (P6-24): ochiq stol boshqa ofitsiantga berildi. Eski ofitsiant telefoni "stol 5 boshqa
/// ofitsiantga o'tkazildi", yangisiniki "stol 5 sizga berildi" deydi ([isFromMe], [isToMe]); qolganlar zalni qayta
/// o'qiydi. Xabar `user.{id}` kanallariga ham boradi, shuning uchun terminal ham filial kanalidan o'qiy oladi.
class PosOrderTransferred extends PosEvent {
  PosOrderTransferred._(super.name, super.data, this.order, PosJson j)
    : version = j.intOrNull('version'),
      tableRef = j.strOrNull('table_ref'),
      fromWaiterId = j.intOrNull('from_waiter_id'),
      toWaiterId = j.intOrNull('to_waiter_id');

  final PosEventOrder order;
  final int? version;

  /// Stol nomi ("Stol 5"), xabar matni uchun.
  final String? tableRef;
  final int? fromWaiterId;
  final int? toWaiterId;

  bool isFromMe(int staffId) => fromWaiterId == staffId;
  bool isToMe(int staffId) => toWaiterId == staffId;

  @override
  String get orderUuid => order.uuid;
}

/// `waitlist.changed` (P6-24): kutish ro'yxatiga yozuv qo'shildi yoki o'zgardi; hostes ekrani `GET /pos/waitlist` ni
/// qayta o'qiydi.
class PosWaitlistChanged extends PosEvent {
  PosWaitlistChanged._(super.name, super.data, PosJson j)
    : entryUuid = j.obj('entry').strOrNull('uuid'),
      entryStatus = j.obj('entry').strOrNull('status'),
      branchId = j.obj('entry').intOrNull('branch_id'),
      version = j.obj('entry').intOrNull('version');

  final String? entryUuid;

  /// `waiting`, `seated`, `canceled`, `no_show`.
  final String? entryStatus;
  final int? branchId;
  final int? version;
}

/// `table.state_changed` (P6-26): hostes stolni "tozalanmoqda" deb belgiladi yoki belgini olib tashladi; zal xaritasi
/// qayta o'qiladi.
class PosTableStateChanged extends PosEvent {
  PosTableStateChanged._(super.name, super.data, PosJson j)
    : tableId = j.obj('table').intOrNull('id'),
      branchId = j.obj('table').intOrNull('branch_id'),
      serviceState = j.obj('table').strOrNull('service_state');

  final int? tableId;
  final int? branchId;

  /// `cleaning` yoki `null`.
  final String? serviceState;
}

/// `order.created`, `order.status_changed`, `order.assigned`.
class PosOrderEvent extends PosEvent {
  const PosOrderEvent._(super.name, super.data, this.order);

  final PosEventOrder order;

  @override
  String get orderUuid => order.uuid;

  bool get isCreated => name == 'order.created';
}

/// `order.items_changed`: bosqich, yuborish, o'chirish, mehmonlar, ko'chirish, birlashtirish, xizmat qatori.
class PosOrderItemsChanged extends PosEvent {
  PosOrderItemsChanged._(super.name, super.data, this.order, PosJson j)
    : version = j.intOrNull('version'),
      tableId = j.intOrNull('table_id'),
      change = j.strOrNull('change'),
      itemIds = j.ints('item_ids');

  final PosEventOrder order;
  final int? version;
  final int? tableId;

  /// `round_added`, `sent`, `voided`, `guests_changed`, `moved`, `merged`, `charges_changed`, `served`, `cancelled`,
  /// `waiter_changed`, `seat_changed` (P6-24).
  final String? change;
  final List<int> itemIds;

  @override
  String get orderUuid => order.uuid;
}

/// `order.bill_changed`: schyot tuzildi, chiqarildi, to'landi, chek holati o'zgardi.
class PosOrderBillChanged extends PosEvent {
  PosOrderBillChanged._(super.name, super.data, this.order, PosJson j)
    : billUuid = j.objOrNull('bill')?.strOrNull('uuid'),
      billStatus = j.objOrNull('bill')?.strOrNull('status'),
      fiscalStatus = j.objOrNull('bill')?.strOrNull('fiscal_status'),
      change = j.strOrNull('change');

  final PosEventOrder order;
  final String? billUuid;
  final String? billStatus;
  final String? fiscalStatus;
  final String? change;

  @override
  String get orderUuid => order.uuid;
}

/// `kitchen.item_status_changed`: oshpaz qatorni "tayyor" dedi yoki qaytardi, qator o'chirildi yoki berildi.
class PosKitchenItemChanged extends PosEvent {
  PosKitchenItemChanged._(super.name, super.data, this.order, PosJson j)
    : itemId = j.objOrNull('item')?.intOrNull('id'),
      kitchenStatus = j.objOrNull('item')?.strOrNull('kitchen_status'),
      itemsTotal = j.intOrNull('items_total'),
      itemsReady = j.intOrNull('items_ready');

  final PosEventOrder order;
  final int? itemId;
  final String? kitchenStatus;
  final int? itemsTotal;
  final int? itemsReady;

  @override
  String get orderUuid => order.uuid;
}

/// P6-13: `queue.preparing`, `queue.ready` (qayta chaqirilganda [called] `true`), `queue.handed`, `queue.canceled` —
/// navbat raqami taxtada siljidi (mijoz ekrani va kassa ovozi uchun; taxta `GET /pos/queue` dan qayta olinadi).
class PosQueueEvent extends PosEvent {
  PosQueueEvent._(super.name, super.data, this.order, PosJson j)
    : number = j.obj('queue').intOrNull('number'),
      businessDate = j.obj('queue').strOrNull('business_date'),
      pickupStatus = j.obj('queue').strOrNull('status'),
      channel = j.obj('queue').strOrNull('channel'),
      callCount = j.obj('queue').intOrNull('call_count') ?? 0,
      called = j.obj('queue').boolean('called');

  final PosEventOrder order;
  final int? number;
  final String? businessDate;

  /// `preparing`, `ready`, `handed` yoki `null` (bekor qilingan).
  final String? pickupStatus;
  final String? channel;
  final int callCount;

  /// Kassa raqamni qayta chaqirdi (`POST /pos/orders/{uuid}/call`).
  final bool called;

  /// Raqamni ovoz bilan e'lon qilish kerak (tayyor bo'ldi yoki qayta chaqirildi).
  bool get announce => name == 'queue.ready';

  @override
  String get orderUuid => order.uuid;
}

/// P6-14: `display.cart` — kassa mijoz ekranidagi chekni o'zgartirdi. Hodisada faqat kassa uuid'si, tartib raqami va
/// ekran holati bor (taom nomi, narx yo'q): ekran `GET /pos/display/state` ni qayta o'qiydi.
class PosDisplayCartChanged extends PosEvent {
  PosDisplayCartChanged._(super.name, super.data, PosJson j)
    : terminal = j.strOrNull('terminal'),
      seq = j.intOrNull('seq') ?? 0,
      state = j.strOrNull('state');

  /// Qaysi kassa (qurilma uuid).
  final String? terminal;
  final int seq;

  /// `idle`, `cart`, `payment`, `paid`.
  final String? state;
}

/// `shift.changed`: smena ochildi, kassa harakati, yopildi.
class PosShiftChanged extends PosEvent {
  PosShiftChanged._(super.name, super.data, PosJson j)
    : shiftUuid = j.obj('shift').str('uuid'),
      status = j.obj('shift').strOrNull('status'),
      deviceUuid = j.obj('shift').strOrNull('device_uuid'),
      businessDate = j.obj('shift').strOrNull('business_date'),
      zNumber = j.obj('shift').intOrNull('z_number'),
      change = j.strOrNull('change');

  final String shiftUuid;
  final String? status;

  /// Qaysi terminal smenasi.
  final String? deviceUuid;
  final String? businessDate;
  final int? zNumber;

  /// `opened`, `cash_movement`, `closed`, ...
  final String? change;
}

/// `booking.changed`.
class PosBookingChanged extends PosEvent {
  PosBookingChanged._(super.name, super.data, PosJson j)
    : bookingUuid = j.obj('booking').str('uuid'),
      status = j.obj('booking').strOrNull('status'),
      version = j.obj('booking').intOrNull('version'),
      startsAt = j.obj('booking').dateOrNull('starts_at'),
      endsAt = j.obj('booking').dateOrNull('ends_at');

  final String bookingUuid;
  final String? status;
  final int? version;
  final DateTime? startsAt;
  final DateTime? endsAt;
}

/// `fiscal.receipt_updated`: chek `issued` yoki `failed` bo'ldi.
class PosFiscalReceiptUpdated extends PosEvent {
  PosFiscalReceiptUpdated._(super.name, super.data, PosJson j)
    : receiptUuid = j.obj('receipt').str('uuid'),
      type = j.obj('receipt').strOrNull('type'),
      status = j.obj('receipt').strOrNull('status'),
      subjectType = j.obj('receipt').strOrNull('subject_type'),
      subjectId = j.obj('receipt').intOrNull('subject_id');

  final String receiptUuid;
  final String? type;
  final String? status;
  final String? subjectType;
  final int? subjectId;
}

/// `menu.availability_changed`: stop-list, qozon yoki qoldiq o'zgardi (menyu versiyasi o'zgarmaydi).
class PosMenuAvailabilityChanged extends PosEvent {
  PosMenuAvailabilityChanged._(super.name, super.data, PosJson j)
    : reason = j.strOrNull('reason'),
      items = j.objects(
        'items',
        (PosJson i) => (
          type: i.strOrNull('type') ?? '',
          id: i.intOrNull('id') ?? 0,
          available: i.boolean('available'),
        ),
      );

  final String? reason;
  final List<({String type, int id, bool available})> items;
}

/// Noma'lum yoki o'qib bo'lmagan hodisa.
class PosUnknownEvent extends PosEvent {
  const PosUnknownEvent(super.name, super.data);
}
