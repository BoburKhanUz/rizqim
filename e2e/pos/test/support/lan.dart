// The ESC/POS builder is pos_hardware's pure-Dart part; its public library also exports the Flutter image rasterizer,
// which `dart test` cannot load.
// ignore_for_file: implementation_imports
import 'dart:async';
import 'dart:io';
import 'dart:typed_data';

import 'package:pos_core/pos_core.dart';
import 'package:pos_hardware/src/escpos/code_page.dart';
import 'package:pos_hardware/src/escpos/esc_pos_builder.dart';
import 'package:pos_hardware/testing.dart';
import 'package:pos_lan/pos_lan.dart';

/// The restaurant's internet: a TCP relay between the devices and the stack (the API or Reverb) that the test can cut.
/// Cut, it drops every open connection and refuses new ones at once, as a dead uplink does (the Wi-Fi in the hall stays
/// up: the local bridge runs on loopback, outside it).
class CloudLink {
  CloudLink._(this._server, this.host, this.port);

  /// A relay on 127.0.0.1 (a free port) to [target] (`http://127.0.0.1:18200`, `ws://127.0.0.1:18280`).
  static Future<CloudLink> start(Uri target) async {
    final ServerSocket server = await ServerSocket.bind(
      InternetAddress.loopbackIPv4,
      0,
    );
    final CloudLink link = CloudLink._(server, target.host, target.port);
    server.listen(link._accept);
    return link;
  }

  final ServerSocket _server;
  final String host;
  final int port;
  final Set<Socket> _open = <Socket>{};
  bool _cut = false;

  /// Connections refused while cut.
  int refused = 0;

  int get localPort => _server.port;

  bool get cut => _cut;

  set cut(bool value) {
    _cut = value;
    if (value) {
      for (final Socket s in _open.toList()) {
        s.destroy();
      }
      _open.clear();
    }
  }

  Future<void> _accept(Socket client) async {
    if (_cut) {
      refused++;
      client.destroy();
      return;
    }
    final Socket upstream;
    try {
      upstream = await Socket.connect(host, port);
    } on Object {
      client.destroy();
      return;
    }
    if (_cut) {
      refused++;
      client.destroy();
      upstream.destroy();
      return;
    }
    _open
      ..add(client)
      ..add(upstream);
    void close() {
      _open
        ..remove(client)
        ..remove(upstream);
      client.destroy();
      upstream.destroy();
    }

    void pipe(Socket from, Socket to) => from.listen(
      (Uint8List data) {
        try {
          to.add(data);
        } on Object {
          close();
        }
      },
      onDone: close,
      onError: (Object _) => close(),
      cancelOnError: true,
    );
    pipe(client, upstream);
    pipe(upstream, client);
  }

  Future<void> close() async {
    cut = true;
    await _server.close();
  }
}

/// One LAN message a device heard, with the moment it arrived.
class HeardLan {
  HeardLan(this.event, this.at);

  final LanEvent event;
  final DateTime at;

  Map<String, Object?> get payload => event.payload;
}

/// Everything a device heard from the hub; a test waits for the next one that fits.
class LanInbox {
  LanInbox(Stream<LanEvent> events) {
    _sub = events.listen((LanEvent e) {
      final HeardLan heard = HeardLan(e, DateTime.now());
      all.add(heard);
      for (final _LanWaiter w in List<_LanWaiter>.of(_waiters)) {
        if (w.type == e.type && w.where(e)) {
          _waiters.remove(w);
          w.done.complete(heard);
        }
      }
    });
  }

  late final StreamSubscription<LanEvent> _sub;
  final List<HeardLan> all = <HeardLan>[];
  final List<_LanWaiter> _waiters = <_LanWaiter>[];

  List<HeardLan> of(String type, [bool Function(LanEvent e)? where]) =>
      <HeardLan>[
        for (final HeardLan h in all)
          if (h.event.type == type && (where == null || where(h.event))) h,
      ];

  /// The first [type] message (already heard, or the next) that satisfies [where]; heard after [since] when given.
  Future<HeardLan> wait(
    String type, {
    bool Function(LanEvent e)? where,
    DateTime? since,
    Duration timeout = const Duration(seconds: 10),
    String? what,
  }) {
    bool fits(LanEvent e) => where == null || where(e);
    for (final HeardLan h in all) {
      if (h.event.type == type &&
          fits(h.event) &&
          (since == null || !h.at.isBefore(since))) {
        return Future<HeardLan>.value(h);
      }
    }
    final _LanWaiter w = _LanWaiter(type, fits);
    _waiters.add(w);
    return w.done.future.timeout(
      timeout,
      onTimeout: () {
        _waiters.remove(w);
        throw TimeoutException(
          'No ${what ?? type} within ${timeout.inSeconds}s (heard: '
          '${all.map((HeardLan h) => h.event.type).toSet().join(', ')})',
        );
      },
    );
  }

  Future<void> dispose() => _sub.cancel();
}

class _LanWaiter {
  _LanWaiter(this.type, this.where);

  final String type;
  final bool Function(LanEvent e) where;
  final Completer<HeardLan> done = Completer<HeardLan>();
}

/// The till's receipt printer: pos_hardware's LAN (9100) ESC/POS emulator. A layout is rendered like pos_hardware's
/// LayoutRenderer text mode (the printer's own font, CP866) and decoded back to text by the emulator.
class ReceiptPrinterSim {
  ReceiptPrinterSim._(this.emulator);

  static Future<ReceiptPrinterSim> start() async =>
      ReceiptPrinterSim._(await LanPrinterEmulator.start());

  final LanPrinterEmulator emulator;

  /// What came out, in order: the title the till gave it and the text the emulator decoded.
  final List<({String title, String text})> printed =
      <({String title, String text})>[];

  int get port => emulator.port;

  /// The text-mode bytes of [layout] (pos_hardware LayoutRenderer.renderText, a printer with a cutter).
  static Uint8List render(PrintLayout layout) {
    final EscPosBuilder b = EscPosBuilder(codePage: EscPosCodePage.cp866)
      ..init();
    for (final PrintLine line in layout.lines) {
      switch (line) {
        case PrintText():
          b
            ..align(switch (line.align) {
              PrintAlign.left => EscPosAlign.left,
              PrintAlign.center => EscPosAlign.center,
              PrintAlign.right => EscPosAlign.right,
            })
            ..bold(line.bold)
            ..size(width: line.size.width, height: line.size.height)
            ..invert(line.inverted)
            ..textLine(line.text);
          if (line.bold) b.bold(false);
          if (!line.size.isNormal) b.size();
          if (line.inverted) b.invert(false);
          if (line.align != PrintAlign.left) b.align(EscPosAlign.left);
        case PrintDivider():
          b.textLine(line.char * layout.paper.chars);
        case PrintFeed():
          b.feed(line.lines);
        case PrintQr():
          b.qrCode(line.data);
        case PrintCut():
          b
            ..feed(1)
            ..cut();
      }
    }
    return b.build();
  }

  Future<void> _turn = Future<void>.value();

  /// Sends one document to the printer at [address]:[port] (the bootstrap's) and waits for the emulator to decode it.
  Future<String> send(
    String title,
    PrintLayout layout,
    String address,
    int port,
  ) {
    final Future<String> mine = _turn.then(
      (_) => _send(title, layout, address, port),
    );
    _turn = mine.then<void>((_) {}, onError: (Object _) {});
    return mine;
  }

  Future<String> _send(
    String title,
    PrintLayout layout,
    String address,
    int port,
  ) async {
    final Future<EmulatedPrint> next = emulator.next(
      timeout: const Duration(seconds: 10),
    );
    final Socket socket = await Socket.connect(address, port);
    socket.add(render(layout));
    await socket.flush();
    await socket.close();
    final String text = (await next).document.text;
    printed.add((title: title, text: text));
    return text;
  }

  Future<void> stop() => emulator.stop();
}

/// A free UDP port on loopback (the drill's beacon port: the real one, 18480, may be taken on a developer machine).
Future<int> freeUdpPort() async {
  final RawDatagramSocket s = await RawDatagramSocket.bind(
    InternetAddress.loopbackIPv4,
    0,
  );
  final int port = s.port;
  s.close();
  return port;
}

/// Median and max of [all], for the latency line at the end.
String latencyStat(List<Duration> all) {
  if (all.isEmpty) return 'none';
  final List<Duration> sorted = List<Duration>.of(all)..sort();
  return 'median ${sorted[(sorted.length - 1) ~/ 2].inMilliseconds} ms, '
      'max ${sorted.last.inMilliseconds} ms (${sorted.length})';
}
