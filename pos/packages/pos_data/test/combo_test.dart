import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:test/test.dart';

import 'offline_test.dart' show OfflineHarness;

import 'package:pos_data/testing.dart';

/// P6-31: the combo in pos_data — the catalog's `combo`, the order line's `combo_parts`, the offline pricing (the same
/// rules and sums as the backend's Combos::resolve, the fixed cases of tests/Feature/PosFastFood/ComboTest.php) and an
/// offline combo sale queued with its picks and delta.
void main() {
  // The backend's catalog `combo` of "Lavash kombo" (45 000): Asosiy (lavash default, tovuq +5 000, modifiers allowed),
  // Ichimlik (pepsi default with its label, fanta, ayran +2 000), Sous (0–2)
  Map<String, Object?> option(
    int id,
    String name,
    int variantId, {
    String delta = '0.00',
    bool isDefault = false,
    String? label,
    bool available = true,
    List<Object?> modifiers = const <Object?>[],
  }) => <String, Object?>{
    'id': id,
    'uuid': '00000000-0000-4000-9000-${id.toString().padLeft(12, '0')}',
    'variant_id': variantId,
    'variant_uuid': FakePosState.variantUuid(variantId),
    'product_id': variantId,
    'name': name,
    'variant_name': null,
    'quantity': 1,
    'price_delta': delta,
    'modifiers': modifiers,
    'is_default': isDefault,
    'label': label,
    'receipt_label': label ?? name,
    'is_available': available,
  };
  String uuidOf(int id) =>
      '00000000-0000-4000-9000-${id.toString().padLeft(12, '0')}';

  Map<String, Object?> comboJson({bool pepsiAvailable = true}) =>
      <String, Object?>{
        'id': 1,
        'uuid': 'combo-1',
        'product_id': 20,
        'slots': <Object?>[
          <String, Object?>{
            'id': 1,
            'name': 'Asosiy',
            'min_picks': 1,
            'max_picks': 1,
            'allow_modifiers': true,
            'options': <Object?>[
              option(
                1,
                'Lavash',
                23,
                isDefault: true,
                modifiers: <Object?>[
                  <String, Object?>{
                    'id': 5,
                    'uuid': '00000000-0000-4000-8000-0000000000b1',
                    'name': "Mol go'shtli",
                    'quantity': 1,
                    'price_delta': '0.00',
                  },
                ],
              ),
              option(2, 'Tovuq lavash', 24, delta: '5000.00'),
            ],
          },
          <String, Object?>{
            'id': 2,
            'name': 'Ichimlik',
            'min_picks': 1,
            'max_picks': 1,
            'options': <Object?>[
              option(
                3,
                'Pepsi',
                21,
                isDefault: true,
                label: 'Ichimlik — 0,4 l Pepsi',
                available: pepsiAvailable,
              ),
              option(4, 'Fanta', 22),
              option(5, 'Ayran', 25, delta: '2000.00'),
            ],
          },
          <String, Object?>{
            'id': 3,
            'name': 'Sous',
            'min_picks': 0,
            'max_picks': 2,
            'options': <Object?>[option(6, 'Achchiq sous', 26)],
          },
        ],
      };

  group('pricing (the backend fixed cases)', () {
    final PosCombo combo = PosCombo.maybe(comboJson())!;

    test('nothing picked: the defaults, 45 000', () {
      final PosComboResolution r = PosComboPricing.resolve(
        combo,
        const <PosComboPick>[],
      );
      expect(r.parts.map((PosComboPart p) => p.name), <String>[
        'Lavash',
        'Pepsi',
      ]);
      expect(r.delta, PosMoney.zero);
      expect(r.parts.map((PosComboPart p) => p.label), <String>[
        "Asosiy — Lavash, Mol go'shtli",
        'Ichimlik — 0,4 l Pepsi',
      ]);
      expect(
        PosComboPricing.lineTotal(
          PosMoney.parse('45000'),
          PosMoney.zero,
          r,
          PosQuantity.one,
        ),
        PosMoney.parse('45000'),
      );
    });

    test(
      'tovuq +5 000, ayran +2 000, two sauces: 52 000 a combo, 104 000 for two',
      () {
        final PosComboResolution r = PosComboPricing.resolve(
          combo,
          <PosComboPick>[
            PosComboPick(uuidOf(2)),
            PosComboPick(uuidOf(5)),
            PosComboPick(uuidOf(6), quantity: 2),
          ],
        );
        expect(r.delta, PosMoney.parse('7000'));
        expect(r.parts.map((PosComboPart p) => p.quantity), <int>[1, 1, 2]);
        expect(
          PosComboPricing.lineTotal(
            PosMoney.parse('45000'),
            PosMoney.zero,
            r,
            PosQuantity.parse('2'),
          ),
          PosMoney.parse('104000'),
        );
      },
    );

    test(
      'an added modifier is charged, a dropped preset gives nothing back',
      () {
        PosComboResolution pick(Map<String, int> mods) =>
            PosComboPricing.resolve(
              combo,
              <PosComboPick>[PosComboPick(uuidOf(1), modifiers: mods)],
              modifierInfo: (String uuid) => uuid.endsWith('b2')
                  ? (
                      name: 'Pishloq',
                      priceDelta: PosMoney.parse('3000'),
                      groupName: "Go'sht",
                    )
                  : null,
            );
        expect(
          pick(<String, int>{
            '00000000-0000-4000-8000-0000000000b1': 1,
            '00000000-0000-4000-8000-0000000000b2': 1,
          }).delta,
          PosMoney.parse('3000'),
        );
        expect(pick(<String, int>{}).delta, PosMoney.zero);
      },
    );

    test(
      'the slot rules and a stale option are refused with the server reasons',
      () {
        String reason(List<PosComboPick> picks, {PosCombo? on}) {
          try {
            PosComboPricing.resolve(on ?? combo, picks);
          } on PosComboRejected catch (e) {
            return e.reason;
          }
          return 'ok';
        }

        expect(
          reason(<PosComboPick>[
            PosComboPick(uuidOf(3)),
            PosComboPick(uuidOf(4)),
          ]),
          'combo_slot_limit',
        );
        expect(
          reason(<PosComboPick>[PosComboPick(uuidOf(6), quantity: 3)]),
          'combo_slot_limit',
        );
        expect(
          reason(<PosComboPick>[const PosComboPick('nope')]),
          'combo_option_invalid',
        );
        final PosCombo gone = PosCombo.maybe(comboJson(pepsiAvailable: false))!;
        expect(
          reason(<PosComboPick>[PosComboPick(uuidOf(3))], on: gone),
          'combo_option_unavailable',
        );
        // The default drink is gone: a required slot left out has no default to take
        expect(reason(const <PosComboPick>[], on: gone), 'combo_slot_required');
        expect(gone.isOpen(), isTrue);
      },
    );
  });

  test('the catalog product and the order line carry the combo', () {
    final PosProduct p = PosProduct.fromJson(
      PosJson(<String, Object?>{
        'id': 20,
        'name': 'Lavash kombo',
        'variants': <Object?>[],
        'combo': comboJson(),
      }),
    );
    expect(p.isCombo, isTrue);
    expect(p.combo!.slots.map((PosComboSlot s) => s.isRequired), <bool>[
      true,
      true,
      false,
    ]);
    expect(p.combo!.slots[1].defaultOption!.name, 'Pepsi');

    final PosOrderItem item = PosOrderItem.fromJson(
      const PosJson(<String, Object?>{
        'id': 9,
        'product_name': 'Lavash kombo',
        'quantity': 1,
        'unit_price': '45000.00',
        'modifiers_total': '2000.00',
        'total_price': '47000.00',
        'kitchen_status': 'sent',
        'combo_parts': <Object?>[
          <String, Object?>{
            'slot_name': 'Ichimlik',
            'name': 'Ayran',
            'quantity': 1,
            'price_delta': '2000.00',
            'label': 'Ichimlik — Ayran',
            'item_id': 11,
          },
        ],
      }),
    );
    expect(item.isCombo, isTrue);
    expect(item.comboParts!.single.label, 'Ichimlik — Ayran');
    expect(item.comboParts!.single.itemId, 11);
    expect(
      const PosLineInput(variantUuid: 'v', quantity: PosQuantity.one).toJson(),
      isNot(contains('combo')),
    );
    expect(
      PosLineInput(
        variantUuid: 'v',
        quantity: PosQuantity.one,
        combo: <PosComboPick>[PosComboPick(uuidOf(5))],
      ).toJson()['combo'],
      <Object?>[
        <String, Object?>{'option_id': uuidOf(5)},
      ],
    );
  });

  group('offline', () {
    late OfflineHarness h;

    setUp(() async {
      h = await OfflineHarness.create();
      FakeCashierBackend(h.state);
      FakeSyncBackend(h.state);
      h.state.openShift();
      // The menu: the combo and its dishes
      for (final (int id, String name, String price) in <(int, String, String)>[
        (21, 'Pepsi', '8000.00'),
        (22, 'Fanta', '8000.00'),
        (23, 'Lavash', '32000.00'),
        (24, 'Tovuq lavash', '30000.00'),
        (25, 'Ayran', '6000.00'),
        (26, 'Achchiq sous', '2000.00'),
      ]) {
        h.state.products.add(h.state.sampleProduct(id, 2, name, price));
      }
      h.state.products.add(<String, Object?>{
        ...h.state.sampleProduct(20, 2, 'Lavash kombo', '45000.00'),
        'combo': comboJson(),
      });
      await h.signIn();
    });

    tearDown(() => h.pos.dispose());

    test('a combo sold offline is priced as the server prices it and queued with its picks and delta', () async {
      await h.warmUp();
      await h.goOffline();

      final PosOrder order = await h.pos.orders.open(
        channel: PosChannel.takeaway,
        items: <PosLineInput>[
          PosLineInput(
            variantUuid: FakePosState.variantUuid(20),
            quantity: PosQuantity.parse('2'),
            combo: <PosComboPick>[PosComboPick(uuidOf(5))],
          ),
        ],
        send: true,
      );
      final PosOrderItem line = order.items.single;
      expect(line.modifiersTotal, PosMoney.parse('2000'));
      expect(line.totalPrice, PosMoney.parse('94000'));
      expect(line.isCombo, isTrue);
      expect(line.comboParts!.map((PosComboPart p) => p.name), <String>[
        'Lavash',
        'Ayran',
      ]);

      final SyncOperation op = h.offline.store.pending.single;
      final Map<String, Object?> queued =
          ((op.payload['items']! as List<Object?>).single!
                  as Map<Object?, Object?>)
              .cast<String, Object?>();
      expect(queued['combo'], <Object?>[
        <String, Object?>{'option_id': uuidOf(5)},
      ]);
      expect(queued['combo_delta'], '2000.00');
      expect(queued['unit_price'], '45000.00');

      // A pick the server would refuse (two drinks in a one-drink slot) is not sold offline either
      await expectLater(
        h.pos.orders.open(
          channel: PosChannel.takeaway,
          items: <PosLineInput>[
            PosLineInput(
              variantUuid: FakePosState.variantUuid(20),
              quantity: PosQuantity.one,
              combo: <PosComboPick>[
                PosComboPick(uuidOf(3)),
                PosComboPick(uuidOf(4)),
              ],
            ),
          ],
        ),
        throwsA(isA<PosOfflineUnavailable>()),
      );
    });
  });
}
