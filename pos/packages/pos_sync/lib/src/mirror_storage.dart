import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'crc32.dart';
import 'export.dart';
import 'operation.dart';
import 'signature.dart';
import 'storage.dart';

/// What [MirroredSyncStorage.restore] did on start.
class MirrorRestore {
  const MirrorRestore({
    this.restored = 0,
    this.restoredPayments = 0,
    this.kept = 0,
    this.keptKey,
    this.journalLines = 0,
    this.mirrorLines = 0,
    this.changed = false,
    this.error,
  });

  /// Operations put back into the journal from the copy (each one's MAC verified).
  final int restored;

  /// Of them, payments (`bill.pay`).
  final int restoredPayments;

  /// Operations only the copy had that could not be verified (unsigned, another key, a changed line): never put into
  /// the journal, kept in the copy's folder under [keptKey] ([MirroredSyncStorage.setAside], so the export file
  /// carries them).
  final int kept;
  final String? keptKey;

  /// Lines of the journal and of the copy before the restore.
  final int journalLines;
  final int mirrorLines;

  /// The journal or the copy was written.
  final bool changed;

  /// Why the copy could not be compared or written (the copy is then not written in this run). Never stops the app.
  final Object? error;
}

/// Wave M5 S4 (docs/scale/M5_DESIGN.md 5, layer 2): the journal ([primary]) with a second copy of its lines
/// ([mirror]) outside the app's own folder. Windows: `Documents\RIZQIM\<device uuid>\` (survives an uninstall and a
/// clean-up of AppData); Android: the app's external files directory. The cashier app picks the folder (`main.dart`).
///
/// * Every journal line is appended to the copy after the journal has it; a compaction replaces the copy too. The
///   journal decides: a failing copy never fails an append or a compaction ([mirrorError], [lastMirroredAt]). After
///   a failure the copy is written whole again from the journal (at most once a minute while it keeps failing).
/// * A copy that does not answer (a hung share, an antivirus lock) holds a sale up for at most [writeTimeout]: the
///   journal has the line by then; while that write is still out, later lines are not queued behind it, the copy is
///   written whole from the journal once it answers (so it never gets lines out of order).
/// * The copies of documents (menu, bootstrap) stay with the journal only; the queues set aside at a disconnect
///   ([copiedDocPrefixes]) are copied too.
/// * [restore] runs once on start, BEFORE `SyncStore.open`; until it has compared the two the copy is not written
///   (it may hold the only record of a journal that is gone). It puts back every operation the copy has and the
///   journal does not know, when its MAC verifies under one of the till's keys, and never removes a journal line.
///   It needs nothing from the journal's folder (a wiped folder takes its bootstrap copy with it): a verified line is
///   the till's own and the server takes an `op_id` once. What it cannot verify stays in the copy's folder and goes
///   into the export file ([setAside]).
class MirroredSyncStorage implements SyncStorage {
  MirroredSyncStorage(
    this.primary,
    this.mirror, {
    this.directory,
    this.copiedDocPrefixes = const <String>['orphan_'],
    this.writeTimeout = const Duration(seconds: 2),
    this.restoreTimeout = const Duration(seconds: 15),
    DateTime Function()? clock,
  }) : _clock = clock ?? DateTime.now;

  /// The journal in the app's own folder: what the store reads.
  final SyncStorage primary;

  /// The copy (only its journal is used, plus documents of lines kept aside by [restore]).
  final SyncStorage mirror;

  /// The copy's folder, for the screen and as the default folder of the export file (`null`: not given).
  final Directory? directory;

  /// Documents also written to the copy's folder (never read back from it by this class): by default the queues set
  /// aside at a disconnect (pos_data `PosOffline.orphanPrefix`), so a wiped app folder does not take them with it.
  final List<String> copiedDocPrefixes;

  /// The longest a journal append (a sale) waits for the copy.
  final Duration writeTimeout;

  /// The longest [restore] waits for each read or write of the copy (start-up never hangs on it).
  final Duration restoreTimeout;

  final DateTime Function() _clock;

  /// A failing copy is tried again no sooner than this.
  static const Duration retryAfter = Duration(minutes: 1);

  /// Document key prefix (in the copy) of lines [restore] kept aside.
  static const String keptPrefix = 'mirror_kept_';

  bool _ready = false;
  bool _frozen = false;
  bool _stale = false;
  DateTime? _failedAt;
  Object? _error;
  DateTime? _lastMirroredAt;
  MirrorRestore? _lastRestore;
  final StreamController<void> _changes = StreamController<void>.broadcast();

  // The copy's write that is out (at most one) and whether lines came while it was
  Future<void>? _busy;
  bool _behind = false;

  // The copy's queues set aside, by document key
  final Map<String, SyncExportQueue> _aside = <String, SyncExportQueue>{};

  /// The last time a line reached the copy (`null`: none in this run).
  DateTime? get lastMirroredAt => _lastMirroredAt;

  /// The last failure of the copy, until a write succeeds again (`null`: the copy is up to date).
  Object? get mirrorError => _error;

  /// The copy is written in this run ([restore] compared it and nothing stopped it).
  bool get mirroring => _ready && !_frozen;

  /// What [restore] did (`null`: not run).
  MirrorRestore? get lastRestore => _lastRestore;

  /// The copy's state changed (written, failed, restored): for the settings card.
  Stream<void> get changes => _changes.stream;

  /// What the copy's folder holds besides the journal's lines, for the export file (`SyncExporter` adds it to its
  /// own queues set aside): the copies of the queues set aside at a disconnect ([copiedDocPrefixes]; the journal's
  /// may be gone with the app folder) and the lines [restore] could not verify ([keptPrefix]; their own device's
  /// unless signed under another). Read by [restore], kept up to date by [writeDoc] / [deleteDoc]. Operations the
  /// journal also holds are left to the export's `op_id` check (the server takes an `op_id` once).
  List<SyncExportQueue> get setAside => <SyncExportQueue>[
    for (final String k in _aside.keys.toList()..sort()) _aside[k]!,
  ];

  // --- SyncStorage ---------------------------------------------------------------------------------------------

  @override
  Future<List<String>> readLines() => primary.readLines();

  @override
  Future<void> appendLine(String line) async {
    await primary.appendLine(line);
    if (!_mayWrite()) return;
    await _toCopy(() async {
      if (_stale) {
        await _copyWhole();
      } else {
        await mirror.appendLine(line);
      }
    });
  }

  @override
  Future<void> replaceLines(List<String> lines) async {
    await primary.replaceLines(lines);
    if (!_mayWrite()) return;
    await _toCopy(() => mirror.replaceLines(lines));
  }

  @override
  Future<String?> readDoc(String key) => primary.readDoc(key);

  @override
  Future<void> writeDoc(String key, String content) async {
    await primary.writeDoc(key, content);
    if (_copied(key) && mirroring) {
      await _guard(
        () => mirror.writeDoc(key, content).timeout(writeTimeout),
        lines: false,
      );
      _noteAside(key, content);
    }
  }

  @override
  Future<void> deleteDoc(String key) async {
    await primary.deleteDoc(key);
    if (_copied(key) && mirroring) {
      await _guard(
        () => mirror.deleteDoc(key).timeout(writeTimeout),
        lines: false,
      );
      _aside.remove(key);
    }
  }

  bool _copied(String key) =>
      copiedDocPrefixes.any((String p) => key.startsWith(p));

  @override
  Future<List<String>> docKeys() => primary.docKeys();

  // --- the copy --------------------------------------------------------------------------------------------------

  bool _mayWrite() {
    if (!mirroring) return false;
    final DateTime? failed = _failedAt;
    if (failed == null) return true;
    if (_clock().difference(failed) < retryAfter) {
      _stale = true;
      return false;
    }
    return true;
  }

  /// A write of the journal's lines to the copy, waited for at most [writeTimeout]. Only one is out at a time: one
  /// that comes while another is out is not queued (the copy would get it late or out of order) but marks the copy
  /// behind, and the write that is out then copies the journal whole.
  Future<void> _toCopy(Future<void> Function() write) async {
    if (_busy != null) {
      _behind = true;
      return;
    }
    final Future<void> run = _drain(write);
    _busy = run;
    try {
      await run.timeout(writeTimeout);
    } on TimeoutException catch (e) {
      // The journal has the line; the copy catches up when it answers
      _error = e;
      _emit();
    }
  }

  Future<void> _drain(Future<void> Function() first) async {
    try {
      await _guard(first);
      while (_behind && mirroring && _failedAt == null) {
        _behind = false;
        await _guard(_copyWhole);
      }
      if (_behind) _stale = true;
      _behind = false;
    } finally {
      _busy = null;
    }
  }

  Future<void> _copyWhole() async =>
      mirror.replaceLines(await _complete(await primary.readLines()));

  /// Runs a write to the copy; a failure is noted, never thrown. [lines]: a write of the journal's lines (a failed
  /// one makes the copy stale; a document's does not).
  Future<void> _guard(
    Future<void> Function() write, {
    bool lines = true,
  }) async {
    try {
      await write();
      if (lines) {
        _stale = false;
        _failedAt = null;
        _error = null;
        _lastMirroredAt = _clock();
      }
    } on Object catch (e) {
      // Some, all or none of the line may be in the copy: it is written whole next time
      if (lines) {
        _stale = true;
        _failedAt = _clock();
      }
      _error = e;
    }
    _emit();
  }

  void _emit() {
    if (!_changes.isClosed) _changes.add(null);
  }

  Future<void> dispose() => _changes.close();

  // --- restore ---------------------------------------------------------------------------------------------------

  /// Compares the copy with the journal, on start before the store opens (calling it again later compares again).
  ///
  /// An operation line of the copy whose `op_id` the journal does not know (not as a line, an answer or an answered
  /// operation of its `meta` record) is put back when it is signed and its MAC verifies under one of [keys] (the
  /// key's number and device must be the line's). The lines put back go in front of the journal's own lines, which
  /// all stay as they are (a torn last line stays last: the store cuts it as usual). Every other such line (unsigned,
  /// under a key the till no longer holds, changed on the disk) is kept in the copy's folder ([keptPrefix] document),
  /// never put into the journal, and is part of [setAside]. A line kept by an earlier start is put back the same way
  /// once it verifies (and only then leaves its kept document). Only then is the copy made equal to the journal.
  /// Nothing here depends on the journal's folder (its bootstrap copy may be gone with it).
  ///
  /// Never throws and never waits for the copy longer than [restoreTimeout] a step: a copy that cannot be read, a
  /// journal that cannot be rewritten or a copy whose lines cannot be kept aside leave the journal as it was, and
  /// the copy is not written in this run ([MirrorRestore.error]).
  Future<MirrorRestore> restore({required Iterable<SyncKey> keys}) async {
    _ready = false;
    _frozen = false;
    final MirrorRestore r = await _restore(keys.toList());
    // (A copy that did not answer is not asked again: start-up goes on)
    if (r.error is! TimeoutException) {
      await _loadAside(_knownIds(await _journalOrEmpty()));
    }
    _lastRestore = r;
    if (r.error != null) {
      _error = r.error;
      _frozen = true;
    } else {
      _ready = true;
      _stale = false;
      _failedAt = null;
      _error = null;
      if (r.changed) _lastMirroredAt = _clock();
    }
    _emit();
    return r;
  }

  Future<MirrorRestore> _restore(List<SyncKey> keys) async {
    final List<String> journal;
    final List<String> copy;
    final Map<String, List<String>> earlier;
    try {
      journal = await primary.readLines();
      copy = await _bounded(mirror.readLines());
      earlier = await _keptDocs();
    } on Object catch (e) {
      return MirrorRestore(error: e);
    }
    final Set<String> known = _knownIds(journal);
    final Set<String> keptBefore = <String>{
      for (final List<String> lines in earlier.values)
        for (final String l in lines) ?_idOfLine(l),
    };
    if (_equal(journal, copy) &&
        !keptBefore.any((String id) => !known.contains(id))) {
      return MirrorRestore(
        journalLines: journal.length,
        mirrorLines: copy.length,
      );
    }

    final List<_Restorable> back = <_Restorable>[];
    final List<String> kept = <String>[];
    final Set<String> taken = <String>{};
    for (final String line in copy) {
      final Map<String, Object?>? r = _decode(line);
      if (r == null || r['k'] != 'op') continue;
      final String? id = _opIdOf(r);
      if (id == null || known.contains(id) || !taken.add(id)) continue;
      final _Restorable? ok = _verified(line, r, keys);
      if (ok != null && ok.opId == id) {
        back.add(ok);
      } else if (!keptBefore.contains(id)) {
        kept.add(line);
      }
    }
    // Lines an earlier start kept aside: put back once they verify, else left where they are
    final Map<String, List<String>> rest = <String, List<String>>{};
    for (final MapEntry<String, List<String>> doc in earlier.entries) {
      final List<String> left = <String>[];
      for (final String line in doc.value) {
        final Map<String, Object?>? r = _decode(line);
        final String? id = r == null ? null : _opIdOf(r);
        final _Restorable? ok = r == null || r['k'] != 'op' || id == null
            ? null
            : _verified(line, r, keys);
        if (id != null &&
            ok != null &&
            ok.opId == id &&
            !known.contains(id) &&
            taken.add(id)) {
          back.add(ok);
        } else {
          left.add(line);
        }
      }
      if (left.length != doc.value.length) rest[doc.key] = left;
    }

    String? keptKey;
    if (kept.isNotEmpty) {
      keptKey = '$keptPrefix${_stamp(_clock())}';
      try {
        await _bounded(mirror.writeDoc(keptKey, _keptDoc(kept)));
      } on Object catch (e) {
        return MirrorRestore(
          journalLines: journal.length,
          mirrorLines: copy.length,
          error: e,
        );
      }
    }

    back.sort((_Restorable a, _Restorable b) => a.seq.compareTo(b.seq));
    final List<String> next = <String>[
      for (final _Restorable b in back) b.line,
      ...journal,
    ];
    if (back.isNotEmpty) {
      try {
        await primary.replaceLines(next);
      } on Object catch (e) {
        return MirrorRestore(
          kept: kept.length,
          keptKey: keptKey,
          journalLines: journal.length,
          mirrorLines: copy.length,
          error: e,
        );
      }
    }
    try {
      // The copy follows the journal (a torn journal tail is not copied: it is cut on open)
      await _bounded(mirror.replaceLines(await _complete(next)));
      // Only now (the journal and the copy hold them) do restored lines leave the documents they were kept in
      for (final MapEntry<String, List<String>> doc in rest.entries) {
        await _bounded(
          doc.value.isEmpty
              ? mirror.deleteDoc(doc.key)
              : mirror.writeDoc(doc.key, _keptDoc(doc.value)),
        );
      }
    } on Object catch (e) {
      return MirrorRestore(
        restored: back.length,
        restoredPayments: back.where((_Restorable b) => b.payment).length,
        kept: kept.length,
        keptKey: keptKey,
        journalLines: journal.length,
        mirrorLines: copy.length,
        changed: back.isNotEmpty,
        error: e,
      );
    }
    return MirrorRestore(
      restored: back.length,
      restoredPayments: back.where((_Restorable b) => b.payment).length,
      kept: kept.length,
      keptKey: keptKey,
      journalLines: journal.length,
      mirrorLines: copy.length,
      changed: true,
    );
  }

  Future<T> _bounded<T>(Future<T> f) => f.timeout(restoreTimeout);

  String _keptDoc(List<String> lines) => jsonEncode(<String, Object?>{
    'version': 1,
    'kept_at': _clock().toUtc().toIso8601String(),
    'reason': 'not_verified',
    'lines': lines,
  });

  Future<List<String>> _journalOrEmpty() async {
    try {
      return await primary.readLines();
    } on Object {
      return const <String>[];
    }
  }

  /// The copy's [keptPrefix] documents: key → their lines (an unreadable one is left out, and left alone).
  Future<Map<String, List<String>>> _keptDocs() async {
    final Map<String, List<String>> out = <String, List<String>>{};
    for (final String k in await _bounded(mirror.docKeys())) {
      if (!k.startsWith(keptPrefix)) continue;
      final List<String>? lines = _keptLines(await _bounded(mirror.readDoc(k)));
      if (lines != null) out[k] = lines;
    }
    return out;
  }

  static List<String>? _keptLines(String? text) {
    if (text == null) return null;
    try {
      final Object? v = jsonDecode(text);
      final Object? lines = v is Map ? v['lines'] : null;
      return lines is List ? <String>[...lines.whereType<String>()] : null;
    } on FormatException {
      return null;
    }
  }

  static String? _idOfLine(String line) {
    final Map<String, Object?>? r = _decode(line);
    return r == null ? null : _opIdOf(r);
  }

  /// Reads [setAside] from the copy's folder (never throws; a copy that does not answer leaves it empty).
  Future<void> _loadAside(Set<String> known) async {
    _aside.clear();
    try {
      for (final String k in await _bounded(mirror.docKeys())) {
        if (!k.startsWith(keptPrefix) && !_copied(k)) continue;
        final String? text = await _bounded(mirror.readDoc(k));
        if (text != null) _noteAside(k, text, known: known);
      }
    } on Object {
      // Not readable now: the export carries the journal's own queues
    }
    _emit();
  }

  /// [content] of the copy's document [key] as a queue set aside (a kept document's lines, or a queue set aside at
  /// a disconnect as the store writes it: `{"value": {"device_uuid", "operations": [...]}}`).
  void _noteAside(
    String key,
    String content, {
    Set<String> known = const <String>{},
  }) {
    final List<SyncOperation> ops = <SyncOperation>[];
    String? device;
    try {
      if (key.startsWith(keptPrefix)) {
        for (final String line in _keptLines(content) ?? const <String>[]) {
          final Map<String, Object?>? r = _decode(line);
          if (r == null || r['k'] != 'op') continue;
          final String? id = _opIdOf(r);
          if (id == null || known.contains(id)) continue;
          try {
            // A journal record: the signed one carries its seal, the unsigned one its operation under `op`
            final Object? op = r['op'];
            ops.add(
              SyncSeal.tryParse(r) == null && op is Map
                  ? SyncOperation.fromJson(op.cast<String, Object?>())
                  : SyncOperation.fromStored(r),
            );
          } on Object {
            // Not an operation the server could read either
          }
        }
      } else {
        final Object? doc = jsonDecode(content);
        final Object? value = doc is Map ? doc['value'] : null;
        if (value is Map) {
          device = value['device_uuid'] is String
              ? value['device_uuid']! as String
              : null;
          final Object? list = value['operations'];
          for (final Object? o in list is List ? list : const <Object?>[]) {
            if (o is! Map) continue;
            try {
              ops.add(SyncOperation.fromStored(o.cast<String, Object?>()));
            } on Object {
              // An unreadable entry stays in the file as it is
            }
          }
        }
      }
    } on FormatException {
      // Left out
    }
    if (ops.isEmpty) {
      _aside.remove(key);
    } else {
      _aside[key] = SyncExportQueue(device: device, operations: ops);
    }
  }

  /// [lines] without a last line that is not a whole record (it never was confirmed).
  static Future<List<String>> _complete(List<String> lines) async {
    var n = lines.length;
    while (n > 0 && lines[n - 1].isEmpty) {
      n--;
    }
    if (n > 0 && _decode(lines[n - 1]) == null) return lines.sublist(0, n - 1);
    return lines;
  }

  static bool _equal(List<String> a, List<String> b) {
    if (a.length != b.length) return false;
    for (var i = 0; i < a.length; i++) {
      if (a[i] != b[i]) return false;
    }
    return true;
  }

  /// Every `op_id` the journal knows: its operation lines, its answers and its `meta` record's answered operations.
  static Set<String> _knownIds(List<String> journal) {
    final Set<String> ids = <String>{};
    for (final String line in journal) {
      final Map<String, Object?>? r = _decode(line);
      if (r == null) continue;
      switch (r['k']) {
        case 'op':
          final String? id = _opIdOf(r);
          if (id != null) ids.add(id);
        case 'ack':
          if (r['op_id'] is String) ids.add(r['op_id']! as String);
        case 'meta':
          final Object? answered = r['answered'];
          if (answered is! List) continue;
          for (final Object? a in answered) {
            final Object? op = a is Map ? a['op'] : null;
            final Object? id = op is Map ? op['op_id'] : null;
            if (id is String) ids.add(id);
          }
      }
    }
    return ids;
  }

  /// The `op_id` of an operation line: of its signed text when signed (what the server reads), else of `op`.
  static String? _opIdOf(Map<String, Object?> r) {
    final Object? raw = r['raw'];
    Object? op = r['op'];
    if (raw is String) {
      try {
        op = jsonDecode(raw);
      } on FormatException {
        op = null;
      }
    }
    if (op is Map && op['op_id'] is String) return op['op_id']! as String;
    return null;
  }

  static _Restorable? _verified(
    String line,
    Map<String, Object?> r,
    List<SyncKey> keys,
  ) {
    final SyncSeal? seal = SyncSeal.tryParse(r);
    if (seal == null || !SyncSignature.isMac(seal.prev)) return null;
    for (final SyncKey k in keys) {
      if (k.kid != seal.kid) continue;
      final String? dev = seal.device;
      if (dev != null && dev.toLowerCase() != k.deviceUuid.toLowerCase()) {
        continue;
      }
      if (SyncSignature.mac(k, seal.prev, seal.raw) != seal.mac) continue;
      try {
        final SyncOperation op = SyncOperation.fromStored(r);
        return _Restorable(
          line: line,
          opId: op.opId,
          seq: op.seq,
          payment: op.type == SyncOpType.billPay,
        );
      } on Object {
        return null;
      }
    }
    return null;
  }

  /// A journal record: `<crc32> <json>` (the store's line format).
  static Map<String, Object?>? _decode(String line) {
    if (line.length < 10 || line.codeUnitAt(8) != 0x20) return null;
    final String json = line.substring(9);
    if (crc32Hex(json) != line.substring(0, 8)) return null;
    try {
      final Object? v = jsonDecode(json);
      return v is Map ? v.cast<String, Object?>() : null;
    } on FormatException {
      return null;
    }
  }

  static String _stamp(DateTime at) =>
      at.toUtc().toIso8601String().replaceAll(RegExp('[^0-9]'), '');
}

class _Restorable {
  const _Restorable({
    required this.line,
    required this.opId,
    required this.seq,
    required this.payment,
  });

  final String line;
  final String opId;
  final int seq;
  final bool payment;
}

/// The folder of the copy and of the export files under a base folder: `<base>/RIZQIM/<device uuid>` (Windows: the
/// user's Documents; Android: the app's external files directory; chosen in the cashier app).
Directory mirrorDirectoryIn(Directory base, String deviceUuid) => Directory(
  '${base.path}${Platform.pathSeparator}RIZQIM${Platform.pathSeparator}${deviceUuid.toLowerCase()}',
);
