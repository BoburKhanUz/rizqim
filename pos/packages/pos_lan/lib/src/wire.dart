import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';
import 'dart:typed_data';

import 'package:crypto/crypto.dart' show sha1;

/// The limits of the hub's listener (README §5, review P6 hardware/LAN S1/S2): anyone on the Wi-Fi reaches it without a
/// key, so nothing is buffered or kept open beyond these before a device proved itself with its `hello`.
abstract final class LanWireLimits {
  /// The HTTP request head (request line and headers).
  static const int maxHead = 8 << 10;

  /// One WebSocket message before `hello` (a hello with its grant is ~2 KB).
  static const int preHello = 64 << 10;

  /// One WebSocket message after `hello` (LanFrameCodec.maxBytes).
  static const int afterHello = 4 << 20;
}

/// One HTTP request's head, as far as the hub needs it.
class LanHttpHead {
  const LanHttpHead(this.method, this.path, this.headers);

  /// The head in [text] (without the closing blank line), `null` when it is not an HTTP/1.x request.
  static LanHttpHead? parse(String text) {
    final List<String> lines = text.split('\r\n');
    final List<String> first = lines.first.split(' ');
    if (first.length != 3 || !first[2].startsWith('HTTP/1.')) return null;
    final Map<String, String> headers = <String, String>{};
    for (final String l in lines.skip(1)) {
      final int colon = l.indexOf(':');
      if (colon <= 0) return null;
      headers[l.substring(0, colon).trim().toLowerCase()] = l
          .substring(colon + 1)
          .trim();
    }
    final String target = first[1];
    final int q = target.indexOf('?');
    return LanHttpHead(
      first[0],
      q < 0 ? target : target.substring(0, q),
      headers,
    );
  }

  final String method;
  final String path;

  /// Lower-case names.
  final Map<String, String> headers;

  /// The host part of `Host` (no port, no IPv6 brackets), lower case.
  String? get host {
    final String? h = headers['host']?.trim().toLowerCase();
    if (h == null || h.isEmpty) return null;
    if (h.startsWith('[')) {
      final int end = h.indexOf(']');
      return end < 0 ? null : h.substring(1, end);
    }
    final int colon = h.indexOf(':');
    return colon < 0 ? h : h.substring(0, colon);
  }

  bool _has(String name, String token) => (headers[name] ?? '')
      .toLowerCase()
      .split(',')
      .any((String t) => t.trim() == token);

  /// RFC 6455 §4.2.1: the `Sec-WebSocket-Accept` of a valid upgrade request, `null` when it is not one.
  String? get webSocketAccept {
    if (method != 'GET' ||
        !_has('upgrade', 'websocket') ||
        !_has('connection', 'upgrade') ||
        headers['sec-websocket-version'] != '13') {
      return null;
    }
    final String key = headers['sec-websocket-key'] ?? '';
    try {
      if (base64.decode(key).length != 16) return null;
    } on FormatException {
      return null;
    }
    return base64.encode(
      sha1
          .convert(ascii.encode('${key}258EAFA5-E914-47DA-95CA-C5AB0DC85B11'))
          .bytes,
    );
  }
}

/// A TCP connection to the hub (review P6 S1): reads the HTTP head itself (at most [LanWireLimits.maxHead]), then, once
/// upgraded, hands the bytes to dart:io's WebSocket ([WebSocket.fromUpgradedSocket] takes this as its socket) only
/// after reading every frame header on the way: a message longer than [limit] (declared or added up over frames), a
/// compressed frame (RSV bits: no extension is agreed) or a control frame over 125 bytes ends the connection at once,
/// before its payload is buffered anywhere.
class LanConnection extends Stream<Uint8List> implements Socket {
  LanConnection(this._raw, {required this.onHead, required this.onClosed}) {
    _sub = _raw.listen(
      _onData,
      onError: (Object _) => _end(),
      onDone: _end,
      cancelOnError: true,
    );
  }

  final Socket _raw;

  /// The head arrived (the bytes after it stay for the WebSocket).
  final void Function(LanConnection c, LanHttpHead? head) onHead;

  /// The TCP connection ended (either side).
  final void Function(LanConnection c) onClosed;

  late final StreamSubscription<Uint8List> _sub;
  final StreamController<Uint8List> _out = StreamController<Uint8List>(
    sync: true,
  );
  final BytesBuilder _head = BytesBuilder(copy: false);
  bool _headDone = false;
  bool _upgraded = false;
  bool _closed = false;

  /// Why the connection was cut (`too_big`, `compressed`, `head_too_big`...), for the logs and tests.
  String? violation;

  /// The longest WebSocket message taken now ([LanWireLimits.preHello], then [LanWireLimits.afterHello]).
  int limit = LanWireLimits.preHello;

  String get ip {
    try {
      return _raw.remoteAddress.address;
    } on Object {
      return '';
    }
  }

  bool get closed => _closed;

  // --- the head ------------------------------------------------------------------------------------------------------

  void _onData(Uint8List data) {
    if (_closed) return;
    if (_headDone) {
      if (_upgraded) _gate(data);
      return;
    }
    _head.add(data);
    final Uint8List all = _head.toBytes();
    final int end = _crlf2(all);
    if (end < 0) {
      if (all.length > LanWireLimits.maxHead) cut('head_too_big');
      return;
    }
    if (end > LanWireLimits.maxHead) {
      cut('head_too_big');
      return;
    }
    _headDone = true;
    _head.clear();
    final Uint8List rest = Uint8List.sublistView(all, end + 4);
    // Nothing more is read until the WebSocket (if any) listens
    _sub.pause();
    LanHttpHead? head;
    try {
      head = LanHttpHead.parse(
        latin1.decode(Uint8List.sublistView(all, 0, end)),
      );
    } on Object {
      head = null;
    }
    if (rest.isNotEmpty) _early = rest;
    onHead(this, head);
  }

  Uint8List? _early;

  static int _crlf2(Uint8List b) {
    for (int i = 0; i + 3 < b.length; i++) {
      if (b[i] == 13 && b[i + 1] == 10 && b[i + 2] == 13 && b[i + 3] == 10) {
        return i;
      }
    }
    return -1;
  }

  /// Answers a plain HTTP request ([status] with [body]) and ends the connection.
  Future<void> respond(
    int status,
    String reason, {
    String body = '',
    String contentType = 'text/plain; charset=utf-8',
  }) async {
    final List<int> bytes = utf8.encode(body);
    try {
      _raw.add(
        latin1.encode(
          'HTTP/1.1 $status $reason\r\n'
          'Content-Type: $contentType\r\n'
          'Content-Length: ${bytes.length}\r\n'
          'Cache-Control: no-store\r\n'
          'Connection: close\r\n\r\n',
        ),
      );
      _raw.add(bytes);
      await _raw.flush().timeout(const Duration(seconds: 2));
    } on Object {
      // The caller went
    }
    cut(null);
  }

  /// Accepts the WebSocket upgrade with [accept] (no extension, no subprotocol); the bytes from now on go to the
  /// WebSocket that listens to this.
  void upgrade(String accept) {
    _upgraded = true;
    _raw.add(
      latin1.encode(
        'HTTP/1.1 101 Switching Protocols\r\n'
        'Upgrade: websocket\r\n'
        'Connection: Upgrade\r\n'
        'Sec-WebSocket-Accept: $accept\r\n\r\n',
      ),
    );
  }

  /// Ends the connection now ([reason]: why, `null` for a normal end).
  void cut(String? reason) {
    if (_closed) return;
    violation ??= reason;
    _raw.destroy();
    _end();
  }

  void _end() {
    if (_closed) return;
    _closed = true;
    unawaited(_sub.cancel());
    if (!_out.isClosed) unawaited(_out.close());
    onClosed(this);
  }

  // --- the frames ------------------------------------------------------------------------------------------------------

  int _state =
      0; // 0 first byte, 1 length, 2 extended length, 3 mask, 4 payload
  int _need = 0;
  int _len = 0;
  int _message = 0;
  bool _fin = false;
  int _opcode = 0;
  bool _masked = false;

  void _gate(Uint8List data) {
    int i = 0;
    while (i < data.length) {
      switch (_state) {
        case 0:
          final int b = data[i++];
          if (b & 0x70 != 0) return cut('compressed');
          _fin = b & 0x80 != 0;
          _opcode = b & 0x0f;
          _state = 1;
        case 1:
          final int b = data[i++];
          _masked = b & 0x80 != 0;
          final int l = b & 0x7f;
          if (l < 126) {
            _len = l;
            if (!_lengthDone()) return;
          } else {
            _len = 0;
            _need = l == 126 ? 2 : 8;
            _state = 2;
          }
        case 2:
          _len = (_len << 8) | data[i++];
          // Far beyond any limit already (and no overflow of the 64-bit length)
          if (_len > (1 << 40)) return cut('too_big');
          if (--_need == 0 && !_lengthDone()) return;
        case 3:
          i++;
          if (--_need == 0) _payloadStart();
        default:
          final int n = min(_need, data.length - i);
          i += n;
          _need -= n;
          if (_need == 0) _frameEnd();
      }
    }
    if (!_out.isClosed) _out.add(data);
  }

  /// The frame's length is known: checked before a byte of its payload.
  bool _lengthDone() {
    if (_opcode >= 8) {
      if (_len > 125) {
        cut('bad_control');
        return false;
      }
    } else {
      if (_len < 0 || _message + _len > limit) {
        cut('too_big');
        return false;
      }
      _message += _len;
    }
    if (_masked) {
      _state = 3;
      _need = 4;
    } else {
      _payloadStart();
    }
    return true;
  }

  void _payloadStart() {
    if (_len == 0) {
      _frameEnd();
    } else {
      _state = 4;
      _need = _len;
    }
  }

  void _frameEnd() {
    if (_opcode < 8 && _fin) _message = 0;
    _state = 0;
  }

  // --- Stream<Uint8List> (what the WebSocket reads) ----------------------------------------------------------------------

  @override
  StreamSubscription<Uint8List> listen(
    void Function(Uint8List event)? onData, {
    Function? onError,
    void Function()? onDone,
    bool? cancelOnError,
  }) {
    _out
      ..onPause = _sub.pause
      ..onResume = _sub.resume
      ..onCancel = () => cut(null);
    final StreamSubscription<Uint8List> s = _out.stream.listen(
      onData,
      onError: onError,
      onDone: onDone,
      cancelOnError: cancelOnError,
    );
    final Uint8List? early = _early;
    _early = null;
    if (early != null) _gate(early);
    if (!_closed) _sub.resume();
    return s;
  }

  // --- IOSink and Socket (what the WebSocket writes): the TCP socket's own ------------------------------------------------

  @override
  Encoding get encoding => _raw.encoding;

  @override
  set encoding(Encoding value) => _raw.encoding = value;

  @override
  void add(List<int> data) => _raw.add(data);

  @override
  void addError(Object error, [StackTrace? stackTrace]) =>
      _raw.addError(error, stackTrace);

  @override
  Future<void> addStream(Stream<List<int>> stream) => _raw.addStream(stream);

  @override
  Future<void> close() => _raw.close();

  @override
  Future<void> get done => _raw.done;

  @override
  Future<void> flush() => _raw.flush();

  @override
  void write(Object? object) => _raw.write(object);

  @override
  void writeAll(Iterable<Object?> objects, [String separator = '']) =>
      _raw.writeAll(objects, separator);

  @override
  void writeCharCode(int charCode) => _raw.writeCharCode(charCode);

  @override
  void writeln([Object? object = '']) => _raw.writeln(object);

  @override
  void destroy() => cut(null);

  @override
  bool setOption(SocketOption option, bool enabled) =>
      _raw.setOption(option, enabled);

  @override
  Uint8List getRawOption(RawSocketOption option) => _raw.getRawOption(option);

  @override
  void setRawOption(RawSocketOption option) => _raw.setRawOption(option);

  @override
  int get port => _raw.port;

  @override
  int get remotePort => _raw.remotePort;

  @override
  InternetAddress get address => _raw.address;

  @override
  InternetAddress get remoteAddress => _raw.remoteAddress;
}
