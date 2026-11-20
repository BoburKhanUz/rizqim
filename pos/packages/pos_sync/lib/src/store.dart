import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'crc32.dart';
import 'operation.dart';
import 'queue_numbers.dart';
import 'storage.dart';
import 'uuid.dart';

/// Kalit bo'yicha saqlangan nusxa (bootstrap, menyu, zal, buyurtma...): qiymati, qachon olingani va o'sha paytdagi
/// oxirgi `seq` (undan keyingi amallar nusxa ustiga qo'yiladi).
class SyncDoc {
  const SyncDoc({required this.value, required this.takenAt, this.seq = 0});

  final Object? value;
  final DateTime takenAt;
  final int seq;

  Map<String, Object?>? get map =>
      value is Map ? (value! as Map).cast<String, Object?>() : null;

  Duration age(DateTime now) => now.difference(takenAt);
}

/// Jurnalning o'qib bo'lmagan qatori (nazorat yig'indisi to'g'ri kelmadi): o'tkazib yuborilgan, undan keyingi qatorlar
/// o'qilgan. Bu qatorda tasdiqlangan amal bo'lgan bo'lishi mumkin — menejer ko'rishi kerak.
class JournalDamage {
  const JournalDamage({
    required this.line,
    required this.text,
    required this.foundAt,
  });

  /// Topilgan paytdagi qator raqami (1 dan).
  final int line;

  /// Qatorning diskdagi matni (buzilgan holida).
  final String text;
  final DateTime foundAt;
}

/// Kassaning internetsiz rejimdagi xotirasi (docs/POS_SPEC.md 5.2): diskdagi amallar navbati va serverdan olingan
/// oxirgi nusxalar.
///
/// * [enqueue] amalni jurnalga yozadi va diskka tasdiqlatadi (`fsync`), shundan keyingina qaytadi: kassir "Saqlandi"ni
///   ko'rgan amal elektr o'chsa ham yo'qolmaydi. Har amalga yangi `op_id` (UUID) va keyingi `seq` beriladi.
/// * Qayta ochilganda ([open]) jurnal o'qiladi: nazorat yig'indisi to'g'ri kelmagan (chala yozilgan) oxirgi qator
///   kesib tashlanadi, navbat, `seq`, lokal raqamlar va server id'lari qayta tiklanadi. O'rtadagi buzilgan qator
///   o'tkazib yuboriladi va [damaged] da ko'rsatiladi: undan keyingi qatorlar yo'qolmaydi.
/// * Yozuv xato bersa (disk to'la, kiritish-chiqarish xatosi) amal navbatga tushmaydi; ilova ishlashda davom etsa,
///   keyingi yozuv yangi qatordan boshlanadi va xato bergan yozuvning qoldig'i bekor deb belgilanadi.
/// * Server javobi ([applyResults]) amalni navbatdan oladi; rad etilgan yoki farq bilan qo'llanganlari [errors] da
///   qoladi, oflayn ochilgan buyurtma va schyotning server uuid'i [aliases] ga yoziladi.
/// * Nusxalar ([putDoc], [doc]) — alohida fayllar, har biri bir butun yoziladi; yoshi [SyncDoc.takenAt] dan.
class SyncStore {
  SyncStore._(this.storage, this._clock, this._random);

  final SyncStorage storage;
  final DateTime Function() _clock;
  final Random? _random;

  final List<SyncOperation> _pending = <SyncOperation>[];
  final List<SyncOperation> _answered = <SyncOperation>[];
  final Map<String, String> _aliases = <String, String>{};
  final Map<String, SyncDoc> _docs = <String, SyncDoc>{};
  final List<JournalDamage> _damaged = <JournalDamage>[];
  final StreamController<void> _changes = StreamController<void>.broadcast();

  int _lastSeq = 0;
  int _nextLocal = 1;
  // P6-13: the business day of the queue numbers and the last number this till used in it
  String? _queueDate;
  int _queueLast = 0;
  int _floorSeq = 0;
  int _journalLines = 0;
  DateTime? _lastContactAt;
  DateTime? _contactWrittenAt;

  // M5 bug 3: the journal line whose append failed while the app went on. Some, all or none of it may be in the file;
  // the next append fences it off first (see [_append]).
  String? _failedLine;

  // Every mutation (journal append, compaction, copy write) runs one at a time, in call order: the sender's acks,
  // the cashier's payment and a copy refresh can all be in flight at once.
  Future<void>? _tail;

  /// Jurnalda shuncha qatordan oshsa siqiladi.
  static const int compactAfterLines = 800;

  /// Javob olgan amallardan oxirgi shunchasi saqlanadi (xatolar ro'yxati va qayta ochilganda ko'rinish uchun); nusxalarda
  /// hali yo'q amallar ([markReloaded] dan keyingilari) esa hammasi.
  static const int keepAnswered = 200;

  /// Buzilgan qatorlardan oxirgi shunchasi saqlanadi.
  static const int keepDamaged = 100;

  /// Jurnal va nusxalarni o'qib ochadi.
  static Future<SyncStore> open(
    SyncStorage storage, {
    DateTime Function()? clock,
    Random? random,
  }) async {
    final SyncStore s = SyncStore._(storage, clock ?? DateTime.now, random);
    await s._load();
    return s;
  }

  /// Navbat, javob yoki nusxa o'zgardi.
  Stream<void> get changes => _changes.stream;

  /// Serverga hali yetmagan amallar, `seq` tartibida.
  List<SyncOperation> get pending => List<SyncOperation>.unmodifiable(_pending);

  int get pendingCount => _pending.length;

  bool get hasPending => _pending.isNotEmpty;

  /// Navbatdagi oflayn ochilgan buyurtmalar soni (500 dan oshsa yangi buyurtma bloklanadi).
  int get pendingOrders => _pending
      .where((SyncOperation o) => o.type == SyncOpType.orderOpen)
      .length;

  /// Navbatdagi eng eski amal qachon bajarilgan.
  DateTime? get oldestPendingAt =>
      _pending.isEmpty ? null : _pending.first.occurredAt;

  /// Navbatdagi eng eski to'lov (uning fiskal cheki hali so'ralmagan: 48 soatlik chegara shundan sanaladi).
  DateTime? get oldestUnsyncedSaleAt {
    for (final SyncOperation o in _pending) {
      if (o.type == SyncOpType.billPay) return o.occurredAt;
    }
    return null;
  }

  int get lastSeq => _lastSeq;

  /// Server javob bergan amallar (eng yangisi oxirida), ko'pi bilan [keepAnswered].
  List<SyncOperation> get answered =>
      List<SyncOperation>.unmodifiable(_answered);

  /// Rad etilgan yoki farq bilan qo'llangan amallar (eng yangisi birinchi).
  List<SyncOperation> get errors => <SyncOperation>[
    for (final SyncOperation o in _answered.reversed)
      if (o.status.needsAttention) o,
  ];

  /// Barcha amallar (javob olganlar va navbatdagilar), `seq` tartibida: nusxa ustiga qayta qo'yish uchun.
  List<SyncOperation> operationsAfter(int seq) => <SyncOperation>[
    for (final SyncOperation o in _answered)
      if (o.seq > seq) o,
    for (final SyncOperation o in _pending)
      if (o.seq > seq) o,
  ]..sort((SyncOperation a, SyncOperation b) => a.seq.compareTo(b.seq));

  /// Qurilma id'si → server uuid'i (oflayn ochilgan buyurtma, schyot).
  Map<String, String> get aliases => Map<String, String>.unmodifiable(_aliases);

  /// Server uuid'i ma'lum bo'lsa — o'sha, aks holda [id] ning o'zi.
  String resolve(String id) => _aliases[id] ?? id;

  /// Jurnalning o'qib bo'lmagan qatorlari (eng eskisi birinchi): o'tkazib yuborilgan, lekin unutilmagan — siqishdan
  /// keyin ham saqlanadi, faqat [clear] o'chiradi. Elektr o'chganda chala qolgan oxirgi qator va xato bergan yozuvning
  /// qoldig'i bu yerga kirmaydi (ular hech qachon tasdiqlanmagan).
  List<JournalDamage> get damaged => List<JournalDamage>.unmodifiable(_damaged);

  /// Server bilan oxirgi aloqa (javob kelgan) payti.
  DateTime? get lastContactAt => _lastContactAt;

  // --- navbat ---------------------------------------------------------------------------------------------------

  /// Amalni navbatga yozadi (diskka tasdiqlangach qaytadi). Bir vaqtda kelgan chaqiriqlar navbat bilan yoziladi:
  /// `seq` yozuv paytida beriladi, takrorlanmaydi.
  ///
  /// P6 lokal ko'prik: markaz boshqa qurilmaning amalini o'sha qurilma bergan [opId] va [origin] bilan yozadi; shu
  /// [opId] li amal navbatda (yoki javob olganlar orasida) bo'lsa — yangisi yozilmaydi, o'sha qaytadi.
  Future<SyncOperation> enqueue(
    String type,
    Map<String, Object?> payload, {
    required int staffId,
    DateTime? occurredAt,
    String? opId,
    String? origin,
  }) => _locked(() async {
    if (opId != null) {
      final SyncOperation? known = find(opId);
      if (known != null) return known;
    }
    var id = opId ?? newUuid(_random);
    while (opId == null && find(id) != null) {
      id = newUuid(_random);
    }
    final SyncOperation op = SyncOperation(
      opId: id,
      seq: _lastSeq + 1,
      type: type,
      occurredAt: occurredAt ?? _clock(),
      staffId: staffId,
      payload: payload,
      origin: origin,
    );
    await _append(<String, Object?>{'k': 'op', 'op': op.toJson()});
    _lastSeq = op.seq;
    _bumpLocal(payload);
    _pending.add(op);
    _emit();
    return op;
  });

  /// [opId] li amal (navbatdagi yoki javob olgan, oxirgi [keepAnswered] tasi), bo'lmasa `null`.
  SyncOperation? find(String opId) {
    for (final SyncOperation o in _pending) {
      if (o.opId == opId) return o;
    }
    for (final SyncOperation o in _answered) {
      if (o.opId == opId) return o;
    }
    return null;
  }

  /// Keyingi lokal buyurtma raqami (`K1-017`): qurilmaning prefiksi bilan, hech qachon takrorlanmaydi
  /// ([serverNext] — serverdagi shu qurilmaning keyingi raqami, ilova qayta o'rnatilsa ham 1 dan boshlanmasin).
  /// Raqam `order.open` amalining `local_number` ida saqlanadi.
  String nextLocalNumber(String prefix, {int serverNext = 1}) {
    final int n = max(_nextLocal, serverNext);
    _nextLocal = n + 1;
    return '$prefix-${n.toString().padLeft(3, '0')}';
  }

  /// [businessDate] da kassa ishlatgan oxirgi navbat raqami (boshqa kun — 0).
  int lastQueueNumber(String businessDate) =>
      _queueDate == businessDate ? _queueLast : 0;

  /// P6-13: keyingi navbat raqamini oladi (docs/POS_SPEC.md 2.14): [ranges] — kassaning [businessDate] dagi bloklari
  /// (bootstrap `queue.blocks`). Raqam diskka yozilgach qaytadi va shu kunda boshqa hech qachon berilmaydi (internet
  /// bo'lmasa ham, ilova qayta ochilsa ham). Bloklar tugagan bo'lsa — `null`: buyurtma raqamsiz ochiladi, raqamni
  /// server beradi. Raqam `order.open` ning `queue_number` ida (yoki onlayn `POST /pos/orders` da) yuboriladi.
  Future<int?> takeQueueNumber(String businessDate, List<QueueRange> ranges) =>
      _locked(() async {
        final int? n = QueueNumberRule.next(
          ranges,
          lastQueueNumber(businessDate),
        );
        if (n == null) return null;
        await _append(<String, Object?>{
          'k': 'queue',
          'date': businessDate,
          'n': n,
        });
        _noteQueue(businessDate, n);
        _emit();
        return n;
      });

  /// Server buyurtmaga boshqa raqam bergan bo'lsa (`queue_number_taken`) yoki onlayn javobdagi raqam: u ham
  /// ishlatilgan hisoblanadi (kassa undan pastga qaytmaydi).
  Future<void> noteQueueNumber(String businessDate, int number) =>
      _locked(() async {
        if (number <= lastQueueNumber(businessDate)) return;
        await _append(<String, Object?>{
          'k': 'queue',
          'date': businessDate,
          'n': number,
        });
        _noteQueue(businessDate, number);
        _emit();
      });

  /// [ranges] da [businessDate] uchun qolgan raqamlar soni.
  int queueNumbersLeft(String businessDate, List<QueueRange> ranges) =>
      QueueNumberRule.remaining(ranges, lastQueueNumber(businessDate));

  /// Server javoblari: navbatdan olinadi, javob saqlanadi, server uuid'lari yoziladi.
  Future<void> applyResults(List<SyncResult> results) async {
    if (results.isEmpty) return;
    await _locked(() => _applyResults(results));
  }

  Future<void> _applyResults(List<SyncResult> results) async {
    final DateTime now = _clock();
    for (final SyncResult r in results) {
      final int i = _pending.indexWhere((SyncOperation o) => o.opId == r.opId);
      if (i < 0) continue;
      await _append(<String, Object?>{
        'k': 'ack',
        'op_id': r.opId,
        'status': r.status.wire,
        'reason': r.reason,
        'message': r.message,
        'refs': r.refs,
        'at': now.toUtc().toIso8601String(),
      });
      _answer(_pending.removeAt(i).answered(r, now));
    }
    await _maybeCompact();
    _emit();
  }

  /// M5 S1 review (design bug 5): the server holds this device's numbers up to [serverLastSeq], and the till's journal
  /// does not know it (the journal was lost or damaged while the pairing survived, so the till counts from a lower
  /// number and every batch is answered 409 `seq_reused`). New operations are numbered after [serverLastSeq]; the
  /// operations still waiting (never acknowledged) whose numbers the server has already used are renumbered after it,
  /// in the same order. Their `op_id` and payload (the idempotency key included) stay, so one the server did take
  /// earlier is still answered as a duplicate. Acknowledged operations keep their numbers.
  ///
  /// The journal is rewritten in one piece before this returns. `false`: nothing had to change.
  Future<bool> adoptServerSeq(int serverLastSeq) => _locked(() async {
    final bool collides = _pending.any(
      (SyncOperation o) => o.seq <= serverLastSeq,
    );
    if (!collides && serverLastSeq <= _lastSeq) return false;
    final List<SyncOperation> before = List<SyncOperation>.of(_pending);
    final int lastBefore = _lastSeq;
    if (collides) {
      var next = serverLastSeq;
      for (var i = 0; i < _pending.length; i++) {
        _pending[i] = SyncOperation.fromJson(<String, Object?>{
          ..._pending[i].toJson(),
          'seq': ++next,
        });
      }
      _lastSeq = max(_lastSeq, next);
    } else {
      _lastSeq = serverLastSeq;
    }
    try {
      await _maybeCompact(force: true);
    } catch (_) {
      // Not written: the old numbers stay (the server answers `seq_reused` again and this is done again)
      _pending
        ..clear()
        ..addAll(before);
      _lastSeq = lastBefore;
      rethrow;
    }
    _emit();
    return true;
  });

  /// Navbat bo'shab, server holati qayta yuklandi: hozirgi [lastSeq] gacha bo'lgan amallar endi nusxalarda bor
  /// (siqishda tashlanadi).
  Future<void> markReloaded() => _locked(() async {
    _floorSeq = _lastSeq;
    _trim();
    await _maybeCompact(force: true);
  });

  // --- nusxalar ---------------------------------------------------------------------------------------------------

  SyncDoc? doc(String key) => _docs[key];

  Iterable<String> docKeys({String prefix = ''}) =>
      _docs.keys.where((String k) => k.startsWith(prefix));

  /// Serverdan olingan nusxa (u hozirgi [lastSeq] gacha hamma amalni o'z ichiga oladi).
  Future<void> putDoc(String key, Object? value, {DateTime? takenAt}) =>
      _locked(() async {
        final SyncDoc d = SyncDoc(
          value: value,
          takenAt: takenAt ?? _clock(),
          seq: _lastSeq,
        );
        _docs[key] = d;
        await storage.writeDoc(
          key,
          jsonEncode(<String, Object?>{
            'taken_at': d.takenAt.toUtc().toIso8601String(),
            'seq': d.seq,
            'value': value,
          }),
        );
        _emit();
      });

  Future<void> removeDoc(String key) => _locked(() async {
    if (_docs.remove(key) == null) return;
    await storage.deleteDoc(key);
    _emit();
  });

  /// Server javob berdi (yarim daqiqada bir martadan ko'p diskka yozilmaydi).
  Future<void> markContact([DateTime? at]) async {
    final DateTime now = at ?? _clock();
    _lastContactAt = now;
    final DateTime? written = _contactWrittenAt;
    if (written != null && now.difference(written).inSeconds < 30) return;
    _contactWrittenAt = now;
    await _locked(
      () => storage.writeDoc(
        _contactKey,
        jsonEncode(<String, Object?>{'at': now.toUtc().toIso8601String()}),
      ),
    );
  }

  /// Terminal uzildi: navbat va nusxalar o'chiriladi. Oxirgi `seq` va lokal raqam esa qoladi: server bir qurilmada
  /// bir `seq` ni ikki marta qabul qilmaydi (`seq_reused`), qayta ulangan kassa davom ettiradi.
  Future<void> clear() => _locked(() async {
    _pending.clear();
    _answered.clear();
    _aliases.clear();
    _damaged.clear();
    for (final String k in _docs.keys.toList()) {
      await storage.deleteDoc(k);
    }
    _docs.clear();
    await storage.deleteDoc(_contactKey);
    _floorSeq = _lastSeq;
    await _maybeCompact(force: true);
    _lastContactAt = null;
    _contactWrittenAt = null;
    _emit();
  });

  Future<void> dispose() => _changes.close();

  // --- jurnal -----------------------------------------------------------------------------------------------------

  static const String _contactKey = '_contact';

  /// Runs [body] after every mutation called before it has finished (whether it failed or not).
  Future<T> _locked<T>(Future<T> Function() body) {
    final Future<void>? before = _tail;
    final Completer<void> done = Completer<void>();
    _tail = done.future;
    // Idle: start at once (no hop through an already completed future, which may belong to another zone)
    final Future<T> run = before == null
        ? Future<T>.sync(body)
        : before.then((_) => body());
    return run.whenComplete(() {
      if (identical(_tail, done.future)) _tail = null;
      done.complete();
    });
  }

  Future<void> _append(Map<String, Object?> record) async {
    final String? failed = _failedLine;
    if (failed != null) {
      // M5 bug 3: the last append failed and the app went on. An empty line ends whatever part of that line is in the
      // file, so this record starts on a fresh line; the `void` record names the failed line by its checksum, so the
      // reader drops it even if it landed whole (written but not confirmed: the cashier saw an error, and the next
      // operation reuses its `seq`).
      await storage.appendLine('');
      _journalLines++;
      await storage.appendLine(
        _encode(<String, Object?>{'k': 'void', 'sum': failed.substring(0, 8)}),
      );
      _journalLines++;
      _failedLine = null;
    }
    final String line = _encode(record);
    try {
      await storage.appendLine(line);
    } catch (_) {
      _failedLine = line;
      rethrow;
    }
    _journalLines++;
  }

  static String _encode(Map<String, Object?> record) {
    final String json = jsonEncode(record);
    return '${crc32Hex(json)} $json';
  }

  static Map<String, Object?>? _decode(String line) {
    final int space = line.indexOf(' ');
    if (space != 8) return null;
    final String json = line.substring(9);
    if (crc32Hex(json) != line.substring(0, 8)) return null;
    try {
      final Object? v = jsonDecode(json);
      return v is Map ? v.cast<String, Object?>() : null;
    } on FormatException {
      return null;
    }
  }

  Future<void> _load() async {
    final List<String> lines = await storage.readLines();
    final List<Map<String, Object?>?> records = <Map<String, Object?>?>[
      for (final String line in lines) _decode(line),
    ];
    // A torn tail: the last line is unreadable (a power cut in the middle of a write; it was never confirmed). Cut
    // it, with the empty lines after it. Only the LAST line: an unreadable line with good lines after it is damage
    // (below), and the good lines are kept (M5 bug 3).
    var count = lines.length;
    while (count > 0 && lines[count - 1].isEmpty) {
      count--;
    }
    if (count > 0 && records[count - 1] == null) {
      count--;
    } else {
      count = lines.length;
    }
    // Lines whose append failed while the app kept running: the `void` record written before the next append names
    // the failed line by its checksum (the first 8 characters). Whatever part of that line is right before the
    // record is not an operation and not damage.
    final Set<int> voided = <int>{};
    for (var i = 0; i < count; i++) {
      final Object? sum = records[i]?['k'] == 'void'
          ? records[i]!['sum']
          : null;
      if (sum is! String || sum.isEmpty) continue;
      for (var j = i - 1; j >= 0; j--) {
        final String before = lines[j];
        if (before.isEmpty) continue;
        final bool whole = before.startsWith('$sum ');
        // (a `void` record that tore itself and was written again is skipped the same way)
        if (!whole &&
            !before.startsWith(sum) &&
            !sum.startsWith(before) &&
            !lines[i].startsWith(before)) {
          break;
        }
        voided.add(j);
        // It landed whole: nothing before it belongs to it
        if (whole && records[j] != null) break;
      }
    }
    final DateTime now = _clock();
    final Map<String, SyncOperation> byId = <String, SyncOperation>{};
    for (var i = 0; i < count; i++) {
      if (lines[i].isEmpty || voided.contains(i)) continue;
      final Map<String, Object?>? r = records[i];
      if (r == null) {
        // Skipped and reported; the lines after it are read as usual
        _damaged.add(JournalDamage(line: i + 1, text: lines[i], foundAt: now));
        continue;
      }
      switch (r['k']) {
        case 'meta':
          _lastSeq = max(_lastSeq, _int(r['last_seq']));
          _nextLocal = max(_nextLocal, _int(r['next_local'], 1));
          _floorSeq = max(_floorSeq, _int(r['floor_seq']));
          if (r['queue_date'] is String) {
            _noteQueue(r['queue_date']! as String, _int(r['queue_last']));
          }
          for (final MapEntry<String, Object?> e in _mapOf(
            r['aliases'],
          ).entries) {
            if (e.value is String) _aliases[e.key] = e.value! as String;
          }
          for (final Object? a
              in (r['answered'] as List<Object?>?) ?? const <Object?>[]) {
            final Map<String, Object?> m = _mapOf(a);
            final SyncOperation op = SyncOperation.fromJson(_mapOf(m['op']));
            _answered.add(
              op.answered(_resultOf(op.opId, m), _dateOf(m['at']) ?? _clock()),
            );
          }
        case 'op':
          final SyncOperation op = SyncOperation.fromJson(_mapOf(r['op']));
          if (byId.containsKey(op.opId) ||
              _answered.any((SyncOperation a) => a.opId == op.opId)) {
            continue;
          }
          byId[op.opId] = op;
          _pending.add(op);
          _lastSeq = max(_lastSeq, op.seq);
          _bumpLocal(op.payload);
        case 'queue':
          if (r['date'] is String) {
            _noteQueue(r['date']! as String, _int(r['n']));
          }
        case 'bad':
          // Damage found at an earlier start, kept through a compaction
          _damaged.add(
            JournalDamage(
              line: _int(r['line']),
              text: r['text'] is String ? r['text']! as String : '',
              foundAt: _dateOf(r['at']) ?? now,
            ),
          );
        case 'ack':
          final String id = r['op_id']! as String;
          final int i = _pending.indexWhere((SyncOperation o) => o.opId == id);
          if (i < 0) continue;
          _answer(
            _pending
                .removeAt(i)
                .answered(_resultOf(id, r), _dateOf(r['at']) ?? _clock()),
          );
      }
    }
    _journalLines = count;
    _pending.sort((SyncOperation a, SyncOperation b) => a.seq.compareTo(b.seq));
    if (count < lines.length) {
      // Cut the torn tail now, before anything is appended after it
      await storage.replaceLines(lines.sublist(0, count));
    }

    for (final String key in await storage.docKeys()) {
      final String? text = await storage.readDoc(key);
      if (text == null) continue;
      try {
        final Map<String, Object?> m = _mapOf(jsonDecode(text));
        if (key == _contactKey) {
          _lastContactAt = _dateOf(m['at']);
          continue;
        }
        _docs[key] = SyncDoc(
          value: m['value'],
          takenAt:
              _dateOf(m['taken_at']) ?? DateTime.fromMillisecondsSinceEpoch(0),
          seq: _int(m['seq']),
        );
      } on FormatException {
        // An unreadable copy is as good as none
      }
    }
  }

  void _answer(SyncOperation op) {
    _answered.add(op);
    _trim();
    final String? server = op.refs['order_uuid'] as String?;
    if (op.type == SyncOpType.orderOpen && server != null) {
      final String? client = op.payload['order'] as String?;
      if (client != null && client != server) _aliases[client] = server;
    }
    final String? bill = op.refs['bill_uuid'] as String?;
    if (op.type == SyncOpType.billCreate && bill != null) {
      final String? client = op.payload['bill'] as String?;
      if (client != null && client != bill) _aliases[client] = bill;
    }
  }

  /// Keeps at most [keepAnswered] answered operations, but never one the copies do not have yet (above the floor).
  void _trim() {
    var excess = _answered.length - keepAnswered;
    if (excess <= 0) return;
    _answered.removeWhere((SyncOperation o) {
      if (excess <= 0 || o.seq > _floorSeq) return false;
      excess--;
      return true;
    });
  }

  /// A later business day starts again from 0; within the day the number only grows.
  void _noteQueue(String date, int n) {
    if (_queueDate == null || date.compareTo(_queueDate!) > 0) {
      _queueDate = date;
      _queueLast = n;
    } else if (date == _queueDate) {
      _queueLast = max(_queueLast, n);
    }
  }

  void _bumpLocal(Map<String, Object?> payload) {
    final Object? local = payload['local_number'];
    if (local is! String) return;
    final int dash = local.lastIndexOf('-');
    final int? n = dash < 0 ? null : int.tryParse(local.substring(dash + 1));
    if (n != null) _nextLocal = max(_nextLocal, n + 1);
  }

  /// Siqish: jurnal boshiga holat (oxirgi `seq`, lokal raqam, server uuid'lari, javob olgan amallar), keyin
  /// navbatdagilar. Vaqtinchalik fayl + rename: o'chib qolsa eski jurnal butun qoladi.
  Future<void> _maybeCompact({bool force = false}) async {
    if (!force && _journalLines < compactAfterLines) return;
    final List<String> lines = <String>[
      _encode(<String, Object?>{
        'k': 'meta',
        'last_seq': _lastSeq,
        'next_local': _nextLocal,
        'floor_seq': _floorSeq,
        'queue_date': _queueDate,
        'queue_last': _queueLast,
        'aliases': _aliases,
        'answered': <Object?>[
          for (final SyncOperation o in _answered)
            if (o.seq > _floorSeq || o.status.needsAttention)
              <String, Object?>{
                'op': o.toJson(),
                'status': o.status.wire,
                'reason': o.reason,
                'message': o.message,
                'refs': o.refs,
                'at': o.answeredAt?.toUtc().toIso8601String(),
              },
        ],
      }),
      for (final JournalDamage d in _damaged.skip(
        max(0, _damaged.length - keepDamaged),
      ))
        _encode(<String, Object?>{
          'k': 'bad',
          'line': d.line,
          'text': d.text,
          'at': d.foundAt.toUtc().toIso8601String(),
        }),
      for (final SyncOperation o in _pending)
        _encode(<String, Object?>{'k': 'op', 'op': o.toJson()}),
    ];
    await storage.replaceLines(lines);
    _journalLines = lines.length;
    // The file is whole again: nothing of a failed append is left in it
    _failedLine = null;
  }

  void _emit() {
    if (!_changes.isClosed) _changes.add(null);
  }

  static SyncResult _resultOf(String id, Map<String, Object?> r) => SyncResult(
    opId: id,
    seq: 0,
    status: SyncOpStatus.fromWire(r['status'] as String?),
    reason: r['reason'] as String?,
    message: r['message'] as String?,
    refs: _mapOf(r['refs']),
  );

  static Map<String, Object?> _mapOf(Object? v) =>
      v is Map ? v.cast<String, Object?>() : const <String, Object?>{};

  static int _int(Object? v, [int fallback = 0]) =>
      v is num ? v.toInt() : fallback;

  static DateTime? _dateOf(Object? v) =>
      v is String ? DateTime.tryParse(v) : null;
}
