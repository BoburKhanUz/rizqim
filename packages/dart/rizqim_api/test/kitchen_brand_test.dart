import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

/// P6-55: chipta qatorining brendi va chiptadagi brendlar (`multi_brand`); brendsiz chipta o'zgarmaydi.
void main() {
  final DateTime now = DateTime.utc(2026, 11, 21, 12);

  test('a branded ticket carries the line brand and the ticket brands', () {
    final KitchenTicket t = KitchenTicket.fromJson(<String, Object?>{
      'uuid': '00000000-0000-4000-8000-000000000001',
      'status': 'accepted',
      'type': 'delivery',
      'items': <Object?>[
        <String, Object?>{
          'id': 1,
          'product_name': 'Burger',
          'quantity_label': '1',
          'kitchen_status': 'pending',
          'brand': <String, Object?>{
            'id': 4,
            'name': 'Burger Bro',
            'code': 'BB',
            'color': '#DC2626',
          },
        },
        <String, Object?>{
          'id': 2,
          'product_name': "Sho'rva",
          'quantity_label': '1',
        },
      ],
      'brands': <Object?>[
        <String, Object?>{
          'id': 4,
          'name': 'Burger Bro',
          'code': 'BB',
          'color': '#DC2626',
        },
      ],
      'waiting_seconds': 30,
    }, receivedAt: now);

    expect(t.lines.first.brand?.code, 'BB');
    expect(t.lines.first.brand?.argb, 0xFFDC2626);
    expect(t.lines.last.brand, isNull);
    expect(t.brands.single.name, 'Burger Bro');
    // copyWith keeps them
    expect(t.copyWith(status: 'preparing').brands.length, 1);
    expect(t.lines.first.copyWith(status: 'ready').brand?.code, 'BB');
  });

  test('a ticket without brands has none', () {
    final KitchenTicket t = KitchenTicket.fromJson(<String, Object?>{
      'uuid': '00000000-0000-4000-8000-000000000002',
      'items': <Object?>[],
    }, receivedAt: now);
    expect(t.brands, isEmpty);
  });
}
