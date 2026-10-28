import 'dart:math';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P4-21: tarif nimaga yo'l qo'yadi (bootstrap'dagi `entitlements`): model, oxirgi ma'lum holat, yangilash, 403 rad etishlar.
void main() {
  PosEntitlements parse(Map<String, Object?> json) =>
      PosEntitlements.fromJson(PosJson(json));

  group('PosEntitlements', () {
    test('Start has no bookings, deposits, nasiya, KDS or service charge, and says which plan has them', () {
      final PosEntitlements e = parse(fakeEntitlements(plan: 'start'));
      expect(e.planName, 'Start');
      expect(e.has(PosFeature.bookings), isFalse);
      expect(e.has(PosFeature.deposits), isFalse);
      expect(e.has(PosFeature.customerAccounts), isFalse);
      expect(e.has(PosFeature.kds), isFalse);
      expect(e.has(PosFeature.serviceCharge), isFalse);
      expect(e.planNameFor(PosFeature.bookings), 'Asosiy');
      expect(e.planNameFor(PosFeature.banquet), 'Pro');
      expect(e.lockLabel(PosFeature.bookings), 'Asosiy tarifda bor');
      expect(
        e.lockReason(PosFeature.customerAccounts),
        "Nasiya Asosiy tarifda bor. Hozirgi tarifingizda yo'q.",
      );
    });

    test('Asosiy has them, Pro features are locked', () {
      final PosEntitlements e = parse(fakeEntitlements());
      expect(e.has(PosFeature.bookings), isTrue);
      expect(e.has(PosFeature.customerAccounts), isTrue);
      expect(e.has(PosFeature.banquet), isFalse);
      expect(e.lockLabel(PosFeature.banquet), 'Pro tarifda bor');
    });

    test('open: every feature, no notice; a legacy store is open whatever the list says', () {
      expect(PosEntitlements.open.has(PosFeature.bookings), isTrue);
      expect(PosEntitlements.open.notice, isNull);
      final PosEntitlements legacy = parse(
        fakeEntitlements(plan: 'start', legacy: true),
      );
      expect(legacy.has(PosFeature.kds), isTrue);
    });

    test('a feature the server does not list is open (the server judges the write)', () {
      final PosEntitlements e = parse(<String, Object?>{
        'features': <String, Object?>{'bookings': false},
      });
      expect(e.has(PosFeature.bookings), isFalse);
      expect(e.has(PosFeature.deposits), isTrue);
      expect(e.lockLabel(PosFeature.bookings), "Tarifingizda yo'q");
    });

    test('the notice: reminder with days and invoice, then locked', () {
      final PosEntitlements reminder = parse(
        fakeEntitlements(
          status: 'past_due',
          lockAt: DateTime.utc(2026, 11, 19),
          notice: fakeBillingNotice(lockAt: DateTime.utc(2026, 11, 19)),
        ),
      );
      expect(reminder.notice!.locked, isFalse);
      expect(reminder.notice!.daysOverdue, 3);
      expect(reminder.notice!.invoiceNumber, 'OB-202610-000042');
      expect(reminder.notice!.outstanding, const PosMoney.som(249000));
      expect(reminder.notice!.message, contains('Kassa ishlashda davom etadi'));
      expect(reminder.status, PosBillingStatus.pastDue);

      final PosEntitlements locked = parse(
        fakeEntitlements(
          status: 'locked',
          notice: fakeBillingNotice(locked: true, daysOverdue: 9),
        ),
      );
      expect(locked.notice!.locked, isTrue);
      expect(locked.settingsLocked, isTrue);
      expect(locked.status, PosBillingStatus.locked);
    });

    test('the notice speaks plain words: days to the lock, then the lock, and the till keeps working', () {
      final DateTime now = DateTime.utc(2026, 11, 15, 8);
      PosBillingNotice reminder(DateTime? lockAt) => parse(
        fakeEntitlements(
          status: 'past_due',
          notice: fakeBillingNotice(lockAt: lockAt),
        ),
      ).notice!;
      final PosBillingNotice four = reminder(DateTime.utc(2026, 11, 19, 8));
      expect(four.daysUntilLock(now), 4);
      expect(
        four.title(now),
        "To'lov muddati o'tdi — 4 kundan keyin sozlamalar yopiladi",
      );
      expect(four.text, contains('OB-202610-000042 hisob-faktura, qoldiq'));
      expect(four.text, contains('Kassa ishlashda davom etadi'));
      expect(
        reminder(DateTime.utc(2026, 11, 15, 20)).title(now),
        "To'lov muddati o'tdi — 1 kundan keyin sozlamalar yopiladi",
      );
      expect(
        reminder(DateTime.utc(2026, 11, 14)).title(now),
        "To'lov muddati o'tdi — bugun sozlamalar yopiladi",
      );
      expect(
        reminder(null).title(now),
        "To'lov muddati o'tdi — sozlamalar yopilmasidan oldin to'lang",
      );

      final PosBillingNotice locked = parse(
        fakeEntitlements(
          status: 'locked',
          notice: fakeBillingNotice(locked: true),
        ),
      ).notice!;
      expect(locked.title(now), "Sozlamalar yopiq — to'lov qiling");
      expect(locked.text, contains('darhol ochiladi'));
      expect(
        locked.text,
        contains(
          "Kassa to'lov qabul qilish, chek chiqarish va smenada davom etadi",
        ),
      );
    });

    test('trial days left round up', () {
      final PosEntitlements e = parse(
        fakeEntitlements(
          status: 'trialing',
          trialEndsAt: DateTime.utc(2026, 10, 27, 12),
        ),
      );
      expect(e.trialDaysLeft(DateTime.utc(2026, 10, 22, 13)), 5);
      expect(e.trialDaysLeft(DateTime.utc(2026, 10, 27, 13)), 0);
      expect(
        parse(fakeEntitlements()).trialDaysLeft(DateTime.utc(2026)),
        isNull,
      );
    });

    test('devices: till and kitchen display with the price of an extra one, waiter phones unlimited', () {
      final PosEntitlements e = parse(fakeEntitlements(activePos: 2));
      expect(e.pos.included, 1);
      expect(e.pos.extra, 1);
      expect(e.pos.extraPrice, const PosMoney.som(59000));
      expect(e.kds.extraPrice, const PosMoney.som(29000));
      expect(e.waiter.unlimited, isTrue);
    });
  });

  group('bootstrap and session', () {
    late Harness h;

    setUp(() async {
      h = Harness();
      FakeCashierBackend(h.state);
      await h.signIn();
    });

    test('the bootstrap carries the entitlements; an old server has none and the session is open', () async {
      final PosBootstrap old = await h.pos.session.loadBootstrap();
      expect(old.entitlements, isNull);
      expect(h.pos.session.entitlements.has(PosFeature.bookings), isTrue);

      h.state.entitlements = fakeEntitlements(plan: 'start');
      final PosBootstrap b = await h.pos.session.loadBootstrap();
      expect(b.entitlements!.planName, 'Start');
      expect(h.pos.session.entitlements.has(PosFeature.bookings), isFalse);
    });

    test('a block the app cannot read leaves everything open instead of failing the bootstrap', () async {
      h.state.entitlements = <String, Object?>{
        'plan': 'not-an-object',
        'features': 5,
      };
      final PosBootstrap b = await h.pos.session.loadBootstrap();
      expect(b.entitlements, isNull);
      expect(h.pos.session.entitlements.has(PosFeature.kds), isTrue);
    });

    test('the last known plan stays when the staff locks the screen, and is dropped when the terminal is unpaired', () async {
      h.state.entitlements = fakeEntitlements(plan: 'start');
      await h.pos.session.loadBootstrap();
      await h.pos.session.lock();
      expect(h.pos.session.bootstrap, isNull);
      expect(h.pos.session.entitlements.has(PosFeature.bookings), isFalse);

      await h.client.auth.signInStaff(userId: 3, pin: '4827');
      await h.client.auth.unpairTerminal();
      await Future<void>.delayed(Duration.zero);
      expect(h.pos.session.entitlements.has(PosFeature.bookings), isTrue);
    });

    test('refreshEntitlements reads the plan again: paying opens the locks at once', () async {
      h.state.entitlements = fakeEntitlements(
        plan: 'start',
        status: 'locked',
        notice: fakeBillingNotice(locked: true),
      );
      await h.pos.session.loadBootstrap();
      expect(h.pos.session.entitlements.notice!.locked, isTrue);

      final List<PosSessionPhase> seen = <PosSessionPhase>[];
      h.pos.session.changes.listen(seen.add);
      h.state.entitlements = fakeEntitlements(plan: 'asosiy');
      await h.pos.session.refreshEntitlements();
      expect(h.last.uri.path, '/api/v1/owner/billing/entitlements');
      expect(h.pos.session.entitlements.notice, isNull);
      expect(h.pos.session.entitlements.has(PosFeature.bookings), isTrue);
      expect(h.pos.session.bootstrap!.entitlements!.planName, 'Asosiy');
      await Future<void>.delayed(Duration.zero);
      expect(seen, isNotEmpty);
    });

    test('a failed refresh keeps the last known plan', () async {
      h.state.entitlements = fakeEntitlements(plan: 'start');
      await h.pos.session.loadBootstrap();
      h.state.failPaths['/v1/owner/billing/entitlements'] = 500;
      await h.pos.session.refreshEntitlements();
      expect(h.pos.session.entitlements.has(PosFeature.bookings), isFalse);
    });
  });

  test('the plan is kept in the offline copy of the bootstrap: reopened without internet, the locks and the notice are still there', () async {
    final DateTime start = DateTime.utc(2026, 10, 22, 13);
    final FakePosAdapter adapter = FakePosAdapter(clock: () => start);
    final RizqimClient client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: MemoryKeyValueStore(),
      dio: Dio()..httpClientAdapter = adapter,
      clock: () => start,
    );
    final PosData pos = PosData(
      client,
      clock: () => start,
      random: Random(7),
      offlineStore: await SyncStore.open(
        MemorySyncStorage(),
        clock: () => start,
        random: Random(3),
      ),
    );
    adapter.state.entitlements = fakeEntitlements(
      plan: 'start',
      status: 'locked',
      notice: fakeBillingNotice(locked: true),
    );
    await client.auth.pairTerminal('ABCD-EFGH');
    await client.auth.signInStaff(userId: 3, pin: adapter.state.pins[3]!);
    await pos.session.loadBootstrap();

    // The network goes and the till is opened again: the copy has the plan
    adapter.offline = true;
    final PosBootstrap again = await pos.session.loadBootstrap();
    expect(again.entitlements!.planName, 'Start');
    expect(pos.session.entitlements.notice!.locked, isTrue);
    expect(pos.session.entitlements.has(PosFeature.bookings), isFalse);
    // Refreshing without internet changes nothing
    await pos.session.refreshEntitlements();
    expect(pos.session.entitlements.notice!.locked, isTrue);
    await pos.dispose();
  });

  group('the server refusals', () {
    late Harness h;
    late FakeCashierBackend backend;

    setUp(() async {
      h = Harness();
      backend = FakeCashierBackend(h.state);
      await h.signIn(userId: 2);
    });

    test('a new nasiya account on Start is a PosPlanRefused with the plan that has it', () async {
      h.state.entitlements = fakeEntitlements(plan: 'start');
      await expectLater(
        h.pos.accounts.create(const PosAccountInput(name: 'Karim')),
        throwsA(
          isA<PosPlanRefused>()
              .having(
                (PosPlanRefused e) => e.reason,
                'reason',
                'plan_feature_missing',
              )
              .having(
                (PosPlanRefused e) => e.feature,
                'feature',
                'customer_accounts',
              )
              .having(
                (PosPlanRefused e) => e.requiredPlanName,
                'plan',
                'Asosiy',
              )
              .having(
                (PosPlanRefused e) => e.byUnpaidInvoice,
                'unpaid',
                isFalse,
              )
              .having((PosPlanRefused e) => e.statusCode, 'status', 403),
        ),
      );
      expect(backend.accounts, isEmpty);
    });

    test('on Asosiy the same call goes through', () async {
      h.state.entitlements = fakeEntitlements(plan: 'asosiy');
      final PosCustomerAccount a = await h.pos.accounts.create(
        const PosAccountInput(name: 'Karim'),
      );
      expect(a.name, 'Karim');
    });

    PosException refusal(int status, Map<String, Object?> body) {
      final RequestOptions options = RequestOptions(path: '/v1/pos/x');
      return PosHttp.mapError(
        DioException(
          requestOptions: options,
          response: Response<Object?>(
            requestOptions: options,
            statusCode: status,
            data: body,
          ),
        ),
      );
    }

    test('subscription_locked and device_limit are PosPlanRefused by an unpaid invoice, a plain 403 is not', () {
      final PosException locked = refusal(403, <String, Object?>{
        'success': false,
        'message':
            "To'lanmagan hisob-faktura sababli yangi sozlamalar yopilgan.",
        'code': 'subscription_locked',
        'data': <String, Object?>{
          'reason': 'subscription_locked',
          'locked_since': '2026-11-19T00:00:00+05:00',
        },
      });
      expect(locked, isA<PosPlanRefused>());
      expect((locked as PosPlanRefused).byUnpaidInvoice, isTrue);
      expect(locked.message, contains("To'lanmagan"));

      final PosException device = refusal(403, <String, Object?>{
        'success': false,
        'message': '',
        'data': <String, Object?>{
          'reason': 'device_limit',
          'device_type': 'pos',
        },
      });
      expect(device, isA<PosPlanRefused>());
      // The server said nothing readable: the app's own plain words
      expect(device.message, contains("Eskisini o'chirib"));

      final PosException plain = refusal(403, <String, Object?>{
        'success': false,
        'message': "Bu amal uchun ruxsat yo'q.",
        'data': <String, Object?>{'reason': 'forbidden'},
      });
      expect(plain, isNot(isA<PosPlanRefused>()));
      expect(plain, isA<PosForbidden>());
    });
  });
}
