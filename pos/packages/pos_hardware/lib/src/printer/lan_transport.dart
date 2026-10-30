import 'dart:async';
import 'dart:io';
import 'dart:typed_data';

import '../escpos/esc_pos_builder.dart';
import 'printer.dart';

/// Tarmoq printeri: har ish uchun yangi TCP ulanish (printer odatda bitta ulanishni qabul qiladi; ulanishni ochiq
/// ushlab tursak boshqa kassa chop eta olmaydi).
///
/// [checkPaperBeforePrint] yoqilgan bo'lsa yuborishdan oldin `DLE EOT` bilan qog'oz va qopqoq so'raladi: qog'oz
/// tugagan printerga marka "jim" yo'qolmasin. Javob bermaydigan printerda bu tekshiruv o'tkazib yuboriladi.
class LanPrinterTransport implements PrinterTransport {
  LanPrinterTransport(
    this.target, {
    this.connectTimeout = const Duration(seconds: 3),
    this.writeTimeout = const Duration(seconds: 10),
    this.statusTimeout = const Duration(milliseconds: 800),
    this.checkPaperBeforePrint = true,
    this.language = PrinterLanguage.escpos,
  });

  final LanPrinterTarget target;

  /// TSPL printerga `DLE EOT` yuborilmaydi (buyruq oqimini buzadi): holat `ESC !?` bilan so'raladi.
  final PrinterLanguage language;
  final Duration connectTimeout;
  final Duration writeTimeout;
  final Duration statusTimeout;
  final bool checkPaperBeforePrint;

  Future<Socket> _connect() async {
    try {
      final Socket socket = await Socket.connect(
        target.host,
        target.port,
        timeout: connectTimeout,
      );
      socket.setOption(SocketOption.tcpNoDelay, true);
      return socket;
    } on SocketException catch (e) {
      throw PrinterException(
        PrinterErrorKind.offline,
        'Printerga ulanib bo\'lmadi (${target.host}:${target.port}): ${e.osError?.message ?? e.message}',
      );
    }
  }

  @override
  Future<void> send(Uint8List bytes) => _write(bytes, checkPaper: true);

  Future<void> _write(Uint8List bytes, {required bool checkPaper}) async {
    final Socket socket = await _connect();
    final _Reader reader = _Reader(socket);
    try {
      if (checkPaper && checkPaperBeforePrint) {
        final PrinterStatus s = await _query(socket, reader);
        if (s == PrinterStatus.paperOut) {
          throw const PrinterException(
            PrinterErrorKind.paperOut,
            "Qog'oz tugadi. Yangi rulon qo'ying va qayta chop eting.",
          );
        }
        if (s == PrinterStatus.coverOpen) {
          throw const PrinterException(
            PrinterErrorKind.coverOpen,
            'Printer qopqog\'i ochiq. Yoping va qayta chop eting.',
          );
        }
      }
      socket.add(bytes);
      await socket.flush().timeout(writeTimeout);
    } on TimeoutException {
      throw const PrinterException(
        PrinterErrorKind.timeout,
        'Printer javob bermadi (vaqt tugadi).',
      );
    } on SocketException catch (e) {
      throw PrinterException(
        PrinterErrorKind.offline,
        'Printer bilan aloqa uzildi: ${e.message}',
      );
    } finally {
      await reader.cancel();
      await _close(socket);
    }
  }

  @override
  Future<PrinterStatus> status() async {
    final Socket socket;
    try {
      socket = await _connect();
    } on PrinterException {
      return PrinterStatus.offline;
    }
    final _Reader reader = _Reader(socket);
    try {
      return await _query(socket, reader);
    } on Object {
      return PrinterStatus.unknown;
    } finally {
      await reader.cancel();
      await _close(socket);
    }
  }

  /// Kassa qutisi qog'ozga bog'liq emas: qog'oz tugagan printer ham qutini ochadi (tekshiruvsiz yuboriladi).
  @override
  Future<void> openDrawer(Uint8List kickBytes) =>
      _write(kickBytes, checkPaper: false);

  @override
  Future<void> close() async {}

  /// `DLE EOT 2` (offline sababi) va `DLE EOT 4` (qog'oz sensori). Javob bo'lmasa — [PrinterStatus.unknown].
  /// TSPL printerda — `ESC !?` (bitta bayt, [parseTsplStatus]).
  Future<PrinterStatus> _query(Socket socket, _Reader reader) async {
    if (language == PrinterLanguage.tspl) {
      socket.add(const <int>[0x1B, 0x21, 0x3F]);
      await socket.flush();
      final List<int>? answer = await reader.take(1, statusTimeout);
      return answer == null
          ? PrinterStatus.unknown
          : parseTsplStatus(answer[0]);
    }
    socket.add(
      (EscPosBuilder()
            ..statusRequest(2)
            ..statusRequest(4))
          .build(),
    );
    await socket.flush();
    final List<int>? answer = await reader.take(2, statusTimeout);
    if (answer == null) return PrinterStatus.unknown;
    return parseStatusBytes(offline: answer[0], paper: answer[1]);
  }

  Future<void> _close(Socket socket) async {
    try {
      await socket.close().timeout(const Duration(seconds: 2));
    } on Object {
      socket.destroy();
    }
  }
}

/// TSPL `ESC !?` javobi (bitta bayt, bitlar): 0x00 tayyor; 0x01 bosh ochiq; 0x02 qog'oz tiqilgan; 0x04 qog'oz tugadi;
/// 0x08 lenta tugadi; 0x10 to'xtatilgan; 0x20 chop etilmoqda; 0x40 qopqoq ochiq; 0x80 boshqa xato.
PrinterStatus parseTsplStatus(int status) {
  if (status & 0x04 != 0) return PrinterStatus.paperOut;
  if (status & 0x41 != 0) return PrinterStatus.coverOpen;
  if (status & 0x8A != 0) return PrinterStatus.error;
  return PrinterStatus.ready;
}

/// `DLE EOT 2` va `DLE EOT 4` javoblaridan holat (Epson ESC/POS hujjati):
/// offline: bit 2 — qopqoq ochiq, bit 5 — qog'oz tugab to'xtadi, bit 6 — xato;
/// paper: bit 2–3 — qog'oz tugayapti, bit 5–6 — qog'oz tugadi.
PrinterStatus parseStatusBytes({required int offline, required int paper}) {
  // Har javobda bit 1 va 4 doim 1, bit 0 va 7 doim 0 bo'lishi kerak; bo'lmasa bu holat javobi emas
  bool valid(int b) => (b & 0x93) == 0x12;
  if (!valid(offline) || !valid(paper)) return PrinterStatus.unknown;
  if (paper & 0x60 != 0 || offline & 0x20 != 0) return PrinterStatus.paperOut;
  if (offline & 0x04 != 0) return PrinterStatus.coverOpen;
  if (offline & 0x40 != 0) return PrinterStatus.error;
  if (paper & 0x0C != 0) return PrinterStatus.paperNearEnd;
  return PrinterStatus.ready;
}

/// Soketdan kelgan baytlarni yig'adi (holat javobi uchun).
class _Reader {
  _Reader(Socket socket) {
    _sub = socket.listen(
      (Uint8List data) {
        _buffer.addAll(data);
        _wake();
      },
      onError: (Object _) => _wake(),
      onDone: () {
        _done = true;
        _wake();
      },
      cancelOnError: false,
    );
  }

  late final StreamSubscription<Uint8List> _sub;
  final List<int> _buffer = <int>[];
  bool _done = false;
  Completer<void>? _waiter;

  void _wake() {
    final Completer<void>? w = _waiter;
    _waiter = null;
    if (w != null && !w.isCompleted) w.complete();
  }

  Future<List<int>?> take(int count, Duration timeout) async {
    final DateTime deadline = DateTime.now().add(timeout);
    while (_buffer.length < count && !_done) {
      final Duration left = deadline.difference(DateTime.now());
      if (left <= Duration.zero) return null;
      final Completer<void> w = _waiter = Completer<void>();
      try {
        await w.future.timeout(left);
      } on TimeoutException {
        return null;
      }
    }
    if (_buffer.length < count) return null;
    final List<int> out = _buffer.sublist(0, count);
    _buffer.removeRange(0, count);
    return out;
  }

  Future<void> cancel() => _sub.cancel();
}
