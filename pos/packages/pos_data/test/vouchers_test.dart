import 'dart:math';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P6-43: sovg'a sertifikati — sotish (so'rov tanasi, chop etiladigani), kod bo'yicha qidirish, `voucher` tender bilan
/// to'lov (qoldiq, rad etish), sotuv chekida "oldindan to'langan", X/Z `vouchers` qismi, promo-kod so'rovlari,
/// internetsiz oddiy xabar.
void main() {
  late Harness h;
  late FakeVoucherBackend vouchers;

  setUp(() async {
    h = Harness();
    FakeCashierBackend(h.state);
    vouchers = FakeVoucherBackend(h.state);
    await h.signIn();
  });

  Future<T> throws<T extends Object>(Future<Object?> Function() call) async {
    try {
      await call();
    } on T catch (e) {
      return e;
    }
    fail('expected $T');
  }

  test(
    'a certificate is sold: the body, the code and the print block',
    () async {
      h.state.openShift();
      final PosVoucher sold = await h.pos.vouchers.sell(
        const PosVoucherSale(
          faceValue: PosMoney.som(200000),
          tender: PosTender.card,
          cardType: 'humo',
          transactionId: 'RRN-1',
          buyerName: 'Aziz',
        ),
      );
      expect(Harness.bodyOf(h.last), <String, Object?>{
        'face_value': '200000.00',
        'tender': 'card',
        'card_type': 'humo',
        'transaction_id': 'RRN-1',
        'buyer_name': 'Aziz',
      });
      expect(h.last.headers['Idempotency-Key'], isNotNull);
      expect(sold.faceValue, const PosMoney.som(200000));
      expect(sold.balance, const PosMoney.som(200000));
      expect(sold.status, PosVoucherStatus.active);
      expect(sold.saleTender, PosTender.card);
      expect(sold.print.qr, sold.code);
      expect(sold.print.store, 'Choyxona');

      final PosVoucherLookup found = await h.pos.vouchers.lookup(
        sold.code.toLowerCase().replaceAll('-', ' '),
      );
      expect(found.voucher.uuid, sold.uuid);
    },
  );

  test('selling refused: no shift, the module off', () async {
    final PosConflict noShift = await throws<PosConflict>(
      () => h.pos.vouchers.sell(
        const PosVoucherSale(
          faceValue: PosMoney.som(10000),
          tender: PosTender.cash,
        ),
      ),
    );
    expect(noShift.reason, 'shift_not_open');
    h.state.openShift();
    vouchers.moduleOff = true;
    final PosException off = await throws<PosException>(
      () => h.pos.vouchers.sell(
        const PosVoucherSale(
          faceValue: PosMoney.som(10000),
          tender: PosTender.cash,
        ),
      ),
    );
    expect(off.toString(), contains("o'chiq"));
  });

  test('a bill paid partly with a certificate: the balance, the prepaid part of the receipt', () async {
    h.state.openShift();
    final Map<String, Object?> v = vouchers.add(faceValue: '50000.00');
    final PosOrder order = await h.pos.orders.get(
      h.state.seedOrder(tableId: 1, guests: 4),
    );
    final PosBill bill = (await h.pos.billing.createBills(
      order.uuid,
      version: order.version,
    )).bills.single;

    final PosConflict short = await throws<PosConflict>(
      () => h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[
          PosTenderInput.voucher(
            const PosMoney.som(60000),
            code: '${v['code']}',
          ),
        ],
      ),
    );
    expect(short.reason, 'voucher_insufficient');

    final PosBillResult paid = await h.pos.billing.pay(
      bill.uuid,
      tenders: <PosTenderInput>[
        PosTenderInput.voucher(const PosMoney.som(50000), code: '${v['code']}'),
        PosTenderInput.cash(bill.total - const PosMoney.som(50000)),
      ],
    );
    expect(
      (Harness.bodyOf(h.last)['tenders']! as List<Object?>).first,
      <String, Object?>{
        'tender': 'voucher',
        'amount': '50000.00',
        'voucher_code': v['code'],
      },
    );
    expect(vouchers.vouchers[v['code']]!['status'], 'used');
    final List<PosFiscalPaymentPart> parts = paid.bill.fiscal.receipt!.payments;
    expect(parts.first.prepayment, isTrue);
    expect(parts.last.prepayment, isFalse);
  });

  test('X/Z vouchers: the sold and redeemed parts; an old server none', () {
    final PosShiftReport old = PosShiftReport.fromJson(
      PosJson.of(<String, Object?>{'shift_uuid': 's'}),
    );
    expect(old.vouchers.isEmpty, isTrue);
    final PosShiftReport x = PosShiftReport.fromJson(
      PosJson.of(<String, Object?>{
        'shift_uuid': 's',
        'vouchers': <String, Object?>{
          'sold': <String, Object?>{
            'count': 2,
            'amount': '250000.00',
            'cash': '200000.00',
            'card': '50000.00',
            'qr': '0.00',
          },
          'redeemed': <String, Object?>{'count': 1, 'amount': '120000.00'},
        },
        'cash': <String, Object?>{'vouchers': '200000.00'},
      }),
    );
    expect(x.vouchers.soldCount, 2);
    expect(x.vouchers.soldCash, const PosMoney.som(200000));
    expect(x.vouchers.redeemedAmount, const PosMoney.som(120000));
    expect(x.vouchers.drawerCash, const PosMoney.som(200000));
  });

  test('the voucher tender JSON and its money flag', () {
    expect(
      const PosTenderInput.voucher(
        PosMoney.som(1000),
        code: 'ACDE-FGHJ-KMNP',
      ).toJson(),
      <String, Object?>{
        'tender': 'voucher',
        'amount': '1000.00',
        'voucher_code': 'ACDE-FGHJ-KMNP',
      },
    );
    expect(PosTender.voucher.isMoneyReceived, isFalse);
    expect(PosTender.fromWire('voucher'), PosTender.voucher);
  });

  test(
    'offline: selling, the lookup and promo codes need the server',
    () async {
      final FakePosAdapter adapter = FakePosAdapter(
        clock: () => DateTime.utc(2026, 10, 22, 13),
      );
      final RizqimClient client = RizqimClient(
        baseUrl: 'https://api.test/api',
        store: MemoryKeyValueStore(),
        dio: Dio()..httpClientAdapter = adapter,
        clock: () => DateTime.utc(2026, 10, 22, 13),
      );
      final SyncStore store = await SyncStore.open(
        MemorySyncStorage(),
        clock: () => DateTime.utc(2026, 10, 22, 13),
        random: Random(3),
      );
      final PosData pos = PosData(
        client,
        clock: () => DateTime.utc(2026, 10, 22, 13),
        random: Random(7),
        offlineStore: store,
      );
      FakeCashierBackend(adapter.state);
      FakeVoucherBackend(adapter.state);
      await client.auth.pairTerminal('ABCD-EFGH');
      await client.auth.signInStaff(userId: 3, pin: adapter.state.pins[3]!);

      adapter.offline = true;
      // The till notices the lost connection on its first failed request
      await expectLater(
        pos.vouchers.lookup('ACDE-FGHJ-KMNP'),
        throwsA(isA<PosException>()),
      );
      for (final Future<Object?> Function() call
          in <Future<Object?> Function()>[
            () => pos.vouchers.lookup('ACDE-FGHJ-KMNP'),
            () => pos.vouchers.sell(
              const PosVoucherSale(
                faceValue: PosMoney.som(1000),
                tender: PosTender.cash,
              ),
            ),
            () => pos.vouchers.applyPromo('o', version: 1, code: 'KUZ10'),
          ]) {
        await expectLater(
          call(),
          throwsA(
            isA<PosOfflineUnavailable>().having(
              (PosOfflineUnavailable e) => e.message,
              'message',
              PosVoucherRepository.offlineMessage,
            ),
          ),
        );
      }
    },
  );
}
