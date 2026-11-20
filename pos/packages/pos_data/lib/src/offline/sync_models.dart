import 'package:pos_sync/pos_sync.dart';

import '../json.dart';
import '../http/pos_exceptions.dart';

/// Internetsiz rejimda bajarib bo'lmaydigan amal (docs/POS_SPEC.md 5.1): sababi o'zbekcha ([message]). Server
/// so'ralmagan, shuning uchun bu tarmoq xatosi emas (`statusCode` 503: "hozir mumkin emas").
class PosOfflineUnavailable extends PosException {
  const PosOfflineUnavailable(
    String message, {
    super.reason = 'offline_unavailable',
  }) : super(message: message, statusCode: 503);
}

/// Oflayn to'langan va serverga yetgan schyot: uning sotuv cheki chop etilishi kerak ([PosOffline.salesToPrint]).
class PosOfflineSale {
  const PosOfflineSale({
    required this.opId,
    required this.orderUuid,
    required this.billUuid,
  });

  factory PosOfflineSale.fromJson(Map<String, Object?> j) => PosOfflineSale(
    opId: '${j['op_id']}',
    orderUuid: '${j['order_uuid']}',
    billUuid: '${j['bill_uuid']}',
  );

  /// `bill.pay` amalining `op_id` si.
  final String opId;

  /// Server uuid'lari.
  final String orderUuid;
  final String billUuid;

  Map<String, Object?> toJson() => <String, Object?>{
    'op_id': opId,
    'order_uuid': orderUuid,
    'bill_uuid': billUuid,
  };
}

/// M5 S1: kassa serverdan uzilganda (terminal tokeniga 401 yoki "Terminalni uzish") serverga yetmagan amallar. Ular
/// o'chirilmaydi: navbatdan olinib, shu kassaning diskida alohida saqlanadi ([PosOffline.orphans]) va boshqa
/// ulanishning tokeni bilan yuborilmaydi. Faylga chiqarish va serverga yuklash — keyingi bosqichda (S4).
class PosOrphanQueue {
  const PosOrphanQueue({
    required this.key,
    required this.setAsideAt,
    required this.operations,
    this.reason,
    this.deviceUuid,
    this.storeId,
    this.damagedLines = 0,
  });

  /// [key] — diskdagi hujjat kaliti; [value] — [toJson] shakli. O'qib bo'lmasa — `null`.
  static PosOrphanQueue? tryParse(String key, Object? value) {
    if (value is! Map) return null;
    final Map<String, Object?> j = value.cast<String, Object?>();
    final DateTime? at = DateTime.tryParse('${j['set_aside_at']}');
    if (at == null) return null;
    final List<SyncOperation> ops = <SyncOperation>[];
    for (final Object? o
        in (j['operations'] as List<Object?>?) ?? const <Object?>[]) {
      if (o is! Map) continue;
      try {
        // (M5 S3: a signed operation comes back with its signature, [SyncOperation.toStored])
        ops.add(SyncOperation.fromStored(o.cast<String, Object?>()));
      } on Object {
        // An unreadable entry stays in the file as it is; it is only not counted here
      }
    }
    return PosOrphanQueue(
      key: key,
      setAsideAt: at,
      operations: ops,
      reason: j['reason'] as String?,
      deviceUuid: j['device_uuid'] as String?,
      storeId: (j['store_id'] as num?)?.toInt(),
      damagedLines:
          ((j['damaged'] as List<Object?>?) ?? const <Object?>[]).length,
    );
  }

  /// Diskdagi hujjat kaliti (`orphan_...`).
  final String key;
  final DateTime setAsideAt;

  /// `terminal_revoked` (server tokenni rad etdi), `terminal_forgotten` (kassada uzildi), `paired_again`.
  final String? reason;

  /// Amallar qaysi qurilma (ulanish) va do'konniki edi (ma'lum bo'lsa).
  final String? deviceUuid;
  final int? storeId;

  /// Serverga yetmagan amallar, `seq` tartibida.
  final List<SyncOperation> operations;

  /// Jurnalning o'qib bo'lmagan qatorlari soni (ular ham saqlangan).
  final int damagedLines;

  int get count => operations.length;

  /// Shulardan to'lovlar (`bill.pay`).
  int get payments => operations
      .where((SyncOperation o) => o.type == SyncOpType.billPay)
      .length;

  PosOrphanQueue withKey(String next) => PosOrphanQueue(
    key: next,
    setAsideAt: setAsideAt,
    operations: operations,
    reason: reason,
    deviceUuid: deviceUuid,
    storeId: storeId,
    damagedLines: damagedLines,
  );
}

/// M5 S1: lentada ko'rsatiladigan, menejer hali ko'rmagan ogohlantirishlar ([PosOffline.warnings]).
class PosOfflineWarnings {
  const PosOfflineWarnings({
    this.damagedLines = 0,
    this.orphans = const <PosOrphanQueue>[],
    this.held = false,
  });

  /// Kassa jurnalining o'qib bo'lmagan qatorlari: har birida tasdiqlangan amal (sotuv) bo'lgan bo'lishi mumkin.
  final int damagedLines;

  /// Uzilgan ulanishlardan qolgan, serverga yuborilmagan amallar.
  final List<PosOrphanQueue> orphans;

  /// Uzilgan kassaning navbati hali jurnalda (chetga olib bo'lmadi): yuborilmaydi.
  final bool held;

  int get orphanOperations =>
      orphans.fold(0, (int n, PosOrphanQueue o) => n + o.count);

  int get orphanPayments =>
      orphans.fold(0, (int n, PosOrphanQueue o) => n + o.payments);

  bool get isEmpty => damagedLines == 0 && orphans.isEmpty && !held;
}

/// "Sinxronlash xatolari": serverda rad etilgan yoki farq bilan qo'llangan oflayn amal (`GET /pos/sync/errors`,
/// `GET /pos/sync/status`).
class PosSyncError {
  const PosSyncError({
    required this.opId,
    required this.seq,
    required this.type,
    required this.typeLabel,
    required this.status,
    this.reason,
    this.message,
    this.staffName,
    this.deviceName,
    this.occurredAt,
    this.receivedAt,
    this.orderUuid,
    this.localNumber,
    this.tableRef,
    this.resolvedAt,
    this.resolution,
    this.resolutionNote,
    this.amount,
  });

  factory PosSyncError.fromJson(PosJson j) {
    final PosJson? order = j.objOrNull('order');
    final PosJson? payload = j.objOrNull('payload');
    String? amount;
    final Object? tenders = payload?['tenders'];
    if (tenders is List && tenders.isNotEmpty) {
      var sum = 0;
      for (final Object? t in tenders) {
        if (t is Map) sum += PosJson.of(t).money('amount').tiyin;
      }
      amount = (sum ~/ 100).toString();
    }
    return PosSyncError(
      opId: j.str('op_id'),
      seq: j.intOrNull('seq') ?? 0,
      type: j.str('type'),
      typeLabel: j.strOrNull('type_label') ?? j.str('type'),
      status: j.str('status'),
      reason: j.strOrNull('reason'),
      message:
          j.strOrNull('message') ??
          PosSyncReasons.describe(j.strOrNull('reason')),
      staffName: j.objOrNull('staff')?.strOrNull('name'),
      deviceName: j.objOrNull('device')?.strOrNull('name'),
      occurredAt: j.dateOrNull('occurred_at'),
      receivedAt: j.dateOrNull('received_at'),
      orderUuid: order?.strOrNull('uuid'),
      localNumber:
          order?.strOrNull('local_number') ??
          payload?.strOrNull('local_number'),
      tableRef: order?.strOrNull('table_ref'),
      resolvedAt: j.dateOrNull('resolved_at'),
      resolution: j.strOrNull('resolution'),
      resolutionNote: j.strOrNull('resolution_note'),
      amount: amount,
    );
  }

  final String opId;
  final int seq;
  final String type;
  final String typeLabel;

  /// `rejected` (qo'llanmadi) yoki `conflict` (farq bilan qo'llandi).
  final String status;
  final String? reason;
  final String? message;
  final String? staffName;
  final String? deviceName;
  final DateTime? occurredAt;
  final DateTime? receivedAt;
  final String? orderUuid;
  final String? localNumber;
  final String? tableRef;
  final DateTime? resolvedAt;

  /// `resolved` yoki `dismissed`.
  final String? resolution;
  final String? resolutionNote;

  /// To'lov amalida olingan summa (so'm), boshqasida `null`.
  final String? amount;

  bool get isRejected => status == 'rejected';
  bool get isResolved => resolvedAt != null;
}

/// `GET /pos/sync/errors` sahifasi.
class PosSyncErrorsPage {
  const PosSyncErrorsPage({required this.errors, this.total = 0});

  factory PosSyncErrorsPage.fromJson(PosJson j) => PosSyncErrorsPage(
    errors: j.objects('errors', PosSyncError.fromJson),
    total: j.objOrNull('meta')?.intOrNull('total') ?? 0,
  );

  final List<PosSyncError> errors;
  final int total;
}

/// Oflayn amal natijasidagi sabab va belgilar (`POST /pos/sync` `results[].reason`, `refs.flags[].flag`) va ularning
/// o'zbekcha matni — server matni bo'lmaganda ("Sinxronlash xatolari" ro'yxati, kassirga xabar).
abstract final class PosSyncReasons {
  /// P3-35: amal ma'lumotini server saqlay olmadi (noto'g'ri yoki juda katta qiymat) — `rejected`, navbat to'xtamaydi.
  static const String invalidPayload = 'invalid_payload';

  /// P3-35: qo'shimcha menyudan o'chirilgan yoki boshqa do'konniki — `rejected`.
  static const String modifierNotFound = 'modifier_not_found';

  /// P3-35: qurilma narxi menyu nusxasi vaqtida bo'lmagan — qator **server narxida** saqlandi (`conflict`). Kassadagi
  /// jami serverdagidan farq qilishi mumkin: buyurtma va schyot serverdan qayta yuklanadi.
  static const String offlinePriceMismatch = 'offline_price_mismatch';

  /// P3-35: qurilma soati 5 daqiqadan ko'p farq qilgani uchun amal vaqti tuzatildi (faqat ma'lumot uchun).
  static const String deviceTimeClamped = 'device_time_clamped';

  /// M5 S1: to'lov xodim tekshiruvidan o'tmadi (PIN sessiyasi 36 soatdan eski, ruxsat olib qo'yilgan yoki xodim
  /// topilmadi) — pul baribir yozildi (`conflict`): ochiq schyot bo'lsa unga tushdi (`refs.bill_uuid`), bo'lmasa
  /// schyotsiz; menejer ko'rib chiqadi.
  static const String staffUnverified = 'staff_unverified';

  static const Map<String, String> _messages = <String, String>{
    staffUnverified: "To'lov xodim tekshiruvidan o'tmadi (PIN bilan kirganiga 36 soatdan oshgan yoki ruxsati o'zgargan). Pul yozildi: menejer tekshirsin.",
    'staff_not_signed_in': "Xodim bu kassada uzilishdan oldingi 36 soat ichida PIN bilan kirmagan: amal qo'llanmadi.",
    invalidPayload: "Amal ma'lumotlari serverda saqlanmadi (noto'g'ri yoki juda katta qiymat). Menejer qo'lda tekshirsin.",
    modifierNotFound: "Qo'shimcha menyuda topilmadi (o'chirilgan yoki boshqa do'konniki): amal qo'llanmadi. Menejer tekshirsin.",
    offlinePriceMismatch: "Offlayn sotilgan narx menyuda bo'lmagan: server narxi qo'yildi. Buyurtma jami o'zgargan bo'lishi mumkin.",
    deviceTimeClamped: "Qurilma soati noto'g'ri edi: amal vaqti server soati bo'yicha tuzatildi.",
    'stale_menu': "Narxlar 24 soatdan eski menyu nusxasidan olingan: menejer ko'rib chiqsin.",
    'sold_while_unavailable': "Taom offlayn sotilgan, lekin serverda u hozir sotilmaydi (stop-list yoki yashirilgan).",
    'oversold': "Qozonda yetmagan taom offlayn sotilgan: qoldiq 0 da qoldi.",
    'stock_short': "Qoldiqdan ko'p sotilgan.",
    'table_occupied': "Joy serverda band edi: buyurtma joysiz ochildi, menejer joyini tanlasin.",
    'table_inactive': "Joy serverda yopilgan edi: buyurtma joysiz ochildi, menejer joyini tanlasin.",
    'table_missing':
        'Stol yoki xona serverda topilmadi: buyurtma joysiz ochildi.',
    'underpaid': "Schyot serverda boshqacha chiqdi: kam to'langan.",
    'overpaid': "Schyot serverda boshqacha chiqdi: ortiqcha olingan.",
    'payment_unallocated': "To'lov schyotga bog'lanmadi, lekin pul buyurtmaga yozildi: menejer tekshirsin.",
    'order_not_found': 'Buyurtma serverda topilmadi.',
    'order_closed': 'Buyurtma serverda yopilgan yoki bekor qilingan edi.',
    'line_not_found': 'Taom qatori serverda topilmadi.',
    'line_sent':
        "Taom shu orada oshxonaga yuborilgan edi: o'chirish qo'llanmadi.",
    'variant_not_found': 'Taom menyuda topilmadi (o\'chirilgan).',
    'discount_not_allowed': "Internetsiz berilgan chegirma serverda qabul qilinmadi (ruxsat yoki chegara): schyot to'liq summada qoldi. Menejer tekshirsin.",
    // (enforce mode after the S3 review: the sale itself is applied and shown to the manager; only a void or a
    // discount is refused)
    'signature_invalid': "Amal imzosi tekshiruvdan o'tmadi: amal qo'llandi, menejer tekshirsin (taomni o'chirish yoki chegirma bo'lsa, u qo'llanmadi).",
    'payment_unverified':
        "To'lov imzosi tekshiruvdan o'tmadi: pul yozildi, menejer tekshirsin.",
    'chain_gap':
        "Kassa navbatidan amal yo'qolgan bo'lishi mumkin: menejer tekshirsin.",
    'tender_not_offline': "Oflayn faqat naqd va karta to'lovi olinadi.",
  };

  /// [reason] (yoki belgi) uchun o'zbekcha matn (noma'lum — `null`).
  static String? describe(String? reason) =>
      reason == null ? null : _messages[reason];
}

/// P3-35: offlayn sotilgan qator server narxida saqlandi (`offline_price_mismatch` belgisi): kassirga xabar.
class PosSyncPriceNotice {
  const PosSyncPriceNotice({
    required this.opId,
    required this.name,
    required this.devicePrice,
    required this.serverPrice,
    this.orderUuid,
    this.localNumber,
  });

  final String opId;
  final String? orderUuid;

  /// Qurilmadagi raqam ("K1-0005").
  final String? localNumber;
  final String name;

  /// So'mda, serverdagi kabi ("12000.00").
  final String devicePrice;
  final String serverPrice;

  /// Kassirga: nima bo'ldi va nima qilish kerak.
  String get message =>
      "${localNumber == null ? '' : '$localNumber: '}'$name' offlayn ${_som(devicePrice)} so'mdan sotilgan, serverda "
      "${_som(serverPrice)} so'm qo'yildi. Buyurtma va schyot serverdan yangilandi — jami boshqacha bo'lishi mumkin.";

  static String _som(String v) =>
      v.endsWith('.00') ? v.substring(0, v.length - 3) : v;

  /// Javob olgan amallardagi narx farqlari (boshqa belgilar e'tiborsiz; `device_time_clamped` — faqat ma'lumot).
  static List<PosSyncPriceNotice> of(Iterable<SyncOperation> ops) =>
      <PosSyncPriceNotice>[
        for (final SyncOperation o in ops)
          for (final Object? f
              in (o.refs['flags'] as List<Object?>?) ?? const <Object?>[])
            if (f is Map && f['flag'] == PosSyncReasons.offlinePriceMismatch)
              PosSyncPriceNotice(
                opId: o.opId,
                orderUuid: o.refs['order_uuid'] as String?,
                localNumber:
                    o.refs['local_number'] as String? ??
                    o.payload['local_number'] as String?,
                name: '${f['name'] ?? 'Taom'}',
                devicePrice: '${f['device_price'] ?? ''}',
                serverPrice: '${f['server_price'] ?? ''}',
              ),
      ];
}

/// Javob olgan amallar orasida [orderRef] buyurtmasiga (qurilma id'si yoki server uuid'si; [resolve] — qurilma
/// id'sini server uuid'iga) tegishlisi bormi: ekran buyurtmani serverdan qayta yuklasin.
bool syncTouchesOrder(
  Iterable<SyncOperation> ops,
  String orderRef,
  String Function(String id) resolve,
) {
  final String uuid = resolve(orderRef);
  return ops.any(
    (SyncOperation o) =>
        o.refs['order_uuid'] == uuid ||
        o.orderRef == orderRef ||
        (o.orderRef != null && resolve(o.orderRef!) == uuid),
  );
}
