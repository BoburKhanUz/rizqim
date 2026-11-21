import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'limits.dart';
import 'operation.dart';
import 'signature.dart';
import 'store.dart';

/// Wave M5 S4 (docs/scale/M5_DESIGN.md 5, "As built: the export file"): the constants of the till's rescue file,
/// version 1. Pinned, with the server's reader (`App\Domain\Pos\Sync\SyncExportFile`), to
/// backend/tests/Fixtures/sync_export_vectors.json.
abstract final class SyncExportFormat {
  static const String format = 'rizqim-sync-export';
  static const int version = 1;

  /// What the file MAC is made over, in front of the file's bytes ([SyncSignature.fileLabel]).
  static const String fileLabel = SyncSignature.fileLabel;

  /// The largest file the server takes (8 MiB) and the most body lines in one file.
  static const int maxBytes = 8 * 1024 * 1024;
  static const int maxLines = 10000;

  static const String namePrefix = 'rizqim-navbat-';
  static const String extension = '.rzq';

  /// `rizqim-navbat-<device>-<first seq>-<last seq>.rzq` (0 for a seq that is null).
  static String fileName(String device, int? firstSeq, int? lastSeq) =>
      '$namePrefix${device.toLowerCase()}-${firstSeq ?? 0}-${lastSeq ?? 0}$extension';
}

/// The till cannot write the file now. [message] is for the screen (Uzbek).
class SyncExportRefused implements Exception {
  const SyncExportRefused(this.reason, this.message);

  /// The server does not take such files (bootstrap `offline.export` is not true).
  static const String exportOff = 'export_off';

  /// The till holds no sync key: the file could not be sealed.
  static const String noKey = 'no_key';

  /// No folder to write to (this platform has no backup folder and none was chosen).
  static const String noFolder = 'no_folder';

  /// Past [SyncExportFormat.maxLines] or [SyncExportFormat.maxBytes] ([SyncExport.build]; [SyncExport.buildAll]
  /// splits instead).
  static const String tooLarge = 'too_large';

  /// The folder could not be written (a full or removed USB stick, no permission).
  static const String writeFailed = 'write_failed';

  final String reason;
  final String message;

  @override
  String toString() => 'SyncExportRefused($reason)';
}

/// A queue set aside from an earlier pairing (M5 S1 orphan queue, `PosOrphanQueue` in pos_data): its operations
/// (signatures kept, [SyncOperation.fromStored]) and the uuid of the device they were queued under (`null`:
/// unknown; an unsigned operation then counts as the till's own).
class SyncExportQueue {
  const SyncExportQueue({required this.device, required this.operations});

  final String? device;
  final List<SyncOperation> operations;
}

/// One written rescue file: its [text] (UTF-8, LF), the [name] it is saved under and its header.
class SyncExportFile {
  const SyncExportFile._({
    required this.text,
    required this.name,
    required this.header,
    required this.fileMac,
  });

  /// The whole file, trailer and its LF included.
  final String text;

  /// `rizqim-navbat-<device>-<first>-<last>.rzq`.
  final String name;

  /// The header line, keys in the format's order.
  final Map<String, Object?> header;

  /// The trailer's `file_mac`.
  final String fileMac;

  List<int> get bytes => utf8.encode(text);

  String get device => header['device']! as String;
  int get kid => header['kid']! as int;
  List<int> get kids => (header['kids']! as List<Object?>).cast<int>();
  int? get firstSeq => header['first_seq'] as int?;
  int? get lastSeq => header['last_seq'] as int?;
  int get count => header['count']! as int;
  String get createdAt => header['created_at']! as String;
}

/// What a reader says of a file ([SyncExport.verify]): [ok], or the reason it refuses the whole file with, the same
/// words as the server's 422 (`not_export_file`, `version_unsupported`, `file_too_large`, `file_mac_invalid`).
class SyncExportCheck {
  const SyncExportCheck._(this.reason, {this.header, this.lines = 0});

  final String? reason;
  final Map<String, Object?>? header;

  /// The number of body lines.
  final int lines;

  bool get ok => reason == null;

  static const String notExportFile = 'not_export_file';
  static const String versionUnsupported = 'version_unsupported';
  static const String fileTooLarge = 'file_too_large';
  static const String fileMacInvalid = 'file_mac_invalid';
}

/// Builds and reads the rescue file. Flutter-free, no I/O ([SyncExporter] writes it).
abstract final class SyncExport {
  /// The body lines of the till's queue: the operations still waiting in the journal ([SyncStore.pending]), then the
  /// queues set aside from earlier pairings ([setAside]). Each as `POST /pos/sync` takes it ([SyncOperation.toWire]:
  /// a signed one is its journal text, mac, prev and kid unchanged), with `dev` when it belongs to a device (its
  /// key's device, else the queue's). [build] drops a `dev` that is the file's own device, sorts the lines and keeps
  /// one line per `op_id` (the journal's first).
  static List<Map<String, Object?>> linesOf({
    required List<SyncOperation> pending,
    Iterable<SyncExportQueue> setAside = const <SyncExportQueue>[],
  }) {
    Map<String, Object?> line(SyncOperation o, String? device) {
      final String? dev = o.seal?.device ?? device;
      return <String, Object?>{...o.toWire(), 'dev': ?dev};
    }

    return <Map<String, Object?>>[
      for (final SyncOperation o in pending) line(o, null),
      for (final SyncExportQueue q in setAside)
        for (final SyncOperation o in q.operations) line(o, q.device),
    ];
  }

  /// One file of [lines] (as [linesOf] gives them, or the vector file's `input.lines`), sealed with [key]: the
  /// header names [key]'s device and number. Throws [SyncExportRefused] ([SyncExportRefused.tooLarge]) past the
  /// limits; [buildAll] splits instead.
  static SyncExportFile build({
    required SyncKey key,
    required List<Map<String, Object?>> lines,
    required DateTime createdAt,
    int maxLines = SyncExportFormat.maxLines,
    int maxBytes = SyncExportFormat.maxBytes,
  }) {
    final List<_Line> body = _prepare(lines, key.deviceUuid.toLowerCase());
    final SyncExportFile file = _seal(key, body, createdAt);
    if (body.length > maxLines || utf8.encode(file.text).length > maxBytes) {
      throw const SyncExportRefused(
        SyncExportRefused.tooLarge,
        "Navbat bitta fayl uchun juda katta: bir nechta faylga yoziladi.",
      );
    }
    return file;
  }

  /// [lines] as one file or, past [maxLines] / [maxBytes], several files, each whole by itself (its own header and
  /// MAC), in the body's order: the server imports them one after the other.
  static List<SyncExportFile> buildAll({
    required SyncKey key,
    required List<Map<String, Object?>> lines,
    required DateTime createdAt,
    int maxLines = SyncExportFormat.maxLines,
    int maxBytes = SyncExportFormat.maxBytes,
  }) {
    final List<_Line> body = _prepare(lines, key.deviceUuid.toLowerCase());
    // Room for the header (its kids list included) and the trailer
    const int reserve = 4096;
    final List<List<_Line>> chunks = <List<_Line>>[<_Line>[]];
    var size = 0;
    for (final _Line l in body) {
      final int n = l.size;
      if (n + reserve > maxBytes) {
        throw const SyncExportRefused(
          SyncExportRefused.tooLarge,
          "Navbatdagi bir amal faylga sig'maydi.",
        );
      }
      if (chunks.last.length >= maxLines || size + n + reserve > maxBytes) {
        chunks.add(<_Line>[]);
        size = 0;
      }
      chunks.last.add(l);
      size += n;
    }
    return <SyncExportFile>[
      for (final List<_Line> c in chunks) _seal(key, c, createdAt),
    ];
  }

  /// Reads [bytes] as the server does and checks the file MAC under [key]. The body lines are not decoded.
  static SyncExportCheck verify(List<int> bytes, SyncKey key) {
    if (bytes.length > SyncExportFormat.maxBytes) {
      return const SyncExportCheck._(SyncExportCheck.fileTooLarge);
    }
    const SyncExportCheck notOurs = SyncExportCheck._(
      SyncExportCheck.notExportFile,
    );
    // The trailer is the last line; the LF after it may be missing
    final int end = bytes.isNotEmpty && bytes.last == 0x0a
        ? bytes.length - 1
        : bytes.length;
    final int cut = bytes.lastIndexOf(0x0a, end - 1);
    if (end == 0 || cut < 0) return notOurs;
    final List<int> signed = bytes.sublist(0, cut + 1);
    final Map<String, Object?>? trailer = _json(bytes.sublist(cut + 1, end));
    final Object? mac = trailer?['file_mac'];
    if (!SyncSignature.isMac(mac)) return notOurs;
    final int headEnd = signed.indexOf(0x0a);
    final Map<String, Object?>? header = _json(signed.sublist(0, headEnd));
    if (header == null || header['format'] != SyncExportFormat.format) {
      return notOurs;
    }
    if (header['version'] != SyncExportFormat.version) {
      return SyncExportCheck._(
        SyncExportCheck.versionUnsupported,
        header: header,
      );
    }
    final Object? device = header['device'];
    final Object? kid = header['kid'];
    if (device is! String ||
        !_uuid.hasMatch(device) ||
        kid is! int ||
        kid < 1) {
      return notOurs;
    }
    var lines = 0;
    for (var i = headEnd + 1; i < signed.length; i++) {
      if (signed[i] == 0x0a) lines++;
    }
    if (lines > SyncExportFormat.maxLines) {
      return const SyncExportCheck._(SyncExportCheck.fileTooLarge);
    }
    if (!_same(SyncSignature.fileMac(key, signed), mac! as String)) {
      return SyncExportCheck._(
        SyncExportCheck.fileMacInvalid,
        header: header,
        lines: lines,
      );
    }
    return SyncExportCheck._(null, header: header, lines: lines);
  }

  static final RegExp _uuid = RegExp(
    r'^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$',
  );

  static Map<String, Object?>? _json(List<int> bytes) {
    try {
      final Object? v = jsonDecode(utf8.decode(bytes));
      return v is Map ? v.cast<String, Object?>() : null;
    } on FormatException {
      return null;
    }
  }

  static bool _same(String a, String b) {
    if (a.length != b.length) return false;
    var diff = 0;
    for (var i = 0; i < a.length; i++) {
      diff |= a.codeUnitAt(i) ^ b.codeUnitAt(i);
    }
    return diff == 0;
  }

  /// The body: `dev` lower case and dropped when it is [device]; one line per `op_id` (the first); the lines with
  /// `dev` first (by `dev`, `seq`, `op_id`), then the till's own (by `seq`, `op_id`).
  static List<_Line> _prepare(List<Map<String, Object?>> lines, String device) {
    final Set<String> seen = <String>{};
    final List<_Line> out = <_Line>[];
    for (final Map<String, Object?> given in lines) {
      final Object? d = given['dev'];
      final String? dev = d is String ? d.toLowerCase() : null;
      final Map<String, Object?> map = <String, Object?>{
        for (final MapEntry<String, Object?> e in given.entries)
          if (e.key != 'dev') e.key: e.value,
        if (dev != null && dev != device) 'dev': dev,
      };
      final Map<String, Object?> op = _operationOf(map);
      final Object? id = op['op_id'];
      final String opId = id is String ? id : '';
      if (opId.isNotEmpty && !seen.add(opId)) continue;
      final Object? seq = op['seq'];
      out.add(
        _Line(
          map: map,
          json: jsonEncode(map),
          dev: map['dev'] as String?,
          seq: seq is num ? seq.toInt() : 0,
          opId: opId,
          signed: map['signed'] is String,
          kid: map['kid'] is int ? map['kid']! as int : null,
          index: out.length,
        ),
      );
    }
    out.sort(_Line.compare);
    return out;
  }

  /// The operation a body line holds: the signed text decoded, or the line itself.
  static Map<String, Object?> _operationOf(Map<String, Object?> line) {
    final Object? signed = line['signed'];
    if (signed is! String) return line;
    try {
      final Object? v = jsonDecode(signed);
      return v is Map ? v.cast<String, Object?>() : const <String, Object?>{};
    } on FormatException {
      return const <String, Object?>{};
    }
  }

  static SyncExportFile _seal(SyncKey key, List<_Line> body, DateTime at) {
    final String device = key.deviceUuid.toLowerCase();
    final List<int> own = <int>[
      for (final _Line l in body)
        if (l.dev == null) l.seq,
    ];
    final List<int> kids = <int>{
      for (final _Line l in body)
        if (l.signed && l.kid != null) l.kid!,
    }.toList()..sort();
    int? fold(int Function(int, int) pick) =>
        own.isEmpty ? null : own.reduce(pick);
    final int? first = fold((int a, int b) => a < b ? a : b);
    final int? last = fold((int a, int b) => a > b ? a : b);
    final Map<String, Object?> header = <String, Object?>{
      'format': SyncExportFormat.format,
      'version': SyncExportFormat.version,
      'device': device,
      'kid': key.kid,
      'kids': kids,
      'first_seq': first,
      'last_seq': last,
      'count': body.length,
      // UTC, milliseconds (always written: `.000Z`)
      'created_at': DateTime.fromMillisecondsSinceEpoch(
        at.millisecondsSinceEpoch,
        isUtc: true,
      ).toIso8601String(),
    };
    final StringBuffer signed = StringBuffer()
      ..write(jsonEncode(header))
      ..write('\n');
    for (final _Line l in body) {
      signed
        ..write(l.json)
        ..write('\n');
    }
    final String before = signed.toString();
    final String mac = SyncSignature.fileMac(key, utf8.encode(before));
    return SyncExportFile._(
      text: '$before${jsonEncode(<String, Object?>{'file_mac': mac})}\n',
      name: SyncExportFormat.fileName(device, first, last),
      header: header,
      fileMac: mac,
    );
  }
}

class _Line {
  _Line({
    required this.map,
    required this.json,
    required this.dev,
    required this.seq,
    required this.opId,
    required this.signed,
    required this.kid,
    required this.index,
  });

  final Map<String, Object?> map;
  final String json;
  final String? dev;
  final int seq;
  final String opId;
  final bool signed;
  final int? kid;
  final int index;

  int get size => utf8.encode(json).length + 1;

  String get type {
    final Object? t = SyncExport._operationOf(map)['type'];
    return t is String ? t : '';
  }

  static int compare(_Line a, _Line b) {
    if ((a.dev == null) != (b.dev == null)) return a.dev == null ? 1 : -1;
    if (a.dev != null) {
      final int byDev = a.dev!.compareTo(b.dev!);
      if (byDev != 0) return byDev;
    }
    final int bySeq = a.seq.compareTo(b.seq);
    if (bySeq != 0) return bySeq;
    final int byId = a.opId.compareTo(b.opId);
    return byId != 0 ? byId : a.index.compareTo(b.index);
  }
}

/// When the till offers the export by itself (the screen is S4-c's).
enum SyncExportOffer {
  /// The outage (or a queue that does not leave) has passed `alert_after_minutes` (2 h) with operations waiting.
  outage,

  /// "Disconnect this till" is about to run while operations (or queues set aside) are waiting.
  beforeDisconnect,
}

/// The result of [SyncExporter.export].
class SyncExportResult {
  const SyncExportResult({
    required this.files,
    required this.paths,
    required this.directory,
    required this.operations,
    required this.payments,
    required this.at,
  });

  final List<SyncExportFile> files;

  /// Where each file was written (absolute paths, in [files]' order).
  final List<String> paths;
  final Directory directory;

  /// Body lines in all files, and of them `bill.pay`.
  final int operations;
  final int payments;
  final DateTime at;
}

/// "Navbatni faylga saqlash" (docs/scale/M5_DESIGN.md 5, layer 3): writes the till's rescue file(s) from the
/// journal's waiting operations and the queues set aside, sealed with the till's current key, to the backup folder
/// (the mirror folder, `MirroredSyncStorage.directory`) or a folder chosen by the cashier (a USB stick).
///
/// * Offered only while the server takes such files: [enabled] is bootstrap `offline.export` (pos_data
///   `PosOfflineSettings.export`). Otherwise [export] refuses ([SyncExportRefused.exportOff]) and nothing is offered.
/// * Never stops a sale: it reads a snapshot of the queue and only writes its own files (a temporary file renamed
///   into place, so a file is either whole or absent).
/// * The key is used for the MAC only; it is never written, logged or shown.
class SyncExporter {
  SyncExporter({
    required this.store,
    required SyncKeyProvider keyProvider,
    required this._enabled,
    List<SyncExportQueue> Function()? setAside,
    Directory? Function()? directory,
    DateTime Function()? clock,
  }) : _key = keyProvider,
       _setAside = setAside ?? (() => const <SyncExportQueue>[]),
       _directory = directory ?? (() => null),
       _clock = clock ?? DateTime.now;

  final SyncStore store;
  final SyncKeyProvider _key;
  final bool Function() _enabled;
  final List<SyncExportQueue> Function() _setAside;
  final Directory? Function() _directory;
  final DateTime Function() _clock;

  SyncExportResult? _last;
  Future<SyncExportResult>? _running;

  /// The server takes rescue files (bootstrap `offline.export`): the settings card shows the action.
  bool get available {
    try {
      return _enabled();
    } on Object {
      return false;
    }
  }

  /// The folder a file goes to when none is chosen (`null`: none on this device).
  Directory? get defaultDirectory => _directory();

  /// The last file(s) written in this run (`null`: none yet).
  SyncExportResult? get last => _last;

  /// Something waits that a file would carry: the journal's queue or a queue set aside.
  bool get hasSomethingToExport =>
      store.hasPending ||
      _setAside().any((SyncExportQueue q) => q.operations.isNotEmpty);

  /// [SyncExportOffer.outage] when [status] is past the alert (`alert_after_minutes`) with operations waiting.
  SyncExportOffer? offerFor(OfflineStatus status) =>
      available && status.queueSize > 0 && status.level == OfflineLevel.alert
      ? SyncExportOffer.outage
      : null;

  /// [SyncExportOffer.beforeDisconnect] when a disconnect would leave operations that are not on the server: ask
  /// before `PosOffline.forget` / the "disconnect this till" action.
  SyncExportOffer? get offerBeforeDisconnect =>
      available && hasSomethingToExport
      ? SyncExportOffer.beforeDisconnect
      : null;

  /// Writes the file(s) to [to] (else [defaultDirectory]) and answers where. An empty queue writes an empty file
  /// (proof that nothing waits). Calls made while one runs get that one's answer.
  Future<SyncExportResult> export({Directory? to}) {
    final Future<SyncExportResult>? running = _running;
    if (running != null) return running;
    final Future<SyncExportResult> run = _export(to);
    _running = run;
    return run.whenComplete(() => _running = null);
  }

  Future<SyncExportResult> _export(Directory? to) async {
    if (!available) {
      throw const SyncExportRefused(
        SyncExportRefused.exportOff,
        "Server hozircha navbat faylini qabul qilmaydi: kassa ilovasi va server yangilanishini kuting.",
      );
    }
    SyncKey? key;
    try {
      key = await _key();
    } on Object {
      key = null;
    }
    if (key == null) {
      throw const SyncExportRefused(
        SyncExportRefused.noKey,
        "Kassada imzo kaliti yo'q: faylni muhrlab bo'lmaydi. Internet tiklangach kassa kalitni o'zi oladi.",
      );
    }
    final Directory? dir = to ?? _directory();
    if (dir == null) {
      throw const SyncExportRefused(
        SyncExportRefused.noFolder,
        "Faylni saqlash uchun papka tanlang (masalan, fleshka).",
      );
    }
    final DateTime at = _clock();
    final List<Map<String, Object?>> lines = SyncExport.linesOf(
      pending: store.pending,
      setAside: _setAside(),
    );
    final List<SyncExportFile> files = SyncExport.buildAll(
      key: key,
      lines: lines,
      createdAt: at,
    );
    final List<String> paths = <String>[];
    final Set<String> names = <String>{};
    try {
      await dir.create(recursive: true);
      for (final SyncExportFile f in files) {
        var name = f.name;
        // Several files of other pairings' lines alone would share a name
        for (var n = 2; !names.add(name); n++) {
          name = f.name.replaceFirst(
            SyncExportFormat.extension,
            '-$n${SyncExportFormat.extension}',
          );
        }
        paths.add(await _write(dir, name, f.bytes));
      }
    } on FileSystemException {
      throw const SyncExportRefused(
        SyncExportRefused.writeFailed,
        "Faylni yozib bo'lmadi: papka (fleshka) ulanganini va bo'sh joy borligini tekshiring.",
      );
    }
    var operations = 0;
    var payments = 0;
    for (final SyncExportFile f in files) {
      operations += f.count;
    }
    for (final _Line l in _bodyOf(lines, key)) {
      if (l.type == SyncOpType.billPay) payments++;
    }
    return _last = SyncExportResult(
      files: files,
      paths: paths,
      directory: dir,
      operations: operations,
      payments: payments,
      at: at,
    );
  }

  static List<_Line> _bodyOf(List<Map<String, Object?>> lines, SyncKey key) =>
      SyncExport._prepare(lines, key.deviceUuid.toLowerCase());

  static Future<String> _write(
    Directory dir,
    String name,
    List<int> bytes,
  ) async {
    final File target = File('${dir.path}${Platform.pathSeparator}$name');
    final File tmp = File('${target.path}.tmp');
    final RandomAccessFile f = await tmp.open(mode: FileMode.write);
    try {
      await f.writeFrom(bytes);
      await f.flush();
    } finally {
      await f.close();
    }
    await tmp.rename(target.path);
    return target.path;
  }
}
