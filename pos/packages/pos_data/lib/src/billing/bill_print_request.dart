import '../json.dart';

/// Schyotni pre-check sifatida chiqarish so'rovi (P6, A bosqichi): ofitsiant "Schyot so'rash"ni bosganda (server
/// schyotni o'zi tuzadi, `reprint: false`) yoki "Schyotni qayta chiqarish"ni bosganda (`reprint: true`). Hech qachon
/// fiskal emas va schyotning "chiqarilgan" hisobini o'zgartirmaydi.
///
/// Model transportga bog'liq emas: so'rov serverdan `bill.print_requested` hodisasi bilan keladi
/// ([PosBillPrintRequested]), keyingi bosqichda kassa uni LAN ko'prigi orqali ham uzatadi (`toJson` shu uchun);
/// kassa ikkalasini bir xil ishlov beradi ([BillPrintRequests.accept]).
class BillPrintRequest {
  const BillPrintRequest({
    required this.billUuid,
    required this.orderUuid,
    required this.requestId,
    this.reprint = false,
    this.printerId,
    this.targetDevice,
    this.requestedBy,
  });

  /// Hodisa tanasidan: `{order: {uuid}, bill: {uuid}, request_id, reprint, printer_id, target_device, requested_by}`.
  factory BillPrintRequest.fromJson(Map<String, Object?> data) {
    final PosJson j = PosJson(data);
    return BillPrintRequest(
      billUuid: j.obj('bill').str('uuid'),
      orderUuid: j.obj('order').str('uuid'),
      requestId: j.str('request_id'),
      reprint: j.boolean('reprint'),
      printerId: j.intOrNull('printer_id'),
      targetDevice: j.strOrNull('target_device'),
      requestedBy: j.intOrNull('requested_by'),
    );
  }

  final String billUuid;
  final String orderUuid;

  /// Har so'rovga yangi uuid: bir hodisa ikki marta kelsa ham (qayta ulanish, LAN va bulut) bir marta chiqadi.
  final String requestId;

  /// Qayta chiqarish ("NUSXA"): ofitsiantning "Schyotni qayta chiqarish" so'rovi.
  final bool reprint;

  /// Chiqariladigan printer (serverdagi id); `null` — kassaning o'z chek printeri.
  final int? printerId;

  /// Chiqaradigan kassa (qurilma uuid'si); `null` — nomlanmagan: filialning hamma kassasi chiqaradi.
  final String? targetDevice;

  /// So'ragan xodim id'si.
  final int? requestedBy;

  /// Shu qurilma chiqarishi kerakmi.
  bool isFor(String? deviceUuid) =>
      targetDevice == null || targetDevice == deviceUuid;

  Map<String, Object?> toJson() => <String, Object?>{
    'order': <String, Object?>{'uuid': orderUuid},
    'bill': <String, Object?>{'uuid': billUuid},
    'request_id': requestId,
    'reprint': reprint,
    'printer_id': printerId,
    'target_device': targetDevice,
    'requested_by': requestedBy,
  };

  @override
  bool operator ==(Object other) =>
      other is BillPrintRequest &&
      other.billUuid == billUuid &&
      other.requestId == requestId;

  @override
  int get hashCode => Object.hash(billUuid, requestId);

  @override
  String toString() => 'BillPrintRequest($billUuid, $requestId)';
}

/// `POST /pos/bills/{uuid}/print-request` javobi: so'rov qabul qilindi, kim chiqarishi aytiladi.
class PosBillPrintAck {
  const PosBillPrintAck({
    required this.requestId,
    this.printerId,
    this.targetDevice,
  });

  factory PosBillPrintAck.fromJson(PosJson j) => PosBillPrintAck(
    requestId: j.str('request_id'),
    printerId: j.intOrNull('printer_id'),
    targetDevice: j.strOrNull('target_device'),
  );

  final String requestId;
  final int? printerId;
  final String? targetDevice;
}

/// Qabul qilingan so'rovlarni eslab qoladi: bir so'rov ([BillPrintRequest.billUuid] + [BillPrintRequest.requestId])
/// bir marta chiqadi. Chegaralangan (eng eskisi chiqib ketadi).
class BillPrintRequests {
  BillPrintRequests({this.capacity = 200});

  final int capacity;
  final List<String> _seen = <String>[];

  /// Yangi so'rov bo'lsa `true` va eslab qoladi; takror — `false`.
  bool accept(BillPrintRequest request) {
    final String key = '${request.billUuid}:${request.requestId}';
    if (_seen.contains(key)) return false;
    _seen.add(key);
    if (_seen.length > capacity) _seen.removeAt(0);
    return true;
  }

  /// Chiqarib bo'lmadi: shu so'rov qayta kelsa (masalan qo'lda qayta urinish) yana qabul qilinadi.
  void forget(BillPrintRequest request) =>
      _seen.remove('${request.billUuid}:${request.requestId}');
}
