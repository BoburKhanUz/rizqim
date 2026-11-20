import 'dart:convert';

import 'package:pos_core/pos_core.dart';
import 'package:pos_sync/pos_sync.dart' show SyncKey, SyncOpType, SyncSignature;

import '../offline/sync_models.dart';
import 'fake_pos_state.dart';

/// P3-31: soxta backend'ning `POST /pos/sync` qismi: har amalni soxta backend'ning odatdagi yo'li orqali o'sha xodim
/// nomidan bajaradi (buyurtma ochish, bosqich, yuborish, o'chirish, "berildi", schyot, to'lov), `op_id` bo'yicha bir
/// marta; javob — haqiqiy backend'dagi shakl (`results[].status`, `refs`). Rad etilgan va farqli amallar "Sinxronlash
/// xatolari"da (`GET /pos/sync/errors`, `.../resolve`).
///
/// M5 S2: `order.discount` — internetsiz berilgan chegirma onlayn qoidaning o'zi bilan, menejer tasdig'isiz
/// qo'llanadi (`refs.discount`, `discount_kind`, `discount_value`, belgi `offline_discount`); server qabul qilmasa —
/// `rejected` `discount_not_allowed` (`refs.cause`), keyingi schyot to'liq narxda (`amount_mismatch`, ma'lumot
/// uchun), to'lov baribir olinadi va kam chiqqani `conflict` `underpaid` bo'ladi. Serverdagidek (egasining qarori,
/// 20.11.2026): sinxronlash orqali HAMMA ko'pi bilan filialning kassir chegarasigacha beradi (`offline_limit`;
/// chegara yo'q — chegirma yo'q), PIN'siz davom etayotgan xodim (`staff_continued: true`) bermaydi
/// (`staff_continued`), xodim tekshiruvi (`staff_invalid`, `staff_not_signed_in`: PIN bilan kirish server amalni
/// OLGAN vaqtdan [sessionWindow] ichida) o'tmasa ham `discount_not_allowed`. Bloklangan xodim (`blocked_at`) puldan
/// boshqa hech narsa qila olmaydi (`staff_invalid`; to'lov — `staff_unverified`).
///
/// ```dart
/// final FakePosState state = FakePosState();
/// FakeCashierBackend(state);
/// final FakeSyncBackend sync = FakeSyncBackend(state);
/// sync.rejectNext[SyncOpType.orderRound] = 'order_closed';
/// ```
class FakeSyncBackend {
  FakeSyncBackend(this.state) {
    state.terminalRoutes = _terminal;
    state.syncRoutes = _staff;
    state.syncKeyGuard = _keyGuard;
  }

  final FakePosState state;

  /// M5 S3: what the server would write to `pos_sync_operations.sig_status` for each NEW operation (op_id →
  /// `ok`, `missing`, `bad`, `chain_gap`); it is also in each result (`results[].sig_status`) and in the error list.
  /// In observe mode the operation is applied all the same ([enforceSignatures]). [signedSeen]: the `kid` each signed
  /// one came with.
  final Map<String, String> sigStatus = <String, String>{};
  final Map<String, int> signedSeen = <String, int>{};

  /// M5 S3-d: the store's "enforce" switch (`stores.pos_sync_enforce_signatures`). Off: observe, everything is
  /// applied as it came. On (as `SignaturePolicy`, after the S3 review: a sale is never dropped): an unsigned or
  /// badly signed concession is refused (`order.void` rejected `signature_invalid`; `order.discount` rejected
  /// `discount_not_allowed`, `refs.cause: signature_invalid`), a payment is recorded as a conflict
  /// `payment_unverified`, every other operation is applied and becomes a conflict `signature_invalid`; a
  /// `chain_gap` one is applied and becomes a conflict `chain_gap`. An operation that is a conflict or rejected for
  /// its own reason keeps that reason.
  bool enforceSignatures = false;

  /// Every `data.unreadable` entry answered so far: signed elements whose text was no usable operation and for which
  /// nothing was recorded (`{index, op_id, seq, reason, message}`).
  final List<Map<String, Object?>> unreadable = <Map<String, Object?>>[];

  /// The chain's head as the server keeps it (`devices.sync_chain`): the last VERIFIED operation's key and mac. A
  /// badly signed one never moves it; the mac it claims counts as a `prev` only in its own place ([_claimedMac]).
  int? _headKid;
  String? _headMac;
  String? _claimedMac;
  int? _claimedKid;
  bool _lastMissing = false;

  /// Qo'llangan amallar (op_id → natija).
  final Map<String, Map<String, Object?>> results =
      <String, Map<String, Object?>>{};

  /// Hamma kelgan amallar (`seq` tartibida qo'llangan).
  final List<Map<String, Object?>> received = <Map<String, Object?>>[];

  /// Qurilma id'si → server uuid'i (buyurtma, schyot); qurilma qator id'si → server qator id'si.
  final Map<String, String> uuids = <String, String>{};
  final Map<String, int> lines = <String, int>{};

  /// Keyingi shu turdagi amal shu sabab bilan rad etiladi (sinash uchun; P3-35: `invalid_payload`,
  /// `modifier_not_found` ham).
  final Map<String, String> rejectNext = <String, String>{};

  /// P3-35: keyingi shu turdagi amal qo'llanadi va javobiga shu belgilar (`refs.flags`) qo'shiladi, masalan
  /// `{'flag': 'offline_price_mismatch', 'name': 'Osh', 'device_price': '40000.00', 'server_price': '45000.00'}`
  /// (holat `conflict`) yoki `{'flag': 'device_time_clamped', ...}` (faqat ma'lumot: `applied`).
  final Map<String, List<Map<String, Object?>>> flagNext =
      <String, List<Map<String, Object?>>>{};

  /// Serverdagidek: bu belgilar e'tibor talab qilmaydi (holat `applied` qoladi).
  static const Set<String> informationalFlags = <String>{
    'offline_price',
    'amount_mismatch',
    'local_number_taken',
    PosSyncReasons.deviceTimeClamped,
    // M5 S2
    'offline_discount',
    'discount_mismatch',
  };

  /// M5 S1: xodim shu kassada PIN bilan qachon kirgan (xodim id → vaqt). Yozuv bo'lsa, amal undan
  /// [sessionWindow] dan keyin qilingan bo'lsa xodim tekshiruvi o'tmaydi (`staff_not_signed_in`); yozuv yo'q xodim
  /// tekshirilmaydi (eski testlar).
  final Map<int, DateTime> sessionStartedAt = <int, DateTime>{};

  /// Serverdagi `PosSyncApplier::SESSION_WINDOW_HOURS`.
  static const Duration sessionWindow = Duration(hours: 36);

  /// M5 S1: xodim tekshiruvidan o'tmagan (`staff_unverified`) va schyotga tusha OLMAGAN to'lovlar (ochiq schyot yo'q:
  /// buyurtma bekor qilingan, schyot to'langan yoki noma'lum) — pul yozilgan, schyotga tushmagan:
  /// `{op_id, order_uuid, amount, reason, staff_id, claimed_staff_id}`.
  final List<Map<String, Object?>> unallocated = <Map<String, Object?>>[];

  /// M5 S1 review: xodim tekshiruvidan o'tmagan, lekin ochiq schyotga odatdagi yo'l bilan tushgan to'lovlar (schyot
  /// to'landi, buyurtma yopiladi): `{op_id, order_uuid, bill_uuid, amount, reason, staff_id, claimed_staff_id}`.
  /// `staff_id` — faqat shu kassada PIN bilan kirgani ma'lum xodim ([sessionStartedAt]), aks holda `null`.
  final List<Map<String, Object?>> unverified = <Map<String, Object?>>[];

  /// Server kassaning navbati haqida bilgani.
  int pendingReported = 0;

  /// Hal qilingan xatolar.
  final Set<String> resolved = <String>{};

  /// Har paket (`POST /pos/sync` chaqiruvlari soni).
  int batches = 0;

  List<Map<String, Object?>> get errors => <Map<String, Object?>>[
    for (final Map<String, Object?> r in results.values.toList().reversed)
      if (r['status'] != 'applied' && !resolved.contains(r['op_id'])) r,
  ];

  FakeResponse? _terminal(
    String method,
    String path,
    Map<String, String> query,
    Map<String, Object?> data,
  ) {
    if (method == 'POST' && path == '/v1/pos/sync') return _sync(data);
    if (method == 'GET' && path == '/v1/pos/sync/status') {
      if (query['queue_size'] != null) {
        pendingReported = int.tryParse(query['queue_size']!) ?? pendingReported;
      }
      return _ok(<String, Object?>{
        'server_time': state.now().toUtc().toIso8601String(),
        'device': _device(),
        'errors': <Object?>[
          for (final Map<String, Object?> e in errors) _error(e),
        ],
        'errors_count': errors.length,
      });
    }
    return null;
  }

  FakeResponse? _staff(FakeRequest r) {
    if (r.method == 'GET' && r.path == '/v1/pos/sync/errors') {
      final List<Map<String, Object?>> list = r.query['all'] == '1'
          ? <Map<String, Object?>>[
              for (final Map<String, Object?> e
                  in results.values.toList().reversed)
                if (e['status'] != 'applied') e,
            ]
          : errors;
      return _ok(<String, Object?>{
        'errors': <Object?>[
          for (final Map<String, Object?> e in list) _error(e),
        ],
        'meta': <String, Object?>{
          'current_page': 1,
          'last_page': 1,
          'per_page': 50,
          'total': list.length,
        },
      });
    }
    final RegExpMatch? m = RegExp(r'^/v1/pos/sync/errors/([^/]+)/resolve$')
        .firstMatch(r.path);
    if (r.method == 'POST' && m != null) {
      if (!state.permissionsOf(r.member).contains('orders.cancel')) {
        return const FakeResponse(403, <String, Object?>{
          'success': false,
          'message': 'Ruxsat etilmagan',
          'data': <String, Object?>{},
        });
      }
      final Map<String, Object?>? e = results[m.group(1)];
      if (e == null) {
        return const FakeResponse(404, <String, Object?>{
          'success': false,
          'message': 'Topilmadi',
          'data': <String, Object?>{},
        });
      }
      resolved.add(e['op_id']! as String);
      e['resolution'] = r.data['resolution'];
      e['resolved_at'] = state.now().toUtc().toIso8601String();
      return _ok(_error(e));
    }
    return null;
  }

  FakeResponse _sync(Map<String, Object?> data) {
    batches++;
    final List<Map<String, Object?>> notRead = <Map<String, Object?>>[];
    final List<Object?> sent =
        (data['operations'] as List<Object?>?) ?? const <Object?>[];
    final List<Map<String, Object?>> ops = <Map<String, Object?>>[];
    for (var index = 0; index < sent.length; index++) {
      final Object? o = sent[index];
      if (o is! Map) continue;
      final Map<String, Object?>? opened = _opened(
        o.cast<String, Object?>(),
        index,
        notRead,
      );
      if (opened != null) ops.add(opened);
    }
    ops.sort(
      (Map<String, Object?> a, Map<String, Object?> b) =>
          (a['seq']! as int).compareTo(b['seq']! as int),
    );
    final List<Object?> out = <Object?>[];
    for (final Map<String, Object?> op in ops) {
      final String id = op['op_id']! as String;
      final String? invalid = op.remove(_invalid) as String?;
      final int index = (op.remove(_index) as int?) ?? 0;
      final Map<String, Object?>? before = results[id];
      final bool numberTaken = received.any(
        (Map<String, Object?> o) => o['seq'] == op['seq'],
      );
      if (invalid != null && before == null && numberTaken) {
        // Its number belongs to something else: text nobody can vouch for refuses nothing
        _seals.remove(id);
        notRead.add(_unreadable(index, id, op['seq'] as int?, invalid));
        continue;
      }
      if (before != null) {
        _seals.remove(id);
        out.add(<String, Object?>{...before, 'duplicate': true});
        continue;
      }
      // As the server: one device never uses a number twice (PosSyncService: 409 `seq_reused`; what came before it
      // in the batch stays applied)
      if (numberTaken) {
        return FakeResponse(409, <String, Object?>{
          'success': false,
          'message':
              "Navbat raqami (${op['seq']}) boshqa amalga berilgan: kassa navbati buzilgan, administratorga ayting.",
          'data': <String, Object?>{'reason': 'seq_reused', 'seq': op['seq']},
        });
      }
      received.add(op);
      final String sig = _signature(id, (op['seq']! as num).toInt());
      sigStatus[id] = sig;
      final Map<String, Object?> r = <String, Object?>{
        ..._marked(
          invalid != null
              ? _result(
                  op,
                  'rejected',
                  SyncOpType.all.contains(op['type'])
                      ? PosSyncReasons.invalidPayload
                      : 'type_unknown',
                  invalid,
                  const <String, Object?>{},
                )
              : (_refusedUnsigned(op, sig) ?? _apply(op)),
          op,
          sig,
        ),
        'sig_status': sig,
      };
      results[id] = r;
      out.add(<String, Object?>{...r, 'duplicate': false});
    }
    unreadable.addAll(notRead);
    final Map<String, Object?>? queue = (data['queue'] as Map?)
        ?.cast<String, Object?>();
    final int size = (queue?['size'] as num?)?.toInt() ?? 0;
    // (a line answered as unreadable leaves the till's queue too)
    final int left = size - out.length - notRead.length;
    pendingReported = left < 0 ? 0 : left;
    return _ok(<String, Object?>{
      'server_time': state.now().toUtc().toIso8601String(),
      'clock_skew_seconds': 0,
      'clock_warning': false,
      'stopped_at': null,
      'results': out,
      'unreadable': notRead
        ..sort(
          (Map<String, Object?> a, Map<String, Object?> b) =>
              (a['index']! as int).compareTo(b['index']! as int),
        ),
      'device': _device(),
    }, message: 'Sinxronlandi.');
  }

  final Map<String, Map<String, Object?>> _seals =
      <String, Map<String, Object?>>{};
  int _lastSeq = 0;

  // (markers on an opened element, taken off before it is recorded)
  static const String _invalid = '__invalid';
  static const String _index = '__index';
  static final RegExp _uuid = RegExp(
    r'^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$',
    caseSensitive: false,
  );

  static Map<String, Object?> _unreadable(
    int index,
    String? opId,
    int? seq,
    String message,
  ) => <String, Object?>{
    'index': index,
    'op_id': opId,
    'seq': seq,
    'reason': PosSyncReasons.invalidPayload,
    'message': message,
  };

  /// M5 S3: a signed operation (`{signed, mac, prev, kid}`) is the JSON inside `signed`; its seal is kept aside for
  /// [_signature]. The old shape passes as it is.
  ///
  /// As `PosSyncController::openSigned` after the S3 review: signed text that holds no usable operation never refuses
  /// the batch. One that names itself (a uuid `op_id`, a `seq`) comes back marked [_invalid] and is recorded
  /// rejected; one that does not is answered in [notRead] by its place in the batch and `null` is returned.
  Map<String, Object?>? _opened(
    Map<String, Object?> op,
    int index,
    List<Map<String, Object?>> notRead,
  ) {
    final Object? raw = op['signed'];
    if (raw is! String) return op;
    Object? decoded;
    try {
      decoded = jsonDecode(raw);
    } on FormatException {
      decoded = null;
    }
    final Map<String, Object?> inner = decoded is Map
        ? decoded.cast<String, Object?>()
        : <String, Object?>{};
    final String? opId =
        inner['op_id'] is String && _uuid.hasMatch(inner['op_id']! as String)
        ? (inner['op_id']! as String).toLowerCase()
        : null;
    final int? seq = inner['seq'] is int && (inner['seq']! as int) >= 1
        ? inner['seq']! as int
        : null;
    final List<String> wrong = <String>[
      if (opId == null) 'op_id',
      if (seq == null) 'seq',
      if (!SyncOpType.all.contains(inner['type'])) 'type',
      if (DateTime.tryParse('${inner['occurred_at']}') == null) 'occurred_at',
      if (inner['staff_id'] is! int) 'staff_id',
      if (inner['payload'] != null && inner['payload'] is! Map) 'payload',
    ];
    if (wrong.isEmpty) {
      _seals[opId!] = op;
      return <String, Object?>{...inner, 'op_id': opId, _index: index};
    }
    final String message =
        "Navbatdagi yozuv buzilgan: amal ma'lumotlari to'liq emas yoki noto'g'ri (${wrong.join(', ')}). Amal "
        "qo'llanmadi.";
    if (opId == null || seq == null) {
      notRead.add(_unreadable(index, opId, seq, message));
      return null;
    }
    _seals[opId] = op;
    return <String, Object?>{
      'op_id': opId,
      'seq': seq,
      'type': inner['type'] is String && inner['type'] != ''
          ? inner['type']
          : 'unknown',
      'occurred_at': inner['occurred_at'] is String
          ? inner['occurred_at']
          : null,
      'staff_id': inner['staff_id'] is int ? inner['staff_id'] : null,
      'payload': inner['payload'] is Map
          ? inner['payload']
          : <String, Object?>{},
      _invalid: message,
      _index: index,
    };
  }

  /// As the server's `SyncChain` (docs/API.md "Imzolangan navbat"): the mac against the key the `kid` names (the
  /// current one or the one before it), then, in this order: the key order (an older key than the head's never
  /// follows), `seq = last + 1`, and the `prev` (the head's mac under the head's key; the mac a badly signed line in
  /// its place claimed; the key's first prev for a newer key, or for the same key straight after an unsigned
  /// operation: a till that came back from a build that does not sign).
  String _signature(String opId, int seq) {
    final int last = _lastSeq;
    final bool numberFollows = last == 0 || seq == last + 1;
    if (seq > _lastSeq) _lastSeq = seq;
    final bool afterMissing = _lastMissing;
    final Map<String, Object?>? seal = _seals.remove(opId);
    _lastMissing = seal == null;
    if (seal == null) return 'missing';
    final Object? sentKid = seal['kid'];
    // (a number sent as text, "2", is the same number)
    final int? kid = sentKid is int
        ? sentKid
        : (sentKid is String && RegExp(r'^[0-9]{1,9}$').hasMatch(sentKid)
              ? int.parse(sentKid)
              : null);
    final Object? mac = seal['mac'];
    final Object? prev = seal['prev'];
    if (kid != null) signedSeen[opId] = kid;
    final String? secret = kid == null || kid < 1
        ? null
        : (kid == state.syncKid
              ? state.syncKey
              : (kid == state.syncKid - 1 ? state.syncKeyPrev : null));
    final SyncKey? key = secret == null
        ? null
        : SyncKey.fromBase64(
            secret,
            kid: kid!,
            deviceUuid: FakePosState.deviceUuid,
          );
    if (key == null ||
        !SyncSignature.isMac(mac) ||
        !SyncSignature.isMac(prev) ||
        SyncSignature.mac(key, prev! as String, seal['signed']! as String) !=
            mac) {
      // Never the head. Its claim counts only in its own place and under a key the server holds
      if (numberFollows && SyncSignature.isMac(mac) && key != null) {
        _claimedMac = mac! as String;
        _claimedKid = kid;
      } else if (!numberFollows) {
        _claimedMac = null;
        _claimedKid = null;
      }
      return 'bad';
    }
    final int? headKid = _headKid;
    bool follows() {
      if (headKid != null && headKid > kid!) return false;
      if ((headKid == null || headKid == kid) &&
          _headMac != null &&
          prev == _headMac) {
        return true;
      }
      if (_claimedMac != null &&
          prev == _claimedMac &&
          (_claimedKid ?? headKid ?? kid) == kid) {
        return true;
      }
      final bool first = prev == SyncSignature.firstPrev(key);
      if (headKid == kid) {
        return first && afterMissing && seq == last + 1 && kid == state.syncKid;
      }
      return first;
    }

    final bool ok = follows() && numberFollows;
    _headKid = kid;
    _headMac = mac! as String;
    _claimedMac = null;
    _claimedKid = null;
    return ok ? 'ok' : 'chain_gap';
  }

  /// Enforce mode, an unsigned or badly signed concession (`SignaturePolicy::CONCESSIONS`): refused, not applied.
  /// `null`: the operation is applied.
  Map<String, Object?>? _refusedUnsigned(Map<String, Object?> op, String sig) {
    if (!enforceSignatures || (sig != 'missing' && sig != 'bad')) return null;
    final Map<String, Object?> p =
        (op['payload'] as Map?)?.cast<String, Object?>() ?? <String, Object?>{};
    return switch (op['type']) {
      'order.void' => _result(
        op,
        'rejected',
        'signature_invalid',
        _signatureText(sig),
        const <String, Object?>{},
      ),
      'order.discount' => _discountRefused(
        op,
        p,
        'signature_invalid',
        _signatureText(sig),
      ),
      _ => null,
    };
  }

  /// Enforce mode, as `SignaturePolicy::mark` and the applier's unverified payment: an operation that was APPLIED
  /// although its signature is missing or wrong becomes a conflict `signature_invalid` (a payment:
  /// `payment_unverified`, whatever else it earned: the money is on its bill all the same), one with a line missing
  /// before it a conflict `chain_gap`. Rejected, or a conflict for its own reason: unchanged.
  Map<String, Object?> _marked(
    Map<String, Object?> r,
    Map<String, Object?> op,
    String sig,
  ) {
    if (!enforceSignatures || sig == 'ok' || r['status'] == 'rejected') {
      return r;
    }
    final bool unverified = sig == 'missing' || sig == 'bad';
    if (unverified && op['type'] == 'bill.pay') {
      if (r['reason'] == 'payment_unallocated' ||
          r['reason'] == PosSyncReasons.staffUnverified) {
        return r;
      }
      return <String, Object?>{
        ...r,
        'status': 'conflict',
        'reason': 'payment_unverified',
        'message':
            "${_signatureText(sig)} Pul schyotga yozildi: menejer tekshirsin.",
      };
    }
    if (r['status'] != 'applied') return r;
    return <String, Object?>{
      ...r,
      'status': 'conflict',
      'reason': unverified ? 'signature_invalid' : 'chain_gap',
      'message': unverified
          ? "${_signatureText(sig)} Amal qo'llandi, menejer tekshirsin."
          : _signatureText(sig),
    };
  }

  /// `SignaturePolicy::message`.
  static String _signatureText(String sig) => switch (sig) {
    'missing' =>
      "Amal imzosiz keldi (kassa ilovasi eski yoki kassada imzo kaliti yo'q).",
    'bad' => "Amal imzosi noto'g'ri: kassa navbatidagi yozuv o'zgartirilgan yoki kalit bu kassaniki emas.",
    _ =>
      "Bu amaldan oldingi amal(lar) serverga kelmagan: kassa navbatidan amal yo'qolgan. Amal qo'llandi, menejer "
          'tekshirsin.',
  };

  /// As `SyncSignature::issueUnlessQueued` (`POST /terminal/sync-key`): a key that exists is not replaced while the
  /// till's last reported queue is not empty: 409 `queue_not_empty`. The first key is given whatever the queue.
  FakeResponse? _keyGuard() => state.syncKid > 0 && pendingReported > 0
      ? FakeResponse(409, <String, Object?>{
          'success': false,
          'message': 'Kassa navbatida yuborilmagan amallar bor: avval navbat yuborilsin, keyin kalit yangilanadi.',
          'data': <String, Object?>{
            'reason': 'queue_not_empty',
            'pending_ops': pendingReported,
          },
        })
      : null;

  Map<String, Object?> _apply(Map<String, Object?> op) {
    final String type = op['type']! as String;
    final Map<String, Object?> p =
        (op['payload'] as Map?)?.cast<String, Object?>() ?? <String, Object?>{};
    final int staff = (op['staff_id']! as num).toInt();
    final String? forced = rejectNext.remove(type);
    if (forced != null) {
      return _result(
        op,
        'rejected',
        forced,
        PosSyncReasons.describe(forced) ?? 'Serverda rad etildi ($forced).',
        const <String, Object?>{},
      );
    }

    final String? ref = p['order'] as String?;
    final String? orderUuid = ref == null ? null : (uuids[ref] ?? ref);
    // M5 S1 (as PosSyncApplier): a failed staff check rejects the operation, except money, which is never rejected
    // (flagged `staff_unverified`), and the close of an order such a payment paid in full
    final (String, String)? failed = _staffCheck(type, staff, op);
    if (failed != null) {
      final (String why, String text) = failed;
      // The staff id is only a claim: the row names a person only when they are known to have signed in on this till
      final int? attributed = _attributable(staff);
      final Map<String, Object?> row = <String, Object?>{
        ...op,
        'staff_id': attributed,
        'claimed_staff_id': staff,
      };
      if (type == 'order.close' &&
          unverified.any(
            (Map<String, Object?> u) => u['order_uuid'] == orderUuid,
          ) &&
          state.orders[orderUuid]?['closed_at'] != null) {
        return _result(row, 'applied', null, null, <String, Object?>{
          'order_uuid': orderUuid,
          'flags': <Object?>[
            <String, Object?>{
              'flag': PosSyncReasons.staffUnverified,
              'reason': why,
            },
          ],
        });
      }
      if (type == 'order.discount') return _discountRefused(op, p, why, text);
      if (type != 'bill.pay') {
        return _result(op, 'rejected', why, text, const <String, Object?>{});
      }
      final String amount = PosMoney.sum(<PosMoney>[
        for (final Object? t
            in (p['tenders'] as List<Object?>?) ?? const <Object?>[])
          if (t is Map) PosMoney.parse(t['amount']),
      ]).toApiString();
      final Map<String, Object?> flag = <String, Object?>{
        'flag': PosSyncReasons.staffUnverified,
        'amount': amount,
        'reason': why,
        'message': text,
      };
      // The bill is open: settled through the normal payment path (the shift's opener stands in for the drawer)
      final String bill = uuids[p['bill']] ?? '${p['bill']}';
      // (the server settles it whatever the claimed person may do: here somebody who may take a payment stands in)
      int? standIn;
      for (final Object? id in <Object?>[
        attributed,
        (state.shift?['opened_by'] as Map?)?['id'],
        for (final Map<String, Object?> m in state.staff) m['id'],
      ]) {
        final Iterable<Map<String, Object?>> who = state.staff.where(
          (Map<String, Object?> m) => m['id'] == id,
        );
        if (standIn == null &&
            who.isNotEmpty &&
            state.permissionsOf(who.first).contains('pos.take_payment')) {
          standIn = who.first['id']! as int;
        }
      }
      final FakeResponse? settled = standIn == null
          ? null
          : state.handleAsStaff(
              standIn,
              'POST',
              '/api/v1/pos/bills/$bill/payments',
              <String, Object?>{
                'tenders': p['tenders'],
                'shift_uuid': ?p['shift_uuid'],
              },
            );
      final Map<String, Object?>? paid = settled == null
          ? null
          : _data(settled);
      if (paid != null) {
        unverified.add(<String, Object?>{
          'op_id': op['op_id'],
          'order_uuid': orderUuid,
          'bill_uuid': bill,
          'amount': amount,
          'reason': why,
          'staff_id': attributed,
          'claimed_staff_id': staff,
        });
        return _result(
          row,
          'conflict',
          PosSyncReasons.staffUnverified,
          "To'lov ($amount so'm) xodim tekshiruvidan o'tmadi: $text Pul schyotga yozildi: menejer tekshirsin.",
          <String, Object?>{
            'order_uuid': orderUuid,
            'bill_uuid': bill,
            'fiscal_status':
                ((paid['bill'] as Map?)?['fiscal'] as Map?)?['status'],
            'flags': <Object?>[flag],
          },
        );
      }
      // No open bill to put it on: the money is recorded without one
      unallocated.add(<String, Object?>{
        'op_id': op['op_id'],
        'order_uuid': orderUuid,
        'amount': amount,
        'reason': why,
        'staff_id': attributed,
        'claimed_staff_id': staff,
      });
      return _result(
        row,
        'conflict',
        PosSyncReasons.staffUnverified,
        "To'lov ($amount so'm) xodim tekshiruvidan o'tmadi: $text Pul yozildi, schyotga tushmadi: menejer "
            'tekshirsin.',
        <String, Object?>{
          'order_uuid': orderUuid,
          'flags': <Object?>[flag],
        },
      );
    }
    FakeResponse res;
    final Map<String, Object?> refs = <String, Object?>{};
    // Flags the operation earns by itself (as PosSyncApplier), before the ones a test asks for ([flagNext])
    final List<Map<String, Object?>> own = <Map<String, Object?>>[];
    switch (type) {
      case 'order.open':
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders',
          <String, Object?>{
            'channel': p['channel'],
            'table_id': ?p['table_id'],
            'guests_count': ?p['guests_count'],
            'external_id': ref,
            'note': ?p['note'],
            'table_label': ?p['table_label'],
            'items': _items(p['items']),
            if (p['send'] == true) 'send': true,
          },
        );
        final Map<String, Object?>? order = _data(res);
        if (order != null) {
          uuids[ref!] = order['uuid']! as String;
          _mapLines(p['items'], order, const <int>{});
        }
      case 'order.round':
        final Set<int> before = _itemIds(orderUuid!);
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders/$orderUuid/rounds',
          <String, Object?>{
            'version': _version(orderUuid),
            'items': _items(p['items']),
            'fire_mode': ?p['fire_mode'],
            if (p['send'] == true) 'send': true,
          },
        );
        final Map<String, Object?>? order = _data(res);
        if (order != null) _mapLines(p['items'], order, before);
      case 'order.send':
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders/$orderUuid/send',
          <String, Object?>{
            'version': _version(orderUuid!),
            if (p['lines'] != null)
              'item_ids': <Object?>[
                for (final Object? l in p['lines']! as List<Object?>) _line(l),
              ],
          },
        );
      case 'order.void':
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders/$orderUuid/items/${_line(p['line'])}/void',
          <String, Object?>{
            'version': _version(orderUuid!),
            'reason': ?p['reason'],
          },
        );
      case 'order.served':
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders/$orderUuid/items/${_line(p['line'])}/served',
          <String, Object?>{'version': _version(orderUuid!)},
        );
      case 'order.course_send':
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders/$orderUuid/courses/${p['course_no']}/send',
          <String, Object?>{'version': _version(orderUuid!)},
        );
      case 'order.weight':
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders/$orderUuid/items/${_line(p['line'])}/weight',
          <String, Object?>{
            'version': _version(orderUuid!),
            'quantity': p['quantity'],
          },
        );
      case 'bill.create':
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders/$orderUuid/bills',
          <String, Object?>{'version': _version(orderUuid!), 'mode': 'single'},
        );
        final Map<String, Object?>? billing = _data(res);
        for (final Object? b
            in (billing?['bills'] as List<Object?>?) ?? const <Object?>[]) {
          if (b is Map && b['status'] != 'void' && b['status'] != 'paid') {
            uuids[p['bill']! as String] = b['uuid']! as String;
            refs['bill_uuid'] = b['uuid'];
            // As the server: the bill's total, and a note when the till expected another one
            final PosMoney total = PosMoney.parse(b['total']);
            refs['bill_total'] = total.toApiString();
            final PosMoney? expected = PosMoney.tryParse(p['expected_total']);
            if (expected != null && expected != total) {
              own.add(<String, Object?>{
                'flag': 'amount_mismatch',
                'device_total': expected.toApiString(),
                'server_total': total.toApiString(),
              });
            }
          }
        }
      case 'bill.pay':
        final String bill = uuids[p['bill']] ?? p['bill']! as String;
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/bills/$bill/payments',
          <String, Object?>{
            'tenders': p['tenders'],
            'shift_uuid': ?p['shift_uuid'],
          },
        );
        refs['bill_uuid'] = bill;
        final Map<String, Object?>? r = _data(res);
        refs['fiscal_status'] =
            ((r?['bill'] as Map?)?['fiscal'] as Map?)?['status'];
        // As the server: the money is taken as it is; what the bill still lacks is for the manager (`underpaid`)
        final PosMoney left = PosMoney.parse(
          (r?['bill'] as Map?)?['due_amount'],
        );
        if (r != null && left.isPositive) {
          own.add(<String, Object?>{
            'flag': 'underpaid',
            'amount': left.toApiString(),
            'server_total': PosMoney.parse((r['bill'] as Map?)?['total'])
                .toApiString(),
          });
        }
      case 'order.discount':
        // M5 S2 (PosSyncApplier::discount): the offline limit, then the online rule itself, without an approval token
        Map<String, Object?> refusedDiscount(String cause, String message) =>
            _discountRefused(op, p, cause, message);
        final Map<String, Object?> member = state.staff.firstWhere(
          (Map<String, Object?> s) => s['id'] == staff,
        );
        final List<String> perms = state.permissionsOf(member);
        if (!perms.contains('orders.discount') &&
            !perms.contains('orders.manage')) {
          return refusedDiscount(
            'permission_denied',
            "${member['name']} uchun bu amalga ruxsat yo'q.",
          );
        }
        // (after the staff check, before the order is looked at, as the server)
        if (p['staff_continued'] == true ||
            p['staff_continued'] == 'true' ||
            p['staff_continued'] == 1) {
          return refusedDiscount(
            'staff_continued',
            "PIN'siz davom etayotgan xodim chegirma bera olmaydi.",
          );
        }
        if (!state.orders.containsKey(orderUuid)) {
          return refusedDiscount(
            'order_not_found',
            'Buyurtma serverda topilmadi.',
          );
        }
        final PosMoney? value = PosMoney.tryParse(p['value']);
        if (value == null) {
          return refusedDiscount(
            'value_invalid',
            "Chegirma qiymati noto'g'ri.",
          );
        }
        // Owner decision of 20.11.2026: through sync everybody gives at most the branch's cashier limit (the value
        // as typed); none configured: no offline discount. Taking a discount off is not capped
        if (value.isPositive &&
            (p['kind'] == 'percent' || p['kind'] == 'amount')) {
          final String? max = state.cashierDiscountMaxPercent;
          final int limit = max == null ? 0 : percentHundredths(max);
          if (limit <= 0) {
            return refusedDiscount(
              'offline_limit',
              'Filialda kassir chegirmasi chegarasi belgilanmagan: internetsiz chegirma berilmaydi.',
            );
          }
          var items = 0;
          for (final Object? i
              in (state.orders[orderUuid]!['items'] as List<Object?>?) ??
                  const <Object?>[]) {
            if (i is Map && i['kitchen_status'] != 'void') {
              items += PosMoney.parse(i['total_price']).tiyin;
            }
          }
          final bool within = p['kind'] == 'percent'
              ? value.tiyin <= limit
              : value.tiyin * 10000 <= items * limit;
          if (!within) {
            return refusedDiscount(
              'offline_limit',
              'Internetsiz chegirma filial chegarasidan (${formatPercent(max!)}%) oshmaydi; '
                  "kattarog'i internet bilan beriladi.",
            );
          }
        }
        res = state.handleAsStaff(
          staff,
          'POST',
          '/api/v1/pos/orders/$orderUuid/discount',
          <String, Object?>{
            'version': _version(orderUuid!),
            'kind': p['kind'],
            'value': value.toApiString(),
            'reason': ?p['reason'],
          },
        );
        final Map<String, Object?>? order = _data(res);
        if (order == null) {
          final Map<String, Object?> body =
              (res.body as Map?)?.cast<String, Object?>() ??
              <String, Object?>{};
          return refusedDiscount(
            ((body['data'] as Map?)?['reason'] as String?) ?? 'invalid',
            body['message'] as String? ?? 'Rad etildi.',
          );
        }
        final PosMoney applied = PosMoney.parse(order['discount']);
        final bool removed = order['discount_kind'] == null;
        refs
          ..['discount'] = applied.toApiString()
          ..['discount_kind'] = removed ? null : p['kind']
          ..['discount_value'] = removed ? null : value.toApiString();
        if (!removed) {
          own.add(<String, Object?>{
            'flag': 'offline_discount',
            'amount': applied.toApiString(),
            'kind': p['kind'],
            'value': value.toApiString(),
            'staff_id': staff,
          });
        }
        final PosMoney? expected = PosMoney.tryParse(p['expected_discount']);
        if (expected != null && expected != applied) {
          own.add(<String, Object?>{
            'flag': 'discount_mismatch',
            'device_discount': expected.toApiString(),
            'server_discount': applied.toApiString(),
          });
        }
      default:
        res = const FakeResponse(200, <String, Object?>{
          'data': <String, Object?>{},
        });
    }

    if (orderUuid != null || ref != null) {
      refs['order_uuid'] = uuids[ref] ?? orderUuid;
    }
    refs['items'] = <String, Object?>{
      for (final MapEntry<String, int> e in lines.entries) e.key: e.value,
    };
    if (res.status >= 200 && res.status < 300) {
      final List<Map<String, Object?>> flags = <Map<String, Object?>>[
        ...own,
        ...?flagNext.remove(type),
      ];
      if (flags.isNotEmpty) refs['flags'] = flags;
      final List<Map<String, Object?>> attention = <Map<String, Object?>>[
        for (final Map<String, Object?> f in flags)
          if (!informationalFlags.contains(f['flag'])) f,
      ];
      if (attention.isEmpty) return _result(op, 'applied', null, null, refs);
      final Map<String, Object?> first = attention.first;
      final String flag = '${first['flag']}';
      return _result(
        op,
        'conflict',
        flag,
        flag == PosSyncReasons.offlinePriceMismatch
            ? "'${first['name']}' kassada ${first['device_price']} so'mdan sotilgan, lekin menyu nusxasi vaqtida "
                  "bunday narx bo'lmagan: server narxi (${first['server_price']} so'm) qo'yildi. Menejer tekshirsin."
            : PosSyncReasons.describe(flag),
        refs,
      );
    }
    final Map<String, Object?> body =
        (res.body as Map?)?.cast<String, Object?>() ?? <String, Object?>{};
    final String reason =
        ((body['data'] as Map?)?['reason'] as String?) ?? 'invalid';
    final String message = body['message'] as String? ?? 'Rad etildi.';
    return type == 'bill.pay'
        ? _result(
            op,
            'conflict',
            'payment_unallocated',
            "To'lov schyotga tushmadi: $message",
            refs,
          )
        : _result(op, 'rejected', reason, message, refs);
  }

  /// The server's staff check (`PosSyncApplier::staff`): `(reason, message)` when it fails. Only the rules a test can
  /// set up: an unknown or blocked (`blocked_at`) staff id, a PIN login on this till older than [sessionWindow] before
  /// the operation (when [sessionStartedAt] names the staff member; for `order.discount` before the server RECEIVED
  /// it: the till's own time cannot stretch the window), a payment without `pos.take_payment`.
  (String, String)? _staffCheck(
    String type,
    int staff,
    Map<String, Object?> op,
  ) {
    Map<String, Object?>? member;
    for (final Map<String, Object?> s in state.staff) {
      if (s['id'] == staff) member = s;
    }
    if (member == null || member['blocked_at'] != null) {
      return ('staff_invalid', 'Xodim topilmadi yoki bu kassada ishlamaydi.');
    }
    final DateTime? started = sessionStartedAt[staff];
    final DateTime? at = type == 'order.discount'
        ? state.now()
        : DateTime.tryParse('${op['occurred_at']}');
    if (started != null &&
        at != null &&
        (at.isBefore(started) || at.difference(started) > sessionWindow)) {
      return (
        'staff_not_signed_in',
        '${member['name']} bu kassada uzilishdan oldin PIN bilan kirmagan.',
      );
    }
    if (type == 'bill.pay' &&
        !state.permissionsOf(member).contains('pos.take_payment')) {
      return (
        'permission_denied',
        "${member['name']} uchun bu amalga ruxsat yo'q.",
      );
    }
    return null;
  }

  /// As `PosSyncApplier::discountRefused`: an offline discount the server does not take, for the manager
  /// (`discount_not_allowed`, the rule's own reason in `refs.cause`).
  Map<String, Object?> _discountRefused(
    Map<String, Object?> op,
    Map<String, Object?> p,
    String cause,
    String message,
  ) => _result(
    op,
    'rejected',
    'discount_not_allowed',
    "Chegirma serverda qabul qilinmadi: $message Schyot to'liq narxda hisoblanadi: menejer chegirmani "
        'onlayn bersin yoki farqni hal qilsin.',
    <String, Object?>{
      'cause': cause,
      if (p['kind'] is String) 'kind': p['kind'],
      'value': ?PosMoney.tryParse(p['value'])?.toApiString(),
      'expected_discount': ?PosMoney.tryParse(p['expected_discount'])
          ?.toApiString(),
    },
  );

  /// As `PosSyncApplier::attributable`: the claimed staff member is named only when they work here and are known to
  /// have signed in on this till ([sessionStartedAt]).
  int? _attributable(int staff) =>
      sessionStartedAt.containsKey(staff) &&
          state.staff.any((Map<String, Object?> s) => s['id'] == staff)
      ? staff
      : null;

  Map<String, Object?> _result(
    Map<String, Object?> op,
    String status,
    String? reason,
    String? message,
    Map<String, Object?> refs,
  ) => <String, Object?>{
    'op_id': op['op_id'],
    'seq': op['seq'],
    'type': op['type'],
    'status': status,
    'reason': reason,
    'message': message,
    'refs': refs,
    'payload': op['payload'],
    'occurred_at': op['occurred_at'],
    'staff_id': op['staff_id'],
    // (a failed staff check: what the till claimed, kept apart from the person the row names)
    if (op.containsKey('claimed_staff_id'))
      'claimed_staff_id': op['claimed_staff_id'],
  };

  Map<String, Object?> _error(Map<String, Object?> e) {
    final Map<String, Object?> p =
        (e['payload'] as Map?)?.cast<String, Object?>() ?? <String, Object?>{};
    String? name;
    for (final Map<String, Object?> s in state.staff) {
      if (s['id'] == e['staff_id']) name = s['name'] as String?;
    }
    return <String, Object?>{
      'op_id': e['op_id'],
      'seq': e['seq'],
      'type': e['type'],
      'type_label': _labels[e['type']] ?? e['type'],
      'status': e['status'],
      'reason': e['reason'],
      'message': e['message'],
      'staff': <String, Object?>{'id': e['staff_id'], 'name': name},
      'device': <String, Object?>{
        'uuid': FakePosState.deviceUuid,
        'name': state.deviceName,
      },
      'occurred_at': e['occurred_at'],
      'received_at': state.now().toUtc().toIso8601String(),
      'applied_at': null,
      'clock_skew_seconds': 0,
      'order': null,
      'payload': p,
      'refs': e['refs'],
      'sig_status': e['sig_status'],
      'resolved_at': e['resolved_at'],
      'resolved_by': null,
      'resolution': e['resolution'],
      'resolution_note': null,
    };
  }

  static const Map<String, String> _labels = <String, String>{
    'order.open': 'Buyurtma ochildi',
    'order.round': "Bosqich qo'shildi",
    'order.send': 'Oshxonaga yuborildi',
    'order.void': "Taom o'chirildi",
    'order.served': 'Taom berildi',
    'bill.create': 'Schyot tuzildi',
    'bill.pay': "To'lov olindi",
    'order.close': 'Buyurtma yopildi',
    'order.course_send': 'Kurs oshxonaga yuborildi',
    'order.weight': "Og'irlik kiritildi",
    'order.discount': 'Chegirma berildi',
  };

  Map<String, Object?> _device() => <String, Object?>{
    'uuid': FakePosState.deviceUuid,
    'name': state.deviceName,
    'prefix': 'K1',
    'last_seq': received.fold<int>(
      0,
      (int last, Map<String, Object?> o) =>
          (o['seq']! as int) > last ? o['seq']! as int : last,
    ),
    'pending_ops': pendingReported,
    'oldest_op_at': null,
    'reported_at': state.now().toUtc().toIso8601String(),
    'clock_skew_seconds': 0,
    'clock_warning': false,
  };

  List<Object?> _items(Object? items) => <Object?>[
    for (final Object? i in (items as List<Object?>?) ?? const <Object?>[])
      if (i is Map)
        <String, Object?>{
          'variant_id': i['variant_id'],
          'quantity': i['quantity'],
          if (i['modifiers'] is List &&
              (i['modifiers']! as List<Object?>).isNotEmpty)
            'modifiers': <Object?>[
              for (final Object? m in i['modifiers']! as List<Object?>)
                if (m is Map)
                  <String, Object?>{
                    'id': m['id'],
                    'quantity': m['quantity'] ?? 1,
                  },
            ],
          'note': ?i['note'],
          'seat_no': ?i['seat_no'],
          'fire_mode': ?i['fire_mode'],
          'course_no': ?i['course_no'],
          'awaiting_weight': ?i['awaiting_weight'],
        },
  ];

  void _mapLines(Object? sent, Map<String, Object?> order, Set<int> before) {
    final List<int> fresh = <int>[
      for (final Object? i
          in (order['items'] as List<Object?>?) ?? const <Object?>[])
        if (i is Map && !before.contains(i['id'])) (i['id']! as num).toInt(),
    ];
    var k = 0;
    for (final Object? l in (sent as List<Object?>?) ?? const <Object?>[]) {
      if (l is Map && l['line_id'] is String && k < fresh.length) {
        lines[l['line_id']! as String] = fresh[k];
      }
      k++;
    }
  }

  Object? _line(Object? ref) => ref is String ? (lines[ref] ?? ref) : ref;

  int _version(String uuid) {
    final Map<String, Object?>? o = state.orders[uuid];
    return (o?['version'] as num?)?.toInt() ?? 0;
  }

  Set<int> _itemIds(String uuid) => <int>{
    for (final Object? i
        in (state.orders[uuid]?['items'] as List<Object?>?) ??
            const <Object?>[])
      if (i is Map) (i['id']! as num).toInt(),
  };

  static Map<String, Object?>? _data(FakeResponse r) {
    if (r.status < 200 || r.status >= 300) return null;
    final Object? d = (r.body as Map?)?['data'];
    return d is Map ? d.cast<String, Object?>() : null;
  }

  static FakeResponse _ok(Map<String, Object?> data, {String message = ''}) =>
      FakeResponse(200, <String, Object?>{
        'success': true,
        'message': message,
        'data': data,
      });
}
