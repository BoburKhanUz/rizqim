import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';

import '../../shell/pos_scope.dart';
import 'cashier_ui.dart';

/// Internetsiz olingan naqd/karta — schyotda kutilayotgan (yoki provayder ushlab turgan) dinamik QR bilan birga
/// (review P6 wave 4, C3). Kassir "Mijoz QR orqali to'lamaganiga ishonch hosil qiling" ni tasdiqlagan payt, kim va
/// qaysi QR yoziladi; aloqa qaytgach natija ([outcome]) va pul qaytarilgani ham shu yozuvda qoladi (menejer uchun
/// jurnal).
class QrOfflineTender {
  const QrOfflineTender({
    required this.opId,
    required this.billUuid,
    required this.qrUuid,
    required this.qrLabel,
    required this.qrAmount,
    required this.due,
    required this.taken,
    required this.at,
    this.staffId,
    this.staffName,
    this.held = false,
    this.outcome,
    this.toReturn = PosMoney.zero,
    this.resolvedAt,
    this.returnedAt,
    this.returnedBy,
  });

  factory QrOfflineTender.fromJson(Map<String, Object?> j) => QrOfflineTender(
    opId: '${j['op_id']}',
    billUuid: '${j['bill_uuid']}',
    qrUuid: '${j['qr_uuid']}',
    qrLabel: '${j['qr_label'] ?? 'QR'}',
    qrAmount: PosMoney.parse(j['qr_amount']),
    due: PosMoney.parse(j['due']),
    taken: PosMoney.parse(j['taken']),
    at: DateTime.tryParse('${j['at']}') ?? DateTime.now(),
    staffId: (j['staff_id'] as num?)?.toInt(),
    staffName: j['staff_name'] as String?,
    held: j['held'] == true,
    outcome: j['outcome'] as String?,
    toReturn: PosMoney.parse(j['to_return']),
    resolvedAt: DateTime.tryParse('${j['resolved_at']}'),
    returnedAt: DateTime.tryParse('${j['returned_at']}'),
    returnedBy: j['returned_by'] as String?,
  );

  /// Oflayn `bill.pay` amalining `op_id` si.
  final String opId;
  final String billUuid;
  final String qrUuid;

  /// "Click", "Payme"...
  final String qrLabel;
  final PosMoney qrAmount;

  /// Oflayn to'lovdan oldingi schyot qarzi.
  final PosMoney due;

  /// Oflayn olingan summa (naqd va karta).
  final PosMoney taken;
  final DateTime at;
  final int? staffId;
  final String? staffName;

  /// Tasdiqlash paytida provayder QR'ni ushlab turgan edi ("Mijoz to'lamoqda").
  final bool held;

  /// `null` — hali hal qilinmagan; [sent] — QR to'lanmagan, oflayn to'lov yuborildi; [qrCancelled] — QR serverda bekor
  /// qilindi va oflayn to'lov yuborildi; [dropped] — provayder to'lagan, oflayn to'lov yuborilmadi; [overpaid] —
  /// provayder qismini to'lagan, oflayn to'lov yuborildi (server ortiqchasini kesadi).
  final String? outcome;

  /// Mijozga qaytariladigan summa ([dropped] — hammasi, [overpaid] — ortiqchasi).
  final PosMoney toReturn;
  final DateTime? resolvedAt;
  final DateTime? returnedAt;
  final String? returnedBy;

  static const String sent = 'sent';
  static const String qrCancelled = 'qr_cancelled';
  static const String dropped = 'dropped';
  static const String overpaid = 'overpaid';

  bool get mustReturn => toReturn.isPositive && returnedAt == null;

  QrOfflineTender resolve(String outcome, DateTime at, [PosMoney? back]) =>
      QrOfflineTender.fromJson(<String, Object?>{
        ...toJson(),
        'outcome': outcome,
        'to_return': (back ?? PosMoney.zero).toApiString(),
        'resolved_at': at.toUtc().toIso8601String(),
      });

  QrOfflineTender returned(DateTime at, String? by) =>
      QrOfflineTender.fromJson(<String, Object?>{
        ...toJson(),
        'returned_at': at.toUtc().toIso8601String(),
        'returned_by': by,
      });

  Map<String, Object?> toJson() => <String, Object?>{
    'op_id': opId,
    'bill_uuid': billUuid,
    'qr_uuid': qrUuid,
    'qr_label': qrLabel,
    'qr_amount': qrAmount.toApiString(),
    'due': due.toApiString(),
    'taken': taken.toApiString(),
    'at': at.toUtc().toIso8601String(),
    'staff_id': staffId,
    'staff_name': staffName,
    'held': held,
    'outcome': outcome,
    'to_return': toReturn.toApiString(),
    'resolved_at': resolvedAt?.toUtc().toIso8601String(),
    'returned_at': returnedAt?.toUtc().toIso8601String(),
    'returned_by': returnedBy,
  };
}

/// Review P6 wave 4 (C3): internetsiz rejimda kutilayotgan dinamik QR. U serverda hali to'lanishi mumkin (muddat va
/// provayderning `held` i — 15 daqiqagacha), shuning uchun:
///
/// * kassa ko'rgan kutilayotgan QR'lar eslab qolinadi ([note], [pendingFor]) — oflayn to'lov sahifasi QR'ni "Aloqa
///   yo'q — to'lov tizimi hali tasdiqlashi mumkin" holida ko'rsatadi va naqd/kartadan oldin tasdiq so'raydi;
/// * tasdiqlangan oflayn to'lov yoziladi ([record], kassa diskida);
/// * aloqa qaytgach navbat yuborilishidan OLDIN ([send] — [SyncTransport] o'rami) shu QR serverdan qayta o'qiladi:
///   to'lanmagan bo'lsa — serverda bekor qilinadi, keyin oflayn to'lov yuboriladi; provayder to'lagan bo'lsa — oflayn
///   to'lov yuborilmaydi (navbatda "rad etildi" bo'lib qoladi), kassirga "pulni qaytaring" deyiladi ([toReturn]);
///   provayder hali ushlab turgan bo'lsa — to'lov (va undan keyingilar) kutadi.
class QrOfflineGuard implements SyncTransport {
  QrOfflineGuard({DateTime Function()? clock}) : _clock = clock ?? DateTime.now;

  final DateTime Function() _clock;
  PosData? _data;
  SyncTransport? _inner;
  final Map<String, PosQrPayment> _known = <String, PosQrPayment>{};
  final StreamController<void> _changes = StreamController<void>.broadcast();

  static const String _key = '_qr_offline_tenders';

  /// Local sync reason of an offline payment kept back because the provider took the money.
  static const String droppedReason = 'qr_paid_offline_tender';

  /// Bog'lanadi ([CashierServices] — [PosData] yaratilgach).
  void attach(PosData data) {
    _data = data;
    _inner = PosSyncTransport(data.http);
  }

  Stream<void> get changes => _changes.stream;

  /// Kassa ko'rgan QR holati: kutilayotgani eslab qolinadi, qolgani unutiladi.
  void note(String billUuid, PosQrPayment? qr) {
    if (qr != null && qr.status == PosQrStatus.pending) {
      _known[billUuid] = qr;
    } else {
      _known.remove(billUuid);
    }
  }

  /// Bu schyotda kutilayotgan (kassa oxirgi ko'rgan) dinamik QR.
  PosQrPayment? pendingFor(String billUuid) => _known[billUuid];

  SyncStore? get _store => _data?.offline?.store;

  List<QrOfflineTender> get records => <QrOfflineTender>[
    for (final Object? r
        in (_store?.doc(_key)?.map?['records'] as List<Object?>?) ??
            const <Object?>[])
      if (r is Map) QrOfflineTender.fromJson(r.cast<String, Object?>()),
  ];

  /// Kassir mijozga qaytarishi kerak bo'lgan pullar.
  List<QrOfflineTender> get toReturn => <QrOfflineTender>[
    for (final QrOfflineTender r in records)
      if (r.mustReturn) r,
  ];

  Future<void> _write(List<QrOfflineTender> list) async {
    final SyncStore? store = _store;
    if (store == null) return;
    // The last 200 are kept (the manager's log on this till)
    final List<QrOfflineTender> kept = list.length > 200
        ? list.sublist(list.length - 200)
        : list;
    await store.putDoc(_key, <String, Object?>{
      'records': <Object?>[for (final QrOfflineTender r in kept) r.toJson()],
    });
    if (!_changes.isClosed) _changes.add(null);
  }

  Future<void> _replace(QrOfflineTender next) => _write(<QrOfflineTender>[
    for (final QrOfflineTender r in records) r.opId == next.opId ? next : r,
  ]);

  /// Kassir QR kutilayotgan schyotga internetsiz to'lov oldi (tasdiqlab): shu `bill.pay` amali yoziladi.
  Future<void> record({
    required PosBill billBefore,
    required PosQrPayment qr,
    required List<PosTenderInput> tenders,
    int? staffId,
    String? staffName,
  }) async {
    final SyncStore? store = _store;
    if (store == null) return;
    final SyncOperation? op = store.pending
        .where(
          (SyncOperation o) =>
              o.type == SyncOpType.billPay &&
              o.payload['bill'] == billBefore.uuid,
        )
        .lastOrNull;
    if (op == null) return;
    await _write(<QrOfflineTender>[
      ...records,
      QrOfflineTender(
        opId: op.opId,
        billUuid: billBefore.uuid,
        qrUuid: qr.uuid,
        qrLabel: qr.providerLabel ?? qr.provider?.label ?? 'QR',
        qrAmount: qr.amount,
        due: billBefore.dueAmount,
        taken: PosMoney.sum(tenders.map((PosTenderInput t) => t.amount)),
        at: _clock(),
        staffId: staffId,
        staffName: staffName,
        held: qr.held,
      ),
    ]);
  }

  /// Kassir pulni mijozga qaytardi.
  Future<void> returned(String opId, {String? by}) async {
    final QrOfflineTender? r = records
        .where((QrOfflineTender x) => x.opId == opId)
        .firstOrNull;
    if (r == null) return;
    await _replace(r.returned(_clock(), by));
  }

  // --- navbatni yuborish ----------------------------------------------------------------------------------------------

  @override
  Future<SyncBatchResponse> send(SyncBatchRequest request) async {
    final SyncTransport? inner = _inner;
    final PosData? data = _data;
    if (inner == null || data == null) {
      throw const SyncUnreachable('not attached');
    }
    final Map<String, QrOfflineTender> watched = <String, QrOfflineTender>{
      for (final QrOfflineTender r in records) r.opId: r,
    };
    if (!request.operations.any(
      (SyncOperation o) => watched.containsKey(o.opId),
    )) {
      return inner.send(request);
    }
    final List<SyncOperation> toSend = <SyncOperation>[];
    final List<SyncResult> local = <SyncResult>[];
    int? stoppedAt;
    for (final SyncOperation op in request.operations) {
      QrOfflineTender? r = watched[op.opId];
      if (r == null) {
        toSend.add(op);
        continue;
      }
      if (r.outcome == null) {
        r = await _decide(data, r);
        if (r == null) {
          // The provider is still taking the money: this payment (and what follows it) waits
          stoppedAt = op.seq;
          break;
        }
        await _replace(r);
        _known.remove(r.billUuid);
      }
      if (r.outcome == QrOfflineTender.dropped) {
        local.add(
          SyncResult(
            opId: op.opId,
            seq: op.seq,
            status: SyncOpStatus.rejected,
            reason: droppedReason,
            message: returnMessage(r),
          ),
        );
      } else {
        toSend.add(op);
      }
    }
    SyncBatchResponse? answer;
    if (toSend.isNotEmpty) {
      answer = await inner.send(
        SyncBatchRequest(
          operations: toSend,
          sentAt: request.sentAt,
          queueSize: request.queueSize,
          oldestAt: request.oldestAt,
        ),
      );
    }
    final int? serverStop = answer?.stoppedAt;
    return SyncBatchResponse(
      results: <SyncResult>[...local, ...?answer?.results],
      clockSkewSeconds: answer?.clockSkewSeconds,
      clockWarning: answer?.clockWarning ?? false,
      stoppedAt: serverStop == null || stoppedAt == null
          ? (serverStop ?? stoppedAt)
          : (serverStop < stoppedAt ? serverStop : stoppedAt),
    );
  }

  /// The QR as the server has it now; `null` — wait (held by the provider, or not readable now).
  Future<QrOfflineTender?> _decide(PosData data, QrOfflineTender r) async {
    final DateTime now = _clock();
    PosQrPayment? qr;
    try {
      qr = await _poll(data, r.qrUuid);
      if (qr.status == PosQrStatus.pending) {
        if (qr.held) return null;
        // Not paid: cancelled on the server first, so the customer's phone cannot pay it after the cash
        try {
          qr = await data.http.mutate(
            'DELETE',
            '/v1/pos/qr/${Uri.encodeComponent(r.qrUuid)}',
            _one,
            idempotencyKey: 'qr-offline-${r.opId}',
            query: const <String, Object?>{'reason': 'offline_tender'},
          );
        } on PosConflict {
          // Paid or taken by the provider meanwhile: read again
          qr = await _poll(data, r.qrUuid);
          if (qr.status == PosQrStatus.pending) return null;
        }
        if (qr.status == PosQrStatus.cancelled) {
          return r.resolve(QrOfflineTender.qrCancelled, now);
        }
      }
    } on PosNetworkException catch (e) {
      throw SyncUnreachable(e);
    } on PosNotFound {
      return r.resolve(QrOfflineTender.sent, now);
    } on PosException {
      // Not readable now (e.g. nobody signed in): the payment waits, the next try asks again
      return null;
    }
    if (qr.status != PosQrStatus.paid) {
      return r.resolve(QrOfflineTender.sent, now);
    }
    // The provider took the money: what the bill still owed after the QR
    final PosMoney left = r.due - qr.amount;
    if (!left.isPositive) {
      return r.resolve(QrOfflineTender.dropped, now, r.taken);
    }
    if (r.taken > left) {
      return r.resolve(QrOfflineTender.overpaid, now, r.taken - left);
    }
    return r.resolve(QrOfflineTender.sent, now);
  }

  static Future<PosQrPayment> _poll(PosData data, String uuid) =>
      data.http.get('/v1/pos/qr/${Uri.encodeComponent(uuid)}', _one);

  static PosQrPayment _one(Object? data) =>
      PosQrPayment.fromJson(dataObject(data).obj('qr'));

  /// Kassirga oddiy matn.
  static String returnMessage(QrOfflineTender r) =>
      r.outcome == QrOfflineTender.overpaid
      ? "Mijoz ${r.qrLabel} QR orqali ${r.qrAmount.format()} to'lagan: internetsiz olingan pulning "
            "${r.toReturn.format()} qismi ortiqcha — uni mijozga qaytaring."
      : "Mijoz ${r.qrLabel} QR orqali ${r.qrAmount.format()} to'lagan: internetsiz olingan ${r.taken.format()} "
            "serverga yuborilmadi — pulni mijozga qaytaring.";

  Future<void> dispose() => _changes.close();
}

/// Ish ekranining tepasida: provayder to'lagan QR ustiga internetsiz olingan pul — "mijozga qaytaring" va
/// "Qaytarildi" (kim qaytargani yoziladi).
class QrOfflineReturnBanner extends StatelessWidget {
  const QrOfflineReturnBanner({super.key});

  @override
  Widget build(BuildContext context) {
    final PosScope scope = PosScope.of(context);
    final QrOfflineGuard? guard = scope.services.qrGuard;
    if (guard == null) return const SizedBox.shrink();
    return StreamBuilder<void>(
      stream: guard.changes,
      builder: (BuildContext context, _) {
        final List<QrOfflineTender> list = guard.toReturn;
        if (list.isEmpty) return const SizedBox.shrink();
        return Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
          child: Column(
            children: <Widget>[
              for (final QrOfflineTender r in list)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: NoticeBanner(
                    key: ValueKey<String>('qr-return-${r.opId}'),
                    tone: StatusTone.danger,
                    icon: Icons.currency_exchange_rounded,
                    message: QrOfflineGuard.returnMessage(r),
                    action: TextButton(
                      onPressed: () => unawaited(
                        guard.returned(r.opId, by: scope.session.staffName),
                      ),
                      child: const Text('Qaytarildi'),
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}
