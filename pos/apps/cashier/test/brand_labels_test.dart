import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_cashier/src/features/order/kitchen_tickets.dart';
import 'package:rizqim_cashier/src/printing/printing_service.dart';
import 'package:rizqim_cashier/src/printing/label_printing.dart';

import 'fast_food_test.dart' show fastFoodTill;
import 'support/harness.dart';
import 'support/p22.dart';

// P6-55 / wave 5: the brand code on cup stickers, bag labels and the kitchen tickets the till prints; a line without a
// brand prints exactly as before.
class _Labels extends CashierLabels {
  _Labels(CashierPrinting printing)
    : super(
        printing: printing,
        bootstrap: () => null,
        catalog: () => null,
        now: () => DateTime(2026, 11, 22, 12),
      );

  // Every dish gets a sticker; the bag label is on
  @override
  PosLabelSettings? get settings =>
      const PosLabelSettings(configured: true, allItems: true);
}

Map<String, Object?> _item(
  int id,
  String name, {
  Map<String, Object?>? brand,
}) => <String, Object?>{
  'id': id,
  'round_no': 1,
  'product_variant_id': id,
  'product_name': name,
  'quantity': '1.000',
  'unit': 'piece',
  'unit_price': '10000.00',
  'total_price': '10000.00',
  'kitchen_status': 'sent',
  'sent_at': '2026-11-22T12:00:00Z',
  'station_id': 1,
  'brand': ?brand,
};

PosOrder _order(List<Map<String, Object?>> items) => PosOrder.fromJson(
  PosJson.of(<String, Object?>{
    'uuid': 'o-1',
    'short_code': '#T101',
    'status': 'accepted',
    'channel': 'pos_takeaway',
    'type': 'pickup',
    'version': 1,
    'total_price': '20000.00',
    'items': items,
  }),
);

const Map<String, Object?> bek = <String, Object?>{
  'id': 1,
  'name': 'Bek Burger',
  'code': 'BB',
  'color': '#F59E0B',
};

void main() {
  test(
    'kitchen ticket: a branded line carries its code, a plain one nothing',
    () {
      final PosOrder order = _order(<Map<String, Object?>>[
        _item(1, 'Burger', brand: bek),
        _item(2, 'Non'),
      ]);
      final StationTicket t = buildStationTicket(
        order,
        order.items,
        stationName: 'Oshxona',
        sentAt: DateTime(2026, 11, 22, 12),
      );
      expect(t.items[0].brandCode, 'BB');
      expect(t.items[1].brandCode, isNull);
      final String text = layoutStationTicket(
        t,
        PaperWidth.mm80,
      ).texts.join('\n');
      expect(text, contains('[BB] 1 x Burger'));
      expect(text, contains('Brend: BB'));
    },
  );

  test('kitchen ticket without brands: no brand marks at all', () {
    final PosOrder order = _order(<Map<String, Object?>>[_item(2, 'Non')]);
    final StationTicket t = buildStationTicket(
      order,
      order.items,
      stationName: 'Oshxona',
      sentAt: DateTime(2026, 11, 22, 12),
    );
    final String text = layoutStationTicket(
      t,
      PaperWidth.mm80,
    ).texts.join('\n');
    expect(text, isNot(contains('Brend')));
    expect(text, isNot(contains('[')));
  });

  testWidgets('stickers and the bag label carry the brand code', (
    WidgetTester tester,
  ) async {
    final (P22 p, _, _) = await fastFoodTill();
    await pumpHome(tester, p.h);
    final PosOrder order = _order(<Map<String, Object?>>[
      _item(1, 'Latte', brand: bek),
      _item(2, 'Pepsi'),
    ]);
    final LabelBatch batch = _Labels(p.h.services.printing)
        .forOrder(order, number: 57, bag: true);
    expect(batch.stickers, hasLength(2));
    expect(batch.stickers[0].brandCode, 'BB');
    expect(batch.stickers[1].brandCode, isNull);
    expect(batch.bag!.brandCodes, <String>['BB']);
    final LabelLayout latte = batch.layouts[0];
    expect(latte.texts.last.text, ' BB ');
    // The plain sticker has no mark: its last text is the take-away line
    expect(batch.layouts[1].texts.last.text, 'Olib ketish');
    expect(batch.layouts.last.allText.join('\n'), contains('BB'));
  });
}
