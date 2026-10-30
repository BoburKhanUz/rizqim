import 'dart:async';
import 'dart:typed_data';

import '../printer/lan_transport.dart';
import '../printer/printer.dart';
import '../printer/sunmi_transport.dart';
import '../printer/windows_spooler.dart';

enum PrintJobStatus {
  queued('Navbatda'),
  printing('Chop etilmoqda'),
  done('Chop etildi'),

  /// Urinishlar tugadi yoki odam aralashuvi kerak: [PrintQueue.retry] bilan qayta yuboriladi.
  failed('Chop etilmadi'),
  canceled('Bekor qilindi');

  const PrintJobStatus(this.label);

  final String label;

  bool get isFinal => this == done || this == canceled;
}

enum PrintJobKind { document, drawer }

/// Navbatdagi bitta ish.
class PrintJob {
  PrintJob._({
    required this.id,
    required this.printer,
    required this.kind,
    required this.title,
    required this.bytes,
    required this.createdAt,
  });

  final String id;
  final PrinterConfig printer;
  final PrintJobKind kind;

  /// "Chek 000123", "Marka: Osh sexi, Xona 3".
  final String title;
  final Uint8List bytes;
  final DateTime createdAt;

  PrintJobStatus status = PrintJobStatus.queued;
  int attempts = 0;

  /// Oxirgi xato (o'zbekcha matn).
  String? lastError;
  PrinterErrorKind? lastErrorKind;
  DateTime? finishedAt;

  final Completer<PrintJob> _settled = Completer<PrintJob>();

  /// Ish chop etilganda yoki bekor qilinganda tugaydi (muvaffaqiyatsiz urinishlardan keyin ham emas: `failed`
  /// ishni qayta yuborish mumkin). Faqat natijani kutish kerak bo'lsa [PrintQueue.whenSettledOrFailed].
  Future<PrintJob> get done => _settled.future;

  @override
  String toString() => 'PrintJob($id, ${printer.id}, $title, ${status.name})';
}

/// Printer bo'yicha holat (ekrandagi belgi uchun).
class PrinterQueueState {
  const PrinterQueueState({
    required this.printerId,
    required this.status,
    this.pending = 0,
    this.failed = 0,
    this.message,
    this.paused = false,
  });

  final String printerId;

  /// Oxirgi ma'lum holat (ish natijasi yoki so'rov).
  final PrinterStatus status;

  /// Navbatdagi va chop etilayotgan ishlar.
  final int pending;

  /// Qayta yuborishni kutayotgan ishlar.
  final int failed;
  final String? message;

  /// Xatodan keyin navbat to'xtagan (keyingi ishlar ham kutmoqda).
  final bool paused;
}

/// Qayta urinish qoidasi: [delays] har urinishdan keyingi kutish; jami urinishlar `delays.length + 1`.
class RetryPolicy {
  const RetryPolicy({
    this.delays = const <Duration>[
      Duration(seconds: 1),
      Duration(seconds: 3),
      Duration(seconds: 10),
    ],
  });

  /// Qayta urinmaslik.
  static const RetryPolicy none = RetryPolicy(delays: <Duration>[]);

  final List<Duration> delays;

  int get maxAttempts => delays.length + 1;
}

/// Printer sozlamasidan transport.
typedef TransportFactory = PrinterTransport Function(PrinterConfig config);

/// Standart transportlar: LAN, Windows spooler, Sunmi.
PrinterTransport defaultTransportFor(PrinterConfig config) =>
    switch (config.target) {
      final LanPrinterTarget t => LanPrinterTransport(
        t,
        language: config.language,
      ),
      final WindowsPrinterTarget t => WindowsPrinterTransport(t),
      SunmiPrinterTarget() => SunmiPrinterTransport(),
    };

/// Chop etish navbati: har printer uchun alohida ketma-ket navbat (bir printerga bir vaqtda bitta ish, turli
/// printerlar parallel), vaqtinchalik xatoda (ulanmadi, vaqt tugadi) [RetryPolicy] bo'yicha qayta urinadi.
///
/// Urinishlar tugasa yoki odam kerak bo'lsa (qog'oz tugadi, qopqoq ochiq) ish `failed` bo'ladi va **shu printer
/// navbati to'xtaydi**: keyingi markalar tartibini yo'qotmay kutib turadi. [retry] (yoki [resume]) navbatni davom
/// ettiradi; [autoResumeAfter] berilsa, shu vaqtdan keyin o'zi yana urinadi (kechasi "chop etuvchi" kassa uchun).
///
/// Diqqat: ish printerga yetib, javob olinmasa (masalan, ulanish yozishdan keyin uzildi) qayta urinish hujjatni
/// ikki marta chiqarishi mumkin — marka yo'qolgandan ko'ra ikki marta chiqqani yaxshi.
class PrintQueue {
  PrintQueue({
    this.transportFor = defaultTransportFor,
    this.retryPolicy = const RetryPolicy(),
    this.autoResumeAfter,
    Future<void> Function(Duration)? sleep,
    DateTime Function()? clock,
  }) : _sleep = sleep ?? Future<void>.delayed,
       _clock = clock ?? DateTime.now;

  final TransportFactory transportFor;
  final RetryPolicy retryPolicy;
  final Duration? autoResumeAfter;
  final Future<void> Function(Duration) _sleep;
  final DateTime Function() _clock;

  final Map<String, _PrinterLane> _lanes = <String, _PrinterLane>{};
  final StreamController<PrintJob> _jobs =
      StreamController<PrintJob>.broadcast();
  final StreamController<PrinterQueueState> _states =
      StreamController<PrinterQueueState>.broadcast();
  var _seq = 0;
  var _disposed = false;

  /// Har ish holati o'zgarganda.
  Stream<PrintJob> get jobUpdates => _jobs.stream;

  /// Printer holati o'zgarganda.
  Stream<PrinterQueueState> get stateChanges => _states.stream;

  /// Printer bo'yicha joriy holat (hali ish bo'lmagan printer — `unknown`).
  PrinterQueueState stateOf(String printerId) =>
      _lanes[printerId]?.state ??
      PrinterQueueState(printerId: printerId, status: PrinterStatus.unknown);

  /// Printerning tugamagan ishlari (navbatda, chop etilmoqda, xato).
  List<PrintJob> jobsOf(String printerId) =>
      List<PrintJob>.unmodifiable(_lanes[printerId]?.jobs ?? <PrintJob>[]);

  /// Barcha xato bilan to'xtagan ishlar ("Chop etilmadi" ro'yxati).
  List<PrintJob> get failedJobs => <PrintJob>[
    for (final _PrinterLane lane in _lanes.values)
      ...lane.jobs.where((PrintJob j) => j.status == PrintJobStatus.failed),
  ];

  /// Hujjatni navbatga qo'yadi va darhol qaytaradi.
  PrintJob submit(
    PrinterConfig printer,
    Uint8List bytes, {
    String title = '',
  }) => _add(printer, PrintJobKind.document, bytes, title, priority: false);

  /// Kassa qutisini ochish: navbat boshiga (kutib turgan markalardan oldin).
  PrintJob openDrawer(PrinterConfig printer, Uint8List kickBytes) => _add(
    printer,
    PrintJobKind.drawer,
    kickBytes,
    'Kassa qutisi',
    priority: true,
  );

  PrintJob _add(
    PrinterConfig printer,
    PrintJobKind kind,
    Uint8List bytes,
    String title, {
    required bool priority,
  }) {
    if (_disposed) throw StateError('PrintQueue is disposed');
    final PrintJob job = PrintJob._(
      id: 'pj${++_seq}',
      printer: printer,
      kind: kind,
      title: title,
      bytes: bytes,
      createdAt: _clock(),
    );
    final _PrinterLane lane = _laneFor(printer);
    if (priority) {
      // birinchi navbatdagi (chop etilmayotgan) ishdan oldin
      final int at = lane.jobs.indexWhere(
        (PrintJob j) => j.status == PrintJobStatus.queued,
      );
      lane.jobs.insert(at < 0 ? lane.jobs.length : at, job);
    } else {
      lane.jobs.add(job);
    }
    _emitJob(job);
    _emitState(lane);
    lane.kick();
    return job;
  }

  /// Ish chop etilguncha yoki `failed` bo'lguncha kutadi (testlar va "chek chiqdimi" tugmasi uchun).
  Future<PrintJob> whenSettledOrFailed(PrintJob job) async {
    if (job.status == PrintJobStatus.failed || job.status.isFinal) return job;
    return jobUpdates
        .firstWhere(
          (PrintJob j) =>
              j.id == job.id &&
              (j.status == PrintJobStatus.failed || j.status.isFinal),
        )
        .then((_) => job);
  }

  /// Xato bergan ishni qayta navbatga qo'yadi va printer navbatini davom ettiradi.
  void retry(String jobId) {
    for (final _PrinterLane lane in _lanes.values) {
      for (final PrintJob job in lane.jobs) {
        if (job.id == jobId && job.status == PrintJobStatus.failed) {
          job
            ..status = PrintJobStatus.queued
            ..attempts = 0;
          _emitJob(job);
          lane.paused = false;
          _emitState(lane);
          lane.kick();
          return;
        }
      }
    }
  }

  /// Printerning barcha xato ishlarini qayta yuboradi.
  void resume(String printerId) {
    final _PrinterLane? lane = _lanes[printerId];
    if (lane == null) return;
    for (final PrintJob job in lane.jobs) {
      if (job.status == PrintJobStatus.failed) {
        job
          ..status = PrintJobStatus.queued
          ..attempts = 0;
        _emitJob(job);
      }
    }
    lane.paused = false;
    _emitState(lane);
    lane.kick();
  }

  /// Navbatdagi yoki xato ishni bekor qiladi (chop etilayotganini emas).
  bool cancel(String jobId) {
    for (final _PrinterLane lane in _lanes.values) {
      for (final PrintJob job in lane.jobs) {
        if (job.id == jobId &&
            (job.status == PrintJobStatus.queued ||
                job.status == PrintJobStatus.failed)) {
          lane.jobs.remove(job);
          _finish(job, PrintJobStatus.canceled);
          if (!lane.jobs.any(
            (PrintJob j) => j.status == PrintJobStatus.failed,
          )) {
            lane.paused = false;
          }
          _emitState(lane);
          lane.kick();
          return true;
        }
      }
    }
    return false;
  }

  /// Printer holatini so'raydi va saqlaydi.
  Future<PrinterStatus> checkStatus(PrinterConfig printer) async {
    final _PrinterLane lane = _laneFor(printer);
    final PrinterStatus s = await lane.transport.status();
    lane.status = s;
    lane.message = null;
    _emitState(lane);
    return s;
  }

  /// Hamma transportni yopadi; tugamagan ishlar tashlanadi.
  Future<void> dispose() async {
    _disposed = true;
    for (final _PrinterLane lane in _lanes.values) {
      lane.disposed = true;
      await lane.transport.close();
    }
    await _jobs.close();
    await _states.close();
  }

  _PrinterLane _laneFor(PrinterConfig printer) {
    final _PrinterLane? existing = _lanes[printer.id];
    if (existing != null) {
      if (!identical(existing.config, printer)) {
        // sozlama o'zgargan bo'lishi mumkin: keyingi ish yangi transport bilan
        existing.updateConfig(printer, transportFor(printer));
      }
      return existing;
    }
    return _lanes[printer.id] = _PrinterLane(
      this,
      printer,
      transportFor(printer),
    );
  }

  void _finish(PrintJob job, PrintJobStatus status) {
    job
      ..status = status
      ..finishedAt = _clock();
    _emitJob(job);
    if (!job._settled.isCompleted) job._settled.complete(job);
  }

  void _emitJob(PrintJob job) {
    if (!_jobs.isClosed) _jobs.add(job);
  }

  void _emitState(_PrinterLane lane) {
    if (!_states.isClosed) _states.add(lane.state);
  }
}

class _PrinterLane {
  _PrinterLane(this.queue, this.config, this.transport);

  final PrintQueue queue;
  PrinterConfig config;
  PrinterTransport transport;
  final List<PrintJob> jobs = <PrintJob>[];
  PrinterStatus status = PrinterStatus.unknown;
  String? message;
  bool paused = false;
  bool running = false;
  bool disposed = false;

  PrinterQueueState get state => PrinterQueueState(
    printerId: config.id,
    status: status,
    pending: jobs
        .where(
          (PrintJob j) =>
              j.status == PrintJobStatus.queued ||
              j.status == PrintJobStatus.printing,
        )
        .length,
    failed: jobs
        .where((PrintJob j) => j.status == PrintJobStatus.failed)
        .length,
    message: message,
    paused: paused,
  );

  void updateConfig(PrinterConfig next, PrinterTransport nextTransport) {
    final PrinterTransport old = transport;
    config = next;
    transport = nextTransport;
    unawaited(old.close());
  }

  void kick() {
    if (running || disposed) return;
    // A stopped lane still opens the drawer (the cashier is holding the change)
    if (paused && _next() == null) return;
    running = true;
    unawaited(_run());
  }

  Future<void> _run() async {
    try {
      while (!disposed) {
        final PrintJob? job = _next();
        if (job == null) break;
        await _print(job);
      }
    } finally {
      running = false;
    }
  }

  /// Keyingi ish: navbat to'xtagan bo'lsa faqat kassa qutisi.
  PrintJob? _next() {
    for (final PrintJob j in jobs) {
      if (j.status == PrintJobStatus.queued &&
          (!paused || j.kind == PrintJobKind.drawer)) {
        return j;
      }
    }
    return null;
  }

  Future<void> _print(PrintJob job) async {
    final List<Duration> delays = queue.retryPolicy.delays;
    while (!disposed) {
      job
        ..status = PrintJobStatus.printing
        ..attempts += 1;
      queue._emitJob(job);
      queue._emitState(this);
      try {
        if (job.kind == PrintJobKind.drawer) {
          await transport.openDrawer(job.bytes);
        } else {
          await transport.send(job.bytes);
        }
        jobs.remove(job);
        status = PrinterStatus.ready;
        message = null;
        queue._finish(job, PrintJobStatus.done);
        queue._emitState(this);
        return;
      } on PrinterException catch (e) {
        job
          ..lastError = e.message
          ..lastErrorKind = e.kind;
        status = _statusFor(e.kind);
        message = e.message;
        final int retryIndex = job.attempts - 1;
        if (e.retryable && retryIndex < delays.length) {
          job.status = PrintJobStatus.queued;
          queue._emitJob(job);
          queue._emitState(this);
          await queue._sleep(delays[retryIndex]);
          if (job.status != PrintJobStatus.queued) return; // bekor qilindi
          continue;
        }
      } on Object catch (e) {
        job
          ..lastError = 'Printer xatosi: $e'
          ..lastErrorKind = PrinterErrorKind.failed;
        status = PrinterStatus.error;
        message = job.lastError;
        final int retryIndex = job.attempts - 1;
        if (retryIndex < delays.length) {
          job.status = PrintJobStatus.queued;
          queue._emitJob(job);
          await queue._sleep(delays[retryIndex]);
          if (job.status != PrintJobStatus.queued) return; // bekor qilindi
          continue;
        }
      }
      if (job.kind == PrintJobKind.drawer) {
        // A drawer kick is not kept for later (the drawer opening minutes later is worse) and does not stop the lane
        jobs.remove(job);
        job.status = PrintJobStatus.failed;
        queue._emitJob(job);
        queue._emitState(this);
        return;
      }
      // urinishlar tugadi yoki odam kerak: ish xato, navbat to'xtaydi
      job.status = PrintJobStatus.failed;
      paused = true;
      queue._emitJob(job);
      queue._emitState(this);
      final Duration? again = queue.autoResumeAfter;
      if (again != null) {
        unawaited(
          queue._sleep(again).then((_) {
            if (!disposed && paused) queue.resume(config.id);
          }),
        );
      }
      return;
    }
  }

  static PrinterStatus _statusFor(PrinterErrorKind kind) => switch (kind) {
    PrinterErrorKind.offline ||
    PrinterErrorKind.timeout => PrinterStatus.offline,
    PrinterErrorKind.paperOut => PrinterStatus.paperOut,
    PrinterErrorKind.coverOpen => PrinterStatus.coverOpen,
    PrinterErrorKind.unsupported ||
    PrinterErrorKind.failed => PrinterStatus.error,
  };
}
