import 'dart:async';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:pos_hardware/testing.dart';

const PrinterConfig kitchen = PrinterConfig(
  id: 'kitchen',
  name: 'Oshxona',
  target: LanPrinterTarget('10.0.0.10'),
);
const PrinterConfig bar = PrinterConfig(
  id: 'bar',
  name: 'Bar',
  target: LanPrinterTarget('10.0.0.11'),
);

Uint8List doc(String text) =>
    (EscPosBuilder()
          ..init()
          ..textLine(text))
        .build();

String textOf(Uint8List bytes) => EscPosDocument.parse(bytes).lines.join('|');

class Harness {
  Harness({
    RetryPolicy retry = const RetryPolicy(),
    Duration? autoResumeAfter,
  }) {
    queue = PrintQueue(
      transportFor: (PrinterConfig c) =>
          transports.putIfAbsent(c.id, MemoryPrinterTransport.new),
      retryPolicy: retry,
      autoResumeAfter: autoResumeAfter,
      sleep: (Duration d) {
        sleeps.add(d);
        return Future<void>.value();
      },
    );
  }

  final Map<String, MemoryPrinterTransport> transports =
      <String, MemoryPrinterTransport>{};
  final List<Duration> sleeps = <Duration>[];
  late final PrintQueue queue;

  MemoryPrinterTransport operator [](String id) =>
      transports.putIfAbsent(id, MemoryPrinterTransport.new);
}

const PrinterException offline = PrinterException(
  PrinterErrorKind.offline,
  "Printerga ulanib bo'lmadi",
);
const PrinterException paperOut = PrinterException(
  PrinterErrorKind.paperOut,
  "Qog'oz tugadi",
);

void main() {
  test('prints jobs of one printer in order, printers in parallel', () async {
    final Harness h = Harness();
    h['kitchen'].delay = const Duration(milliseconds: 5);
    final PrintJob a = h.queue.submit(kitchen, doc('A'), title: 'A');
    final PrintJob b = h.queue.submit(kitchen, doc('B'), title: 'B');
    final PrintJob c = h.queue.submit(bar, doc('C'), title: 'C');
    await Future.wait(<Future<PrintJob>>[a.done, b.done, c.done]);
    expect(h['kitchen'].received.map(textOf), <String>['A', 'B']);
    expect(h['bar'].received.map(textOf), <String>['C']);
    expect(a.status, PrintJobStatus.done);
    expect(a.attempts, 1);
    expect(h.queue.jobsOf('kitchen'), isEmpty);
    expect(h.queue.stateOf('kitchen').status, PrinterStatus.ready);
  });

  test('retries a transient error with the policy delays', () async {
    final Harness h = Harness();
    h['kitchen'].failNext(2, offline);
    final PrintJob job = h.queue.submit(kitchen, doc('Marka'));
    await job.done;
    expect(job.status, PrintJobStatus.done);
    expect(job.attempts, 3);
    expect(h.sleeps, <Duration>[
      const Duration(seconds: 1),
      const Duration(seconds: 3),
    ]);
    expect(h['kitchen'].received, hasLength(1));
  });

  test('gives up after the last attempt and pauses the printer; retry resumes in order', () async {
    final Harness h = Harness();
    h['kitchen'].failNext(4, offline);
    final List<PrinterQueueState> states = <PrinterQueueState>[];
    final StreamSubscription<PrinterQueueState> sub = h.queue.stateChanges
        .listen(states.add);
    final PrintJob first = h.queue.submit(kitchen, doc('1'));
    final PrintJob second = h.queue.submit(kitchen, doc('2'));
    await h.queue.whenSettledOrFailed(first);
    await pumpEventQueue();

    expect(first.status, PrintJobStatus.failed);
    expect(first.attempts, 4);
    expect(first.lastErrorKind, PrinterErrorKind.offline);
    expect(second.status, PrintJobStatus.queued); // waits, order kept
    final PrinterQueueState s = h.queue.stateOf('kitchen');
    expect(s.paused, isTrue);
    expect(s.failed, 1);
    expect(s.pending, 1);
    expect(s.status, PrinterStatus.offline);
    expect(s.message, "Printerga ulanib bo'lmadi");
    expect(h.queue.failedJobs, <PrintJob>[first]);
    expect(states.any((PrinterQueueState x) => x.paused), isTrue);

    h.queue.retry(first.id);
    await Future.wait(<Future<PrintJob>>[first.done, second.done]);
    expect(h['kitchen'].received.map(textOf), <String>['1', '2']);
    expect(h.queue.stateOf('kitchen').paused, isFalse);
    await sub.cancel();
  });

  test('paper out needs a person: no automatic retry', () async {
    final Harness h = Harness();
    h['kitchen'].failNext(1, paperOut);
    final PrintJob job = h.queue.submit(kitchen, doc('Chek'));
    await h.queue.whenSettledOrFailed(job);
    expect(job.status, PrintJobStatus.failed);
    expect(job.attempts, 1);
    expect(h.sleeps, isEmpty);
    expect(h.queue.stateOf('kitchen').status, PrinterStatus.paperOut);

    h.queue.resume('kitchen');
    await job.done;
    expect(job.status, PrintJobStatus.done);
  });

  test('auto resume after a pause (unattended printing cashier)', () async {
    final Harness h = Harness(
      retry: RetryPolicy.none,
      autoResumeAfter: const Duration(minutes: 1),
    );
    h['kitchen'].failNext(1, offline);
    final PrintJob job = h.queue.submit(kitchen, doc('Telegram'));
    await job.done;
    expect(job.status, PrintJobStatus.done);
    expect(h['kitchen'].sendCalls, 2);
    expect(h.sleeps, contains(const Duration(minutes: 1)));
  });

  test('cash drawer jumps the queue', () async {
    final Harness h = Harness();
    h['kitchen'].delay = const Duration(milliseconds: 5);
    final PrintJob a = h.queue.submit(kitchen, doc('A'));
    final PrintJob b = h.queue.submit(kitchen, doc('B'));
    final PrintJob drawer = h.queue.openDrawer(
      kitchen,
      (EscPosBuilder()..openCashDrawer()).build(),
    );
    await Future.wait(<Future<PrintJob>>[a.done, b.done, drawer.done]);
    // A was already printing; the drawer goes before B
    expect(
      h['kitchen'].received.map(
        (Uint8List x) =>
            EscPosDocument.parse(x).drawerKicks > 0 ? 'drawer' : textOf(x),
      ),
      <String>['A', 'drawer', 'B'],
    );
    expect(h['kitchen'].drawerOpens, 1);
  });

  test('paper out stops the lane but the cash drawer still opens; a failed kick does not stop it', () async {
    final Harness h = Harness();
    h['kitchen'].failNext(1, paperOut);
    final PrintJob receipt = h.queue.submit(kitchen, doc('Chek'));
    await h.queue.whenSettledOrFailed(receipt);
    expect(h.queue.stateOf('kitchen').paused, isTrue);

    final PrintJob drawer = h.queue.openDrawer(
      kitchen,
      (EscPosBuilder()..openCashDrawer()).build(),
    );
    await h.queue.whenSettledOrFailed(drawer);
    expect(drawer.status, PrintJobStatus.done);
    expect(h['kitchen'].drawerOpens, 1);
    expect(receipt.status, PrintJobStatus.failed, reason: 'still waiting');

    // A kick that fails is dropped, the lane is not stopped by it
    h.queue.resume('kitchen');
    await receipt.done;
    h['kitchen'].failNext(4, offline);
    final PrintJob lost = h.queue.openDrawer(
      kitchen,
      (EscPosBuilder()..openCashDrawer()).build(),
    );
    await h.queue.whenSettledOrFailed(lost);
    expect(lost.status, PrintJobStatus.failed);
    expect(h.queue.stateOf('kitchen').paused, isFalse);
    expect(h.queue.failedJobs, isEmpty);
    final PrintJob next = h.queue.submit(kitchen, doc('Keyingi'));
    await next.done;
    expect(next.status, PrintJobStatus.done);
  });

  test('cancel removes a waiting or failed job', () async {
    final Harness h = Harness(retry: RetryPolicy.none);
    h['kitchen'].failNext(1, offline);
    final PrintJob a = h.queue.submit(kitchen, doc('A'));
    final PrintJob b = h.queue.submit(kitchen, doc('B'));
    await h.queue.whenSettledOrFailed(a);
    expect(h.queue.cancel(b.id), isTrue);
    expect(b.status, PrintJobStatus.canceled);
    expect(h.queue.cancel(a.id), isTrue);
    expect(await a.done, a);
    expect(a.status, PrintJobStatus.canceled);
    expect(h.queue.jobsOf('kitchen'), isEmpty);
    expect(h.queue.stateOf('kitchen').paused, isFalse);
    expect(h.queue.cancel('nope'), isFalse);
  });

  test('status check updates the printer state', () async {
    final Harness h = Harness();
    h['bar'].printerStatus = PrinterStatus.paperNearEnd;
    expect(await h.queue.checkStatus(bar), PrinterStatus.paperNearEnd);
    expect(h.queue.stateOf('bar').status, PrinterStatus.paperNearEnd);
    expect(h.queue.stateOf('unknown').status, PrinterStatus.unknown);
  });

  test(
    'PosPrinters renders a layout, opens the drawer and test-prints',
    () async {
      final Harness h = Harness();
      final PosPrinters printers = PosPrinters(queue: h.queue);
      final PrintLayout layout =
          (PrintWriter(PaperWidth.mm58)
                ..center('SCHYOT', bold: true)
                ..text('Bu fiskal chek emas')
                ..cut())
              .build(title: 'Schyot');
      final PrintJob job = await printers.print(
        kitchen,
        layout,
        openDrawer: true,
      );
      await job.done;
      expect(job.title, 'Schyot');
      final EscPosDocument printed = h['kitchen'].lastDocument;
      expect(printed.lines, <String>['SCHYOT', 'Bu fiskal chek emas']);
      expect(printed.drawerKicks, 1);
      expect(printed.cuts, 1);

      final PrintJob drawer = printers.openDrawer(kitchen);
      await drawer.done;
      expect(h['kitchen'].drawerOpens, 1);

      final PrintJob test = await printers.testPrint(
        kitchen.copyWith(textMode: PrintTextMode.text),
        now: DateTime(2026, 10, 22),
      );
      await test.done;
      expect(h['kitchen'].lastDocument.text, contains('SINOV CHOP ETISH'));
      await printers.dispose();
      expect(h['kitchen'].closed, isTrue);
    },
  );

  test('a changed printer config gets a new transport', () async {
    final List<PrinterConfig> created = <PrinterConfig>[];
    final PrintQueue queue = PrintQueue(
      transportFor: (PrinterConfig c) {
        created.add(c);
        return MemoryPrinterTransport();
      },
    );
    await queue.submit(kitchen, doc('1')).done;
    await queue.submit(kitchen, doc('2')).done;
    await queue
        .submit(
          kitchen.copyWith(target: const LanPrinterTarget('10.0.0.99')),
          doc('3'),
        )
        .done;
    expect(created, hasLength(2));
    await queue.dispose();
  });
}
