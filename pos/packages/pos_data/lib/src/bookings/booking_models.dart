import 'package:pos_core/pos_core.dart';

import '../billing/billing_models.dart';
import '../json.dart';

/// Bron (`/pos/bookings*`). Holatlar: `requested` → `confirmed` → `arrived` → `completed`; `canceled`, `no_show`.
class PosBooking {
  const PosBooking({
    required this.uuid,
    required this.status,
    required this.version,
    required this.startsAt,
    required this.endsAt,
    this.branchId,
    this.diningTableId,
    this.tableIds = const <int>[],
    this.guestsPlanned,
    this.eventType,
    this.eventTypeLabel,
    this.contactName,
    this.contactPhone,
    this.source,
    this.sourceLabel,
    this.statusLabel,
    this.minSpend,
    this.note,
    this.orderUuid,
    this.depositTotal = PosMoney.zero,
    this.depositAvailable = PosMoney.zero,
    this.depositUnsettled = false,
    this.depositRequired = PosMoney.zero,
    this.depositMissing = PosMoney.zero,
    this.itemsCount = 0,
    this.cancelReason,
    this.confirmationSentAt,
    this.confirmationChannel,
    this.items = const <PosBookingItem>[],
    this.payments = const <PosBookingPayment>[],
    this.raw = const <String, Object?>{},
  });

  factory PosBooking.fromJson(PosJson j) => PosBooking(
    uuid: j.str('uuid'),
    branchId: j.intOrNull('branch_id'),
    diningTableId: j.intOrNull('dining_table_id'),
    tableIds: j.ints('table_ids'),
    startsAt: j.date('starts_at'),
    endsAt: j.date('ends_at'),
    guestsPlanned: j.intOrNull('guests_planned'),
    eventType: j.strOrNull('event_type'),
    eventTypeLabel: j.strOrNull('event_type_label'),
    contactName: j.strOrNull('contact_name'),
    contactPhone: j.strOrNull('contact_phone'),
    source: j.strOrNull('source'),
    sourceLabel: j.strOrNull('source_label'),
    status: j.str('status'),
    statusLabel: j.strOrNull('status_label'),
    minSpend: j.moneyOrNull('min_spend'),
    note: j.strOrNull('note'),
    orderUuid: j.strOrNull('order_uuid'),
    depositTotal: j.money('deposit_total'),
    depositAvailable: j.money('deposit_available'),
    depositUnsettled: j.boolean('deposit_unsettled'),
    depositRequired: j.money('deposit_required'),
    depositMissing: j.money('deposit_missing'),
    itemsCount: j.intOrNull('items_count') ?? 0,
    cancelReason: j.strOrNull('cancel_reason'),
    confirmationSentAt: j.dateOrNull('confirmation_sent_at'),
    confirmationChannel: j.strOrNull('confirmation_channel'),
    version: j.intOrNull('version') ?? 0,
    items: j.objects('items', PosBookingItem.fromJson),
    payments: j.objects('payments', PosBookingPayment.fromJson),
    raw: j.map,
  );

  final String uuid;
  final int? branchId;
  final int? diningTableId;

  /// Banketda birlashtirilgan hamma xona.
  final List<int> tableIds;
  final DateTime startsAt;
  final DateTime endsAt;
  final int? guestsPlanned;
  final String? eventType;
  final String? eventTypeLabel;
  final String? contactName;
  final String? contactPhone;
  final String? source;
  final String? sourceLabel;
  final String status;
  final String? statusLabel;
  final PosMoney? minSpend;
  final String? note;

  /// "Keldi" dan keyin ochilgan buyurtma.
  final String? orderUuid;
  final PosMoney depositTotal;
  final PosMoney depositAvailable;
  final bool depositUnsettled;

  /// P6-26: filialning stol broni qoidasi so'raydigan avans (`0` — so'ramaydi) va undan hali olinmagani; faqat
  /// bitta bronni o'qiganda (`GET /pos/bookings/{uuid}`) keladi.
  final PosMoney depositRequired;
  final PosMoney depositMissing;
  final int itemsCount;
  final String? cancelReason;

  /// P3-38: mehmonga tasdiq oxirgi marta qachon yuborilgan (`null` — yuborilmagan).
  final DateTime? confirmationSentAt;

  /// P3-38: qaysi kanal bilan (`telegram`, `sms`).
  final String? confirmationChannel;

  /// O'zgartiruvchi so'rovlarda (`stale_version` — boshqa qurilma o'zgartirgan).
  final int version;
  final List<PosBookingItem> items;
  final List<PosBookingPayment> payments;
  final Map<String, Object?> raw;
}

/// Oldindan buyurtma qatori.
class PosBookingItem {
  const PosBookingItem({
    required this.id,
    required this.productName,
    required this.quantity,
    this.productVariantId,
    this.variantName,
    this.unit = PosUnit.piece,
    this.unitPrice = PosMoney.zero,
    this.note,
    this.dueAt,
  });

  factory PosBookingItem.fromJson(PosJson j) => PosBookingItem(
    id: j.integer('id'),
    productVariantId: j.intOrNull('product_variant_id'),
    productName: j.str('product_name'),
    variantName: j.strOrNull('variant_name'),
    unit: PosUnit.fromWire(j.strOrNull('unit')),
    quantity: j.quantity('quantity'),
    unitPrice: j.money('unit_price'),
    note: j.strOrNull('note'),
    dueAt: j.dateOrNull('due_at'),
  );

  final int id;
  final int? productVariantId;
  final String productName;
  final String? variantName;
  final PosUnit unit;
  final PosQuantity quantity;
  final PosMoney unitPrice;
  final String? note;
  final DateTime? dueAt;
}

/// Avans (bo'nak).
class PosBookingPayment {
  const PosBookingPayment({
    required this.uuid,
    required this.amount,
    required this.tender,
    required this.status,
    this.statusLabel,
    this.remaining = PosMoney.zero,
    this.appliedAmount = PosMoney.zero,
    this.refundedAmount = PosMoney.zero,
    this.retainedAmount = PosMoney.zero,
    this.cardType,
    this.transactionId,
    this.fiscalReceiptUuid,
    this.fiscalStatus,
    this.createdAt,
  });

  factory PosBookingPayment.fromJson(PosJson j) => PosBookingPayment(
    uuid: j.str('uuid'),
    amount: j.money('amount'),
    tender: j.strOrNull('tender') ?? 'cash',
    cardType: j.strOrNull('card_type'),
    transactionId: j.strOrNull('transaction_id'),
    status: j.strOrNull('status') ?? 'taken',
    statusLabel: j.strOrNull('status_label'),
    remaining: j.money('remaining'),
    appliedAmount: j.money('applied_amount'),
    refundedAmount: j.money('refunded_amount'),
    retainedAmount: j.money('retained_amount'),
    fiscalReceiptUuid: j.strOrNull('fiscal_receipt_uuid'),
    fiscalStatus: j.strOrNull('fiscal_status'),
    createdAt: j.dateOrNull('created_at'),
  );

  final String uuid;
  final PosMoney amount;
  final String tender;
  final String? cardType;
  final String? transactionId;

  /// `taken`, `applied`, `refunded`, `retained`, ...
  final String status;
  final String? statusLabel;

  /// Ishlatilmagan qoldiq.
  final PosMoney remaining;
  final PosMoney appliedAmount;
  final PosMoney refundedAmount;
  final PosMoney retainedAmount;
  final String? fiscalReceiptUuid;
  final String? fiscalStatus;
  final DateTime? createdAt;
}

/// `GET /pos/bookings/calendar`: xonalar (zal tartibida) va ulardagi bronlar.
class PosBookingCalendar {
  const PosBookingCalendar({
    required this.from,
    required this.to,
    required this.rooms,
    this.branchId,
    this.timezone,
    this.unassigned = const <PosBooking>[],
  });

  factory PosBookingCalendar.fromJson(PosJson j) => PosBookingCalendar(
    branchId: j.intOrNull('branch_id'),
    from: j.str('from'),
    to: j.str('to'),
    timezone: j.strOrNull('timezone'),
    rooms: j.objects('rooms', PosCalendarRoom.fromJson),
    unassigned: j.objects('unassigned', PosBooking.fromJson),
  );

  final int? branchId;

  /// `YYYY-MM-DD`.
  final String from;
  final String to;
  final String? timezone;
  final List<PosCalendarRoom> rooms;

  /// Xonasiz bronlar.
  final List<PosBooking> unassigned;
}

class PosCalendarRoom {
  const PosCalendarRoom({
    required this.id,
    required this.name,
    required this.bookings,
    this.kind,
    this.seats,
    this.hallId,
    this.hallName,
    this.isActive = true,
    this.minSpend,
  });

  factory PosCalendarRoom.fromJson(PosJson j) => PosCalendarRoom(
    id: j.integer('id'),
    name: j.str('name'),
    kind: j.strOrNull('kind'),
    seats: j.intOrNull('seats'),
    hallId: j.intOrNull('hall_id'),
    hallName: j.strOrNull('hall_name'),
    isActive: j.boolean('is_active', fallback: true),
    minSpend: j.moneyOrNull('min_spend'),
    bookings: j.objects('bookings', PosBooking.fromJson),
  );

  final int id;
  final String name;
  final String? kind;
  final int? seats;
  final int? hallId;
  final String? hallName;
  final bool isActive;
  final PosMoney? minSpend;
  final List<PosBooking> bookings;
}

/// Yangi bron yoki o'zgartirish (faqat berilgan maydonlar yuboriladi).
class PosBookingInput {
  const PosBookingInput({
    this.diningTableId,
    this.extraTableIds,
    this.startsAt,
    this.endsAt,
    this.guestsPlanned,
    this.eventType,
    this.contactName,
    this.contactPhone,
    this.source,
    this.minSpend,
    this.note,
    this.confirm,
  });

  final int? diningTableId;

  /// Banket: birlashtirilgan qo'shimcha xonalar.
  final List<int>? extraTableIds;
  final DateTime? startsAt;

  /// Yo'q — filial sloti (standart 5 soat).
  final DateTime? endsAt;
  final int? guestsPlanned;

  /// `gap`, `ziyofat`, `tugilgan_kun`, `nahor_oshi`, `korporativ`, `other`.
  final String? eventType;
  final String? contactName;

  /// Istalgan ko'rinishda (server +998XXXXXXXXX ga keltiradi).
  final String? contactPhone;

  /// `phone`, `telegram`, `instagram`, `walk_in`, `other`.
  final String? source;
  final PosMoney? minSpend;
  final String? note;

  /// Darhol tasdiqlangan.
  final bool? confirm;

  Map<String, Object?> toJson() => <String, Object?>{
    'dining_table_id': ?diningTableId,
    'extra_table_ids': ?extraTableIds,
    'starts_at': ?startsAt?.toUtc().toIso8601String(),
    'ends_at': ?endsAt?.toUtc().toIso8601String(),
    'guests_planned': ?guestsPlanned,
    'event_type': ?eventType,
    'contact_name': ?contactName,
    'contact_phone': ?contactPhone,
    'source': ?source,
    'min_spend': ?minSpend?.toApiString(),
    'note': ?note,
    'confirm': ?confirm,
  };
}

/// "Keldi" natijasi: bron va ochilgan buyurtma.
class PosArrival {
  const PosArrival({
    required this.booking,
    required this.orderUuid,
    this.orderStatus,
  });

  factory PosArrival.fromJson(PosJson j) {
    final PosJson order = j.obj('order');
    return PosArrival(
      booking: PosBooking.fromJson(j.obj('booking')),
      orderUuid: order.str('uuid'),
      orderStatus: order.strOrNull('status'),
    );
  }

  final PosBooking booking;
  final String orderUuid;
  final String? orderStatus;
}

/// Avans amali natijasi (olish, qaytarish, ushlab qolish).
class PosDepositResult {
  const PosDepositResult({
    required this.booking,
    required this.payment,
    this.fiscal = const PosFiscalState(),
  });

  factory PosDepositResult.fromJson(PosJson j) => PosDepositResult(
    booking: PosBooking.fromJson(j.obj('booking')),
    payment: PosBookingPayment.fromJson(j.obj('payment')),
    fiscal: j.has('fiscal')
        ? PosFiscalState.fromJson(j.obj('fiscal'))
        : const PosFiscalState(),
  );

  final PosBooking booking;
  final PosBookingPayment payment;

  /// Shu amalning cheki (P3-26): avans — bo'nak cheki, qaytarish — qaytarish cheki, ushlab qolish — sotuv cheki
  /// (do'kon sozlamasi chek chiqarmasa `receipt` bo'sh).
  final PosFiscalState fiscal;
}

/// P3-38: mehmonga yuboriladigan bron tasdig'i (`GET /pos/bookings/{uuid}/confirmation`). [text] — tayyor xabar
/// (Telegram, SMS yoki nusxa uchun), [lines] — o'sha matn qatorlari (ekranda va chekda chiqarish uchun).
class PosBookingConfirmation {
  const PosBookingConfirmation({
    required this.text,
    this.lines = const <PosConfirmationLine>[],
    this.roomFee,
    this.channels = const PosConfirmationChannels(),
  });

  factory PosBookingConfirmation.fromJson(PosJson j) => PosBookingConfirmation(
    text: j.str('text'),
    lines: j.objects('lines', PosConfirmationLine.fromJson),
    roomFee: j.has('room_fee')
        ? PosConfirmationRoomFee.fromJson(j.obj('room_fee'))
        : null,
    channels: j.has('channels')
        ? PosConfirmationChannels.fromJson(j.obj('channels'))
        : const PosConfirmationChannels(),
  );

  final String text;
  final List<PosConfirmationLine> lines;

  /// Xona puli (shu kun turining tarifi bo'yicha); xona belgilanmagan yoki narxsiz — `null`.
  final PosConfirmationRoomFee? roomFee;

  /// Hozir qaysi kanal bilan yuborsa bo'ladi.
  final PosConfirmationChannels channels;
}

/// Tasdiq qatori: `kind` — `header`, `place`, `time`, `room`, `room_fee`, `min_spend`, `service_charge`, `place_fee`,
/// `pre_order`, `deposit`, `cancel_policy`, `contact`.
class PosConfirmationLine {
  const PosConfirmationLine({
    required this.kind,
    this.label = '',
    this.value = '',
  });

  factory PosConfirmationLine.fromJson(PosJson j) => PosConfirmationLine(
    kind: j.strOrNull('kind') ?? '',
    label: j.strOrNull('label') ?? '',
    value: j.strOrNull('value') ?? '',
  );

  static const String header = 'header';
  static const String roomFee = 'room_fee';

  final String kind;
  final String label;
  final String value;
}

class PosConfirmationRoomFee {
  const PosConfirmationRoomFee({
    required this.amount,
    required this.dayType,
    this.tariffId,
  });

  factory PosConfirmationRoomFee.fromJson(PosJson j) => PosConfirmationRoomFee(
    amount: j.money('amount'),
    dayType: j.strOrNull('day_type') ?? 'weekday',
    tariffId: j.intOrNull('tariff_id'),
  );

  final PosMoney amount;

  /// `weekday`, `weekend`, `holiday`.
  final String dayType;
  final int? tariffId;
}

class PosConfirmationChannels {
  const PosConfirmationChannels({this.telegram = false, this.sms = false});

  factory PosConfirmationChannels.fromJson(PosJson j) =>
      PosConfirmationChannels(
        telegram: j.boolean('telegram'),
        sms: j.boolean('sms'),
      );

  /// Mehmon telefoni do'kon Telegram botiga ulangan.
  final bool telegram;

  /// Filial SMS yuborishni yoqqan.
  final bool sms;

  bool get any => telegram || sms;
}

/// `POST /pos/bookings/{uuid}/confirmation/send` natijasi: xabar navbatga qo'yildi.
class PosConfirmationSent {
  const PosConfirmationSent({required this.channel, this.queued = true});

  factory PosConfirmationSent.fromJson(PosJson j) => PosConfirmationSent(
    channel: j.str('channel'),
    queued: j.boolean('queued', fallback: true),
  );

  /// `telegram` yoki `sms`.
  final String channel;
  final bool queued;

  /// Kanal nomi ekranda: "Telegram", "SMS".
  static String label(String? channel) => switch (channel) {
    'telegram' => 'Telegram',
    'sms' => 'SMS',
    null => '',
    final String other => other,
  };
}
