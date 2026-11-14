import 'package:pos_core/pos_core.dart';

import '../json.dart';

/// P6-51: tadbir holati (`status`): `hold` — zal vaqtincha band (zaklad olinmasa muddati tugaydi); `confirmed` —
/// zaklad to'langan; `in_progress` — tadbir kuni; `settled` — yakuniy chek; `cancelled` — bekor qilingan.
enum PosBanquetStatus {
  hold('hold', 'Vaqtincha band'),
  confirmed('confirmed', 'Tasdiqlangan'),
  inProgress('in_progress', "O'tkazilmoqda"),
  settled('settled', 'Yakunlangan'),
  cancelled('cancelled', 'Bekor qilingan'),
  unknown('', "Noma'lum");

  const PosBanquetStatus(this.wire, this.label);

  final String wire;
  final String label;

  static PosBanquetStatus fromWire(String? wire) {
    for (final PosBanquetStatus s in values) {
      if (s.wire == wire && s != unknown) return s;
    }
    return unknown;
  }

  /// Zal va vaqtni hali egallab turibdimi.
  bool get holdsSlot => this == hold || this == confirmed || this == inProgress;

  /// Tafsilot, zal, mehmon, paket o'zgartirilishi mumkinmi.
  bool get isEditable => this == hold || this == confirmed;
}

/// Tadbir vaqti (`slot`): ertalab, kunduzi, kechqurun (do'kon sozlamasidagi soatlar) yoki boshqa vaqt.
enum PosBanquetSlot {
  morning('morning', 'Ertalab'),
  day('day', 'Kunduzi'),
  evening('evening', 'Kechqurun'),
  custom('custom', 'Boshqa vaqt');

  const PosBanquetSlot(this.wire, this.label);

  final String wire;
  final String label;

  static PosBanquetSlot fromWire(String? wire) {
    for (final PosBanquetSlot s in values) {
      if (s.wire == wire) return s;
    }
    return custom;
  }
}

/// Zal (tadbir ro'yxatida).
class PosBanquetHall {
  const PosBanquetHall({required this.id, required this.name});

  factory PosBanquetHall.fromJson(PosJson j) =>
      PosBanquetHall(id: j.integer('id'), name: j.str('name'));

  final int id;
  final String name;
}

/// Ogohlantirish (`warnings`): ko'p mehmon, vaqt oynasidan chiqish — hech qachon rad etish emas.
class PosBanquetWarning {
  const PosBanquetWarning({required this.code, required this.message});

  factory PosBanquetWarning.fromJson(PosJson j) =>
      PosBanquetWarning(code: j.str('code'), message: j.str('message'));

  final String code;
  final String message;
}

/// Tadbir qatori (`lines`): paket, zal ijarasi, qo'shimcha, tadbir kuni buyurtma. amount = narx × miqdor × (mehmon).
class PosBanquetLine {
  const PosBanquetLine({
    required this.uuid,
    required this.kind,
    required this.name,
    required this.quantity,
    required this.unitPrice,
    required this.amount,
    required this.frozen,
    this.persons,
    this.extraId,
    this.hallId,
    this.note,
  });

  factory PosBanquetLine.fromJson(PosJson j) => PosBanquetLine(
    uuid: j.str('uuid'),
    kind: j.str('kind'),
    name: j.str('name'),
    quantity: j.quantity('quantity'),
    unitPrice: j.money('unit_price'),
    amount: j.money('amount'),
    frozen: j.boolean('frozen'),
    persons: j.intOrNull('persons'),
    extraId: j.intOrNull('extra_id'),
    hallId: j.intOrNull('hall_id'),
    note: j.strOrNull('note'),
  );

  final String uuid;

  /// `package`, `hall_rent`, `extra`, `order`.
  final String kind;
  final String name;
  final PosQuantity quantity;
  final PosMoney unitPrice;
  final PosMoney amount;

  /// Shartnoma kuni narxi muzlatilgan.
  final bool frozen;

  /// Har mehmonga qator: hisoblangan mehmonlar soni.
  final int? persons;
  final int? extraId;
  final int? hallId;
  final String? note;

  bool get perPerson => persons != null;
}

/// To'lov jadvali qatori (qisqa; to'lash P6-53).
class PosBanquetScheduleRow {
  const PosBanquetScheduleRow({
    required this.uuid,
    required this.kind,
    required this.amount,
    required this.status,
    this.dueDate,
    this.tender,
    this.paidAt,
  });

  factory PosBanquetScheduleRow.fromJson(PosJson j) => PosBanquetScheduleRow(
    uuid: j.str('uuid'),
    kind: j.str('kind'),
    amount: j.money('amount'),
    status: j.str('status'),
    dueDate: j.strOrNull('due_date'),
    tender: j.strOrNull('tender'),
    paidAt: j.dateOrNull('paid_at'),
  );

  final String uuid;

  /// `deposit`, `interim`, `final`.
  final String kind;
  final PosMoney amount;

  /// `due`, `paid`, `refunded`, `retained`.
  final String status;
  final String? dueDate;
  final String? tender;
  final DateTime? paidAt;
}

/// `GET /pos/banquets/{uuid}` (va ro'yxat qatori): zal × sana × vaqt, mijoz, mehmonlar, summalar. Pul so'mda;
/// [quoteCurrency] faqat ko'rsatish uchun.
class PosBanquetEvent {
  const PosBanquetEvent({
    required this.uuid,
    required this.branchId,
    required this.eventDate,
    required this.slot,
    required this.startTime,
    required this.endTime,
    required this.startsAt,
    required this.endsAt,
    required this.halls,
    required this.eventType,
    required this.eventTypeLabel,
    required this.status,
    required this.customerName,
    required this.guestsGuaranteed,
    required this.guestsMinimum,
    required this.guestsBilled,
    required this.total,
    required this.paidTotal,
    required this.outstanding,
    required this.frozen,
    required this.version,
    this.title,
    this.customerPhone,
    this.guestsActual,
    this.packageId,
    this.packageName,
    this.holdExpiresAt,
    this.contractNo,
    this.packageTotal,
    this.hallTotal,
    this.extrasTotal,
    this.ordersTotal,
    this.quoteCurrency,
    this.quoteRate,
    this.quoteTotal,
    this.note,
    this.lines = const <PosBanquetLine>[],
    this.payments = const <PosBanquetScheduleRow>[],
    this.warnings = const <PosBanquetWarning>[],
  });

  factory PosBanquetEvent.fromJson(PosJson j) {
    final PosJson? quote = j.objOrNull('quote');
    return PosBanquetEvent(
      uuid: j.str('uuid'),
      branchId: j.integer('branch_id'),
      eventDate: j.str('event_date'),
      slot: PosBanquetSlot.fromWire(j.strOrNull('slot')),
      startTime: j.str('start_time'),
      endTime: j.str('end_time'),
      startsAt: j.date('starts_at'),
      endsAt: j.date('ends_at'),
      halls: j.objects('halls', PosBanquetHall.fromJson),
      eventType: j.str('event_type'),
      eventTypeLabel: j.strOrNull('event_type_label') ?? j.str('event_type'),
      status: PosBanquetStatus.fromWire(j.strOrNull('status')),
      title: j.strOrNull('title'),
      customerName: j.str('customer_name'),
      customerPhone: j.strOrNull('customer_phone'),
      guestsGuaranteed: j.intOrNull('guests_guaranteed') ?? 0,
      guestsMinimum: j.intOrNull('guests_minimum') ?? 0,
      guestsActual: j.intOrNull('guests_actual'),
      guestsBilled: j.intOrNull('guests_billed') ?? 0,
      packageId: j.intOrNull('package_id'),
      packageName: j.strOrNull('package_name'),
      total: j.money('total'),
      paidTotal: j.money('paid_total'),
      outstanding: j.money('outstanding'),
      holdExpiresAt: j.dateOrNull('hold_expires_at'),
      frozen: j.boolean('frozen'),
      contractNo: j.strOrNull('contract_no'),
      version: j.intOrNull('version') ?? 1,
      packageTotal: j.moneyOrNull('package_total'),
      hallTotal: j.moneyOrNull('hall_total'),
      extrasTotal: j.moneyOrNull('extras_total'),
      ordersTotal: j.moneyOrNull('orders_total'),
      quoteCurrency: quote?.strOrNull('currency'),
      quoteRate: quote?.strOrNull('rate'),
      quoteTotal: quote?.strOrNull('total'),
      note: j.strOrNull('note'),
      lines: j.has('lines')
          ? j.objects('lines', PosBanquetLine.fromJson)
          : const <PosBanquetLine>[],
      payments: j.has('payments')
          ? j.objects('payments', PosBanquetScheduleRow.fromJson)
          : const <PosBanquetScheduleRow>[],
      warnings: j.has('warnings')
          ? j.objects('warnings', PosBanquetWarning.fromJson)
          : const <PosBanquetWarning>[],
    );
  }

  final String uuid;
  final int branchId;

  /// Mahalliy sana `YYYY-MM-DD`.
  final String eventDate;
  final PosBanquetSlot slot;

  /// Mahalliy vaqt `HH:MM`.
  final String startTime;
  final String endTime;
  final DateTime startsAt;
  final DateTime endsAt;
  final List<PosBanquetHall> halls;
  final String eventType;
  final String eventTypeLabel;
  final PosBanquetStatus status;
  final String? title;
  final String customerName;
  final String? customerPhone;
  final int guestsGuaranteed;
  final int guestsMinimum;
  final int? guestsActual;

  /// max(kafolatlangan, minimal).
  final int guestsBilled;
  final int? packageId;
  final String? packageName;
  final PosMoney total;
  final PosMoney paidTotal;
  final PosMoney outstanding;
  final DateTime? holdExpiresAt;
  final bool frozen;
  final String? contractNo;
  final int version;
  final PosMoney? packageTotal;
  final PosMoney? hallTotal;
  final PosMoney? extrasTotal;
  final PosMoney? ordersTotal;
  final String? quoteCurrency;
  final String? quoteRate;
  final String? quoteTotal;
  final String? note;
  final List<PosBanquetLine> lines;
  final List<PosBanquetScheduleRow> payments;
  final List<PosBanquetWarning> warnings;

  List<int> get hallIds => <int>[for (final PosBanquetHall h in halls) h.id];
}

/// Taqvimdagi zal va uning tadbirlari (`GET /pos/banquets/calendar`).
class PosBanquetCalendarHall {
  const PosBanquetCalendarHall({
    required this.id,
    required this.name,
    required this.isActive,
    required this.events,
  });

  factory PosBanquetCalendarHall.fromJson(PosJson j) => PosBanquetCalendarHall(
    id: j.integer('id'),
    name: j.str('name'),
    isActive: j.boolean('is_active', fallback: true),
    events: j.objects('events', PosBanquetEvent.fromJson),
  );

  final int id;
  final String name;
  final bool isActive;
  final List<PosBanquetEvent> events;
}

/// Do'kon vaqtlari: slot → (boshlanish, tugash) `HH:MM`.
typedef PosBanquetSlotTimes = Map<PosBanquetSlot, (String, String)>;

PosBanquetSlotTimes _slotTimes(PosJson j) => <PosBanquetSlot, (String, String)>{
  for (final PosBanquetSlot s in <PosBanquetSlot>[
    PosBanquetSlot.morning,
    PosBanquetSlot.day,
    PosBanquetSlot.evening,
  ])
    if (j.has(s.wire))
      s: (j.obj(s.wire).str('start'), j.obj(s.wire).str('end')),
};

/// `GET /pos/banquets/calendar`.
class PosBanquetCalendar {
  const PosBanquetCalendar({
    required this.branchId,
    required this.from,
    required this.to,
    required this.slots,
    required this.halls,
  });

  factory PosBanquetCalendar.fromJson(PosJson j) => PosBanquetCalendar(
    branchId: j.integer('branch_id'),
    from: j.str('from'),
    to: j.str('to'),
    slots: _slotTimes(j.obj('slots')),
    halls: j.objects('halls', PosBanquetCalendarHall.fromJson),
  );

  final int branchId;
  final String from;
  final String to;
  final PosBanquetSlotTimes slots;
  final List<PosBanquetCalendarHall> halls;
}

/// Paket (`GET /pos/banquets/catalog`).
class PosBanquetPackage {
  const PosBanquetPackage({
    required this.id,
    required this.name,
    required this.perPersonPrice,
    required this.minimumGuests,
    required this.included,
  });

  factory PosBanquetPackage.fromJson(PosJson j) => PosBanquetPackage(
    id: j.integer('id'),
    name: j.str('name'),
    perPersonPrice: j.money('per_person_price'),
    minimumGuests: j.intOrNull('minimum_guests') ?? 0,
    included: j.has('included') ? j.strings('included') : const <String>[],
  );

  final int id;
  final String name;
  final PosMoney perPersonPrice;
  final int minimumGuests;
  final List<String> included;
}

/// Qo'shimcha (`per_event` yoki `per_person`).
class PosBanquetExtra {
  const PosBanquetExtra({
    required this.id,
    required this.name,
    required this.price,
    required this.perPerson,
  });

  factory PosBanquetExtra.fromJson(PosJson j) => PosBanquetExtra(
    id: j.integer('id'),
    name: j.str('name'),
    price: j.money('price'),
    perPerson: j.strOrNull('pricing') == 'per_person',
  );

  final int id;
  final String name;
  final PosMoney price;
  final bool perPerson;
}

/// `GET /pos/banquets/catalog`: faol paketlar, qo'shimchalar, filial zallari va slot narxlari, sozlamalar.
class PosBanquetCatalog {
  const PosBanquetCatalog({
    required this.packages,
    required this.extras,
    required this.hallRates,
    required this.slots,
    required this.holdHours,
  });

  factory PosBanquetCatalog.fromJson(PosJson j) {
    final PosJson settings = j.obj('settings');
    return PosBanquetCatalog(
      packages: j.objects('packages', PosBanquetPackage.fromJson),
      extras: j.objects('extras', PosBanquetExtra.fromJson),
      hallRates: <int, Map<PosBanquetSlot, PosMoney>>{
        for (final PosJson h in j.objects('halls', (PosJson x) => x))
          h.integer('hall_id'): <PosBanquetSlot, PosMoney>{
            for (final PosBanquetSlot s in PosBanquetSlot.values)
              if (h.obj('rates').moneyOrNull(s.wire) != null)
                s: h.obj('rates').money(s.wire),
          },
      },
      slots: _slotTimes(settings.obj('slots')),
      holdHours: settings.intOrNull('hold_hours') ?? 0,
    );
  }

  final List<PosBanquetPackage> packages;
  final List<PosBanquetExtra> extras;

  /// Zal id → slot → ijara.
  final Map<int, Map<PosBanquetSlot, PosMoney>> hallRates;
  final PosBanquetSlotTimes slots;
  final int holdHours;
}

/// Tadbirdagi qo'shimcha: katalogdan ([extraId]) yoki erkin ([name] + [unitPrice] + [perPerson]).
class PosBanquetExtraInput {
  const PosBanquetExtraInput.catalog(
    int this.extraId, {
    this.quantity,
    this.note,
    this.lineUuid,
  }) : name = null,
       unitPrice = null,
       perPerson = null;

  const PosBanquetExtraInput.free({
    required String this.name,
    required PosMoney this.unitPrice,
    this.perPerson = false,
    this.quantity,
    this.note,
    this.lineUuid,
  }) : extraId = null;

  final int? extraId;
  final String? name;
  final PosMoney? unitPrice;
  final bool? perPerson;
  final PosQuantity? quantity;
  final String? note;
  final String? lineUuid;

  Map<String, Object?> toJson() => <String, Object?>{
    'line_uuid': ?lineUuid,
    'extra_id': ?extraId,
    'name': ?name,
    'unit_price': ?unitPrice?.toApiString(),
    'per_person': ?perPerson,
    'quantity': ?quantity?.toApiString(),
    'note': ?note,
  };
}

/// `POST /pos/banquets` va `PATCH` tanasi: faqat berilgan maydonlar yuboriladi.
class PosBanquetDraft {
  const PosBanquetDraft({
    this.eventDate,
    this.slot,
    this.startTime,
    this.endTime,
    this.hallIds,
    this.eventType,
    this.title,
    this.customerName,
    this.customerPhone,
    this.guestsGuaranteed,
    this.guestsMinimum,
    this.packageId,
    this.extras,
    this.quoteCurrency,
    this.quoteRate,
    this.note,
  });

  final String? eventDate;
  final PosBanquetSlot? slot;
  final String? startTime;
  final String? endTime;
  final List<int>? hallIds;
  final String? eventType;
  final String? title;
  final String? customerName;
  final String? customerPhone;
  final int? guestsGuaranteed;
  final int? guestsMinimum;
  final int? packageId;
  final List<PosBanquetExtraInput>? extras;
  final String? quoteCurrency;
  final String? quoteRate;
  final String? note;

  Map<String, Object?> toJson() => <String, Object?>{
    'event_date': ?eventDate,
    'slot': ?slot?.wire,
    'start_time': ?startTime,
    'end_time': ?endTime,
    'hall_ids': ?hallIds,
    'event_type': ?eventType,
    'title': ?title,
    'customer_name': ?customerName,
    'customer_phone': ?customerPhone,
    'guests_guaranteed': ?guestsGuaranteed,
    'guests_minimum': ?guestsMinimum,
    'banquet_package_id': ?packageId,
    if (extras != null)
      'extras': <Object?>[
        for (final PosBanquetExtraInput e in extras!) e.toJson(),
      ],
    'quote_currency': ?quoteCurrency,
    'quote_rate': ?quoteRate,
    'note': ?note,
  };
}
