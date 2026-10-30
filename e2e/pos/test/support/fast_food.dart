// The TSPL builder and the label grid are pos_hardware's pure-Dart parts; its public library also exports the Flutter
// image rasterizer, which `dart test` cannot load.
// ignore_for_file: implementation_imports
import 'dart:async';
import 'dart:io';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/src/tspl/label_grid.dart';
import 'package:pos_hardware/src/tspl/tspl_builder.dart';
import 'package:pos_hardware/testing.dart';

import 'stack.dart';

Map<String, Object?> asMap(Object? value) =>
    (value! as Map<Object?, Object?>).cast<String, Object?>();

List<Map<String, Object?>> asList(Object? value) => <Map<String, Object?>>[
  for (final Object? e in (value as List<Object?>?) ?? const <Object?>[])
    asMap(e),
];

DateTime? dateOf(Object? value) =>
    value is String ? DateTime.parse(value).toUtc() : null;

/// Where the seeder routes each category (PosStackSeeder::fastFood): the sauces are put in at the grill.
const Map<String, String> stationOfCategory = <String, String>{
  'Lavash': 'grill',
  'Burger': 'grill',
  'Soslar': 'grill',
  'Fri': 'fryer',
  'Ichimliklar': 'drinks',
  'Kofe': 'barista',
};

/// The packaging rules of the seed: a bag per takeaway order, a cup per drink (a drink of a combo is the combo's dish,
/// so it takes no cup of its own).
const PosMoney bagPrice = PosMoney.som(1500);
const PosMoney cupPrice = PosMoney.som(1000);

/// One line of a planned order: a dish (its variant and quick modifiers, sent explicitly as the till does) or a combo
/// with its picks (receipt labels of the options; a slot left out takes its default).
class Item {
  const Item.dish(
    this.name, {
    this.variant,
    this.quantity = 1,
    this.modifiers = const <String>[],
  }) : picks = null;

  const Item.combo(
    this.name, {
    this.quantity = 1,
    List<String> this.picks = const <String>[],
  }) : variant = null,
       modifiers = const <String>[];

  final String name;
  final String? variant;
  final int quantity;
  final List<String> modifiers;

  /// The options picked by their receipt labels (`null`: not a combo).
  final List<String>? picks;

  bool get isCombo => picks != null;

  @override
  String toString() => isCombo
      ? '$quantity × $name${picks!.isEmpty ? '' : ' (${picks!.join(', ')})'}'
      : '$quantity × $name${variant == null ? '' : ' $variant'}${modifiers.isEmpty ? '' : ' [${modifiers.join(', ')}]'}';
}

/// A part of a combo as the test works it out from the catalog (the slot's pick or its default).
class Part {
  Part(this.slot, this.option, this.pieces);

  final PosComboSlot slot;
  final PosComboOption option;
  final int pieces;
}

/// The fast food menu of the till's catalog, with the prices and the routes the test checks the server against.
class FfMenu {
  FfMenu(this.catalog, this.categories);

  final PosCatalog catalog;

  /// Category name => id (the seed).
  final Map<String, int> categories;

  PosProduct product(String name) =>
      catalog.products.firstWhere((PosProduct p) => p.name == name);

  PosProduct productById(int id) =>
      catalog.products.firstWhere((PosProduct p) => p.id == id);

  PosVariant variant(Item item) {
    final PosProduct p = product(item.name);
    return item.variant == null
        ? p.variants.first
        : p.variants.firstWhere((PosVariant v) => v.name == item.variant);
  }

  PosModifier modifier(String dish, String name) => <PosModifier>[
    for (final PosModifierGroup g in product(dish).modifierGroups)
      ...g.modifiers,
  ].firstWhere((PosModifier m) => m.name == name);

  String categoryName(int id) => categories.entries
      .firstWhere((MapEntry<String, int> e) => e.value == id)
      .key;

  /// The option of [combo] whose receipt label is [label].
  PosComboOption option(String combo, String label) {
    for (final PosComboSlot s in product(combo).combo!.slots) {
      for (final PosComboOption o in s.options) {
        if (o.label == label || o.receiptLabel == label) return o;
      }
    }
    throw StateError('$combo has no option "$label"');
  }

  /// What the combo is made of: per slot the picks (by label) or its default.
  List<Part> parts(Item item) {
    final PosCombo combo = product(item.name).combo!;
    final List<Part> out = <Part>[];
    for (final PosComboSlot slot in combo.slots) {
      final List<PosComboOption> picked = <PosComboOption>[
        for (final PosComboOption o in slot.options)
          if (item.picks!.contains(o.label) ||
              item.picks!.contains(o.receiptLabel))
            o,
      ];
      final List<PosComboOption> used = picked.isNotEmpty
          ? picked
          : <PosComboOption>[?slot.defaultOption];
      for (final PosComboOption o in used) {
        out.add(Part(slot, o, o.quantity));
      }
    }
    return out;
  }

  PosLineInput input(Item item) => PosLineInput(
    variantUuid: variant(item).uuid,
    quantity: PosQuantity.whole(item.quantity),
    modifiers: <PosModifierChoice>[
      for (final String m in item.modifiers)
        PosModifierChoice(modifier(item.name, m).uuid),
    ],
    combo: item.isCombo
        ? <PosComboPick>[
            for (final String label in item.picks!)
              PosComboPick(option(item.name, label).uuid),
          ]
        : null,
  );

  List<PosLineInput> inputs(Iterable<Item> items) => <PosLineInput>[
    for (final Item i in items) input(i),
  ];

  /// The Mini App's `items[]` line of `POST /orders`.
  Map<String, Object?> onlineLine(Item item) => <String, Object?>{
    'variant_id': variant(item).uuid,
    'quantity': item.quantity,
    if (item.modifiers.isNotEmpty)
      'modifiers': <Map<String, Object?>>[
        for (final String m in item.modifiers)
          <String, Object?>{'id': modifier(item.name, m).uuid, 'quantity': 1},
      ],
    if (item.isCombo)
      'combo': <Map<String, Object?>>[
        for (final String label in item.picks!)
          <String, Object?>{'option_id': option(item.name, label).uuid},
      ],
  };

  /// What the line costs, from the catalog: (price + quick modifiers + the picks' deltas) × quantity. A combo's preset
  /// modifiers are in its price (the owner's rule), only the option's own delta adds.
  PosMoney price(Item item) {
    PosMoney unit = variant(item).price;
    for (final String m in item.modifiers) {
      unit += modifier(item.name, m).priceDelta;
    }
    if (item.isCombo) {
      for (final Part p in parts(item)) {
        unit += p.option.priceDelta;
      }
    }
    return unit * item.quantity;
  }

  PosMoney total(Iterable<Item> items) => PosMoney.sum(items.map(price));

  /// The category of a dish line or a combo part (by its product).
  String categoryOfProduct(int productId) =>
      categoryName(productById(productId).categoryId);

  /// The stations the order's kitchen lines go to (station keys of the seed).
  Set<String> stations(Iterable<Item> items) => <String>{
    for (final Item i in items)
      if (i.isCombo)
        for (final Part p in parts(i))
          stationOfCategory[categoryOfProduct(p.option.productId!)]!
      else
        stationOfCategory[categoryName(product(i.name).categoryId)]!,
  };

  /// The cups of the drinks sold on their own (the cup rule counts dishes of the drinks category; combos are dishes of
  /// their own category).
  int cups(Iterable<Item> items) => <Item>[
    for (final Item i in items)
      if (!i.isCombo &&
          categoryName(product(i.name).categoryId) == 'Ichimliklar')
        i,
  ].fold<int>(0, (int n, Item i) => n + i.quantity);

  /// The packaging of a takeaway (counter or Telegram pickup) order: a bag (unless removed) and the cups.
  PosMoney packaging(Iterable<Item> items, {bool bag = true}) =>
      (bag ? bagPrice : PosMoney.zero) + cupPrice * cups(items);

  /// Cup stickers for the order's drinks and coffee, including the drinks inside combos (with the combo's name).
  List<CupSticker> stickers(
    Iterable<Item> items, {
    required String number,
    required DateTime at,
    required PosLabelSettings labels,
    String? customer,
  }) {
    final List<
      ({
        String name,
        String? variant,
        List<String> mods,
        int qty,
        String? context,
      })
    >
    cups =
        <
          ({
            String name,
            String? variant,
            List<String> mods,
            int qty,
            String? context,
          })
        >[];
    for (final Item i in items) {
      if (i.isCombo) {
        for (final Part p in parts(i)) {
          final PosProduct dish = productById(p.option.productId!);
          if (!labels.appliesTo(dish.categoryId)) continue;
          cups.add((
            name: p.option.name,
            variant: p.option.modifiers.isEmpty
                ? p.option.variantName
                : p.option.modifiers.first.name,
            mods: const <String>[],
            qty: p.pieces * i.quantity,
            context: i.name,
          ));
        }
      } else {
        final PosProduct dish = product(i.name);
        if (!labels.appliesTo(dish.categoryId)) continue;
        cups.add((
          name: dish.name,
          variant: i.modifiers.isNotEmpty ? i.modifiers.first : variant(i).name,
          mods: i.modifiers.length > 1
              ? i.modifiers.sublist(1)
              : const <String>[],
          qty: i.quantity,
          context: null,
        ));
      }
    }
    final List<CupSticker> out = <CupSticker>[];
    for (final c in cups) {
      final int total = labels.stickersFor(PosQuantity.whole(c.qty));
      for (var k = 1; k <= total; k++) {
        out.add(
          CupSticker(
            number: number,
            itemName: c.name,
            variantName: c.variant,
            // The combo the drink belongs to rides on the modifiers row ("Lavash kombo")
            modifiers: <String>[?c.context, ...c.mods],
            customerName: customer,
            printedAt: at,
            index: k,
            total: total,
          ),
        );
      }
    }
    return out;
  }
}

/// The till's label printer: the TSPL printer of the branch (purpose `label`), here pos_hardware's LAN emulator. The
/// stickers are rendered like pos_hardware's TsplRenderer text mode (printer font 2, `TEXT`/`BAR`/`REVERSE`).
class LabelPrinterSim {
  LabelPrinterSim._(this.emulator);

  static Future<LabelPrinterSim> start() async =>
      LabelPrinterSim._(await LanPrinterEmulator.start());

  final LanPrinterEmulator emulator;

  /// What the emulator decoded, one label per connection, in the order they arrived.
  final List<({String title, String text, TsplDocument doc})> printed =
      <({String title, String text, TsplDocument doc})>[];

  int get port => emulator.port;

  /// The text-mode bytes of a label (pos_hardware TsplRenderer.renderText).
  static Uint8List render(LabelLayout layout, {int copies = 1}) {
    final TsplBuilder b = TsplBuilder()
      ..size(layout.size.widthMm, layout.size.heightMm)
      ..gap(2)
      ..direction(1)
      ..reference(0, 0)
      ..density(8)
      ..cls();
    final LabelSize size = layout.size;
    for (final LabelBar bar in layout.bars) {
      b.bar(
        LabelSize.margin,
        LabelGrid.rowTop(bar.row) + LabelGrid.barOffset,
        size.dotsWidth - 2 * LabelSize.margin,
        LabelGrid.barThickness,
      );
    }
    for (final LabelText t in layout.texts) {
      final int x = LabelGrid.colLeft(t.col);
      final int y = LabelGrid.rowTop(t.row) + LabelGrid.glyphOffset * t.scale;
      b.text(x, y, LabelGrid.font, t.text, xMul: t.scale, yMul: t.scale);
      if (t.bold) {
        b.text(x + 1, y, LabelGrid.font, t.text, xMul: t.scale, yMul: t.scale);
      }
      if (t.inverted) {
        b.reverse(
          x - 2,
          LabelGrid.rowTop(t.row),
          t.cells * LabelSize.cellWidth + 4,
          LabelSize.rowHeight * t.scale,
        );
      }
    }
    return (b..printLabels(copies: copies)).build();
  }

  Future<void> _turn = Future<void>.value();

  /// Sends one label to the printer at [address]:[port] (from the bootstrap) and waits for the emulator to decode it.
  /// One label at a time: both tills share the printer, and the emulator answers in the order the labels finish.
  Future<String> send(LabelLayout layout, String address, int port) {
    final Future<String> mine = _turn.then((_) => _send(layout, address, port));
    _turn = mine.then<void>((_) {}, onError: (Object _) {});
    return mine;
  }

  Future<String> _send(LabelLayout layout, String address, int port) async {
    for (final String text in layout.allText) {
      if (!tsplCanEncode(text)) {
        throw StateError(
          '"$text" is not in the printer font: the till would print it as an image',
        );
      }
    }
    final Future<EmulatedPrint> next = emulator.next(
      timeout: const Duration(seconds: 10),
    );
    final Socket socket = await Socket.connect(address, port);
    socket.add(render(layout));
    await socket.flush();
    await socket.close();
    final EmulatedPrint print = await next;
    final TsplDocument doc = print.label;
    final String text = doc.render();
    printed.add((title: layout.title, text: text, doc: doc));
    return text;
  }

  Future<void> stop() => emulator.stop();
}

/// One kitchen line as the kitchen display showed it (a combo part with its combo).
class LineSeen {
  LineSeen(this.orderUuid, this.id, this.stationId, this.productName);

  final String orderUuid;
  final int id;
  final int? stationId;
  final String productName;
  String? comboName;
  int? comboItemId;
  DateTime? sentAt;
  DateTime? readyAt;
  int? targetSeconds;
  bool lateSeen = false;
  bool online = false;
  DateTime? bumpedAt;

  /// When the line reached the kitchen (POS: sent_at; online: set by the test from the order).
  DateTime? startedAt;

  int? get seconds => startedAt == null || readyAt == null
      ? null
      : readyAt!.difference(startedAt!).inSeconds;
}

/// The cooks at the kitchen display (one PIN session): each station bumps its own lines after its cooking time; a
/// station can be paused (it falls behind). Reads the tickets raw: the test checks the KDS v2 fields
/// (`target_seconds`, `late`, `combo`, `assembly`) as the server sends them.
class KitchenCrew {
  KitchenCrew(this.kds, this.cookTime);

  final Terminal kds;

  /// Station id => how long a line cooks there.
  final Map<int, Duration> cookTime;
  final Set<int> paused = <int>{};
  final Map<int, LineSeen> lines = <int, LineSeen>{};

  /// Order uuid => the ticket's `assembly` as last seen (null: the order does not go through the expo).
  final Map<String, Map<String, Object?>?> assembly =
      <String, Map<String, Object?>?>{};
  final Map<String, Map<String, Object?>> tickets =
      <String, Map<String, Object?>>{};
  final List<String> refused = <String>[];
  final Set<int> _bumping = <int>{};
  bool _running = false;
  Future<void>? _loop;

  void start() {
    if (_running) return;
    _running = true;
    _loop = _run();
  }

  Future<void> stop() async {
    _running = false;
    await _loop;
  }

  static bool cookable(Map<String, Object?> line) {
    final Object? status = line['kitchen_status'];
    // A POS line sent to the kitchen, or an online line (no fire_mode) still to cook
    return status == 'sent' ||
        (status == 'pending' && line['fire_mode'] == null);
  }

  Future<List<Map<String, Object?>>> fetch({bool includeReady = false}) async {
    final Response<Object?> r = await kds.client.dio.get<Object?>(
      '/v1/staff/kitchen/tickets',
      queryParameters: <String, Object?>{if (includeReady) 'include_ready': 1},
    );
    return asList(asMap(r.data)['data']);
  }

  void note(Map<String, Object?> ticket) {
    final String uuid = ticket['uuid']! as String;
    tickets[uuid] = ticket;
    assembly[uuid] = ticket['assembly'] == null
        ? null
        : asMap(ticket['assembly']);
    final bool online = !(ticket['channel'] as String? ?? '').startsWith(
      'pos_',
    );
    for (final Map<String, Object?> l in asList(ticket['items'])) {
      final int id = (l['id']! as num).toInt();
      final LineSeen seen = lines.putIfAbsent(
        id,
        () => LineSeen(
          uuid,
          id,
          (l['station_id'] as num?)?.toInt(),
          '${l['product_name']}',
        ),
      );
      seen.online = online;
      final Object? combo = l['combo'];
      if (combo is Map) {
        seen.comboName = '${combo['name']}';
        seen.comboItemId = (combo['item_id'] as num?)?.toInt();
      }
      seen.sentAt = dateOf(l['sent_at']) ?? seen.sentAt;
      seen.readyAt = dateOf(l['ready_at']) ?? seen.readyAt;
      seen.targetSeconds =
          (l['target_seconds'] as num?)?.toInt() ?? seen.targetSeconds;
      if (l['late'] == true && cookable(l)) seen.lateSeen = true;
    }
  }

  Future<void> _cook(String order, LineSeen line) async {
    final Duration time = cookTime[line.stationId] ?? Duration.zero;
    if (time > Duration.zero) await Future<void>.delayed(time);
    while (paused.contains(line.stationId) && _running) {
      await Future<void>.delayed(const Duration(milliseconds: 100));
    }
    for (var attempt = 0; ; attempt++) {
      try {
        line.bumpedAt = DateTime.now();
        final Response<Object?> r = await kds.client.dio.post<Object?>(
          '/v1/staff/orders/$order/items/${line.id}/ready',
        );
        note(asMap(asMap(r.data)['data']));
        return;
      } on DioException catch (e) {
        // The API's rate limit (300 a minute per user): the cook waits a moment, as the display would
        if (e.response?.statusCode == 429 && attempt < 20) {
          await Future<void>.delayed(const Duration(seconds: 1));
          continue;
        }
        refused.add(
          '$order/${line.id}: ${e.response?.statusCode} ${e.response?.data}',
        );
        return;
      }
    }
  }

  Future<void> _run() async {
    final List<Future<void>> cooking = <Future<void>>[];
    while (_running) {
      try {
        for (final Map<String, Object?> ticket in await fetch()) {
          note(ticket);
          for (final Map<String, Object?> l in asList(ticket['items'])) {
            final int id = (l['id']! as num).toInt();
            final int? station = (l['station_id'] as num?)?.toInt();
            if (!cookable(l) || !cookTime.containsKey(station)) continue;
            if (paused.contains(station)) continue;
            if (!_bumping.add(id)) continue;
            cooking.add(_cook(ticket['uuid']! as String, lines[id]!));
          }
        }
      } on Object catch (e) {
        // A listing over the rate limit is simply tried again (the display polls)
        if (!(e is DioException && e.response?.statusCode == 429)) {
          refused.add('tickets: $e');
        }
      }
      await Future<void>.delayed(const Duration(milliseconds: 500));
    }
    await Future.wait(cooking);
  }
}

/// The expo screen: an order cooked on several stations waits there until every station is done; the expo bumps
/// "Yig'ildi" (`POST /staff/orders/{uuid}/assembled`).
class ExpoWorker {
  ExpoWorker(this.expo);

  final Terminal expo;
  final Map<String, DateTime> bumped = <String, DateTime>{};
  final Map<String, DateTime?> assembledAt = <String, DateTime?>{};
  final List<String> refused = <String>[];
  bool _running = false;
  Future<void>? _loop;

  void start() {
    if (_running) return;
    _running = true;
    _loop = _run();
  }

  Future<void> stop() async {
    _running = false;
    await _loop;
  }

  Future<void> _run() async {
    while (_running) {
      try {
        final Response<Object?> r = await expo.client.dio.get<Object?>(
          '/v1/staff/kitchen/tickets',
        );
        for (final Map<String, Object?> t in asList(asMap(r.data)['data'])) {
          final Object? a = t['assembly'];
          if (a is! Map || a['waiting'] != true) continue;
          final String uuid = t['uuid']! as String;
          if (bumped.containsKey(uuid)) continue;
          bumped[uuid] = DateTime.now();
          try {
            final Response<Object?> done = await expo.client.dio.post<Object?>(
              '/v1/staff/orders/$uuid/assembled',
              options: Options(
                headers: <String, Object?>{
                  'Idempotency-Key': expo.pos.http.newKey(),
                },
              ),
            );
            final Map<String, Object?> ticket = asMap(asMap(done.data)['data']);
            assembledAt[uuid] = dateOf(
              ticket['assembly'] is Map
                  ? asMap(ticket['assembly'])['assembled_at']
                  : null,
            );
          } on DioException catch (e) {
            refused.add('$uuid: ${e.response?.statusCode} ${e.response?.data}');
          }
        }
      } on Object catch (e) {
        // A listing over the rate limit is simply tried again (the display polls)
        if (!(e is DioException && e.response?.statusCode == 429)) {
          refused.add('tickets: $e');
        }
      }
      await Future<void>.delayed(const Duration(milliseconds: 500));
    }
  }
}

/// percentile_cont over sorted whole seconds, rounded half up (PrepTimeReport::percentile).
int? percentile(List<int> sorted, double fraction) {
  if (sorted.isEmpty) return null;
  final double position = fraction * (sorted.length - 1);
  final int low = position.floor();
  final int high = position.ceil();
  final double value =
      sorted[low] + (sorted[high] - sorted[low]) * (position - low);
  return (value + 0.5).floor();
}
