import 'package:pos_core/pos_core.dart';

import 'menu.dart';

/// The charges of the choyxona as `PosStackSeeder` sets them, computed here independently of the backend so the test
/// checks the server's arithmetic rather than repeating it.
abstract final class Rules {
  /// Service charge % per hall.
  static const Map<String, int> servicePercent = <String, int>{
    'Asosiy zal': 10,
    'Hovli': 5,
  };

  /// "Joy narxi" per guest per hall.
  static const Map<String, int> placeFeePerGuest = <String, int>{
    'Asosiy zal': 0,
    'Hovli': 5000,
  };

  /// Choy per guest (a hall rule beats the branch's): 0.5 in the main hall, 1 in the courtyard. Bread: 1 per guest.
  static int teaFor(String hall, int guests) =>
      hall == 'Hovli' ? guests : (guests + 1) ~/ 2;

  static int breadFor(int guests) => guests;

  /// Room fee: (table name or kind) x day type.
  static PosMoney roomFee(String table, FloorTableKind kind, bool weekend) {
    if (table == 'Kabinka 10') return PosMoney.som(weekend ? 600000 : 500000);
    return switch (kind) {
      FloorTableKind.room => PosMoney.som(weekend ? 400000 : 300000),
      FloorTableKind.tapchan => PosMoney.som(weekend ? 150000 : 100000),
      _ => PosMoney.zero,
    };
  }

  /// Minimum spend of a table with guests.
  static PosMoney minSpend(String table, int guests) => switch (table) {
    'Kabinka 3' => const PosMoney.som(500000),
    'Kabinka 7' => const PosMoney.som(60000) * guests,
    _ => PosMoney.zero,
  };

  /// Saturday and Sunday (the branch's default weekend), from the business date the server stamped on the order.
  static bool isWeekend(String businessDate) {
    final DateTime d = DateTime.parse(businessDate);
    return d.weekday == DateTime.saturday || d.weekday == DateTime.sunday;
  }
}

/// What an order must cost, from what was ordered.
class ExpectedCharges {
  ExpectedCharges({
    required this.hall,
    required this.table,
    required this.kind,
    required this.guests,
    required this.weekend,
    required PosMoney food,
  }) {
    subtotal = food;
    serviceCharge = food.percent(Rules.servicePercent[hall]!).roundToSom();
    placeFee =
        PosMoney.som(Rules.placeFeePerGuest[hall]!) * (guests < 1 ? 1 : guests);
    roomFee = Rules.roomFee(table, kind, weekend);
    final PosMoney minimum = Rules.minSpend(table, guests);
    final PosMoney gap = minimum - food;
    topup = gap.isPositive ? gap.roundToSom() : PosMoney.zero;
    total = food + serviceCharge + placeFee + roomFee + topup;
  }

  final String hall;
  final String table;
  final FloorTableKind kind;
  final int guests;
  final bool weekend;
  late final PosMoney subtotal;
  late final PosMoney serviceCharge;
  late final PosMoney placeFee;
  late final PosMoney roomFee;
  late final PosMoney topup;
  late final PosMoney total;
}

/// The choy and non lines the server adds for [guests] guests, as dishes.
List<Dish> guestLines(String hall, int guests) => <Dish>[
  Dish('Choy', PosQuantity.whole(Rules.teaFor(hall, guests))),
  Dish('Non', PosQuantity.whole(Rules.breadFor(guests))),
];
