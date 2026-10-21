import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

final DateTime now = DateTime(2026, 10, 22, 20, 40);

Widget host(Widget child, {ThemeData? theme}) => MaterialApp(
  debugShowCheckedModeBanner: false,
  theme: theme ?? RzTheme.light(),
  home: Scaffold(body: child),
);

void setScreen(WidgetTester tester, Size size) {
  tester.view
    ..physicalSize = size
    ..devicePixelRatio = 1;
  addTearDown(tester.view.reset);
}

/// A choyxona hall: rooms along the walls, tapchans in the garden, tables in the middle.
final List<FloorMapItem> hall = <FloorMapItem>[
  FloorMapItem(
    id: 'r1',
    label: 'Xona 1',
    kind: FloorTableKind.room,
    rect: const Rect.fromLTWH(0, 0, 30, 24),
    status: FloorTableStatus.occupied,
    seats: 15,
    guests: 12,
    openedAt: DateTime(2026, 10, 22, 18, 5),
    amount: const PosMoney.som(2450000),
    waiter: 'Bekzod',
  ),
  FloorMapItem(
    id: 'r2',
    label: 'Xona 2',
    kind: FloorTableKind.room,
    rect: const Rect.fromLTWH(30, 0, 30, 24),
    status: FloorTableStatus.booked,
    seats: 10,
    bookingAt: DateTime(2026, 10, 22, 21, 0),
  ),
  FloorMapItem(
    id: 'r3',
    label: 'Xona 3 (VIP)',
    kind: FloorTableKind.room,
    rect: const Rect.fromLTWH(60, 0, 40, 24),
    status: FloorTableStatus.overdue,
    seats: 20,
    guests: 18,
    openedAt: DateTime(2026, 10, 22, 14, 10),
    amount: const PosMoney.som(5120000),
  ),
  const FloorMapItem(
    id: 't1',
    label: 'Tapchan 1',
    kind: FloorTableKind.tapchan,
    rect: Rect.fromLTWH(0, 30, 24, 20),
    seats: 6,
  ),
  FloorMapItem(
    id: 't2',
    label: 'Tapchan 2',
    kind: FloorTableKind.tapchan,
    rect: const Rect.fromLTWH(24, 30, 24, 20),
    status: FloorTableStatus.billPrinted,
    seats: 6,
    guests: 5,
    openedAt: DateTime(2026, 10, 22, 19, 50),
    amount: const PosMoney.som(640000),
  ),
  const FloorMapItem(
    id: 's1',
    label: 'S-1',
    rect: Rect.fromLTWH(56, 32, 14, 9),
    seats: 4,
  ),
  FloorMapItem(
    id: 's2',
    label: 'S-2',
    rect: const Rect.fromLTWH(72, 32, 14, 9),
    status: FloorTableStatus.occupied,
    guests: 2,
    openedAt: DateTime(2026, 10, 22, 20, 15),
  ),
  const FloorMapItem(
    id: 's3',
    label: 'S-3',
    rect: Rect.fromLTWH(88, 32, 12, 9),
    status: FloorTableStatus.inactive,
  ),
];

Widget tilesScene() => Padding(
  padding: const EdgeInsets.all(16),
  child: Wrap(
    spacing: 12,
    runSpacing: 12,
    children: <Widget>[
      PosTileButton(
        label: 'Osh (devzira)',
        subtitle: "248 000 so'm / kg",
        accent: const Color(0xFFC2410C),
        badge: 'Qoldi: 12',
        onTap: () {},
      ),
      PosTileButton(
        label: "Choy (ko'k)",
        subtitle: "8 000 so'm",
        icon: Icons.emoji_food_beverage_outlined,
        selected: true,
        onTap: () {},
      ),
      PosTileButton(
        label: 'Qozon kabob',
        subtitle: "180 000 so'm / kg",
        accent: const Color(0xFF2563EB),
        unavailable: true,
        onTap: () {},
      ),
      PosTileButton(label: 'Non', subtitle: "6 000 so'm", onTap: () {}),
    ],
  ),
);

Widget amountScene() => Padding(
  padding: const EdgeInsets.all(16),
  child: SizedBox(
    width: 300,
    child: PosAmountEntry(
      initial: const PosMoney.som(250000),
      quickAmounts: const <PosMoney>[
        PosMoney.som(100000),
        PosMoney.som(200000),
      ],
      onChanged: (_) {},
    ),
  ),
);

Widget quantityScene() => Padding(
  padding: const EdgeInsets.all(16),
  child: Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: <Widget>[
      SizedBox(
        width: 300,
        child: PosQuantityKeypad(
          unitLabel: 'kg (guruch)',
          initial: PosQuantity.parse('0.75'),
          step: PosQuantity.parse('0.05'),
          onSubmit: (_) {},
        ),
      ),
      const SizedBox(width: 24),
      Column(
        children: <Widget>[
          PosQuantityStepper(
            value: const PosQuantity.whole(1),
            onChanged: (_) {},
            onRemove: () {},
          ),
          const SizedBox(height: 16),
          PosQuantityStepper(
            value: PosQuantity.parse('2.5'),
            unit: PosUnit.kg,
            step: PosQuantity.parse('0.5'),
            min: PosQuantity.parse('0.5'),
            max: PosQuantity.parse('2.5'),
            onChanged: (_) {},
          ),
        ],
      ),
    ],
  ),
);

Widget floorScene() =>
    FloorMap(items: hall, now: now, selectedId: 't2', onTap: (_) {});

Widget summaryScene() => Padding(
  padding: const EdgeInsets.all(16),
  child: SizedBox(
    width: 440,
    child: RoomSummaryCard(
      title: 'Xona 1',
      status: FloorTableStatus.occupied,
      total: const PosMoney.som(2450000),
      guests: 12,
      seats: 15,
      waiter: 'Bekzod',
      openedAt: DateTime(2026, 10, 22, 18, 5),
      now: now,
      rounds: 3,
      itemsCount: 14,
      prepaid: const PosMoney.som(1000000),
      minSpend: const PosMoney.som(3000000),
      bookingNote: 'Bron: 18:00, Jasur, 12 kishi',
      actions: <Widget>[
        RzButton(label: "Taom qo'shish", icon: Icons.add, onPressed: () {}),
        RzButton(
          label: 'Schyot',
          variant: RzButtonVariant.secondary,
          onPressed: () {},
        ),
      ],
    ),
  ),
);
