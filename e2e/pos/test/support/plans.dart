import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';

import 'menu.dart';

/// One party of the evening: where it sits, who serves it and what it orders round by round. Round 1 is what the
/// order is opened with (the choy and non lines are added by the server); the booking room's round 1 is the pre-order.
class RoomPlan {
  const RoomPlan(
    this.table,
    this.hall,
    this.actor,
    this.guests,
    this.rounds, {
    this.booking = false,
  });

  final String table;
  final String hall;

  /// `waiter1`, `waiter2` or `cashier` (the till opens the room itself, no waiter).
  final String actor;
  final int guests;
  final List<List<Dish>> rounds;

  /// The party booked in advance: the cashier presses "Keldi" and the waiter takes the order over.
  final bool booking;

  bool get byWaiter => actor != 'cashier';
}

PosFireMode get hold => PosFireMode.hold;

/// The Thursday evening: ten rooms (Kabinka 1-10) and, in the courtyard, a tapchan and a table; every party has four or
/// more rounds. Seat and note fields are used here and there, a `hold` line (cooked now, fired later) in Kabinka 5.
final List<RoomPlan> thursdayRooms = <RoomPlan>[
  RoomPlan('Kabinka 1', 'Asosiy zal', 'waiter1', 4, <List<Dish>>[
    <Dish>[Dish.of('Osh', 2, fireMode: hold)], // the pre-order
    <Dish>[Dish.of('Shashlik', 10), Dish.of('Achichuk', 2)],
    <Dish>[Dish.of('Shashlik', 6), Dish.of('Choy', 2)],
    <Dish>[
      Dish.of(
        'Lagmon',
        2,
        modifiers: <String>['Katta', 'Achchiq sous'],
        note: 'Piyozsiz',
      ),
    ],
    <Dish>[Dish.of('Non', 4)],
  ], booking: true),
  RoomPlan('Kabinka 2', 'Asosiy zal', 'waiter1', 5, <List<Dish>>[
    <Dish>[Dish.of('Osh', '1.5'), Dish.of('Achichuk', 2)],
    <Dish>[Dish.of('Shashlik', 10, seatNo: 1)],
    <Dish>[Dish.of('Svejiy salat', 2), Dish.of('Choy', 2)],
    <Dish>[Dish.of('Shashlik', 5)],
  ]),
  // A small order in a room with a 500 000 minimum: the top-up
  RoomPlan('Kabinka 3', 'Asosiy zal', 'waiter1', 3, <List<Dish>>[
    <Dish>[Dish.of('Osh', 1)],
    <Dish>[Dish.of('Achichuk', 2)],
    <Dish>[Dish.of('Shashlik', 4)],
    <Dish>[Dish.of('Svejiy salat', 1)],
  ]),
  RoomPlan('Kabinka 4', 'Asosiy zal', 'waiter1', 6, <List<Dish>>[
    <Dish>[Dish.of('Osh', '2.5')],
    <Dish>[Dish.of('Shashlik', 12)],
    <Dish>[Dish.of('Achichuk', 3), Dish.of('Svejiy salat', 2)],
    <Dish>[
      Dish.of('Lagmon', 2, modifiers: <String>['Oddiy', 'Achchiq sous']),
    ],
    <Dish>[Dish.of('Choy', 3)],
  ]),
  RoomPlan('Kabinka 5', 'Asosiy zal', 'waiter2', 4, <List<Dish>>[
    <Dish>[Dish.of('Osh', 2), Dish.of('Achichuk', 1)],
    <Dish>[Dish.of('Shashlik', 8)],
    // Cooked later: the waiter fires it by id when the party asks
    <Dish>[
      Dish.of(
        'Lagmon',
        2,
        modifiers: <String>['Katta', "Qo'shimcha go'sht"],
        fireMode: hold,
      ),
    ],
    <Dish>[Dish.of('Non', 4), Dish.of('Choy', 2)],
  ]),
  RoomPlan('Kabinka 6', 'Asosiy zal', 'waiter2', 6, <List<Dish>>[
    <Dish>[Dish.of('Osh', '1.5'), Dish.of('Svejiy salat', 2)],
    <Dish>[Dish.of('Shashlik', 8)],
    <Dish>[Dish.of('Achichuk', 2), Dish.of('Choy', 2)],
    <Dish>[Dish.of('Shashlik', 4)],
  ]),
  // A minimum of 60 000 a guest that the order clears
  RoomPlan('Kabinka 7', 'Asosiy zal', 'waiter2', 4, <List<Dish>>[
    <Dish>[Dish.of('Osh', 2)],
    <Dish>[Dish.of('Shashlik', 10)],
    <Dish>[Dish.of('Achichuk', 2)],
    <Dish>[Dish.of('Choy', 2)],
  ]),
  // A line added by mistake and voided before it is sent
  RoomPlan('Kabinka 8', 'Asosiy zal', 'waiter2', 8, <List<Dish>>[
    <Dish>[Dish.of('Osh', 3)],
    <Dish>[Dish.of('Shashlik', 16)],
    <Dish>[Dish.of('Achichuk', 3), Dish.of('Svejiy salat', 3)],
    <Dish>[
      Dish.of('Lagmon', 3, modifiers: <String>['Katta']),
    ],
  ]),
  // Opened by the cashier himself; a sent line voided with the manager's approval
  RoomPlan('Kabinka 9', 'Asosiy zal', 'cashier', 4, <List<Dish>>[
    <Dish>[Dish.of('Osh', 1)],
    <Dish>[Dish.of('Shashlik', 6)],
    <Dish>[Dish.of('Achichuk', 2)],
    <Dish>[Dish.of('Non', 2)],
  ]),
  // The VIP room (its own tariff), twelve guests
  RoomPlan('Kabinka 10', 'Asosiy zal', 'cashier', 12, <List<Dish>>[
    <Dish>[Dish.of('Osh', 5)],
    <Dish>[Dish.of('Shashlik', 24)],
    <Dish>[Dish.of('Achichuk', 4), Dish.of('Svejiy salat', 4)],
    <Dish>[
      Dish.of('Lagmon', 4, modifiers: <String>['Katta', 'Achchiq sous']),
    ],
    <Dish>[Dish.of('Choy', 6), Dish.of('Non', 6)],
  ]),
  RoomPlan('Tapchan 1', 'Hovli', 'waiter1', 5, <List<Dish>>[
    <Dish>[Dish.of('Osh', 2)],
    <Dish>[Dish.of('Shashlik', 10)],
    <Dish>[Dish.of('Achichuk', 2)],
    <Dish>[Dish.of('Choy', 3)],
  ]),
  RoomPlan('Stol 1', 'Hovli', 'waiter2', 3, <List<Dish>>[
    <Dish>[Dish.of('Shashlik', 6)],
    <Dish>[Dish.of('Achichuk', 1)],
    <Dish>[
      Dish.of('Lagmon', 1, modifiers: <String>['Oddiy']),
    ],
    <Dish>[Dish.of('Non', 3)],
  ]),
];

/// How a table pays: the split, then (for every bill) the tenders. Amounts are chosen from the bill's total, so a
/// split with a remainder in tiyin still pays to the tiyin.
class PaymentPlan {
  const PaymentPlan(this.split, this.tenders);

  /// `single`, `equal` with [parts], or `items` (the kg shares).
  final String split;
  final List<List<TenderPlan>> tenders;
}

/// A tender: its kind and how much of the bill's total it takes.
class TenderPlan {
  const TenderPlan.card(this.cardType, {this.fixed})
    : kind = PosTender.card,
      change = false;
  const TenderPlan.cash({this.fixed, this.change = true})
    : kind = PosTender.cash,
      cardType = null;
  const TenderPlan.qr({this.fixed})
    : kind = PosTender.qr,
      cardType = null,
      change = false;
  const TenderPlan.prepayment(this.fixed)
    : kind = PosTender.prepayment,
      cardType = null,
      change = false;
  const TenderPlan.account({this.fixed})
    : kind = PosTender.account,
      cardType = null,
      change = false;

  final PosTender kind;

  /// A set amount; `null` takes the rest of the bill.
  final PosMoney? fixed;
  final String? cardType;

  /// Cash: the guest hands over a rounder sum and gets change.
  final bool change;
}

/// Cash is taken in whole 100 so'm (the drawer's smallest note): a total with tiyin or tens is paid by card for the rest.
PosMoney floorToHundred(PosMoney value) =>
    PosMoney(value.tiyin ~/ 10000 * 10000);

final Map<String, PaymentPlan> thursdayPayments = <String, PaymentPlan>{
  // The deposit (prepayment) and the rest by card
  'Kabinka 1': const PaymentPlan('single', <List<TenderPlan>>[
    <TenderPlan>[
      TenderPlan.prepayment(PosMoney.som(300000)),
      TenderPlan.card('humo'),
    ],
  ]),
  // All cash, with change
  'Kabinka 2': const PaymentPlan('single', <List<TenderPlan>>[
    <TenderPlan>[TenderPlan.cash()],
  ]),
  'Kabinka 3': const PaymentPlan('single', <List<TenderPlan>>[
    <TenderPlan>[TenderPlan.qr()],
  ]),
  // Equal split in three
  'Kabinka 4': const PaymentPlan('equal', <List<TenderPlan>>[
    <TenderPlan>[TenderPlan.card('uzcard')],
    <TenderPlan>[TenderPlan.cash(), TenderPlan.card('humo')],
    <TenderPlan>[TenderPlan.qr()],
  ]),
  'Kabinka 5': const PaymentPlan('single', <List<TenderPlan>>[
    <TenderPlan>[
      TenderPlan.cash(fixed: PosMoney.som(100000), change: false),
      TenderPlan.card('uzcard'),
    ],
  ]),
  // By items, with the kg shares
  'Kabinka 6': const PaymentPlan('items', <List<TenderPlan>>[
    <TenderPlan>[TenderPlan.qr()],
    <TenderPlan>[TenderPlan.cash(), TenderPlan.card('visa')],
  ]),
  // Cash and nasiya (the manager approves)
  'Kabinka 7': const PaymentPlan('single', <List<TenderPlan>>[
    <TenderPlan>[
      TenderPlan.cash(fixed: PosMoney.som(100000), change: false),
      TenderPlan.account(),
    ],
  ]),
  'Kabinka 8': const PaymentPlan('single', <List<TenderPlan>>[
    <TenderPlan>[TenderPlan.card('visa')],
  ]),
  'Kabinka 9': const PaymentPlan('single', <List<TenderPlan>>[
    <TenderPlan>[TenderPlan.qr()],
  ]),
  'Kabinka 10': const PaymentPlan('equal', <List<TenderPlan>>[
    <TenderPlan>[TenderPlan.card('humo')],
    <TenderPlan>[TenderPlan.cash(), TenderPlan.card('uzcard')],
  ]),
  'Tapchan 1': const PaymentPlan('single', <List<TenderPlan>>[
    <TenderPlan>[TenderPlan.card('uzcard')],
  ]),
  'Stol 1': const PaymentPlan('single', <List<TenderPlan>>[
    <TenderPlan>[
      TenderPlan.cash(fixed: PosMoney.som(50000), change: false),
      TenderPlan.qr(),
    ],
  ]),
};
