import 'package:fake_async/fake_async.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

/// Wave M2 fix round 2: paced event reads (throttle with spread and window), the R/S/doubt bookkeeping and the
/// relevance table.
void main() {
  Map<String, Object?> rev(Object? token) => <String, Object?>{
    'rev': <String, Object?>{'pos-branch.5': token},
  };

  const RefreshPacing pacing = RefreshPacing(
    window: Duration(seconds: 5),
    spread: Duration(milliseconds: 1500),
  );

  group('RefreshThrottle', () {
    test('the first request after quiet fires after the spread only', () {
      fakeAsync((FakeAsync async) {
        var fires = 0;
        final RefreshThrottle t = RefreshThrottle(
          pacing: pacing,
          random: () => 0.5,
          onFire: () {
            fires++;
            return true;
          },
        )..request();
        async.elapse(const Duration(milliseconds: 749));
        expect(fires, 0);
        async.elapse(const Duration(milliseconds: 1));
        expect(fires, 1);
        t.dispose();
      });
    });

    test('a burst during the window collapses into ONE read at its end plus a new spread', () {
      fakeAsync((FakeAsync async) {
        final List<Duration> at = <Duration>[];
        final RefreshThrottle t = RefreshThrottle(
          pacing: pacing,
          random: () => 0,
          onFire: () {
            at.add(async.elapsed);
            return true;
          },
        )..request();
        async.elapse(Duration.zero);
        for (var i = 0; i < 20; i++) {
          async.elapse(const Duration(milliseconds: 100));
          t.request();
        }
        async.elapse(const Duration(seconds: 20));
        expect(at, <Duration>[Duration.zero, const Duration(seconds: 5)]);
        t.dispose();
      });
    });

    test('the spread stays within U(0, spread)', () {
      for (final double r in <double>[0, 0.25, 0.999999, 1, 7]) {
        fakeAsync((FakeAsync async) {
          Duration? at;
          RefreshThrottle(
            pacing: pacing,
            random: () => r,
            onFire: () {
              at = async.elapsed;
              return true;
            },
          ).request();
          async.elapse(const Duration(seconds: 2));
          expect(at, isNotNull);
          expect(
            at! <= const Duration(milliseconds: 1500),
            isTrue,
            reason: '$r',
          );
          expect(at! >= Duration.zero, isTrue);
        });
      }
    });

    test(
      'the default random is injectable (app test harnesses set it to zero)',
      () {
        final double Function() saved = RefreshThrottle.defaultRandom;
        addTearDown(() => RefreshThrottle.defaultRandom = saved);
        RefreshThrottle.defaultRandom = () => 1;
        fakeAsync((FakeAsync async) {
          var fired = false;
          RefreshThrottle(pacing: pacing, onFire: () => fired = true).request();
          async.elapse(const Duration(milliseconds: 1499));
          expect(fired, isFalse);
          async.elapse(const Duration(milliseconds: 1));
          expect(fired, isTrue);
        });
      },
    );

    test(
      'a fire that read nothing opens no window: the next request is prompt',
      () {
        fakeAsync((FakeAsync async) {
          var fires = 0;
          final RefreshThrottle t = RefreshThrottle(
            pacing: pacing,
            random: () => 0,
            onFire: () {
              fires++;
              return false;
            },
          )..request();
          async.elapse(const Duration(milliseconds: 10));
          t.request();
          async.elapse(const Duration(milliseconds: 10));
          expect(fires, 2);
          expect(t.inWindow, isFalse);
        });
      },
    );
  });

  group('ChannelRevision R / S / doubt', () {
    test('ignored events: S or S+1 settle; below S nothing; a jump settles and sets doubt', () {
      final ChannelRevision r = ChannelRevision('private-pos-branch.5');
      r.ignored(rev('50'));
      expect(r.settled, isNull, reason: 'nothing known yet');
      r.read(BigInt.from(100));
      r.ignored(rev('101'));
      expect(r.settled, BigInt.from(101));
      r.ignored(rev('101'));
      expect(r.settled, BigInt.from(101));
      expect(r.doubt, isFalse);
      r.ignored(rev('99'));
      expect(r.settled, BigInt.from(101), reason: 'a late older event');
      expect(r.doubt, isFalse);
      r.ignored(rev('104'));
      expect(r.settled, BigInt.from(104));
      expect(r.doubt, isTrue);
      expect(r.known, BigInt.from(100), reason: 'R moves only with a read');
      // A relevant event right after a jump is S+1 but still a catch-up read
      expect(r.event(rev('105')), RevisionVerdict.read);
      expect(r.rereadCount, 1);
      expect(r.nextCount, 0);
      // A random new base far below (Redis flushed) is not "older": a jump
      r.read(BigInt.from(500000));
      expect(r.doubt, isFalse);
      r.ignored(rev('7'));
      expect(r.settled, BigInt.from(7));
      expect(r.doubt, isTrue);
    });

    test('heartbeat: rev != S missed; rev == S with doubt only where the screen reads on doubt', () {
      final ChannelRevision r = ChannelRevision('private-pos-branch.5')
        ..read(BigInt.from(100))
        ..ignored(rev('102'));
      expect(r.heartbeat(rev('102')), HeartbeatVerdict.doubt);
      expect(r.heartbeatRereads, 1);
      expect(
        r.heartbeat(rev('102'), readOnDoubt: false),
        HeartbeatVerdict.doubt,
      );
      expect(r.heartbeatRereads, 1, reason: 'the display does not read');
      expect(r.heartbeat(rev('103')), HeartbeatVerdict.missed);
      r.read(BigInt.from(103));
      expect(r.heartbeat(rev('103')), HeartbeatVerdict.fine);
    });

    test('a read proving at least S settles and clears doubt; a read below S keeps them', () {
      final ChannelRevision r = ChannelRevision('private-pos-branch.5')
        ..read(BigInt.from(100))
        ..ignored(rev('105'));
      r.read(BigInt.from(103));
      expect(r.known, BigInt.from(103));
      expect(r.settled, BigInt.from(105));
      expect(r.doubt, isTrue);
      r.read(BigInt.from(105));
      expect(r.doubt, isFalse);
      // A failed read forgets R and doubts
      r.forget();
      expect(r.known, isNull);
      expect(r.doubt, isTrue);
      // A read that proves nothing forgets R only
      r.read(BigInt.from(105));
      r.read(null);
      expect(r.known, isNull);
      expect(r.doubt, isFalse);
    });
  });

  group('PacedRefresh', () {
    late List<PacedReason> reads;
    late PacedRefresh p;

    /// A screen that read token 100.
    void open({bool readOnDoubt = true}) {
      reads = <PacedReason>[];
      p = PacedRefresh(
        channel: 'private-pos-branch.5',
        pacing: pacing,
        readOnDoubt: readOnDoubt,
        random: () => 0,
        onRead: reads.add,
      );
      p.readStarted();
      p.readDone(BigInt.from(100));
    }

    /// A full read of the screen (paced or not) that proves [token].
    void serve(BigInt token) {
      p.readStarted();
      p.readDone(token);
    }

    test('a relevant event reads promptly; one of the same commit is skipped; R is skipped', () {
      fakeAsync((FakeAsync async) {
        open();
        expect(p.event(rev('100'), relevant: true), PacedVerdict.skipped);
        expect(p.event(rev('101'), relevant: true), PacedVerdict.asked);
        expect(
          p.event(rev('101'), relevant: true),
          PacedVerdict.skipped,
          reason: 'the second event of the same commit',
        );
        async.flushMicrotasks();
        async.elapse(Duration.zero);
        expect(reads, <PacedReason>[PacedReason.event]);
        p.dispose();
      });
    });

    test('ignored events feed S: the next relevant event is not a gap, the heartbeat is fine', () {
      fakeAsync((FakeAsync async) {
        open();
        expect(p.event(rev('101'), relevant: false), PacedVerdict.ignored);
        expect(p.event(rev('102'), relevant: false), PacedVerdict.ignored);
        expect(p.heartbeat(rev('102')), isFalse);
        expect(p.event(rev('103'), relevant: true), PacedVerdict.asked);
        expect(p.revision!.nextCount, 1);
        expect(p.revision!.rereadCount, 0);
        async.elapse(const Duration(seconds: 1));
        expect(reads, hasLength(1));
        p.dispose();
      });
    });

    test('a heartbeat ahead of S is a paced read; with doubt only on tills and the KDS', () {
      fakeAsync((FakeAsync async) {
        open();
        expect(p.heartbeat(rev('101')), isTrue);
        async.elapse(Duration.zero);
        expect(reads, <PacedReason>[PacedReason.heartbeat]);
        serve(BigInt.from(101));
        p.event(rev('104'), relevant: false); // a jump: doubt
        async.elapse(const Duration(seconds: 6));
        expect(p.heartbeat(rev('104')), isTrue);
        async.elapse(Duration.zero);
        expect(reads, hasLength(2));
        p.dispose();

        open(readOnDoubt: false);
        p.event(rev('104'), relevant: false);
        expect(p.heartbeat(rev('104')), isFalse, reason: 'the display');
        expect(p.heartbeat(rev('105')), isTrue, reason: 'missed still reads');
        p.dispose();
      });
    });

    test('a paced read is dropped when another read already shows everything that asked for it', () {
      fakeAsync((FakeAsync async) {
        open();
        p.event(rev('101'), relevant: true);
        async.elapse(Duration.zero);
        expect(reads, hasLength(1));
        // Inside the window: two more events, then an own action reads at once and proves 103
        p.event(rev('102'), relevant: true);
        p.event(rev('103'), relevant: true);
        serve(BigInt.from(103));
        async.elapse(const Duration(seconds: 10));
        expect(reads, hasLength(1), reason: 'the trailing read is covered');
        // A newer event than the read proved: read
        p.event(rev('104'), relevant: true);
        async.elapse(const Duration(seconds: 10));
        expect(reads, hasLength(2));
        p.dispose();
      });
    });

    test('a failed read forgets R, doubts and asks again ONCE (paced); a failed retry leaves it to the heartbeat', () {
      fakeAsync((FakeAsync async) {
        open();
        p.event(rev('101'), relevant: true);
        async.elapse(Duration.zero);
        p.readStarted();
        p.readFailed();
        expect(p.revision!.known, isNull);
        expect(p.revision!.doubt, isTrue);
        expect(p.retries, 1);
        // The retry waits for the end of the window
        async.elapse(const Duration(seconds: 4));
        expect(reads, hasLength(1));
        async.elapse(const Duration(seconds: 2));
        expect(reads, <PacedReason>[PacedReason.event, PacedReason.event]);
        // The retry fails too: no further retry (no loop while offline)
        p.readStarted();
        p.readFailed();
        expect(p.retries, 1);
        async.elapse(const Duration(seconds: 20));
        expect(reads, hasLength(2));
        // The heartbeat at S with doubt re-reads
        expect(p.heartbeat(rev('101')), isTrue);
        async.elapse(Duration.zero);
        expect(reads.last, PacedReason.heartbeat);
        p.dispose();
      });
    });

    test(
      'without a channel (local LAN events) every relevant event reads, paced',
      () {
        fakeAsync((FakeAsync async) {
          final List<PacedReason> got = <PacedReason>[];
          final PacedRefresh local = PacedRefresh(
            channel: null,
            pacing: pacing,
            random: () => 0,
            onRead: got.add,
          );
          local.event(const <String, Object?>{}, relevant: true);
          async.elapse(Duration.zero);
          expect(got, hasLength(1));
          local.event(const <String, Object?>{}, relevant: false);
          expect(local.pending, isTrue, reason: 'the read never started');
          local.readStarted();
          local.event(const <String, Object?>{}, relevant: true);
          async.elapse(const Duration(seconds: 6));
          expect(got, hasLength(2));
          local.dispose();
        });
      },
    );
  });

  group('ScreenRelevance', () {
    test('KDS: kitchen changes and unknown names, not bills, payments, fiscal, shift, menu, cart', () {
      for (final String n in <String>[
        'order.created',
        'order.status_changed',
        'order.assigned',
        'order.items_changed',
        'kitchen.item_status_changed',
        'kitchen.order_assembled',
        'queue.ready',
        'something.new',
      ]) {
        expect(ScreenRelevance.kitchen(n), isTrue, reason: n);
      }
      for (final String n in <String>[
        'order.bill_changed',
        'order.bill_requested',
        'fiscal.receipt_updated',
        'shift.changed',
        'booking.changed',
        'qr.paid',
        'order.course_ready',
        'order.transferred',
        'menu.availability_changed',
        'waitlist.changed',
        'table.state_changed',
        'display.cart',
        'pos.lan_changed',
        'bill.print_requested',
      ]) {
        expect(ScreenRelevance.kitchen(n), isFalse, reason: n);
      }
    });

    test('floors: order and table state, not payment or fiscal bill changes; display: own till and queue', () {
      Map<String, Object?> bill(String change) => <String, Object?>{
        'change': change,
      };
      expect(
        ScreenRelevance.floor('order.bill_changed', bill('printed')),
        isTrue,
      );
      expect(
        ScreenRelevance.floor('order.bill_changed', bill('created')),
        isTrue,
      );
      expect(
        ScreenRelevance.floor('order.bill_changed', bill('payment')),
        isFalse,
      );
      expect(
        ScreenRelevance.floor('order.bill_changed', bill('fiscal')),
        isFalse,
      );
      for (final String n in <String>[
        'order.transferred',
        'order.bill_requested',
        'kitchen.item_status_changed',
        'kitchen.order_assembled',
        'booking.changed',
        'table.state_changed',
      ]) {
        expect(
          ScreenRelevance.floor(n, const <String, Object?>{}),
          isTrue,
          reason: n,
        );
      }
      for (final String n in <String>[
        'fiscal.receipt_updated',
        'shift.changed',
        'queue.ready',
        'waitlist.changed',
        'qr.paid',
        'menu.availability_changed',
        'display.cart',
      ]) {
        expect(
          ScreenRelevance.floor(n, const <String, Object?>{}),
          isFalse,
          reason: n,
        );
      }
      expect(ScreenRelevance.pickup('queue.handed'), isTrue);
      expect(ScreenRelevance.pickup('order.items_changed'), isFalse);
      final Map<String, Object?> cart = <String, Object?>{'terminal': 't1'};
      expect(ScreenRelevance.display('display.cart', cart, 't1'), isTrue);
      expect(ScreenRelevance.display('display.cart', cart, 't2'), isFalse);
      expect(ScreenRelevance.display('queue.ready', cart, 't2'), isTrue);
      expect(ScreenRelevance.display('order.created', cart, null), isFalse);
    });
  });
}
