import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:test/test.dart';

/// The offline view of bills that were partly paid online. The server has no `paying` status: a bill with payments
/// stays `open`/`printed` until it is covered and its sale receipt is issued, and it counts as taken (BillableOrder).
void main() {
  late SyncStore store;
  late OfflineOrderBook book;

  Map<String, Object?> line(int id, String qty, String total) =>
      <String, Object?>{
        'id': id,
        'product_name': 'Osh',
        'quantity': qty,
        'unit': 'piece',
        'unit_price': '100000.00',
        'total_price': total,
        'kitchen_status': 'sent',
        'fire_mode': 'now',
      };

  Map<String, Object?> billOf(
    String uuid,
    int number, {
    required String total,
    required String paid,
    required List<Map<String, Object?>> items,
    String status = 'printed',
  }) {
    final PosMoney due = PosMoney.parse(total) - PosMoney.parse(paid);
    return <String, Object?>{
      'uuid': uuid,
      'number': number,
      'status': status,
      'total': total,
      'paid_amount': paid,
      'due_amount': due.toApiString(),
      'items': items,
      'payments': <Object?>[
        if (PosMoney.parse(paid).isPositive)
          <String, Object?>{
            'uuid': 'p-$uuid',
            'tender': 'cash',
            'amount': paid,
            'status': 'paid',
          },
      ],
    };
  }

  Map<String, Object?> share(int id, String qty, String amount) =>
      <String, Object?>{
        'order_item_id': id,
        'product_name': 'Osh',
        'unit': 'piece',
        'quantity': qty,
        'amount': amount,
      };

  Future<void> copies({
    required List<Map<String, Object?>> items,
    required String total,
    required List<Map<String, Object?>> bills,
  }) async {
    await store.putDoc('order:o1', <String, Object?>{
      'uuid': 'o1',
      'status': 'preparing',
      'channel': 'pos_hall',
      'version': 7,
      'total_price': total,
      'subtotal': total,
      'discount': '0.00',
      'service_charge_amount': '0.00',
      'place_fee_amount': '0.00',
      'room_fee_amount': '0.00',
      'min_spend_topup_amount': '0.00',
      'charges': <String, Object?>{'waived': <Object?>[]},
      'rounds': 1,
      'items': items,
    });
    await store.putDoc('billing:o1', <String, Object?>{
      'order': <String, Object?>{
        'uuid': 'o1',
        'status': 'preparing',
        'version': 7,
        'total_price': total,
      },
      'bills': bills,
      'refunds': <Object?>[],
    });
  }

  PosBilling billing() => PosBilling.fromJson(PosJson(book.billing('o1')!));

  setUp(() async {
    store = await SyncStore.open(MemorySyncStorage());
    book = OfflineOrderBook(
      store: store,
      settings: () => const PosOfflineSettings(),
    );
  });

  test(
    'one bill offline after an online partial payment bills only what is left',
    () async {
      // 300 000 billed and printed, 100 000 paid in cash online; then a 100 000 round offline
      await copies(
        items: <Map<String, Object?>>[line(11, '3.000', '300000.00')],
        total: '300000.00',
        bills: <Map<String, Object?>>[
          billOf(
            'b1',
            1,
            total: '300000.00',
            paid: '100000.00',
            items: <Map<String, Object?>>[share(11, '3.000', '300000.00')],
          ),
        ],
      );
      await store.enqueue(SyncOpType.orderRound, <String, Object?>{
        'order': 'o1',
        'items': <Object?>[
          <String, Object?>{
            'line_id': 'l1',
            'variant_id': 'v',
            'quantity': '1',
            'unit_price': '100000.00',
            'unit': 'piece',
            'name': 'Osh',
          },
        ],
      }, staffId: 1);
      expect(book.state('o1')!.leftToBill, const PosMoney.som(100000));
      await store.enqueue(SyncOpType.billCreate, <String, Object?>{
        'order': 'o1',
        'bill': 'b2',
      }, staffId: 1);

      final PosBilling b = billing();
      final PosBill first = b.bills.firstWhere((PosBill x) => x.uuid == 'b1');
      final PosBill second = b.bills.firstWhere((PosBill x) => x.uuid == 'b2');
      expect(first.status, 'printed', reason: 'a bill with money on it stays');
      expect(first.dueAmount, const PosMoney.som(200000));
      expect(second.total, const PosMoney.som(100000));
      expect(second.dueAmount, const PosMoney.som(100000));
      expect(second.items.single.amount, const PosMoney.som(100000));

      // Both paid: the order closes (every bill covered)
      for (final (String uuid, String amount) in <(String, String)>[
        ('b1', '200000.00'),
        ('b2', '100000.00'),
      ]) {
        await store.enqueue(SyncOpType.billPay, <String, Object?>{
          'order': 'o1',
          'bill': uuid,
          'tenders': <Object?>[
            <String, Object?>{'tender': 'cash', 'amount': amount},
          ],
        }, staffId: 1);
      }
      expect(book.order('o1')!['status'], 'delivered');
      expect(billing().order.paidAmount, const PosMoney.som(400000));
    },
  );

  test(
    'a bill that already covers the order leaves nothing to bill again',
    () async {
      await copies(
        items: <Map<String, Object?>>[line(11, '3.000', '300000.00')],
        total: '300000.00',
        bills: <Map<String, Object?>>[
          billOf(
            'b1',
            1,
            total: '300000.00',
            paid: '100000.00',
            items: <Map<String, Object?>>[share(11, '3.000', '300000.00')],
          ),
        ],
      );
      expect(book.state('o1')!.leftToBill, PosMoney.zero);
    },
  );

  test('a bill covered online (its receipt not issued yet) counts as settled: paying the other one closes the order', () async {
    await copies(
      items: <Map<String, Object?>>[
        line(11, '2.000', '200000.00'),
        line(12, '1.000', '100000.00'),
      ],
      total: '300000.00',
      bills: <Map<String, Object?>>[
        billOf(
          'b1',
          1,
          total: '200000.00',
          paid: '200000.00',
          items: <Map<String, Object?>>[share(11, '2.000', '200000.00')],
        ),
        billOf(
          'b2',
          2,
          total: '100000.00',
          paid: '0.00',
          status: 'open',
          items: <Map<String, Object?>>[share(12, '1.000', '100000.00')],
        ),
      ],
    );
    await store.enqueue(SyncOpType.billPay, <String, Object?>{
      'order': 'o1',
      'bill': 'b2',
      'tenders': <Object?>[
        <String, Object?>{'tender': 'card', 'amount': '100000.00'},
      ],
    }, staffId: 1);
    expect(book.order('o1')!['status'], 'delivered');
    final PosBill paid = billing().bills.firstWhere(
      (PosBill x) => x.uuid == 'b2',
    );
    expect(paid.dueAmount, PosMoney.zero);
    expect(paid.status, isNot('paying'), reason: 'the server never says so');
  });

  test('a part-paid bill does not close the order', () async {
    await copies(
      items: <Map<String, Object?>>[line(11, '3.000', '300000.00')],
      total: '300000.00',
      bills: <Map<String, Object?>>[
        billOf(
          'b1',
          1,
          total: '300000.00',
          paid: '0.00',
          items: <Map<String, Object?>>[share(11, '3.000', '300000.00')],
        ),
      ],
    );
    await store.enqueue(SyncOpType.billPay, <String, Object?>{
      'order': 'o1',
      'bill': 'b1',
      'tenders': <Object?>[
        <String, Object?>{'tender': 'cash', 'amount': '100000.00'},
      ],
    }, staffId: 1);
    expect(book.order('o1')!['status'], 'preparing');
    expect(billing().bills.single.dueAmount, const PosMoney.som(200000));
  });

  test(
    'P6-16: an offline card payment keeps the RRN and the last four digits',
    () async {
      await copies(
        items: <Map<String, Object?>>[line(11, '1.000', '100000.00')],
        total: '100000.00',
        bills: <Map<String, Object?>>[
          billOf(
            'b1',
            1,
            total: '100000.00',
            paid: '0.00',
            items: <Map<String, Object?>>[share(11, '1.000', '100000.00')],
          ),
        ],
      );
      await store.enqueue(SyncOpType.billPay, <String, Object?>{
        'order': 'o1',
        'bill': 'b1',
        'tenders': <Object?>[
          const PosTenderInput.card(
            PosMoney.som(100000),
            cardType: 'humo',
            transactionId: '123456789012',
            cardLast4: '4321',
          ).toJson(),
        ],
      }, staffId: 1);
      final PosPayment p = billing().bills.single.payments.single;
      expect(p.transactionId, '123456789012');
      expect(p.cardLast4, '4321');
    },
  );
}
