import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_cashier/src/printing/offline_summary.dart';

import 'support/harness.dart';
import 'support/p22.dart';

/// M5 S4-d: the printed offline summary: the pure builder, the slip layout and the "Zaxira" card button.
int _seq = 0;

SyncOperation _open(String order, String local) => SyncOperation(
  opId: 'op-${_seq++}',
  seq: _seq,
  type: SyncOpType.orderOpen,
  occurredAt: DateTime.utc(2026, 11, 21, 10),
  staffId: 1,
  payload: <String, Object?>{'order': order, 'local_number': local},
);

SyncOperation _pay(String order, List<Map<String, Object?>> tenders) =>
    SyncOperation(
      opId: 'op-${_seq++}',
      seq: _seq,
      type: SyncOpType.billPay,
      occurredAt: DateTime.utc(2026, 11, 21, 10, 5),
      staffId: 1,
      payload: <String, Object?>{
        'order': order,
        'bill': 'bill-$order',
        'tenders': tenders,
      },
    );

Map<String, Object?> _t(String tender, String amount) => <String, Object?>{
  'tender': tender,
  'amount': amount,
};

final DateTime _since = DateTime.utc(2026, 11, 21, 9, 30);
final DateTime _at = DateTime.utc(2026, 11, 21, 12);

OfflineSummary _build(
  List<SyncOperation> pending, [
  List<PosOrphanQueue> orphans = const <PosOrphanQueue>[],
]) => buildOfflineSummary(
  pending: pending,
  orphans: orphans,
  outageStart: _since,
  printedAt: _at,
);

void main() {
  setUp(() => _seq = 0);

  group('buildOfflineSummary', () {
    test('mixed cash and card orders', () {
      final OfflineSummary s = _build(<SyncOperation>[
        _open('o1', 'K-1'),
        _pay('o1', <Map<String, Object?>>[_t('cash', '45000.00')]),
        _open('o2', 'K-2'),
        _pay('o2', <Map<String, Object?>>[_t('card', '120000.00')]),
        _open('o3', 'K-3'),
      ]);
      expect(s.orders, 3);
      expect(s.payments, 2);
      expect(s.cash, const PosMoney.som(45000));
      expect(s.card, const PosMoney.som(120000));
      expect(s.other, PosMoney.zero);
      expect(s.outageStart, _since);
      expect(
        <String>[for (final OfflineSummaryOrder o in s.lines) o.number!],
        <String>['K-1', 'K-2', 'K-3'],
      );
      expect(s.lines[0].paid, const PosMoney.som(45000));
      expect(s.lines[2].paid, isNull, reason: 'opened, not paid yet');
    });

    test('split tenders add up per type and per order', () {
      final OfflineSummary s = _build(<SyncOperation>[
        _open('o1', 'K-7'),
        _pay('o1', <Map<String, Object?>>[
          _t('cash', '30000.00'),
          _t('card', '70000.00'),
        ]),
      ]);
      expect(s.payments, 1);
      expect(s.cash, const PosMoney.som(30000));
      expect(s.card, const PosMoney.som(70000));
      expect(s.lines.single.paid, const PosMoney.som(100000));
    });

    test('two payments for one order are one line with the sum', () {
      final OfflineSummary s = _build(<SyncOperation>[
        _open('o1', 'K-1'),
        _pay('o1', <Map<String, Object?>>[_t('cash', '10000.00')]),
        _pay('o1', <Map<String, Object?>>[_t('card', '5000.00')]),
      ]);
      expect(s.payments, 2);
      expect(s.lines, hasLength(1));
      expect(s.lines.single.paid, const PosMoney.som(15000));
    });

    test('orphan queues are included, and an operation is counted once', () {
      final SyncOperation shared = _open('o1', 'K-1');
      final OfflineSummary s = _build(
        <SyncOperation>[
          shared,
          _pay('o1', <Map<String, Object?>>[_t('cash', '20000.00')]),
        ],
        <PosOrphanQueue>[
          PosOrphanQueue(
            key: 'orphan_1',
            setAsideAt: _since,
            operations: <SyncOperation>[
              shared,
              _open('o9', 'K-9'),
              _pay('o9', <Map<String, Object?>>[_t('card', '8000.00')]),
            ],
          ),
        ],
      );
      expect(s.orders, 2);
      expect(s.payments, 2);
      expect(s.cash, const PosMoney.som(20000));
      expect(s.card, const PosMoney.som(8000));
      expect(
        <String>[for (final OfflineSummaryOrder o in s.lines) o.number!],
        <String>['K-1', 'K-9'],
      );
    });

    test('nothing queued', () {
      final OfflineSummary s = _build(const <SyncOperation>[]);
      expect(s.isEmpty, isTrue);
      expect(s.orders, 0);
      expect(s.payments, 0);
      expect(s.cash, PosMoney.zero);
      expect(s.card, PosMoney.zero);
      expect(s.lines, isEmpty);
    });

    test('amounts with tiyin are exact (no floating point)', () {
      final OfflineSummary s = _build(<SyncOperation>[
        _open('o1', 'K-1'),
        _pay('o1', <Map<String, Object?>>[_t('cash', '0.10')]),
        _open('o2', 'K-2'),
        _pay('o2', <Map<String, Object?>>[
          _t('cash', '0.20'),
          _t('card', '12345.67'),
        ]),
      ]);
      expect(s.cash.toApiString(), '0.30');
      expect(s.card.toApiString(), '12345.67');
      expect(s.lines[1].paid!.toApiString(), '12345.87');
    });

    test('tips (tip_amount, tip_from_change) are their own totals, so the '
        'drawer and the bank terminal can be matched', () {
      final OfflineSummary s = _build(<SyncOperation>[
        _open('o1', 'K-1'),
        _pay('o1', <Map<String, Object?>>[
          <String, Object?>{
            'tender': 'cash',
            'amount': '45000.00',
            'tendered_amount': '60000.00',
            'tip_amount': '5000.00',
          },
        ]),
        _open('o2', 'K-2'),
        _pay('o2', <Map<String, Object?>>[
          <String, Object?>{
            'tender': 'card',
            'amount': '100000.00',
            'tip_amount': '10000.50',
          },
        ]),
        _open('o3', 'K-3'),
        // The whole change left as the tip (no tip_amount given)
        _pay('o3', <Map<String, Object?>>[
          <String, Object?>{
            'tender': 'cash',
            'amount': '18000.00',
            'tendered_amount': '20000.00',
            'tip_from_change': true,
          },
        ]),
        _pay('o4', <Map<String, Object?>>[
          <String, Object?>{
            'tender': 'qr',
            'amount': '7000.00',
            'tip_amount': '1000.00',
          },
        ]),
      ]);
      // The bill amounts, never with the tip inside
      expect(s.cash, const PosMoney.som(63000));
      expect(s.card, const PosMoney.som(100000));
      expect(s.other, const PosMoney.som(7000));
      expect(s.tipsCash, const PosMoney.som(7000));
      expect(s.tipsCard.toApiString(), '10000.50');
      expect(s.tipsOther, const PosMoney.som(1000));
      expect(s.tips.toApiString(), '18000.50');
      // What the drawer and the terminal hold
      expect(s.drawerCash, const PosMoney.som(70000));
      expect(s.terminalCard.toApiString(), '110000.50');
      expect(s.lines[0].paid, const PosMoney.som(45000));

      final String t = <String>[
        for (final PrintLine p in layoutOfflineSummary(
          s,
          const ReceiptHeader(storeName: 'Bog\'bon'),
          PaperWidth.mm58,
        ).lines)
          if (p is PrintText) p.text,
      ].join('\n');
      expect(t, contains('Choy puli:'));
      expect(t, contains('18 000.50'));
      expect(t, contains('Kassadagi naqd:'));
      expect(t, contains(paperAmount(const PosMoney.som(70000))));
      expect(t, contains('Terminal (karta):'));
      expect(t, contains('110 000.50'));
    });

    test('no tips: no tip lines on the slip', () {
      final OfflineSummary s = _build(<SyncOperation>[
        _open('o1', 'K-1'),
        _pay('o1', <Map<String, Object?>>[_t('cash', '45000.00')]),
      ]);
      expect(s.tips, PosMoney.zero);
      final String t = <String>[
        for (final PrintLine p in layoutOfflineSummary(
          s,
          const ReceiptHeader(storeName: 'Bog\'bon'),
          PaperWidth.mm58,
        ).lines)
          if (p is PrintText) p.text,
      ].join('\n');
      expect(t, isNot(contains('Choy puli')));
    });

    test('QR and other tenders are kept apart from cash and card', () {
      final OfflineSummary s = _build(<SyncOperation>[
        _pay('o1', <Map<String, Object?>>[_t('qr', '9000.50')]),
      ]);
      expect(s.cash, PosMoney.zero);
      expect(s.card, PosMoney.zero);
      expect(s.other.toApiString(), '9000.50');
      expect(s.lines.single.number, isNull, reason: 'order opened online');
    });
  });

  group('layoutOfflineSummary', () {
    String text(PrintLayout l) => <String>[
      for (final PrintLine p in l.lines)
        if (p is PrintText) p.text,
    ].join('\n');

    test('slip texts in Uzbek and the lines of the queue', () {
      final OfflineSummary s = _build(<SyncOperation>[
        _open('o1', 'K-1'),
        _pay('o1', <Map<String, Object?>>[_t('cash', '45000.50')]),
        _open('o2', 'K-2'),
      ]);
      final String t = text(
        layoutOfflineSummary(
          s,
          const ReceiptHeader(storeName: 'Bog\'bon'),
          PaperWidth.mm58,
        ),
      );
      expect(t, contains('QISQA HISOBOT'));
      expect(t, contains('Internetsiz rejim'));
      expect(t, contains('Buyurtmalar:'));
      expect(t, contains("To'lovlar:"));
      expect(t, contains('Naqd:'));
      expect(t, contains('Karta:'));
      expect(t, contains('K-1'));
      expect(t, contains('45 000.50'));
      expect(t, contains("to'lanmagan"));
      expect(t, contains('Bu fiskal chek emas'));
    });

    test('nothing queued says so', () {
      final String t = text(
        layoutOfflineSummary(
          _build(const <SyncOperation>[]),
          const ReceiptHeader(storeName: 'Bog\'bon'),
          PaperWidth.mm80,
        ),
      );
      expect(t, contains('Navbatda hech narsa yo'));
    });
  });

  group('Zaxira card button', () {
    testWidgets('prints the slip on the receipt printer', (
      WidgetTester tester,
    ) async {
      final SyncStore store = await SyncStore.open(MemorySyncStorage());
      final P22 p = P22(offlineStore: store);
      p.state.openShift();
      await pumpHome(tester, p.h);
      final PosOffline o = p.h.data.offline!;
      final PosOfflineSettings s = o.settings;
      o.settings = PosOfflineSettings(
        devicePrefix: s.devicePrefix,
        nextLocalNumber: s.nextLocalNumber,
        businessDate: s.businessDate,
        dayType: s.dayType,
        fees: s.fees,
        places: s.places,
        guestItems: s.guestItems,
        limits: s.limits,
        serviceChargeLocked: s.serviceChargeLocked,
        packaging: s.packaging,
        discount: s.discount,
        signing: s.signing,
        signingKid: s.signingKid,
        export: true,
      );
      await openSection(tester, 'Sozlamalar');
      final Finder button = find.byKey(
        const ValueKey<String>('zaxira-print-summary'),
      );
      expect(find.text('Qisqa hisobotni chop etish'), findsOneWidget);
      await tester.ensureVisible(button);
      await tester.pumpAndSettle();
      await tester.tap(button);
      await tester.pumpAndSettle();
      expect(p.receipts, hasLength(1));
      expect(p.receipts.single, contains('QISQA HISOBOT'));
    });
  });
}
