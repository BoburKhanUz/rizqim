import 'dart:math';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P6-41/P6-42: QR to'lov — bootstrap `qr_payments`, QR so'rash (provayder, summa), so'rab turish, bekor qilish, sinov
/// tasdig'i, `qr.*` hodisalari, X/Z `qr_providers`, internetsiz oddiy xabar.
void main() {
  late Harness h;
  late FakeQrBackend qr;

  setUp(() async {
    h = Harness();
    FakeCashierBackend(h.state);
    qr = FakeQrBackend(h.state);
    await h.signIn();
  });

  test(
    'bootstrap qr_payments: off on an old server, the providers default first',
    () async {
      expect((await h.pos.session.loadBootstrap()).qrPayments.enabled, isFalse);

      h.state.bootstrapExtras['qr_payments'] = <String, Object?>{
        'enabled': true,
        'providers': <Object?>['payme', 'click', 'bitcoin'],
        'default_provider': 'payme',
        'expiry_minutes': 10,
      };
      final PosQrSettings s = (await h.pos.session.loadBootstrap()).qrPayments;
      expect(s.enabled, isTrue);
      expect(s.providers, <PosQrProvider>[
        PosQrProvider.payme,
        PosQrProvider.click,
      ]);
      expect(s.defaultProvider, PosQrProvider.payme);
      expect(s.expiryMinutes, 10);

      h.state.bootstrapExtras['qr_payments'] = <String, Object?>{
        'enabled': true,
        'providers': <Object?>[],
      };
      expect((await h.pos.session.loadBootstrap()).qrPayments.enabled, isFalse);
    },
  );

  test('a QR for the bill: payload for the bill and the customer display, poll, cancel, simulate', () async {
    final PosQrPayment made = await h.pos.qr.create(
      'bill-1',
      provider: PosQrProvider.payme,
      amount: '43000.00',
    );
    expect(Harness.bodyOf(h.last), <String, Object?>{
      'provider': 'payme',
      'amount': '43000.00',
    });
    expect(h.last.headers['Idempotency-Key'], matches(uuidV4));
    expect(made.status, PosQrStatus.pending);
    expect(made.provider, PosQrProvider.payme);
    expect(made.amount, PosMoney.parse('43000'));
    expect(made.display.payload, made.payload);
    expect(made.display.provider, 'payme');
    expect(made.simulated, isTrue);

    final PosQrPayment second = await h.pos.qr.create('bill-1');
    expect((await h.pos.qr.poll(made.uuid)).cancelReason, 'replaced');
    expect(
      (await h.pos.qr.forBill('bill-1')).map((PosQrPayment q) => q.uuid),
      <String>[second.uuid, made.uuid],
    );

    final PosQrPayment cancelled = await h.pos.qr.cancel(
      second.uuid,
      reason: 'Naqd berdi',
    );
    expect(h.last.method, 'DELETE');
    expect(h.last.queryParameters['reason'], 'Naqd berdi');
    expect(cancelled.status, PosQrStatus.cancelled);
    expect(cancelled.status.isFinal, isTrue);

    final PosQrPayment third = await h.pos.qr.create(
      'bill-1',
      provider: PosQrProvider.uzqr,
    );
    final PosQrPayment paid = await h.pos.qr.simulatePaid(third.uuid);
    expect(paid.status, PosQrStatus.paid);
    expect(paid.paymentUuid, isNotNull);
    await expectLater(
      h.pos.qr.cancel(third.uuid),
      throwsA(
        isA<PosConflict>().having(
          (PosConflict e) => e.reason,
          'reason',
          'qr_already_paid',
        ),
      ),
    );
  });

  test(
    "the store's merchant not set up is the server's plain refusal",
    () async {
      qr.notConfigured.add('click');
      await expectLater(
        h.pos.qr.create('bill-1', provider: PosQrProvider.click),
        throwsA(
          isA<PosConflict>().having(
            (PosConflict e) => e.reason,
            'reason',
            'provider_not_configured',
          ),
        ),
      );
    },
  );

  test('qr.* events are typed', () {
    final PosQrPaymentChanged paid = PosEvent.fromWire(
      'qr.paid',
      <String, Object?>{
        'qr': <String, Object?>{
          'uuid': 'q1',
          'bill_uuid': 'b1',
          'order_uuid': 'o1',
          'provider': 'click',
          'status': 'paid',
        },
        'changed_at': '2026-11-19T10:00:00Z',
      },
    ) as PosQrPaymentChanged;
    expect(
      (paid.qrUuid, paid.billUuid, paid.orderUuid, paid.provider, paid.paid),
      ('q1', 'b1', 'o1', 'click', true),
    );
    expect(
      PosEvent.fromWire('qr.expired', <String, Object?>{
        'qr': <String, Object?>{'uuid': 'q2', 'status': 'expired'},
      }),
      isA<PosQrPaymentChanged>(),
    );
    expect(
      PosEvent.fromWire('qr.cancelled', <String, Object?>{}),
      isA<PosUnknownEvent>(),
    );
  });

  test('the X/Z qr_providers lines', () {
    const PosShiftReport report = PosShiftReport(
      raw: <String, Object?>{
        'qr_providers': <Object?>[
          <String, Object?>{
            'provider': 'click',
            'count': 2,
            'amount': '286000.00',
          },
          <String, Object?>{
            'provider': 'manual',
            'count': 1,
            'amount': '10000.00',
          },
        ],
      },
    );
    expect(
      report.qrProviders.map(
        (PosQrProviderTotal t) => (t.provider, t.count, t.amount),
      ),
      <Object>[
        ('click', 2, PosMoney.parse('286000')),
        ('manual', 1, PosMoney.parse('10000')),
      ],
    );
    expect(const PosShiftReport().qrProviders, isEmpty);
  });

  test('offline a QR cannot be made: a plain message (the static QR by hand stays)', () async {
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
    FakeQrBackend(adapter.state);
    await client.auth.pairTerminal('ABCD-EFGH');
    await client.auth.signInStaff(userId: 3, pin: adapter.state.pins[3]!);

    adapter.offline = true;
    await expectLater(pos.qr.poll('x'), throwsA(isA<PosException>()));
    await expectLater(
      pos.qr.create('bill-1'),
      throwsA(
        isA<PosOfflineUnavailable>().having(
          (PosOfflineUnavailable e) => e.message,
          'message',
          PosQrPaymentRepository.offlineMessage,
        ),
      ),
    );
  });
}
