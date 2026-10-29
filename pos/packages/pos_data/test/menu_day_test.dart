import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P6-15: bugungi menyu, kompleks, keyingi partiya va hisobdan chiqarish — bootstrap bloklari, katalog maydonlari,
/// buyurtma qatoridagi tarkib, `POST /owner/batches/{id}/write-off` va X/Z dagi `batch_writeoffs`.
void main() {
  group('models', () {
    test('the bootstrap blocks are read; an old server has none', () async {
      final Harness h = Harness();
      FakeCashierBackend(h.state);
      await h.signIn();
      final PosBootstrap old = await h.pos.session.loadBootstrap();
      expect(old.dailyMenu, isNull);
      expect(old.nextBatches, isEmpty);
      expect(old.mealSets, isEmpty);
      expect(old.setPartsOf(3), isNull);

      h.state.bootstrapExtras.addAll(<String, Object?>{
        'daily_menu': <String, Object?>{
          'business_date': '2026-10-22',
          'enforced': true,
          'set': true,
          'product_ids': <int>[1, 3],
        },
        'next_batches': <Object?>[
          <String, Object?>{
            'id': 9,
            'uuid': 'plan-9',
            'product_id': 3,
            'product_variant_id': null,
            'planned_at': '2026-10-22T07:30:00Z',
            'time': '12:30',
            'quantity': '20.000',
            'label': 'Ikkinchi qozon',
          },
        ],
        'meal_sets': <Object?>[
          <String, Object?>{
            'product_id': 7,
            'parts': <Object?>[
              <String, Object?>{
                'variant_id': 1,
                'variant_uuid': 'v-1',
                'product_id': 1,
                'name': 'Mastava',
                'variant_name': null,
                'quantity': '1.000',
                'unit': 'piece',
                'label': 'Birinchi',
              },
              <String, Object?>{
                'variant_id': 2,
                'variant_uuid': 'v-2',
                'product_id': 2,
                'name': 'Non',
                'variant_name': 'kichik',
                'quantity': '2.000',
                'unit': 'piece',
                'label': null,
              },
            ],
          },
        ],
      });
      final PosBootstrap b = await h.pos.session.loadBootstrap();
      final PosDailyMenu menu = b.dailyMenu!;
      expect(menu.enforced && menu.set, isTrue);
      expect(menu.missing, isFalse);
      expect(menu.contains(3), isTrue);
      expect(menu.contains(2), isFalse);
      expect(b.nextBatches.single.next.text, 'Keyingi partiya 12:30');
      expect(b.nextBatches.single.next.label, 'Ikkinchi qozon');
      expect(b.nextBatches.single.next.quantity, const PosQuantity.whole(20));
      expect(b.setPartsOf(7)!.map((PosSetPart p) => p.display), <String>[
        'Birinchi: Mastava',
        '2 Non (kichik)',
      ]);

      h.state.bootstrapExtras['daily_menu'] = <String, Object?>{
        'business_date': '2026-10-22',
        'enforced': true,
        'set': false,
        'product_ids': <int>[],
      };
      expect((await h.pos.session.loadBootstrap()).dailyMenu!.missing, isTrue);
    });

    test(
      'the catalog: off-menu dishes, the next batch and the kompleks parts',
      () {
        final PosCatalog catalog = PosCatalog.fromJson(
          PosJson(<String, Object?>{
            'channel': 'pos_hall',
            'menu_version': 'v1',
            'branch': <String, Object?>{'id': 1},
            'categories': <Object?>[],
            'products': <Object?>[
              <String, Object?>{
                'id': 1,
                'uuid': 'p1',
                'name': 'Mastava',
                'unavailable_reason': 'stopped',
                'is_available': false,
                'next_batch': null,
                'variants': <Object?>[_variant(1, 1)],
              },
              <String, Object?>{
                'id': 2,
                'uuid': 'p2',
                'name': 'Osh',
                'unavailable_reason': 'sold_out',
                'is_available': false,
                'next_batch': <String, Object?>{
                  'time': '12:30',
                  'planned_at': '2026-10-22T07:30:00Z',
                  'quantity': '15.000',
                  'label': null,
                },
                'variants': <Object?>[
                  _variant(2, 2, next: <String, Object?>{'time': '12:30'}),
                ],
              },
              <String, Object?>{
                'id': 3,
                'uuid': 'p3',
                'name': 'Kompleks',
                'is_available': true,
                'set_parts': <Object?>[
                  <String, Object?>{
                    'variant_id': 1,
                    'product_id': 1,
                    'name': 'Mastava',
                    'quantity': '1.000',
                    'unit': 'piece',
                    'label': 'Birinchi',
                  },
                ],
                'variants': <Object?>[_variant(3, 3)],
              },
            ],
            'availability': <String, Object?>{
              'stopped': <String, Object?>{
                'products': <int>[1],
                'variants': <int>[],
                'modifiers': <int>[],
              },
              'sold_out': <String, Object?>{
                'variants': <int>[2],
              },
              'stop_list': <Object?>[
                <String, Object?>{
                  'id': null,
                  'type': 'product',
                  'subject_id': 1,
                  'reason': "Bugungi menyuda yo'q",
                  'until': null,
                },
              ],
              'batches': <Object?>[],
              'next_batches': <Object?>[],
            },
          }),
        );
        final PosProduct mastava = catalog.product(1)!;
        final PosProduct osh = catalog.product(2)!;
        final PosProduct set = catalog.product(3)!;

        expect(catalog.isOffMenu(mastava), isTrue);
        expect(
          catalog.nextBatchOf(mastava),
          isNull,
          reason: 'off the menu, not coming back today',
        );
        expect(catalog.isOffMenu(osh), isFalse);
        expect(catalog.nextBatchOf(osh)!.text, 'Keyingi partiya 12:30');
        expect(osh.nextBatch!.quantity, const PosQuantity.whole(15));
        expect(
          catalog.nextBatchOf(set),
          isNull,
          reason: 'on sale: no next batch',
        );
        expect(set.isSet, isTrue);
        expect(set.setParts!.single.display, 'Birinchi: Mastava');
        expect(osh.isSet, isFalse);

        // The fresh availability (refreshed without a new menu version) wins over the catalog's copy
        final PosCatalog fresh = catalog.withAvailability(
          PosAvailability.fromJson(
            const PosJson(<String, Object?>{
              'sold_out': <String, Object?>{
                'variants': <int>[2],
              },
              'next_batches': <Object?>[
                <String, Object?>{
                  'id': 4,
                  'product_id': 2,
                  'time': '13:15',
                  'planned_at': '2026-10-22T08:15:00Z',
                },
              ],
            }),
          ),
        );
        expect(fresh.nextBatchOf(fresh.product(2)!)!.time, '13:15');
        expect(fresh.isOffMenu(fresh.product(1)!), isFalse);
      },
    );

    test('an order line keeps the kompleks parts it was sold with', () {
      final PosOrderItem item = PosOrderItem.fromJson(
        const PosJson(<String, Object?>{
          'id': 5,
          'product_name': 'Kompleks',
          'quantity': '1.000',
          'kitchen_status': 'pending',
          'set_parts': <Object?>[
            <String, Object?>{
              'name': 'Mastava',
              'quantity': '1.000',
              'label': 'Birinchi',
            },
            <String, Object?>{'name': 'Salat', 'quantity': '1.000'},
          ],
        }),
      );
      expect(item.setParts!.map((PosSetPart p) => p.display), <String>[
        'Birinchi: Mastava',
        'Salat',
      ]);
      expect(
        PosOrderItem.fromJson(
          const PosJson(<String, Object?>{
            'id': 6,
            'product_name': 'Osh',
            'quantity': '1.000',
            'kitchen_status': 'pending',
          }),
        ).setParts,
        isNull,
      );
    });

    test(
      'write-off reasons match the server and only "other" needs a note',
      () {
        expect(
          PosWriteOffReason.values.map((PosWriteOffReason r) => r.wire),
          <String>[
            'unsold',
            'spoiled',
            'staff_meal',
            'dropped',
            'expired',
            'other',
          ],
        );
        expect(PosWriteOffReason.other.needsNote, isTrue);
        expect(PosWriteOffReason.unsold.needsNote, isFalse);
        expect(
          PosWriteOffReason.fromWire('spoiled'),
          PosWriteOffReason.spoiled,
        );
        expect(PosWriteOffReason.fromWire('x'), isNull);
      },
    );
  });

  group('write-off', () {
    late Harness h;
    late FakeCashierBackend backend;

    setUp(() {
      h = Harness();
      backend = FakeCashierBackend(h.state);
      h.state.format = <String, Object?>{
        'profile': 'osh_markazi',
        'profile_label': 'Osh markazi',
        'mode': 'counter',
        'first_screen': 'quick_counter',
        'modules': <String>['batches', 'batch_writeoff'],
        'locks': <String, Object?>{'service_charge_zero': false},
      };
    });

    test('a cashier lacks batches.manage: the server asks for a manager\'s approval, a token writes it off once', () async {
      await h.signIn();
      h.state.openShift();
      Object? error;
      try {
        await h.pos.batches.writeOff(
          1,
          quantity: const PosQuantity.whole(2),
          reason: PosWriteOffReason.unsold,
        );
      } on Object catch (e) {
        error = e;
      }
      expect(error, isA<PosApprovalRequired>());
      expect((error! as PosApprovalRequired).permission, PosPermissions.batchesManage);
      expect(PosPermissions.approvable, contains(PosPermissions.batchesManage));
      expect(PosPermissions.label(PosPermissions.batchesManage), 'Taomni hisobdan chiqarish');
      expect(backend.batchWriteOffs, isEmpty);

      // The manager types the PIN on the till; the action goes again with the token (runWithApproval)
      final PosBatchWriteOff done = await runWithApproval<PosBatchWriteOff>(
        (String? token) => h.pos.batches.writeOff(
          1,
          quantity: const PosQuantity.whole(2),
          reason: PosWriteOffReason.unsold,
          approvalToken: token,
        ),
        approve: (String permission, PosException cause) =>
            h.pos.approvals.request(permission: permission, userId: 2, pin: h.state.pins[2]!),
      );
      expect(Harness.bodyOf(h.last)['approval_token'], isNotNull);
      expect(done.approvedBy, 2);
      expect(done.batch!.quantityLeft, const PosQuantity.whole(6));
      expect(backend.batchWriteOffs, hasLength(1));

      // The token is spent: the same token again is refused
      await expectLater(
        h.pos.batches.writeOff(
          1,
          quantity: PosQuantity.one,
          reason: PosWriteOffReason.unsold,
          approvalToken: '${Harness.bodyOf(h.last)['approval_token']}',
        ),
        throwsA(isA<PosApprovalRejected>()),
      );
    });

    test('the manager writes off with an Idempotency-Key and it lands on the shift report', () async {
      await h.signIn();

      await h.client.auth.signOutStaff();
      await h.client.auth.signInStaff(userId: 2, pin: h.state.pins[2]!);
      h.state.openShift();

      final PosBatchWriteOff done = await h.pos.batches.writeOff(
        1,
        quantity: const PosQuantity.whole(3),
        reason: PosWriteOffReason.spoiled,
        note: ' qozon kuydi ',
        idempotencyKey: 'key-1',
      );
      expect(h.last.method, 'POST');
      expect(h.last.uri.path, '/api/v1/owner/batches/1/write-off');
      expect(h.last.headers['Idempotency-Key'], 'key-1');
      expect(Harness.bodyOf(h.last), <String, Object?>{
        'quantity': '3.000',
        'reason': 'spoiled',
        'note': 'qozon kuydi',
      });
      expect(done.quantity, const PosQuantity.whole(3));
      expect(done.batch!.quantityLeft, const PosQuantity.whole(5));
      expect(done.shiftId, isNotNull);

      // Same key again: the same answer, nothing written twice
      await h.pos.batches.writeOff(
        1,
        quantity: const PosQuantity.whole(3),
        reason: PosWriteOffReason.spoiled,
        note: 'qozon kuydi',
        idempotencyKey: 'key-1',
      );
      expect(backend.batchWriteOffs, hasLength(1));

      final PosShiftWriteOffs block =
          (await h.pos.shifts.current()).report!.batchWriteoffs;
      expect(block.count, 1);
      expect(block.items.single.productName, isNotNull);
      expect(block.items.single.reasonText, 'Buzildi');
      expect(block.items.single.quantityText, '3 porsiya');
    });

    test(
      'more than what is left and "other" without a note are refused',
      () async {
        await h.signIn(userId: 2);
        await expectLater(
          h.pos.batches.writeOff(
            1,
            quantity: const PosQuantity.whole(99),
            reason: PosWriteOffReason.unsold,
          ),
          throwsA(isA<PosValidationException>()),
        );
        await expectLater(
          h.pos.batches.writeOff(
            1,
            quantity: PosQuantity.one,
            reason: PosWriteOffReason.other,
          ),
          throwsA(isA<PosValidationException>()),
        );
        expect(backend.batchWriteOffs, isEmpty);
      },
    );

    test('with the module off the server answers module_disabled', () async {
      h.state.format = <String, Object?>{
        'profile': 'choyxona',
        'profile_label': 'Choyxona',
        'mode': 'hybrid',
        'first_screen': 'hall_map',
        'modules': <String>['batches'],
        'locks': <String, Object?>{'service_charge_zero': false},
      };
      await h.signIn(userId: 2);
      await expectLater(
        h.pos.batches.writeOff(
          1,
          quantity: PosQuantity.one,
          reason: PosWriteOffReason.unsold,
        ),
        throwsA(
          isA<PosModuleDisabled>().having(
            (PosModuleDisabled e) => e.module,
            'module',
            'batch_writeoff',
          ),
        ),
      );
    });
  });
}

Map<String, Object?> _variant(
  int id,
  int productId, {
  Map<String, Object?>? next,
}) => <String, Object?>{
  'id': id,
  'uuid': 'v$id',
  'product_id': productId,
  'name': '',
  'price': '25000.00',
  'unit': 'piece',
  'next_batch': next,
};
