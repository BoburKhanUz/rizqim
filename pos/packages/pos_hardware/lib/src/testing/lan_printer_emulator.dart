import 'dart:async';
import 'dart:io';
import 'dart:typed_data';

import '../escpos/code_page.dart';
import '../escpos/esc_pos_decoder.dart';

/// Emulyatorga kelgan bitta ulanish (bitta chop etish).
class EmulatedPrint {
  EmulatedPrint(this.bytes, this.codePage);

  final Uint8List bytes;
  final EscPosCodePage codePage;

  EscPosDocument get document => EscPosDocument.parse(bytes, initial: codePage);
}

/// LAN (raw TCP 9100) ESC/POS printer emulyatori: testlarda va printer yo'q kompyuterda sinash uchun.
///
/// * Har TCP ulanish — bitta [EmulatedPrint] (ulanish yopilganda [prints] ga qo'shiladi);
/// * `DLE EOT 1/2/4` so'roviga printer kabi bitta bayt bilan javob beradi ([paperOut], [paperNearEnd],
///   [coverOpen] ga qarab; [answerStatus] `false` — javob bermaydigan arzon printer);
/// * [refuseConnections] — ulanishni darhol yopadi (o'chiq printerga o'xshash).
///
/// ```dart
/// final emu = await LanPrinterEmulator.start();
/// final target = LanPrinterTarget('127.0.0.1', port: emu.port);
/// ```
class LanPrinterEmulator {
  LanPrinterEmulator._(this._server, this.codePage) {
    _server.listen(_accept);
  }

  /// [port] 0 — bo'sh port tanlanadi ([LanPrinterEmulator.port]).
  static Future<LanPrinterEmulator> start({
    int port = 0,
    InternetAddress? address,
    EscPosCodePage codePage = EscPosCodePage.cp866,
  }) async {
    final ServerSocket server = await ServerSocket.bind(
      address ?? InternetAddress.loopbackIPv4,
      port,
    );
    return LanPrinterEmulator._(server, codePage);
  }

  final ServerSocket _server;
  final EscPosCodePage codePage;
  final List<EmulatedPrint> prints = <EmulatedPrint>[];
  final StreamController<EmulatedPrint> _printed =
      StreamController<EmulatedPrint>.broadcast();
  final Set<Socket> _open = <Socket>{};

  bool paperOut = false;
  bool paperNearEnd = false;
  bool coverOpen = false;
  bool answerStatus = true;
  bool refuseConnections = false;

  int get port => _server.port;
  InternetAddress get address => _server.address;

  /// Har tugagan ulanish.
  Stream<EmulatedPrint> get printed => _printed.stream;

  /// Keyingi chop etishni kutadi.
  Future<EmulatedPrint> next({Duration timeout = const Duration(seconds: 5)}) =>
      printed.first.timeout(timeout);

  void _accept(Socket socket) {
    if (refuseConnections) {
      socket.destroy();
      return;
    }
    _open.add(socket);
    final BytesBuilder data = BytesBuilder(copy: false);
    socket.listen(
      (Uint8List chunk) {
        data.add(chunk);
        _answerStatusRequests(socket, chunk);
      },
      onDone: () {
        _open.remove(socket);
        socket.destroy();
        final EmulatedPrint p = EmulatedPrint(data.takeBytes(), codePage);
        prints.add(p);
        if (!_printed.isClosed) _printed.add(p);
      },
      onError: (Object _) {
        _open.remove(socket);
        socket.destroy();
      },
      cancelOnError: true,
    );
  }

  void _answerStatusRequests(Socket socket, Uint8List chunk) {
    if (!answerStatus) return;
    for (var i = 0; i + 2 < chunk.length; i++) {
      if (chunk[i] == 0x10 && chunk[i + 1] == 0x04) {
        final int? answer = statusByte(chunk[i + 2]);
        if (answer != null) socket.add(<int>[answer]);
      }
    }
  }

  /// `DLE EOT n` javobi (Epson bit tartibi: bit 1 va 4 doim 1).
  int? statusByte(int n) {
    const int base = 0x12;
    switch (n) {
      case 1:
        return base | ((paperOut || coverOpen) ? 0x08 : 0); // offline
      case 2:
        return base | (coverOpen ? 0x04 : 0) | (paperOut ? 0x20 : 0);
      case 3:
        return base;
      case 4:
        return base | (paperOut ? 0x60 : 0) | (paperNearEnd ? 0x0C : 0);
    }
    return null;
  }

  Future<void> stop() async {
    for (final Socket s in _open.toList()) {
      s.destroy();
    }
    await _server.close();
    await _printed.close();
  }
}
