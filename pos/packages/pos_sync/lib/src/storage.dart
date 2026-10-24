import 'dart:async';
import 'dart:convert';
import 'dart:io';

/// Navbat va nusxalar saqlanadigan joy. Ikki xil yozuv bor:
///
/// * **jurnal** — faqat oxiriga qo'shiladigan qatorlar ([appendLine]): har qator diskka yozilib, `fsync` bilan
///   tasdiqlangandan keyingina qaytadi. Elektr qator o'rtasida o'chsa, faqat o'sha oxirgi chala qator yo'qoladi
///   ([SyncStore] uni nazorat yig'indisidan taniydi va kesib tashlaydi);
/// * **hujjatlar** — kalit bo'yicha butun fayl ([writeDoc]): avval vaqtinchalik faylga yozilib `fsync` qilinadi, keyin
///   eski fayl o'rniga qo'yiladi (rename). O'chib qolsa — yo eski, yo yangi nusxa qoladi, chalasi emas.
abstract interface class SyncStorage {
  /// Jurnalning hamma qatorlari (oxirgi chala qator ham, agar bo'lsa).
  Future<List<String>> readLines();

  /// Bitta qatorni jurnal oxiriga qo'shadi va diskka tasdiqlatadi.
  Future<void> appendLine(String line);

  /// Jurnalni [lines] bilan almashtiradi (siqish): bir butun amal.
  Future<void> replaceLines(List<String> lines);

  Future<String?> readDoc(String key);

  Future<void> writeDoc(String key, String content);

  Future<void> deleteDoc(String key);

  Future<List<String>> docKeys();
}

/// Diskdagi papka: `journal.log` va `doc_<kalit>.json` fayllari.
///
/// SQLite o'rniga shu tanlandi: tashqi (native) kutubxona kerak emas — Windows, Android va Linux'da bir xil ishlaydi,
/// `dart test` bilan sinaladi. Navbat kichik (bir necha yuz amal) va faqat oxiriga yoziladi; har yozuv `fsync`
/// qilinadi, har qatorda nazorat yig'indisi bor, siqish vaqtinchalik fayl + rename bilan.
class FileSyncStorage implements SyncStorage {
  FileSyncStorage(this.directory);

  final Directory directory;
  Future<RandomAccessFile>? _journal;

  // Journal writes and the compaction's swap run one at a time: one open handle, never two writes on it at once.
  Future<void>? _tail;

  File get _journalFile => File('${directory.path}/journal.log');

  File _docFile(String key) => File('${directory.path}/doc_${_safe(key)}.json');

  static String _safe(String key) =>
      key.replaceAll(RegExp(r'[^A-Za-z0-9_.-]'), '_');

  /// The journal handle; callers that come while it is being opened wait for the same one.
  Future<RandomAccessFile> _open() => _journal ??= () async {
    await directory.create(recursive: true);
    return _journalFile.open(mode: FileMode.append);
  }();

  Future<void> _serial(Future<void> Function() body) {
    final Future<void>? before = _tail;
    final Completer<void> done = Completer<void>();
    _tail = done.future;
    // Idle: start at once (no hop through an already completed future, which may belong to another zone)
    final Future<void> run = before == null
        ? Future<void>.sync(body)
        : before.then((_) => body());
    return run.whenComplete(() {
      if (identical(_tail, done.future)) _tail = null;
      done.complete();
    });
  }

  Future<void> _closeJournal() async {
    final Future<RandomAccessFile>? open = _journal;
    _journal = null;
    if (open == null) return;
    try {
      await (await open).close();
    } on FileSystemException {
      // It never opened: nothing to close
    }
  }

  @override
  Future<List<String>> readLines() async {
    if (!await _journalFile.exists()) return const <String>[];
    final String text = utf8.decode(
      await _journalFile.readAsBytes(),
      allowMalformed: true,
    );
    final List<String> lines = text.split('\n');
    if (lines.isNotEmpty && lines.last.isEmpty) lines.removeLast();
    return lines;
  }

  @override
  Future<void> appendLine(String line) => _serial(() async {
    final RandomAccessFile f;
    try {
      f = await _open();
    } on FileSystemException {
      _journal = null; // try to open again next time
      rethrow;
    }
    await f.writeString('$line\n');
    // fsync: the line is on the disk before the caller goes on
    await f.flush();
  });

  @override
  Future<void> replaceLines(List<String> lines) => _serial(() async {
    await directory.create(recursive: true);
    await _closeJournal();
    final File tmp = File('${directory.path}/journal.tmp');
    final RandomAccessFile f = await tmp.open(mode: FileMode.write);
    try {
      await f.writeString(lines.map((String l) => '$l\n').join());
      await f.flush();
    } finally {
      await f.close();
    }
    await tmp.rename(_journalFile.path);
  });

  @override
  Future<String?> readDoc(String key) async {
    final File f = _docFile(key);
    if (!await f.exists()) return null;
    return f.readAsString();
  }

  @override
  Future<void> writeDoc(String key, String content) async {
    await directory.create(recursive: true);
    final File target = _docFile(key);
    final File tmp = File('${target.path}.tmp');
    final RandomAccessFile f = await tmp.open(mode: FileMode.write);
    try {
      await f.writeString(content);
      await f.flush();
    } finally {
      await f.close();
    }
    await tmp.rename(target.path);
  }

  @override
  Future<void> deleteDoc(String key) async {
    final File f = _docFile(key);
    if (await f.exists()) await f.delete();
  }

  @override
  Future<List<String>> docKeys() async {
    if (!await directory.exists()) return const <String>[];
    return <String>[
      await for (final FileSystemEntity e in directory.list())
        if (e is File && _isDoc(e.uri.pathSegments.last))
          _keyOf(e.uri.pathSegments.last),
    ];
  }

  static bool _isDoc(String name) =>
      name.startsWith('doc_') && name.endsWith('.json');

  static String _keyOf(String name) =>
      name.substring(4, name.length - '.json'.length);

  Future<void> close() => _serial(_closeJournal);
}

/// Xotiradagi saqlash (testlar uchun): elektr o'chishini ham ko'rsatadi — [tearNextAppend] keyingi qatorning faqat
/// boshini yozadi va [SyncStorageCrash] tashlaydi (yozuv o'rtasida o'chdi), [failNextAppend] hech narsa yozmaydi.
class MemorySyncStorage implements SyncStorage {
  final List<String> lines = <String>[];

  /// Diskka "tushgan", lekin qator oxiri yo'q chala yozuv.
  String? tornTail;
  final Map<String, String> docs = <String, String>{};

  bool tearNextAppend = false;
  bool failNextAppend = false;

  @override
  Future<List<String>> readLines() async => <String>[...lines, ?tornTail];

  @override
  Future<void> appendLine(String line) async {
    if (failNextAppend) {
      failNextAppend = false;
      throw const SyncStorageCrash();
    }
    if (tearNextAppend) {
      tearNextAppend = false;
      tornTail = line.substring(0, line.length ~/ 2);
      throw const SyncStorageCrash();
    }
    if (tornTail != null) {
      // A real file would have the torn bytes in front of the new line
      lines.add('$tornTail$line');
      tornTail = null;
      return;
    }
    lines.add(line);
  }

  @override
  Future<void> replaceLines(List<String> next) async {
    lines
      ..clear()
      ..addAll(next);
    tornTail = null;
  }

  @override
  Future<String?> readDoc(String key) async => docs[key];

  @override
  Future<void> writeDoc(String key, String content) async =>
      docs[key] = content;

  @override
  Future<void> deleteDoc(String key) async => docs.remove(key);

  @override
  Future<List<String>> docKeys() async => docs.keys.toList();
}

/// Sinovda: yozish paytida "elektr o'chdi".
class SyncStorageCrash implements Exception {
  const SyncStorageCrash();

  @override
  String toString() => 'SyncStorageCrash';
}
