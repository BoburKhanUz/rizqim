import 'dart:ffi';
import 'dart:io';
import 'dart:isolate';
import 'dart:typed_data';

import 'package:ffi/ffi.dart';

import 'printer.dart';

/// Windows spooler'iga "raw" yozish (USB printer drayveri baytlarni o'zgartirmaydi). Testlarda soxtasi qo'yiladi.
abstract interface class RawSpooler {
  /// O'rnatilgan printerlar nomlari (sozlamada tanlash uchun).
  Future<List<String>> printers();

  /// [bytes] ni `RAW` hujjat sifatida yuboradi.
  Future<void> writeRaw(
    String printerName,
    String documentName,
    Uint8List bytes,
  );

  /// Spooler holati bitlari (`PRINTER_INFO_6.dwStatus`).
  Future<int> statusBits(String printerName);
}

/// USB (Windows) printer: baytlar spooler orqali.
class WindowsPrinterTransport implements PrinterTransport {
  WindowsPrinterTransport(
    this.target, {
    this.spooler,
    this.documentName = 'Rizqim POS',
  });

  final WindowsPrinterTarget target;
  final String documentName;

  /// `null` — Windows'da haqiqiy spooler ([FfiRawSpooler]).
  final RawSpooler? spooler;

  RawSpooler get _active {
    final RawSpooler? s = spooler;
    if (s != null) return s;
    if (!Platform.isWindows) {
      throw const PrinterException(
        PrinterErrorKind.unsupported,
        'USB printer (Windows spooler) faqat Windows kompyuterida ishlaydi.',
      );
    }
    return const FfiRawSpooler();
  }

  @override
  Future<void> send(Uint8List bytes) async {
    final RawSpooler spooler = _active;
    try {
      await spooler.writeRaw(target.printerName, documentName, bytes);
    } on PrinterException {
      rethrow;
    } on Object catch (e) {
      throw PrinterException(
        PrinterErrorKind.failed,
        'Windows printer xatosi: $e',
      );
    }
  }

  @override
  Future<PrinterStatus> status() async {
    try {
      return statusFromSpoolerBits(
        await _active.statusBits(target.printerName),
      );
    } on PrinterException catch (e) {
      return e.kind == PrinterErrorKind.offline
          ? PrinterStatus.offline
          : PrinterStatus.unknown;
    } on Object {
      return PrinterStatus.unknown;
    }
  }

  @override
  Future<void> openDrawer(Uint8List kickBytes) => send(kickBytes);

  @override
  Future<void> close() async {}
}

/// `PRINTER_STATUS_*` bitlaridan holat. USB termoprinter drayverlari ko'pincha hech narsa aytmaydi (0) — bu
/// [PrinterStatus.ready] emas, [PrinterStatus.unknown].
PrinterStatus statusFromSpoolerBits(int bits) {
  const int error = 0x2;
  const int paperJam = 0x8;
  const int paperOut = 0x10;
  const int offline = 0x80;
  const int notAvailable = 0x1000;
  const int doorOpen = 0x400000;
  if (bits & paperOut != 0) return PrinterStatus.paperOut;
  if (bits & doorOpen != 0) return PrinterStatus.coverOpen;
  if (bits & (offline | notAvailable) != 0) return PrinterStatus.offline;
  if (bits & (error | paperJam) != 0) return PrinterStatus.error;
  return PrinterStatus.unknown;
}

/// `winspool.drv` orqali haqiqiy amalga oshirish (dart:ffi, plagin kerak emas). Chaqiruvlar UI'ni to'xtatmasligi
/// uchun alohida isolate'da bajariladi.
class FfiRawSpooler implements RawSpooler {
  const FfiRawSpooler();

  @override
  Future<List<String>> printers() => Isolate.run(_enumPrinters);

  @override
  Future<void> writeRaw(
    String printerName,
    String documentName,
    Uint8List bytes,
  ) => Isolate.run(() => _writeRaw(printerName, documentName, bytes));

  @override
  Future<int> statusBits(String printerName) =>
      Isolate.run(() => _statusBits(printerName));
}

// --- winspool.drv bindings --------------------------------------------------------------------------------------

final class _DocInfo1 extends Struct {
  external Pointer<Utf16> pDocName;
  external Pointer<Utf16> pOutputFile;
  external Pointer<Utf16> pDatatype;
}

final class _PrinterInfo4 extends Struct {
  external Pointer<Utf16> pPrinterName;
  external Pointer<Utf16> pServerName;
  @Uint32()
  external int attributes;
}

typedef _OpenPrinterC = Int32 Function(
  Pointer<Utf16>,
  Pointer<Pointer<Void>>,
  Pointer<Void>,
);
typedef _OpenPrinterD = int Function(
  Pointer<Utf16>,
  Pointer<Pointer<Void>>,
  Pointer<Void>,
);
typedef _HandleC = Int32 Function(Pointer<Void>);
typedef _HandleD = int Function(Pointer<Void>);
typedef _StartDocC = Uint32 Function(Pointer<Void>, Uint32, Pointer<_DocInfo1>);
typedef _StartDocD = int Function(Pointer<Void>, int, Pointer<_DocInfo1>);
typedef _WritePrinterC = Int32 Function(
  Pointer<Void>,
  Pointer<Uint8>,
  Uint32,
  Pointer<Uint32>,
);
typedef _WritePrinterD = int Function(
  Pointer<Void>,
  Pointer<Uint8>,
  int,
  Pointer<Uint32>,
);
typedef _EnumPrintersC = Int32 Function(
  Uint32,
  Pointer<Utf16>,
  Uint32,
  Pointer<Uint8>,
  Uint32,
  Pointer<Uint32>,
  Pointer<Uint32>,
);
typedef _EnumPrintersD = int Function(
  int,
  Pointer<Utf16>,
  int,
  Pointer<Uint8>,
  int,
  Pointer<Uint32>,
  Pointer<Uint32>,
);
typedef _GetPrinterC = Int32 Function(
  Pointer<Void>,
  Uint32,
  Pointer<Uint8>,
  Uint32,
  Pointer<Uint32>,
);
typedef _GetPrinterD = int Function(
  Pointer<Void>,
  int,
  Pointer<Uint8>,
  int,
  Pointer<Uint32>,
);
typedef _GetLastErrorC = Uint32 Function();
typedef _GetLastErrorD = int Function();

class _Winspool {
  _Winspool()
    : _lib = DynamicLibrary.open('winspool.drv'),
      _kernel = DynamicLibrary.open('kernel32.dll');

  final DynamicLibrary _lib;
  final DynamicLibrary _kernel;

  late final _OpenPrinterD openPrinter = _lib
      .lookupFunction<_OpenPrinterC, _OpenPrinterD>('OpenPrinterW');
  late final _HandleD closePrinter = _lib.lookupFunction<_HandleC, _HandleD>(
    'ClosePrinter',
  );
  late final _StartDocD startDoc = _lib.lookupFunction<_StartDocC, _StartDocD>(
    'StartDocPrinterW',
  );
  late final _HandleD endDoc = _lib.lookupFunction<_HandleC, _HandleD>(
    'EndDocPrinter',
  );
  late final _HandleD startPage = _lib.lookupFunction<_HandleC, _HandleD>(
    'StartPagePrinter',
  );
  late final _HandleD endPage = _lib.lookupFunction<_HandleC, _HandleD>(
    'EndPagePrinter',
  );
  late final _WritePrinterD writePrinter = _lib
      .lookupFunction<_WritePrinterC, _WritePrinterD>('WritePrinter');
  late final _EnumPrintersD enumPrinters = _lib
      .lookupFunction<_EnumPrintersC, _EnumPrintersD>('EnumPrintersW');
  late final _GetPrinterD getPrinter = _lib
      .lookupFunction<_GetPrinterC, _GetPrinterD>('GetPrinterW');
  late final _GetLastErrorD getLastError = _kernel
      .lookupFunction<_GetLastErrorC, _GetLastErrorD>('GetLastError');

  /// Printerni ochadi; topilmasa — `offline`.
  Pointer<Void> open(String name, Arena arena) {
    final Pointer<Pointer<Void>> handle = arena<Pointer<Void>>();
    if (openPrinter(name.toNativeUtf16(allocator: arena), handle, nullptr) ==
        0) {
      throw PrinterException(
        PrinterErrorKind.offline,
        'Windows printeri topilmadi: "$name" (xato ${getLastError()}).',
      );
    }
    return handle.value;
  }
}

void _writeRaw(String printerName, String documentName, Uint8List bytes) {
  final _Winspool w = _Winspool();
  using((Arena arena) {
    final Pointer<Void> h = w.open(printerName, arena);
    try {
      final Pointer<_DocInfo1> doc = arena<_DocInfo1>();
      doc.ref
        ..pDocName = documentName.toNativeUtf16(allocator: arena)
        ..pOutputFile = nullptr
        ..pDatatype = 'RAW'.toNativeUtf16(allocator: arena);
      if (w.startDoc(h, 1, doc) == 0) {
        throw PrinterException(
          PrinterErrorKind.failed,
          'StartDocPrinter xatosi ${w.getLastError()}',
        );
      }
      try {
        if (w.startPage(h) == 0) {
          throw PrinterException(
            PrinterErrorKind.failed,
            'StartPagePrinter xatosi ${w.getLastError()}',
          );
        }
        final Pointer<Uint8> buffer = arena<Uint8>(bytes.length);
        buffer.asTypedList(bytes.length).setAll(0, bytes);
        final Pointer<Uint32> written = arena<Uint32>();
        final int ok = w.writePrinter(h, buffer, bytes.length, written);
        w.endPage(h);
        if (ok == 0 || written.value != bytes.length) {
          throw PrinterException(
            PrinterErrorKind.failed,
            'WritePrinter xatosi ${w.getLastError()} (${written.value}/${bytes.length} bayt)',
          );
        }
      } finally {
        w.endDoc(h);
      }
    } finally {
      w.closePrinter(h);
    }
  });
}

List<String> _enumPrinters() {
  const int local = 0x2;
  const int connections = 0x4;
  final _Winspool w = _Winspool();
  return using((Arena arena) {
    final Pointer<Uint32> needed = arena<Uint32>();
    final Pointer<Uint32> returned = arena<Uint32>();
    w.enumPrinters(
      local | connections,
      nullptr,
      4,
      nullptr,
      0,
      needed,
      returned,
    );
    if (needed.value == 0) return <String>[];
    final Pointer<Uint8> buffer = arena<Uint8>(needed.value);
    if (w.enumPrinters(
          local | connections,
          nullptr,
          4,
          buffer,
          needed.value,
          needed,
          returned,
        ) ==
        0) {
      throw PrinterException(
        PrinterErrorKind.failed,
        'EnumPrinters xatosi ${w.getLastError()}',
      );
    }
    final Pointer<_PrinterInfo4> infos = buffer.cast<_PrinterInfo4>();
    return <String>[
      for (var i = 0; i < returned.value; i++)
        infos[i].pPrinterName.toDartString(),
    ];
  });
}

int _statusBits(String printerName) {
  final _Winspool w = _Winspool();
  return using((Arena arena) {
    final Pointer<Void> h = w.open(printerName, arena);
    try {
      final Pointer<Uint32> needed = arena<Uint32>();
      // PRINTER_INFO_6: bitta DWORD dwStatus
      final Pointer<Uint8> buffer = arena<Uint8>(16);
      if (w.getPrinter(h, 6, buffer, 16, needed) == 0) return 0;
      return buffer.cast<Uint32>().value;
    } finally {
      w.closePrinter(h);
    }
  });
}
