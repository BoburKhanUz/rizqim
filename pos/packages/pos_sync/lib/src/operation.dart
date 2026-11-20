import 'dart:convert';

import 'signature.dart';

/// Kassa internetsiz bajara oladigan amallar (docs/POS_SPEC.md 5.1) — serverdagi `POST /pos/sync` turlari.
abstract final class SyncOpType {
  static const String orderOpen = 'order.open';
  static const String orderRound = 'order.round';
  static const String orderSend = 'order.send';
  static const String orderVoid = 'order.void';
  static const String orderServed = 'order.served';
  static const String billCreate = 'bill.create';
  static const String billPay = 'bill.pay';
  static const String orderClose = 'order.close';

  /// P6-21: ushlab turilgan kursni oshxonaga yuborish (`{order, course_no}`).
  static const String orderCourseSend = 'order.course_send';

  /// P6-25: "og'irlikni keyin kiritish" qatorining haqiqiy og'irligi (`{order, line, quantity}`).
  static const String orderWeight = 'order.weight';

  /// P6 lokal ko'prik (POS_SPEC 5.5): KDS qatorni "tayyor" qildi yoki qaytardi (`{order, line}`).
  static const String kitchenBump = 'kitchen.bump';
  static const String kitchenRecall = 'kitchen.recall';

  /// P6: yig'ish ekrani buyurtmani yig'di (`{order}`).
  static const String kitchenAssembled = 'kitchen.assembled';

  /// P6: ofitsiant schyot so'radi (`{order}`).
  static const String billRequest = 'bill.request';

  /// M5 S2: kassada internetsiz berilgan chegirma (`{order, kind: percent|amount, value, reason,
  /// expected_discount}`; `value` 0 — olib tashlash). Faqat kassaning o'zida: markaz orqali uzatilmaydi.
  static const String orderDiscount = 'order.discount';

  static const List<String> all = <String>[
    orderOpen,
    orderRound,
    orderSend,
    orderVoid,
    orderServed,
    billCreate,
    billPay,
    orderClose,
    orderCourseSend,
    orderWeight,
    kitchenBump,
    kitchenRecall,
    kitchenAssembled,
    billRequest,
    orderDiscount,
  ];

  /// Ro'yxatdagi nomi ("Sinxronlash xatolari").
  static String label(String type) => switch (type) {
    orderOpen => 'Buyurtma ochildi',
    orderRound => "Bosqich qo'shildi",
    orderSend => 'Oshxonaga yuborildi',
    orderVoid => "Taom o'chirildi",
    orderServed => 'Taom berildi',
    billCreate => 'Schyot tuzildi',
    billPay => "To'lov olindi",
    orderClose => 'Buyurtma yopildi',
    orderCourseSend => 'Kurs oshxonaga yuborildi',
    orderWeight => "Og'irlik kiritildi",
    kitchenBump => 'Taom tayyor',
    kitchenRecall => 'Taom oshxonaga qaytarildi',
    kitchenAssembled => "Buyurtma yig'ildi",
    billRequest => "Schyot so'raldi",
    orderDiscount => 'Chegirma berildi',
    _ => type,
  };
}

/// Amalning holati: navbatda (serverga yetmagan), qo'llandi, farq bilan qo'llandi (menejer ko'rsin), rad etildi.
enum SyncOpStatus {
  pending('pending'),
  applied('applied'),
  conflict('conflict'),
  rejected('rejected');

  const SyncOpStatus(this.wire);

  final String wire;

  static SyncOpStatus fromWire(String? value) {
    for (final SyncOpStatus s in values) {
      if (s.wire == value) return s;
    }
    return SyncOpStatus.rejected;
  }

  /// Menejer ko'rishi kerak.
  bool get needsAttention =>
      this == SyncOpStatus.conflict || this == SyncOpStatus.rejected;
}

/// Navbatdagi bitta amal: `op_id` (UUID, ikki marta yuborilsa ham server bir marta qo'llaydi), qurilmadagi ketma-ket
/// raqam (`seq`), turi, qurilma soati bo'yicha vaqti, kim bajargani va mazmuni (`payload`). Server javobidan keyin —
/// holati, sababi, xabari va serverdagi id'lar ([refs]). P6 lokal ko'prik: markaz boshqa qurilmadan (ofitsiant
/// telefoni, KDS) olgan amal [origin] bilan (o'sha qurilma uuid'si; serverga `origin_device`).
class SyncOperation {
  const SyncOperation({
    required this.opId,
    required this.seq,
    required this.type,
    required this.occurredAt,
    required this.staffId,
    required this.payload,
    this.origin,
    this.status = SyncOpStatus.pending,
    this.reason,
    this.message,
    this.refs = const <String, Object?>{},
    this.answeredAt,
    this.seal,
  });

  factory SyncOperation.fromJson(Map<String, Object?> j) => SyncOperation(
    opId: j['op_id']! as String,
    seq: (j['seq']! as num).toInt(),
    type: j['type']! as String,
    occurredAt: DateTime.parse(j['occurred_at']! as String),
    staffId: (j['staff_id']! as num).toInt(),
    payload: _map(j['payload']),
    origin: j['origin_device'] as String?,
  );

  /// Reads [toStored] back. A signed one is the operation inside its signed text (`raw`), as the server will read
  /// it; signed text that is no operation throws, like [SyncOperation.fromJson] on a record that is none.
  factory SyncOperation.fromStored(Map<String, Object?> j) {
    final SyncSeal? seal = SyncSeal.tryParse(j);
    if (seal == null) return SyncOperation.fromJson(j);
    return SyncOperation.fromJson(
      (jsonDecode(seal.raw) as Map<Object?, Object?>).cast<String, Object?>(),
    ).sealed(seal);
  }

  final String opId;
  final int seq;
  final String type;
  final DateTime occurredAt;
  final int staffId;
  final Map<String, Object?> payload;

  /// P6: amal qilingan qurilma (markaz LAN orqali olgan bo'lsa), aks holda `null` — shu qurilmaning o'zi.
  final String? origin;
  final SyncOpStatus status;
  final String? reason;
  final String? message;
  final Map<String, Object?> refs;
  final DateTime? answeredAt;

  /// M5 S3: the signature it was queued with (`null`: unsigned, the old shape). Only on an operation that still
  /// waits: an answered one does not carry it.
  final SyncSeal? seal;

  bool get isPending => status == SyncOpStatus.pending;

  /// Amal qaysi buyurtmaga tegishli (qurilma id'si yoki server uuid'si).
  String? get orderRef => payload['order'] as String?;

  /// What goes into the `operations` of `POST /pos/sync`: a signed operation as `{signed, mac, prev, kid}` (the
  /// server takes the operation out of `signed`, the exact text that was signed), an unsigned one as [toJson].
  Map<String, Object?> toWire() => seal?.toWire() ?? toJson();

  /// The same operation with [seal] (`null`: unsigned).
  SyncOperation sealed(SyncSeal? seal) => SyncOperation(
    opId: opId,
    seq: seq,
    type: type,
    occurredAt: occurredAt,
    staffId: staffId,
    payload: payload,
    origin: origin,
    status: status,
    reason: reason,
    message: message,
    refs: refs,
    answeredAt: answeredAt,
    seal: seal,
  );

  /// The operation as it is kept on the disk outside the journal (a queue set aside): [toJson] and, for a signed
  /// one, its signature beside it (`raw`, `mac`, `prev`, `kid`, `dev`), so that it can still be verified later. A
  /// reader that knows nothing of signatures ([SyncOperation.fromJson]) reads the same operation.
  Map<String, Object?> toStored() => <String, Object?>{
    ...toJson(),
    ...?seal?.toStored(),
  };

  /// Amalning ochiq ko'rinishi (imzosiz yuborilganda — serverga shu ketadi).
  Map<String, Object?> toJson() => <String, Object?>{
    'op_id': opId,
    'seq': seq,
    'type': type,
    'occurred_at': occurredAt.toUtc().toIso8601String(),
    'staff_id': staffId,
    'payload': payload,
    'origin_device': ?origin,
  };

  SyncOperation answered(SyncResult r, DateTime at) => SyncOperation(
    opId: opId,
    seq: seq,
    type: type,
    occurredAt: occurredAt,
    staffId: staffId,
    payload: payload,
    origin: origin,
    status: r.status,
    reason: r.reason,
    message: r.message,
    refs: r.refs,
    answeredAt: at,
  );

  static Map<String, Object?> _map(Object? v) =>
      v is Map ? v.cast<String, Object?>() : const <String, Object?>{};
}

/// Server javobidagi bitta amal natijasi (`results[]`).
class SyncResult {
  const SyncResult({
    required this.opId,
    required this.seq,
    required this.status,
    this.reason,
    this.message,
    this.duplicate = false,
    this.refs = const <String, Object?>{},
    this.sigStatus,
    this.unreadable = false,
  });

  /// M5 S3: the server could not use the signed text of this operation and recorded nothing for it (the answer's
  /// `unreadable`): it leaves the queue as a rejected one and its journal line is kept as damage.
  const SyncResult.unreadable({
    required this.opId,
    required this.seq,
    String? reason,
    this.message,
  }) : status = SyncOpStatus.rejected,
       reason = reason ?? 'invalid_payload',
       duplicate = false,
       refs = const <String, Object?>{},
       sigStatus = null,
       unreadable = true;

  factory SyncResult.fromJson(Map<String, Object?> j) => SyncResult(
    opId: j['op_id']! as String,
    seq: (j['seq'] as num?)?.toInt() ?? 0,
    status: SyncOpStatus.fromWire(j['status'] as String?),
    reason: j['reason'] as String?,
    message: j['message'] as String?,
    duplicate: j['duplicate'] == true,
    refs: j['refs'] is Map
        ? (j['refs']! as Map).cast<String, Object?>()
        : const <String, Object?>{},
    sigStatus: j['sig_status'] as String?,
  );

  final String opId;
  final int seq;
  final SyncOpStatus status;
  final String? reason;
  final String? message;
  final bool duplicate;

  /// M5 S3: what the server made of the operation's signature (`results[].sig_status`): `ok`, `missing` (it came
  /// unsigned), `bad` (the mac is not this device's key's), `chain_gap`; `null` from a server that does not say.
  final String? sigStatus;

  /// See [SyncResult.unreadable].
  final bool unreadable;

  /// Serverdagi id'lar: `order_uuid`, `order_version`, `local_number`, `items` (qator id → server id), `bill_uuid`,
  /// `payments`, `flags`.
  final Map<String, Object?> refs;

  String? get orderUuid => refs['order_uuid'] as String?;
}
