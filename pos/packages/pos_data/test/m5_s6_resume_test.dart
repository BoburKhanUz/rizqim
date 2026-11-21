import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'offline_test.dart' show OfflineHarness;

/// Wave M5 stage S6-a (docs/scale/M5_DESIGN.md §2 "App restart during an outage", owner answer 8.1-3): after an app
/// restart with no server the till offers "Davom etish: (ism)" for the last staff member who entered a PIN on it, for
/// at most 12 h after that PIN login, only to finish open orders and take payment. Only the staff member's id, name,
/// permissions and PIN-login time are kept (secure storage): never the PIN, a PIN hash or a token.
void main() {
  late OfflineHarness h;
  late FakeSyncBackend sync;

  Future<void> settle() async {
    for (var i = 0; i < 5; i++) {
      await Future<void>.delayed(Duration.zero);
    }
  }

  Future<void> boot({
    bool supported = true,
    int userId = 3,
    bool windowOver = false,
  }) async {
    h = await OfflineHarness.create();
    FakeCashierBackend(h.state);
    sync = FakeSyncBackend(h.state);
    h.state
      ..offlineStaffResume = supported
      ..offlineStaffWindowOver = windowOver
      ..offlineDiscount = true
      ..openShift();
    await h.client.auth.pairTerminal('ABCD-EFGH');
    await h.client.auth.signInStaff(userId: userId, pin: h.state.pins[userId]!);
    sync.sessionStartedAt[userId] = h.clock.now;
    await settle();
  }

  tearDown(() => h.pos.dispose());

  String shift() => h.state.shift!['uuid']! as String;

  Future<Map<String, Object?>?> fetchRecord() async {
    await h.client.auth.resumeWritten;
    final String? raw = h.keys.values[AuthSession.staffResumeKey];
    return raw == null
        ? null
        : (jsonDecode(raw) as Map<Object?, Object?>).cast<String, Object?>();
  }

  /// The app is closed and opened again while the network is down: the stored terminal is read, the PIN keypad is
  /// shown and its staff list cannot be loaded (the request fails: the till knows the server is not there).
  Future<void> restartOffline() async {
    h.adapter.offline = true;
    await h.pos.dispose();
    h = await OfflineHarness.create(
      disk: h.disk,
      adapter: h.adapter,
      clock: h.clock,
      keys: h.keys,
      seed: 12,
      restore: true,
    );
    await settle();
    expect(h.pos.session.phase, PosSessionPhase.locked);
    // As the PIN keypad does (cashier StaffSignInScreen): the staff list does not come, the server is not there
    try {
      await h.client.auth.loadStaff();
      fail('the staff list came without a network');
    } on ApiException catch (e) {
      expect(e.isNetworkError, isTrue);
      h.pos.connectivity.reportFailure(e);
    }
    expect(h.pos.connectivity.state.online, isFalse);
  }

  /// Signed in at 13:00 with an order open on table 1, everything loaded, then the network goes.
  Future<String> openTableThenOutage() async {
    final String room = h.state.seedOrder(tableId: 1, guests: 2);
    await h.warmUp();
    await h.goOffline();
    return room;
  }

  Matcher refused(String reason) => throwsA(
    isA<PosOfflineUnavailable>().having(
      (PosOfflineUnavailable e) => e.reason,
      'reason',
      reason,
    ),
  );

  group('what is kept in the secure storage', () {
    test('only the id, the name, the permissions and the PIN-login time; never the PIN or a token', () async {
      await boot();
      final String token = h.client.auth.staff!.token;
      final String pin = h.state.pins[3]!;

      final Map<String, Object?> r = (await fetchRecord())!;
      expect(r.keys.toSet(), <String>{
        'v',
        'staff_id',
        'name',
        'permissions',
        'signed_in_at',
      });
      expect(r['staff_id'], 3);
      expect(r['name'], 'Kamola Karimova');
      expect(r['signed_in_at'], h.clock.now.toUtc().toIso8601String());
      expect(
        (r['permissions']! as List<Object?>).toSet(),
        h.state.rolePermissions['cashier']!.toSet(),
      );
      // Nothing in the secure storage holds the PIN or the staff token (the terminal's own record holds its token)
      for (final MapEntry<String, String> e in h.keys.values.entries) {
        expect(e.value, isNot(contains(token)), reason: e.key);
        expect(e.value, isNot(contains('"$pin"')), reason: e.key);
        expect(e.value, isNot(contains(pin)), reason: e.key);
      }
      final String kept = h.keys.values[AuthSession.staffResumeKey]!;
      expect(kept.toLowerCase(), isNot(contains('pin')));
      expect(kept, isNot(contains('token')));
      expect(kept, isNot(contains('hash')));
    });

    test('a successful PIN login of somebody else replaces it', () async {
      await boot();
      h.clock.now = h.clock.now.add(const Duration(hours: 1));
      await h.client.auth.signInStaff(userId: 2, pin: h.state.pins[2]!);
      await settle();
      expect((await fetchRecord())!['staff_id'], 2);
      expect((await fetchRecord())!['name'], 'Malika Rahimova');
      expect(
        (await fetchRecord())!['signed_in_at'],
        h.clock.now.toUtc().toIso8601String(),
      );
    });

    test('a failed PIN login changes nothing', () async {
      await boot();
      final Map<String, Object?> before = (await fetchRecord())!;
      await expectLater(
        h.client.auth.signInStaff(userId: 2, pin: '0000'),
        throwsA(isA<ApiException>()),
      );
      await settle();
      expect((await fetchRecord()), before);
    });

    test('cleared on sign-out (the screen lock)', () async {
      await boot();
      await h.pos.session.lock();
      await settle();
      expect((await fetchRecord()), isNull);
    });

    test('cleared on unpair', () async {
      await boot();
      await h.pos.session.unpair();
      await settle();
      expect((await fetchRecord()), isNull);
    });

    test(
      'cleared when the terminal is forgotten or revoked, and on a new pairing',
      () async {
        await boot();
        await h.client.auth.forgetTerminal();
        await settle();
        expect((await fetchRecord()), isNull);

        await boot();
        expect((await fetchRecord()), isNotNull);
        await h.client.auth.pairTerminal('ABCD-EFGH');
        await settle();
        expect((await fetchRecord()), isNull);
      },
    );

    test(
      'a till without the offline mode (waiter app) keeps nothing',
      () async {
        final MemoryKeyValueStore keys = MemoryKeyValueStore();
        final FakePosAdapter adapter = FakePosAdapter();
        FakeCashierBackend(adapter.state);
        final RizqimClient client = RizqimClient(
          baseUrl: 'https://api.test/api',
          store: keys,
          dio: Dio()..httpClientAdapter = adapter,
        );
        final PosData waiter = PosData(client);
        await client.auth.pairTerminal('ABCD-EFGH');
        await client.auth.signInStaff(userId: 4, pin: adapter.state.pins[4]!);
        expect(keys.values.containsKey(AuthSession.staffResumeKey), isFalse);
        await waiter.dispose();
        // (the harness of this group still needs one to tear down)
        await boot();
      },
    );
  });

  group('the 12 h window', () {
    test('11:59 after the PIN login the offer is there and the staff member goes on', () async {
      await boot();
      await openTableThenOutage();
      h.clock.now = h.clock.now.add(const Duration(hours: 11, minutes: 59));
      await restartOffline();

      final PosStaffResumeOffer offer = h.pos.session.resumeOffer!;
      expect(offer.staffId, 3);
      expect(offer.name, 'Kamola Karimova');
      expect(offer.until, DateTime.utc(2026, 10, 23, 1));
      expect(h.pos.session.resumeStaff(), isTrue);
      await settle();
      expect(h.pos.session.isSignedIn, isTrue);
      expect(h.pos.session.staffResumed, isTrue);
      expect(h.pos.session.staffContinued, isTrue);
      expect(h.pos.session.staffId, 3);
      expect(h.offline.continuedNotice, contains('Kamola Karimova'));
    });

    test('12:01 after the PIN login: no offer, nothing resumes and the record is gone', () async {
      await boot();
      await openTableThenOutage();
      h.clock.now = h.clock.now.add(const Duration(hours: 12, minutes: 1));
      await restartOffline();

      expect(h.pos.session.resumeOffer, isNull);
      expect(h.pos.session.resumeStaff(), isFalse);
      await settle();
      expect(h.pos.session.isSignedIn, isFalse);
      expect((await fetchRecord()), isNull);
    });

    test('resumed at 11:30, the window ends at 12:00: a server that does not record the past-the-window mark '
        '(no offline.staff_window_over, an older one): the staff member is signed out', () async {
      await boot();
      final String room = await openTableThenOutage();
      h.clock.now = h.clock.now.add(const Duration(hours: 11, minutes: 30));
      await restartOffline();
      expect(h.pos.session.resumeStaff(), isTrue);
      await settle();
      final PosOrder order = await h.pos.orders.get(room);

      h.clock.now = h.clock.now.add(const Duration(minutes: 31));
      expect(h.pos.session.isSignedIn, isFalse);
      await settle();
      expect(h.pos.session.phase, PosSessionPhase.locked);
      expect((await fetchRecord()), isNull);
      await expectLater(
        h.pos.billing.createBills(room, version: order.version),
        throwsA(isA<PosOfflineUnavailable>()),
      );
    });
  });

  // Owner decision Q1 (21.11.2026, option 2): past the 12 h, while still offline, the resumed staff member stays,
  // only to take payment and close open orders; what is queued then carries `staff_window_over` (the server records
  // the payment as a `staff_unverified` conflict for the manager)
  group('past the 12 h window (owner decision Q1)', () {
    /// Two tables open, resumed at 11:30, a bill made on table 1 inside the window; then the window passes.
    Future<(String, String)> pastTheWindow() async {
      await boot(windowOver: true);
      final String first = h.state.seedOrder(tableId: 1, guests: 2);
      final String second = h.state.seedOrder(tableId: 2, guests: 1);
      await h.warmUp();
      // Both orders opened on the till while online (each kept for the outage)
      await h.pos.orders.get(first);
      await h.pos.orders.get(second);
      await h.goOffline();
      h.clock.now = h.clock.now.add(const Duration(hours: 11, minutes: 30));
      await restartOffline();
      expect(h.pos.session.resumeStaff(), isTrue);
      await settle();
      await h.pos.session.loadBootstrap();
      final PosOrder order = await h.pos.orders.get(first);
      await h.pos.billing.createBills(first, version: order.version);
      expect(h.pos.session.staffResumeWindowOver, isFalse);

      h.clock.now = h.clock.now.add(const Duration(minutes: 31));
      return (first, second);
    }

    test('the staff member stays; only the payment and the close (and the bill they need) go through, each '
        'marked; the banner says so', () async {
      final (String first, String second) = await pastTheWindow();
      final PosSession s = h.pos.session;
      expect(s.isSignedIn, isTrue);
      expect(s.staffResumed, isTrue);
      expect(s.staffResumeWindowOver, isTrue);
      expect(s.staffId, 3);
      expect(h.offline.continuedNotice, contains('12 soat'));
      expect(
        h.offline.continuedNotice,
        contains("faqat to'lov olish va ochiq buyurtmani yopish"),
      );
      // Kept for a lock on this run (see below)
      expect((await fetchRecord())?['staff_id'], 3);

      // Table 1: billed inside the window (not marked), paid and closed past it (marked)
      final PosBilling billing = await h.pos.billing.bills(first);
      final PosBill bill = billing.bills.single;
      final PosBillResult paid = await h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: shift(),
      );
      expect(paid.order.isClosed, isTrue);
      // Table 2: the bill it needs for the payment, the payment and the close, all past the window
      final PosOrder two = await h.pos.orders.get(second);
      final PosBill bill2 = (await h.pos.billing.createBills(
        second,
        version: two.version,
      )).bills.single;
      await h.pos.billing.pay(
        bill2.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill2.total)],
        shiftUuid: shift(),
      );

      final List<SyncOperation> queued = h.offline.store.pending;
      expect(queued.map((SyncOperation o) => o.type), <String>[
        SyncOpType.billCreate,
        SyncOpType.billPay,
        SyncOpType.orderClose,
        SyncOpType.billCreate,
        SyncOpType.billPay,
        SyncOpType.orderClose,
      ]);
      expect(
        queued.first.payload.containsKey(PosOffline.windowOverKey),
        isFalse,
      );
      for (final SyncOperation o in queued.skip(1)) {
        expect(o.staffId, 3);
        expect(o.payload[PosOffline.continuedKey], isTrue, reason: o.type);
        expect(o.payload[PosOffline.resumedKey], isTrue, reason: o.type);
        expect(o.payload[PosOffline.windowOverKey], isTrue, reason: o.type);
      }
    });

    test('a new order, a round, a void, a discount, sending to the kitchen and asking for the bill are refused '
        'with the reason', () async {
      final (String first, String second) = await pastTheWindow();
      await expectLater(
        h.pos.orders.open(
          channel: PosChannel.takeaway,
          items: <PosLineInput>[
            PosLineInput(
              variantUuid: FakePosState.variantUuid(1),
              quantity: PosQuantity.parse('1'),
            ),
          ],
        ),
        refused(PosOffline.windowOverReason),
      );
      final PosOrder order = await h.pos.orders.get(second);
      await expectLater(
        h.pos.orders.addRound(
          second,
          version: order.version,
          items: <PosLineInput>[
            PosLineInput(
              variantUuid: FakePosState.variantUuid(4),
              quantity: PosQuantity.parse('1'),
            ),
          ],
        ),
        refused(PosOffline.windowOverReason),
      );
      await expectLater(
        h.pos.orders.voidItem(
          second,
          order.items.first.id,
          version: order.version,
        ),
        throwsA(isA<PosOfflineUnavailable>()),
      );
      await expectLater(
        h.pos.orders.discount(
          second,
          version: order.version,
          kind: PosDiscountKind.percent,
          value: '5',
        ),
        refused(PosOffline.windowOverReason),
      );
      await expectLater(
        h.offline.send(second),
        refused(PosOffline.windowOverReason),
      );
      await expectLater(
        h.offline.requestBill(second),
        refused(PosOffline.windowOverReason),
      );
      expect(h.offline.discountBlock(first), contains("12 soat"));
      // Only the bill made inside the window is queued
      expect(h.offline.store.pending.map((SyncOperation o) => o.type), <String>[
        SyncOpType.billCreate,
      ]);
    });

    test(
      'the device clock moved back still ends it at once (record deleted)',
      () async {
        final (String first, _) = await pastTheWindow();
        expect(h.pos.session.staffResumeWindowOver, isTrue);
        h.clock.now = h.clock.now.subtract(const Duration(minutes: 5));
        expect(h.pos.session.isSignedIn, isFalse);
        await settle();
        expect((await fetchRecord()), isNull);
        expect(h.pos.session.resumeOffer, isNull);
        final PosBill bill = (await h.pos.billing.bills(first)).bills.single;
        await expectLater(
          h.offline.pay(
            bill.uuid,
            tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
            shiftUuid: shift(),
          ),
          throwsA(isA<PosOfflineUnavailable>()),
        );
      },
    );

    test('the 12 h by the monotonic clock (the device clock held back) also go on past the window, not out', () async {
      await boot(windowOver: true);
      await openTableThenOutage();
      h.clock.now = h.clock.now.add(const Duration(hours: 2));
      await restartOffline();
      Duration real = Duration.zero;
      h.client.auth.monotonic = () => real;
      expect(h.pos.session.resumeStaff(), isTrue);
      expect(h.pos.session.staffResumeWindowOver, isFalse);
      real = const Duration(hours: 10, minutes: 1);
      expect(h.pos.session.isSignedIn, isTrue);
      expect(h.pos.session.staffResumeWindowOver, isTrue);
    });

    test('the screen locked past the window, still offline: offered again on this run, still past the window', () async {
      await pastTheWindow();
      await h.client.auth.signOutStaff(reason: 'idle');
      await settle();
      expect(h.pos.session.isSignedIn, isFalse);
      expect(h.pos.session.resumeOffer?.staffId, 3);
      expect(h.pos.session.resumeStaff(), isTrue);
      expect(h.pos.session.staffResumeWindowOver, isTrue);
    });

    test('an app restart past the 12 h offers nothing (the past-the-window state is only kept on the run it began)', () async {
      await boot(windowOver: true);
      await openTableThenOutage();
      h.clock.now = h.clock.now.add(const Duration(hours: 12, minutes: 1));
      await restartOffline();
      expect(h.pos.session.resumeOffer, isNull);
      expect(h.pos.session.resumeStaff(), isFalse);
    });

    test('the server answers: the PIN keypad, as inside the window; the marked payment is a staff_unverified '
        'conflict for the manager (money kept), the close applied', () async {
      final (String first, _) = await pastTheWindow();
      final PosBill bill = (await h.pos.billing.bills(first)).bills.single;
      await h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: shift(),
      );
      h.adapter.offline = false;
      final SyncDrainResult synced = await h.offline.syncNow();
      await settle();
      expect(h.pos.session.staffResumed, isFalse);
      expect(h.pos.session.phase, PosSessionPhase.locked);

      expect(synced.left, 0);
      expect(
        <String, (SyncOpStatus, String?)>{
          for (final SyncOperation o in h.offline.store.answered)
            o.type: (o.status, o.reason),
        },
        <String, (SyncOpStatus, String?)>{
          SyncOpType.billCreate: (SyncOpStatus.applied, null),
          SyncOpType.billPay: (SyncOpStatus.conflict, 'staff_unverified'),
          SyncOpType.orderClose: (SyncOpStatus.applied, null),
        },
      );
      expect(h.state.orders[first]?['closed_at'], isNotNull);
    });
  });

  group('the device clock', () {
    test('moved backwards before the PIN login: the PIN-login time is in the future, nothing is offered', () async {
      await boot();
      await openTableThenOutage();
      h.clock.now = h.clock.now.subtract(const Duration(hours: 1));
      await restartOffline();
      expect(h.pos.session.resumeOffer, isNull);
      expect(h.pos.session.resumeStaff(), isFalse);
      await h.client.auth.resumeWritten;
      expect((await fetchRecord()), isNull);
    });

    test(
      'moved backwards behind the newest queued operation: nothing is offered',
      () async {
        await boot();
        final String room = await openTableThenOutage();
        // 15:00: a round is queued, then the app is closed
        h.clock.now = h.clock.now.add(const Duration(hours: 2));
        final PosOrder order = await h.pos.orders.get(room);
        await h.pos.orders.addRound(
          room,
          version: order.version,
          items: <PosLineInput>[
            PosLineInput(
              variantUuid: FakePosState.variantUuid(4),
              quantity: PosQuantity.parse('1'),
            ),
          ],
        );
        expect(h.offline.latestKnownTime, h.clock.now);
        // 14:00 by a clock set back: still inside 12 h of the PIN login, but before what the till already saw
        h.clock.now = h.clock.now.subtract(const Duration(hours: 1));
        await restartOffline();
        expect(h.pos.session.resumeOffer, isNull);
        expect(h.pos.session.resumeStaff(), isFalse);
      },
    );

    test('moved backwards while resumed (before the PIN login): the staff member is signed out', () async {
      await boot();
      await openTableThenOutage();
      h.clock.now = h.clock.now.add(const Duration(hours: 1));
      await restartOffline();
      expect(h.pos.session.resumeStaff(), isTrue);
      h.clock.now = h.clock.now.subtract(const Duration(hours: 2));
      expect(h.pos.session.isSignedIn, isFalse);
    });

    test('moved forwards past 12 h: nothing is offered', () async {
      await boot();
      await openTableThenOutage();
      h.clock.now = h.clock.now.add(const Duration(days: 3));
      await restartOffline();
      expect(h.pos.session.resumeOffer, isNull);
    });
  });

  group('when it is offered at all', () {
    test('the server did not say it records the marker (bootstrap without offline.staff_resume): no offer', () async {
      await boot(supported: false);
      await openTableThenOutage();
      h.clock.now = h.clock.now.add(const Duration(hours: 1));
      await restartOffline();
      expect(h.pos.session.resumeOffer, isNull);
      expect(h.pos.session.resumeStaff(), isFalse);
    });

    test('the server answers: no offer (a PIN login is possible)', () async {
      await boot();
      await h.warmUp();
      await h.pos.dispose();
      h = await OfflineHarness.create(
        disk: h.disk,
        adapter: h.adapter,
        clock: h.clock,
        keys: h.keys,
        seed: 12,
        restore: true,
      );
      await settle();
      // Nobody is signed in: the server answers 401, which is an answer
      await expectLater(h.pos.floor.load(), throwsA(isA<PosException>()));
      expect(h.pos.connectivity.state.online, isTrue);
      expect(h.pos.session.resumeOffer, isNull);
      expect(h.pos.session.resumeStaff(), isFalse);
    });

    test('somebody is signed in: no offer', () async {
      await boot();
      await openTableThenOutage();
      expect(h.pos.session.resumeOffer, isNull);
    });
  });

  group('what the resumed staff member may do', () {
    test('no new order, no round, no void and no discount; the bill, the cash payment and the close go through, '
        'each queued operation marked', () async {
      await boot();
      final String room = await openTableThenOutage();
      h.clock.now = h.clock.now.add(const Duration(hours: 2));
      await restartOffline();
      expect(h.pos.session.resumeStaff(), isTrue);
      await settle();
      await h.pos.session.loadBootstrap();

      await expectLater(
        h.pos.orders.open(
          channel: PosChannel.takeaway,
          items: <PosLineInput>[
            PosLineInput(
              variantUuid: FakePosState.variantUuid(1),
              quantity: PosQuantity.parse('1'),
            ),
          ],
        ),
        refused(PosOffline.continuedReason),
      );
      final PosOrder order = await h.pos.orders.get(room);
      await expectLater(
        h.pos.orders.addRound(
          room,
          version: order.version,
          items: <PosLineInput>[
            PosLineInput(
              variantUuid: FakePosState.variantUuid(4),
              quantity: PosQuantity.parse('1'),
            ),
          ],
        ),
        refused(PosOffline.continuedReason),
      );
      await expectLater(
        h.pos.orders.voidItem(
          room,
          order.items.first.id,
          version: order.version,
        ),
        throwsA(isA<PosOfflineUnavailable>()),
      );
      await expectLater(
        h.pos.orders.discount(
          room,
          version: order.version,
          kind: PosDiscountKind.percent,
          value: '5',
        ),
        refused(PosOffline.continuedReason),
      );
      expect(h.offline.store.pendingCount, 0);

      final PosBilling billing = await h.pos.billing.createBills(
        room,
        version: order.version,
      );
      final PosBill bill = billing.bills.single;
      final PosBillResult paid = await h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: shift(),
      );
      expect(paid.order.isClosed, isTrue);
      final List<SyncOperation> queued = h.offline.store.pending;
      expect(queued.map((SyncOperation o) => o.type), <String>[
        SyncOpType.billCreate,
        SyncOpType.billPay,
        SyncOpType.orderClose,
      ]);
      for (final SyncOperation o in queued) {
        expect(o.staffId, 3);
        expect(o.payload[PosOffline.continuedKey], isTrue, reason: o.type);
        expect(o.payload[PosOffline.resumedKey], isTrue, reason: o.type);
      }
    });

    test('only the rights to finish an order and take payment, whatever the stored permissions: a manager may not '
        'unpair, give a discount, refund or touch the shift', () async {
      await boot(userId: 2);
      expect(
        ((await fetchRecord())!['permissions']! as List<Object?>),
        contains(PosPermissions.devicesManage),
      );
      await openTableThenOutage();
      await restartOffline();
      expect(h.pos.session.resumeStaff(), isTrue);
      final PosSession s = h.pos.session;
      expect(s.can(PosPermissions.takePayment), isTrue);
      expect(s.can(PosPermissions.ordersManage), isTrue);
      for (final String p in <String>[
        PosPermissions.devicesManage,
        PosPermissions.ordersDiscount,
        PosPermissions.ordersVoidItem,
        PosPermissions.ordersCancel,
        PosPermissions.refund,
        PosPermissions.openShift,
        PosPermissions.closeShift,
        PosPermissions.cashInOut,
        PosPermissions.reprintBill,
      ]) {
        expect(s.can(p), isFalse, reason: p);
      }
      expect(s.canUnpair, isFalse);
    });

    test('no staff token is sent on this run', () async {
      await boot();
      await openTableThenOutage();
      await restartOffline();
      expect(h.pos.session.resumeStaff(), isTrue);
      expect(h.client.auth.currentToken, isNull);
    });
  });

  group('the link comes back', () {
    test(
      'the mode ends at the first server answer, a PIN login is required, the record is kept (M5 S6 review 2 '
      'MEDIUM); the queue is sent with the marker and the server applies it',
      () async {
        await boot();
        final String room = await openTableThenOutage();
        h.clock.now = h.clock.now.add(const Duration(hours: 3));
        await restartOffline();
        expect(h.pos.session.resumeStaff(), isTrue);
        final PosOrder order = await h.pos.orders.get(room);
        final PosBilling billing = await h.pos.billing.createBills(
          room,
          version: order.version,
        );
        await h.pos.billing.pay(
          billing.bills.single.uuid,
          tenders: <PosTenderInput>[
            PosTenderInput.cash(billing.bills.single.total),
          ],
          shiftUuid: shift(),
        );

        h.adapter.offline = false;
        final SyncDrainResult synced = await h.offline.syncNow();
        await settle();
        expect(h.pos.session.staffResumed, isFalse);
        expect(h.pos.session.isSignedIn, isFalse);
        expect(h.pos.session.phase, PosSessionPhase.locked);
        // Kept until a PIN login, a sign-out the server hears, the 12 h, the shift's end or the terminal's end
        expect((await fetchRecord())?['staff_id'], 3);
        // The server answers: a PIN login is possible, nothing is offered
        expect(h.pos.session.resumeOffer, isNull);

        expect(synced.left, 0);
        expect(sync.received, hasLength(3));
        for (final Map<String, Object?> o in sync.received) {
          final Map<String, Object?> p =
              (o['payload']! as Map<Object?, Object?>).cast<String, Object?>();
          expect(p['staff_continued'], isTrue, reason: '${o['type']}');
          expect(p['staff_resumed'], isTrue, reason: '${o['type']}');
          expect(o['staff_id'], 3);
        }
        expect(
          h.offline.store.answered.map((SyncOperation o) => o.status).toSet(),
          <SyncOpStatus>{SyncOpStatus.applied},
        );

        // Offline again before anybody entered a PIN: offered again (M5 S6 review 2 MEDIUM, a flapping link)
        await h.goOffline();
        expect(h.pos.session.resumeOffer?.staffId, 3);
        expect(h.pos.session.resumeStaff(), isTrue);

        // A PIN login once the server answers replaces it
        h.adapter.offline = false;
        await expectLater(
          h.pos.session.loadBootstrap(),
          throwsA(isA<PosException>()),
        );
        await settle();
        expect(h.pos.session.isSignedIn, isFalse);
        h.clock.now = h.clock.now.add(const Duration(minutes: 1));
        await h.client.auth.signInStaff(userId: 2, pin: h.state.pins[2]!);
        expect((await fetchRecord())?['staff_id'], 2);
      },
    );
  });
}
