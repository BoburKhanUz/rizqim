import 'dart:async';

import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'offline_test.dart' show OfflineHarness;

/// Wave M5 stage S6 fixes of the restart resume (F-till; docs/scale/M5_DESIGN.md §2 "App restart during an outage",
/// as built): the reviews' findings, each pinned here.
///
/// * Review 1 M1: a lock or a sign-out while the server is out of reach keeps the record, so the offer comes back.
/// * Review 1 M2: a device clock that goes back while resumed ends the resume; a monotonic stopwatch bounds it.
/// * Review 1 LOW1: the 12 h end never falls inside the payment and the close that follows it.
/// * Review 2 HIGH: the shift opened after the PIN login is known after the restart (an always-written copy); with
///   no known shift the payment is queued without `shift_uuid` (the server takes its current shift).
/// * Review 2 MEDIUM: a server answer sends the resumed staff member to the PIN keypad but keeps the record: a link
///   that drops again before a PIN login offers "Davom etish" again.
void main() {
  late OfflineHarness h;
  late FakeSyncBackend sync;

  Future<void> settle() async {
    for (var i = 0; i < 5; i++) {
      await Future<void>.delayed(Duration.zero);
    }
  }

  Future<void> boot({bool shiftFirst = true}) async {
    h = await OfflineHarness.create();
    FakeCashierBackend(h.state);
    sync = FakeSyncBackend(h.state);
    h.state.offlineStaffResume = true;
    if (shiftFirst) h.state.openShift();
    await h.client.auth.pairTerminal('ABCD-EFGH');
    await h.client.auth.signInStaff(userId: 3, pin: h.state.pins[3]!);
    sync.sessionStartedAt[3] = h.clock.now;
    await settle();
  }

  tearDown(() => h.pos.dispose());

  /// One request gets an answer from the server (whatever it says).
  Future<void> answer() async {
    try {
      await h.pos.session.loadBootstrap();
    } on PosException {
      // An answer all the same
    }
    await settle();
    expect(h.pos.connectivity.state.online, isTrue);
  }

  Future<bool> recordKept() async {
    await h.client.auth.resumeWritten;
    return h.keys.values.containsKey(AuthSession.staffResumeKey);
  }

  /// The app is closed and opened again with the network down (as m5_s6_resume_test.dart).
  Future<void> restartOffline() async {
    h.adapter.offline = true;
    await h.client.auth.resumeWritten;
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
    try {
      await h.client.auth.loadStaff();
      fail('the staff list came without a network');
    } on ApiException catch (e) {
      h.pos.connectivity.reportFailure(e);
    }
    expect(h.pos.connectivity.state.online, isFalse);
  }

  Future<String> openTableThenOutage() async {
    final String room = h.state.seedOrder(tableId: 1, guests: 2);
    await h.warmUp();
    await h.goOffline();
    return room;
  }

  Future<PosBill> billOf(String room) async {
    final PosOrder order = await h.pos.orders.get(room);
    final PosBilling billing = await h.pos.billing.createBills(
      room,
      version: order.version,
    );
    return billing.bills.single;
  }

  Future<PosBillResult> payCash(PosBill bill) => h.pos.billing.pay(
    bill.uuid,
    tenders: <PosTenderInput>[PosTenderInput.cash(bill.dueAmount)],
    // As the payment page does (payment_page.dart): the session's shift
    shiftUuid: h.pos.session.shift?.uuid,
  );

  group('M1: a lock or a sign-out while the server is out of reach keeps the record', () {
    test('a resumed staff member locks the screen offline: the offer comes back, also after another restart, and the '
        'table is paid', () async {
      await boot();
      final String room = await openTableThenOutage();
      h.clock.now = h.clock.now.add(const Duration(hours: 1));
      await restartOffline();
      expect(h.pos.session.resumeStaff(), isTrue);
      await settle();

      await h.pos.session.lock();
      await settle();
      expect(h.pos.session.isSignedIn, isFalse);
      expect(await recordKept(), isTrue);
      expect(h.pos.session.resumeOffer?.staffId, 3);

      await restartOffline();
      expect(h.pos.session.resumeOffer?.staffId, 3);
      expect(h.pos.session.resumeStaff(), isTrue);
      await settle();
      await h.pos.session.loadBootstrap();
      final PosBillResult paid = await payCash(await billOf(room));
      expect(paid.order.isClosed, isTrue);
    });

    test('a staff member signed in with a PIN locks during the outage (no restart): the offer is there, the table '
        'is paid', () async {
      await boot();
      final String room = await openTableThenOutage();
      await h.pos.session.lock();
      await settle();
      expect(await recordKept(), isTrue);
      expect(h.pos.session.resumeOffer?.name, 'Kamola Karimova');
      expect(h.pos.session.resumeStaff(), isTrue);
      await settle();
      await h.pos.session.loadBootstrap();
      final PosBillResult paid = await payCash(await billOf(room));
      expect(paid.order.isClosed, isTrue);
      expect(
        h.offline.store.pending.map((SyncOperation o) => o.staffId).toSet(),
        <int>{3},
      );
    });

    test('the screen locked by the idle timer offline keeps it too', () async {
      await boot();
      await openTableThenOutage();
      await h.client.auth.signOutStaff(reason: 'idle');
      await settle();
      expect(await recordKept(), isTrue);
      expect(h.pos.session.resumeOffer, isNotNull);
    });

    test('a sign-out the server hears deletes it', () async {
      await boot();
      await h.warmUp();
      await h.pos.session.lock();
      await settle();
      expect(await recordKept(), isFalse);
    });

    test('the end of the shift deletes it', () async {
      await boot();
      await h.warmUp();
      expect(h.pos.session.shift, isNotNull);
      h.pos.session.updateShift(null);
      await settle();
      expect(await recordKept(), isFalse);
    });
  });

  group('M2: the device clock while resumed', () {
    test('set back behind the newest queued operation (still after the PIN login): the resume ends and nothing more '
        'is queued as this staff member', () async {
      await boot();
      final DateTime pinAt = h.clock.now;
      final String room = await openTableThenOutage();
      h.clock.now = pinAt.add(const Duration(hours: 2));
      await restartOffline();
      expect(h.pos.session.resumeStaff(), isTrue);
      await settle();
      await h.pos.session.loadBootstrap();
      h.clock.now = pinAt.add(const Duration(hours: 11, minutes: 50));
      final PosBill bill = await billOf(room);
      expect(h.offline.latestKnownTime, h.clock.now);

      // Set back to 03:00 after the PIN login
      h.clock.now = pinAt.add(const Duration(hours: 3));
      expect(h.pos.session.staffResumed, isFalse);
      expect(h.pos.session.isSignedIn, isFalse);
      await expectLater(payCash(bill), throwsA(isA<PosOfflineUnavailable>()));
      expect(h.offline.store.pending.map((SyncOperation o) => o.type), <String>[
        SyncOpType.billCreate,
      ]);
      // And it is not offered again by that clock
      expect(h.pos.session.resumeOffer, isNull);
      expect(h.pos.session.resumeStaff(), isFalse);
    });

    test(
      'set back behind the moment of the resume itself: ends at the next check',
      () async {
        await boot();
        final DateTime pinAt = h.clock.now;
        await openTableThenOutage();
        h.clock.now = pinAt.add(const Duration(hours: 5));
        await restartOffline();
        expect(h.pos.session.resumeStaff(), isTrue);
        h.clock.now = pinAt.add(const Duration(hours: 4, minutes: 59));
        expect(h.pos.session.staffResumed, isFalse);
        expect(h.client.auth.staff, isNull);
      },
    );

    test('a clock that stands still (or runs slow): the monotonic stopwatch from the resume ends it when the 12 h '
        'are over in real time', () async {
      await boot();
      final DateTime pinAt = h.clock.now;
      final String room = await openTableThenOutage();
      h.clock.now = pinAt.add(const Duration(hours: 11));
      await restartOffline();
      Duration elapsed = const Duration(hours: 100);
      h.client.auth.monotonic = () => elapsed;
      expect(h.pos.session.resumeStaff(), isTrue);
      await settle();
      await h.pos.session.loadBootstrap();

      // 59 real minutes later, the device clock shows only 10 more: still on
      elapsed += const Duration(minutes: 59);
      h.clock.now = pinAt.add(const Duration(hours: 11, minutes: 10));
      expect(h.pos.session.staffResumed, isTrue);
      final PosBill bill = await billOf(room);

      // 61 real minutes after the resume: the 12 h are over, whatever the device clock says
      elapsed += const Duration(minutes: 2);
      h.clock.now = pinAt.add(const Duration(hours: 11, minutes: 11));
      expect(h.pos.session.staffResumed, isFalse);
      await expectLater(payCash(bill), throwsA(isA<PosOfflineUnavailable>()));
    });
  });

  group('LOW1: one composed action', () {
    test('the 12 h end between bill.pay and order.close: both are queued as the same staff member, marked', () async {
      await boot();
      final DateTime pinAt = h.clock.now;
      final String room = await openTableThenOutage();
      h.clock.now = pinAt.add(const Duration(hours: 11, minutes: 59));
      await restartOffline();
      expect(h.pos.session.resumeStaff(), isTrue);
      await settle();
      await h.pos.session.loadBootstrap();
      final PosBill bill = await billOf(room);

      final Future<PosBillResult> paying = payCash(bill);
      // The 12 h end while the bill.pay line is being written
      h.clock.now = pinAt.add(const Duration(hours: 12, seconds: 1));
      final PosBillResult paid = await paying;
      expect(paid.order.isClosed, isTrue);
      final List<SyncOperation> queued = h.offline.store.pending;
      expect(queued.map((SyncOperation o) => o.type), <String>[
        SyncOpType.billCreate,
        SyncOpType.billPay,
        SyncOpType.orderClose,
      ]);
      for (final SyncOperation o in queued) {
        expect(o.staffId, 3, reason: o.type);
        expect(o.payload[PosOffline.continuedKey], isTrue, reason: o.type);
        expect(o.payload[PosOffline.resumedKey], isTrue, reason: o.type);
      }
      // The action is over: now the end applies
      expect(h.pos.session.staffResumed, isFalse);
    });
  });

  group('HIGH: the shift after a restart', () {
    test('opened at the till after the PIN login (the usual day): known after the restart, the payment goes into it', () async {
      await boot(shiftFirst: false);
      final String room = h.state.seedOrder(tableId: 1, guests: 2);
      await h.warmUp(); // the bootstrap copy has no shift yet
      // As shift_page.dart does: open, then updateShift
      final PosShift shift = await h.pos.shifts.open(
        openingCash: const PosMoney.som(100000),
      );
      h.pos.session.updateShift(shift.toSummary());
      await h.pos.orders.openOrders();
      await h.goOffline();

      await restartOffline();
      expect(h.pos.session.resumeStaff(), isTrue);
      await settle();
      await h.pos.session.loadBootstrap(); // as app.dart does on staffSignedIn
      expect(h.pos.session.shift?.uuid, shift.uuid);
      final PosBillResult paid = await payCash(await billOf(room));
      expect(paid.order.isClosed, isTrue);
      final SyncOperation pay = h.offline.store.pending.firstWhere(
        (SyncOperation o) => o.type == SyncOpType.billPay,
      );
      expect(pay.payload['shift_uuid'], shift.uuid);
    });

    test('opened while operations were queued (the bootstrap copy is not written then): still known', () async {
      await boot(shiftFirst: false);
      final String room = h.state.seedOrder(tableId: 1, guests: 2);
      await h.warmUp();
      final PosShift shift = await h.pos.shifts.open(
        openingCash: const PosMoney.som(100000),
      );
      h.pos.session.updateShift(shift.toSummary());
      await h.goOffline();
      // A round queued: from here on no copy is taken
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
      // The shift changes again (read back from the server: the same shift)
      h.pos.session.updateShift(shift.toSummary());
      await restartOffline();
      expect(h.pos.session.resumeStaff(), isTrue);
      await settle();
      await h.pos.session.loadBootstrap();
      expect(h.pos.session.shift?.uuid, shift.uuid);
    });

    test(
      'a shift closed after the bootstrap copy was taken is not brought back',
      () async {
        await boot();
        await h.warmUp();
        expect(h.offline.storedBootstrap()?.shift, isNotNull);
        h.pos.session.updateShift(null);
        expect(h.offline.storedBootstrap()?.shift, isNull);
      },
    );

    test('safety net: resumed with no known shift, the payment is queued without shift_uuid and the server puts it in '
        'its current shift', () async {
      await boot(shiftFirst: false);
      final String room = h.state.seedOrder(tableId: 1, guests: 2);
      await h.warmUp();
      // The shift is opened on another screen of the server (this till never heard of it)
      h.state.openShift();
      await h.goOffline();
      await restartOffline();
      expect(h.pos.session.resumeStaff(), isTrue);
      await settle();
      await h.pos.session.loadBootstrap();
      expect(h.pos.session.shift, isNull);

      final PosBillResult paid = await payCash(await billOf(room));
      expect(paid.order.isClosed, isTrue);
      final SyncOperation pay = h.offline.store.pending.firstWhere(
        (SyncOperation o) => o.type == SyncOpType.billPay,
      );
      expect(pay.payload.containsKey('shift_uuid'), isFalse);

      h.adapter.offline = false;
      final SyncDrainResult s = await h.offline.syncNow();
      expect(s.left, 0);
      expect(
        h.offline.store.answered.map((SyncOperation o) => o.status).toSet(),
        <SyncOpStatus>{SyncOpStatus.applied},
      );
    });

    test('a staff member signed in with a PIN and no known shift is still told to open one', () async {
      await boot(shiftFirst: false);
      final String room = h.state.seedOrder(tableId: 1, guests: 2);
      await h.warmUp();
      await h.goOffline();
      await expectLater(
        payCash(await billOf(room)),
        throwsA(isA<PosOfflineUnavailable>()),
      );
    });
  });

  group('MEDIUM: a flapping link', () {
    test('one server answer sends the resumed staff member to the PIN keypad but keeps the record; the link drops '
        'again: the offer is back and the table is paid', () async {
      await boot();
      final String room = await openTableThenOutage();
      await restartOffline();
      expect(h.pos.session.resumeStaff(), isTrue);
      await settle();

      // A probe gets through for a moment, then the link is gone again
      h.adapter.offline = false;
      await answer();
      expect(h.pos.session.staffResumed, isFalse);
      expect(h.pos.session.isSignedIn, isFalse);
      expect(await recordKept(), isTrue);
      expect(
        h.pos.session.resumeOffer,
        isNull,
      ); // the server answers: a PIN login is possible

      h.adapter.offline = true;
      try {
        await h.client.auth.loadStaff();
      } on ApiException catch (e) {
        h.pos.connectivity.reportFailure(e);
      }
      await settle();
      expect(h.pos.connectivity.state.online, isFalse);
      expect(h.pos.session.resumeOffer?.staffId, 3);
      expect(h.pos.session.resumeStaff(), isTrue);
      await settle();
      await h.pos.session.loadBootstrap();
      final PosBillResult paid = await payCash(await billOf(room));
      expect(paid.order.isClosed, isTrue);
    });

    test('a PIN login after the answer replaces it; a sign-out online then deletes it', () async {
      await boot();
      await openTableThenOutage();
      await restartOffline();
      expect(h.pos.session.resumeStaff(), isTrue);
      h.adapter.offline = false;
      await answer();
      expect(await recordKept(), isTrue);

      h.clock.now = h.clock.now.add(const Duration(minutes: 5));
      await h.client.auth.signInStaff(userId: 2, pin: h.state.pins[2]!);
      await settle();
      expect(h.client.auth.resumableStaff, isNull); // somebody is signed in
      await h.pos.session.lock();
      await settle();
      expect(await recordKept(), isFalse);
    });
  });
}
