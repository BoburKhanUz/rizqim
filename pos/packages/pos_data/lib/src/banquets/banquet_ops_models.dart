import 'package:pos_core/pos_core.dart';

import '../billing/billing_models.dart';
import '../json.dart';
import 'banquet_models.dart';

/// P6-54: ishlab chiqarish varag'idagi taom: paketdagi kishi boshiga miqdor × rejalangan mehmonlar (`unit`: portion,
/// piece, kg, l). [kg] — kg/l taomlar uchun miqdorning o'zi, porsiya taomlarda porsiya og'irligi sozlangan bo'lsa.
class PosBanquetDish {
  const PosBanquetDish({
    required this.name,
    required this.unit,
    required this.quantityPerPerson,
    required this.quantity,
    this.productId,
    this.kg,
  });

  factory PosBanquetDish.fromJson(PosJson j) => PosBanquetDish(
    productId: j.intOrNull('product_id'),
    name: j.str('name'),
    unit: j.str('unit'),
    quantityPerPerson: j.quantityOrNull('quantity_per_person'),
    quantity: j.quantity('quantity'),
    kg: j.quantityOrNull('kg'),
  );

  final int? productId;
  final String name;
  final String unit;

  /// Bozorlik ro'yxatida (bir necha tadbir yig'indisi) — `null`.
  final PosQuantity? quantityPerPerson;
  final PosQuantity quantity;
  final PosQuantity? kg;
}

/// Osh masallig'i: 1 kg oshga [perKg], jami [quantity] (`unit`: kg, l, dona).
class PosOshIngredient {
  const PosOshIngredient({
    required this.key,
    required this.name,
    required this.unit,
    required this.perKg,
    required this.quantity,
  });

  factory PosOshIngredient.fromJson(PosJson j) => PosOshIngredient(
    key: j.str('key'),
    name: j.str('name'),
    unit: j.str('unit'),
    perKg: j.quantityOrNull('per_kg') ?? PosQuantity.zero,
    quantity: j.quantity('quantity'),
  );

  final String key;
  final String name;
  final String unit;
  final PosQuantity perKg;
  final PosQuantity quantity;
}

/// Osh kalkulyatori natijasi: [persons] kishiga [oshKg] kg osh, masalliqlar va qozonlar (o'lcham kg × soni).
class PosOshResult {
  const PosOshResult({
    required this.persons,
    required this.oshKg,
    this.ingredients = const <PosOshIngredient>[],
    this.kazans = const <(int, int)>[],
  });

  factory PosOshResult.fromJson(PosJson j) => PosOshResult(
    persons: j.intOrNull('persons') ?? 0,
    oshKg: j.quantity('osh_kg'),
    ingredients: j.objects('ingredients', PosOshIngredient.fromJson),
    kazans: <(int, int)>[
      for (final PosJson k in j.objects('kazans', (PosJson k) => k))
        (k.integer('size_kg'), k.integer('count')),
    ],
  );

  final int persons;
  final PosQuantity oshKg;
  final List<PosOshIngredient> ingredients;

  /// (o'lcham kg, soni), kattasi birinchi.
  final List<(int, int)> kazans;
}

/// P6-54 (E4): tadbirning ishlab chiqarish varag'i (`GET /pos/banquets/{uuid}/production-sheet`). Mijoz ma'lumoti yo'q.
class PosProductionSheet {
  const PosProductionSheet({
    required this.eventUuid,
    required this.title,
    required this.eventDate,
    required this.localTime,
    required this.halls,
    required this.guestsBase,
    required this.guestsPlanned,
    required this.reservePercent,
    this.stations = const <(String, List<PosBanquetDish>)>[],
    this.osh,
    this.warnings = const <PosBanquetWarning>[],
  });

  factory PosProductionSheet.fromJson(PosJson j) {
    final PosJson event = j.obj('event');
    final PosJson guests = j.obj('guests');
    return PosProductionSheet(
      eventUuid: event.str('uuid'),
      title: event.strOrNull('title') ?? event.str('event_type_label'),
      eventDate: event.str('event_date'),
      localTime: event.str('local_time'),
      halls: event.strings('halls'),
      guestsBase: guests.integer('base'),
      guestsPlanned: guests.integer('planned'),
      reservePercent: guests.str('reserve_percent'),
      stations: <(String, List<PosBanquetDish>)>[
        for (final PosJson s in j.objects('stations', (PosJson s) => s))
          (s.str('name'), s.objects('dishes', PosBanquetDish.fromJson)),
      ],
      osh: j.has('osh') ? PosOshResult.fromJson(j.obj('osh')) : null,
      warnings: j.objects('warnings', PosBanquetWarning.fromJson),
    );
  }

  final String eventUuid;
  final String title;
  final String eventDate;
  final String localTime;
  final List<String> halls;
  final int guestsBase;
  final int guestsPlanned;
  final String reservePercent;

  /// (sex nomi, taomlar).
  final List<(String, List<PosBanquetDish>)> stations;
  final PosOshResult? osh;
  final List<PosBanquetWarning> warnings;
}

/// P6-54: bozorlik ro'yxati (`GET /pos/banquets/shopping-list?from=&to=`): osh masalliqlari va taomlar jami.
class PosShoppingList {
  const PosShoppingList({
    required this.from,
    required this.to,
    this.events = const <(String, String, String, int)>[],
    this.dishes = const <PosBanquetDish>[],
    this.oshPersons = 0,
    this.oshKg = PosQuantity.zero,
    this.ingredients = const <PosOshIngredient>[],
  });

  factory PosShoppingList.fromJson(PosJson j) {
    final PosJson osh = j.obj('osh');
    return PosShoppingList(
      from: j.str('from'),
      to: j.str('to'),
      events: <(String, String, String, int)>[
        for (final PosJson e in j.objects('events', (PosJson e) => e))
          (
            e.str('uuid'),
            e.str('event_date'),
            e.strOrNull('title') ?? e.str('event_type'),
            e.integer('planned_guests'),
          ),
      ],
      dishes: j.objects('dishes', PosBanquetDish.fromJson),
      oshPersons: osh.intOrNull('persons') ?? 0,
      oshKg: osh.quantity('osh_kg'),
      ingredients: osh.objects('ingredients', PosOshIngredient.fromJson),
    );
  }

  final String from;
  final String to;

  /// (uuid, sana, nomi, rejalangan mehmonlar).
  final List<(String, String, String, int)> events;
  final List<PosBanquetDish> dishes;
  final int oshPersons;
  final PosQuantity oshKg;
  final List<PosOshIngredient> ingredients;
}

/// P6-54 (E5): shartnoma matni (do'kon shabloni tadbir ma'lumotlari bilan to'ldirilgan).
class PosBanquetContract {
  const PosBanquetContract({
    required this.text,
    this.contractNo,
    this.warnings = const <PosBanquetWarning>[],
  });

  factory PosBanquetContract.fromJson(PosJson j) => PosBanquetContract(
    contractNo: j.strOrNull('contract_no'),
    text: j.str('text'),
    warnings: j.objects('warnings', PosBanquetWarning.fromJson),
  );

  final String? contractNo;
  final String text;
  final List<PosBanquetWarning> warnings;

  List<String> get lines => text.split('\n');
}

/// Yakuniy hisob qatori: paket (kishi boshiga × [persons]), zal ijarasi, qo'shimcha.
class PosSettlementLine {
  const PosSettlementLine({
    required this.kind,
    required this.name,
    required this.unitPrice,
    required this.quantity,
    required this.amount,
    this.persons,
  });

  factory PosSettlementLine.fromJson(PosJson j) => PosSettlementLine(
    kind: j.str('kind'),
    name: j.str('name'),
    unitPrice: j.money('unit_price'),
    quantity: j.quantity('quantity'),
    persons: j.intOrNull('persons'),
    amount: j.money('amount'),
  );

  final String kind;
  final String name;
  final PosMoney unitPrice;
  final PosQuantity quantity;
  final int? persons;
  final PosMoney amount;
}

/// Yakuniy hisob holati: `none` (hali yo'q), `open` (schyot tayyor, qoldiq kassada), `settled`.
enum PosSettlementStatus {
  none('none'),
  open('open'),
  settled('settled');

  const PosSettlementStatus(this.wire);

  final String wire;

  static PosSettlementStatus fromWire(String? wire) => switch (wire) {
    'open' => open,
    'settled' => settled,
    _ => none,
  };
}

/// P6-54 (E5): yakuniy hisob (`GET /pos/banquets/{uuid}/settlement`): hisobdagi mehmonlar = max(kelgan, kafolatlangan,
/// minimal); jami = paket + zal + qo'shimchalar + tadbir kuni buyurtmalari; zakladlar ayiriladi ([due]) yoki ortig'i
/// qaytariladi ([excess]).
class PosBanquetSettlement {
  const PosBanquetSettlement({
    required this.eventUuid,
    required this.eventStatus,
    required this.guestsBilled,
    required this.total,
    required this.advances,
    required this.due,
    required this.excess,
    this.guestsActual,
    this.orderUuid,
    this.lines = const <PosSettlementLine>[],
    this.ordersTotal = PosMoney.zero,
    this.status = PosSettlementStatus.none,
    this.billUuid,
    this.excessStatus = 'none',
    this.warnings = const <PosBanquetWarning>[],
  });

  factory PosBanquetSettlement.fromJson(PosJson j) {
    final PosJson event = j.obj('event');
    final PosJson guests = j.obj('guests');
    final PosJson? settlement = j.objOrNull('settlement');
    return PosBanquetSettlement(
      eventUuid: event.str('uuid'),
      eventStatus: PosBanquetStatus.fromWire(event.strOrNull('status')),
      orderUuid: event.strOrNull('order_uuid'),
      guestsBilled: guests.integer('billed'),
      guestsActual: guests.intOrNull('actual'),
      lines: j.objects('lines', PosSettlementLine.fromJson),
      ordersTotal: j.obj('orders').money('total'),
      total: j.money('total'),
      advances: j.obj('advances').money('total'),
      due: j.money('due'),
      excess: j.money('excess'),
      status: settlement == null
          ? PosSettlementStatus.none
          : PosSettlementStatus.fromWire(settlement.strOrNull('status')),
      billUuid: settlement?.strOrNull('bill_uuid'),
      excessStatus: settlement?.strOrNull('excess_status') ?? 'none',
      warnings: j.objects('warnings', PosBanquetWarning.fromJson),
    );
  }

  final String eventUuid;
  final PosBanquetStatus eventStatus;
  final String? orderUuid;
  final int guestsBilled;
  final int? guestsActual;
  final List<PosSettlementLine> lines;
  final PosMoney ordersTotal;
  final PosMoney total;
  final PosMoney advances;
  final PosMoney due;
  final PosMoney excess;
  final PosSettlementStatus status;
  final String? billUuid;

  /// `none`, `pending` (menejer tasdig'i bilan kassada qaytariladi), `refunded`.
  final String excessStatus;
  final List<PosBanquetWarning> warnings;
}

/// `POST /pos/banquets/{uuid}/settle` javobi: yakuniy hisob va schyot (qoldiq `POST /pos/bills/{uuid}/payments`).
class PosSettleResult {
  const PosSettleResult({required this.settlement, required this.bill});

  factory PosSettleResult.fromJson(PosJson j) => PosSettleResult(
    settlement: PosBanquetSettlement.fromJson(j.obj('settlement')),
    bill: PosBill.fromJson(j.obj('bill')),
  );

  final PosBanquetSettlement settlement;
  final PosBill bill;
}
