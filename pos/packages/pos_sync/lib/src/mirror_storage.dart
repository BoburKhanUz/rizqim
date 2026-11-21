import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'crc32.dart';
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

  /// Operations only the copy had that could not be verified (unsigned, another key, a changed line) or were not to
  /// be restored (restore off): never put into the journal, kept in the copy's folder under [keptKey].
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
/// * The copies of documents (menu, bootstrap, queues set aside) stay with the journal only.
/// * [restore] runs once on start, BEFORE `SyncStore.open`; until it has compared the two the copy is not written
///   (it may hold the only record of a journal that is gone). It puts back every operation the copy has and the
///   journal does not know, when its MAC verifies under one of the till's keys, and never removes a journal line.
class MirroredSyncStorage implements SyncStorage {
  MirroredSyncStorage(
    this.primary,
    this.mirror, {
    this.directory,
    this.copiedDocPrefixes = const <String>['orphan_'],
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

  // --- SyncStorage ---------------------------------------------------------------------------------------------

  @override
  Future<List<String>> readLines() => primary.readLines();

  @override
  Future<void> appendLine(String line) async {
    await primary.appendLine(line);
    if (!_mayWrite()) return;
    await _guard(() async {
      if (_stale) {
        await mirror.replaceLines(await _complete(await primary.readLines()));
      } else {
        await mirror.appendLine(line);
      }
    });
  }

  @override
  Future<void> replaceLines(List<String> lines) async {
    await primary.replaceLines(lines);
    if (!_mayWrite()) return;
    await _guard(() => mirror.replaceLines(lines));
  }

  @override
  Future<String?> readDoc(String key) => primary.readDoc(key);

  @override
  Future<void> writeDoc(String key, String content) async {
    await primary.writeDoc(key, content);
    if (_copied(key) && mirroring) {
      await _guard(() => mirror.writeDoc(key, content), lines: false);
    }
  }

  @override
  Future<void> deleteDoc(String key) async {
    await primary.deleteDoc(key);
    if (_copied(key) && mirroring) {
      await _guard(() => mirror.deleteDoc(key), lines: false);
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
  /// under a key the till no longer holds, changed on the disk, or all of them when [enabled] is false: bootstrap
  /// `offline.export` is off) is kept in the copy's folder ([keptPrefix] document), never put into the journal.
  /// Only then is the copy made equal to the journal.
  ///
  /// Never throws: a copy that cannot be read, a journal that cannot be rewritten or a copy whose lines cannot be
  /// kept aside leave the journal as it was, and the copy is not written in this run ([MirrorRestore.error]).
  Future<MirrorRestore> restore({
    required Iterable<SyncKey> keys,
    bool enabled = true,
  }) async {
    _ready = false;
    _frozen = false;
    final MirrorRestore r = await _restore(keys.toList(), enabled);
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

  Future<MirrorRestore> _restore(List<SyncKey> keys, bool enabled) async {
    final List<String> journal;
    final List<String> copy;
    try {
      journal = await primary.readLines();
      copy = await mirror.readLines();
    } on Object catch (e) {
      return MirrorRestore(error: e);
    }
    if (_equal(journal, copy)) {
      return MirrorRestore(
        journalLines: journal.length,
        mirrorLines: copy.length,
      );
    }

    final Set<String> known = _knownIds(journal);
    final List<_Restorable> back = <_Restorable>[];
    final List<String> kept = <String>[];
    final Set<String> taken = <String>{};
    for (final String line in copy) {
      final Map<String, Object?>? r = _decode(line);
      if (r == null || r['k'] != 'op') continue;
      final String? id = _opIdOf(r);
      if (id == null || known.contains(id) || !taken.add(id)) continue;
      final _Restorable? ok = enabled ? _verified(line, r, keys) : null;
      if (ok != null && ok.opId == id) {
        back.add(ok);
      } else {
        kept.add(line);
      }
    }

    String? keptKey;
    if (kept.isNotEmpty) {
      keptKey = '$keptPrefix${_stamp(_clock())}';
      try {
        await mirror.writeDoc(
          keptKey,
          jsonEncode(<String, Object?>{
            'version': 1,
            'kept_at': _clock().toUtc().toIso8601String(),
            'reason': enabled ? 'not_verified' : 'restore_off',
            'lines': kept,
          }),
        );
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
      await mirror.replaceLines(await _complete(next));
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
