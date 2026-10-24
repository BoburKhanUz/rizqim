import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';

/// One order line as the test plans it: a dish by its menu name, how much of it, which modifiers (each once).
class Dish {
  const Dish(
    this.name,
    this.quantity, {
    this.modifiers = const <String>[],
    this.fireMode,
    this.note,
    this.seatNo,
  });

  /// `Dish('Osh', '1.5')`, `Dish('Shashlik', 10)`.
  factory Dish.of(
    String name,
    Object quantity, {
    List<String> modifiers = const <String>[],
    PosFireMode? fireMode,
    String? note,
    int? seatNo,
  }) => Dish(
    name,
    PosQuantity.parse(quantity),
    modifiers: modifiers,
    fireMode: fireMode,
    note: note,
    seatNo: seatNo,
  );

  final String name;
  final PosQuantity quantity;
  final List<String> modifiers;
  final PosFireMode? fireMode;
  final String? note;
  final int? seatNo;
}

/// The POS menu of the hall channel by name (what the apps show), with the prices the test checks the server against.
class Menu {
  Menu(this.catalog);

  final PosCatalog catalog;

  PosProduct product(String name) =>
      catalog.products.firstWhere((PosProduct p) => p.name == name);

  PosVariant variant(String name) => product(name).variants.single;

  PosModifier modifier(String dish, String name) => <PosModifier>[
    for (final PosModifierGroup g in product(dish).modifierGroups)
      ...g.modifiers,
  ].firstWhere((PosModifier m) => m.name == name);

  PosLineInput input(Dish dish) => PosLineInput(
    variantUuid: variant(dish.name).uuid,
    quantity: dish.quantity,
    modifiers: <PosModifierChoice>[
      for (final String m in dish.modifiers)
        PosModifierChoice(modifier(dish.name, m).uuid),
    ],
    fireMode: dish.fireMode,
    note: dish.note,
    seatNo: dish.seatNo,
  );

  List<PosLineInput> inputs(Iterable<Dish> dishes) => <PosLineInput>[
    for (final Dish d in dishes) input(d),
  ];

  /// What the line costs: (price + modifiers) x quantity, from the catalog's prices (not the server's order).
  PosMoney price(Dish dish) {
    PosMoney unit = variant(dish.name).price;
    for (final String m in dish.modifiers) {
      unit += modifier(dish.name, m).priceDelta;
    }
    return unit.timesQuantity(dish.quantity);
  }

  PosMoney total(Iterable<Dish> dishes) => PosMoney.sum(dishes.map(price));
}
