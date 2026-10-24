import 'package:rizqim_api/rizqim_api.dart';

/// POS endpointlari rad etgan amal: [ApiException] ning o'zi (UI'dagi `on ApiException` ushlaydi), lekin turi bo'yicha
/// ajratilgan va javobning `data` qismi ([data]: `version`, `permission`, `conflicts`, `paid_amount` ...) bilan.
///
/// Matn ([message]) — foydalanuvchiga ko'rsatiladigan o'zbekcha: server yozgani, u bo'lmasa (yoki inglizcha bo'lsa)
/// [PosReasons.describe] dagi matn.
///
/// | Tur | Qachon |
/// |---|---|
/// | [PosVersionConflict] | 409 `version_conflict`: buyurtmani boshqa qurilma o'zgartirdi — qayta yuklang |
/// | [PosApprovalRequired] | 403 `approval_required`: menejer tasdig'i kerak ([permission]) |
/// | [PosApprovalRejected] | 403 tasdiq yaroqsiz (`approval_invalid`, `approval_expired`, `approval_used`, ...) |
/// | [PosConflict] | boshqa 409 (`has_payments`, `shift_not_open`, `booking_conflict`, `order_changed` ...) |
/// | [PosRejected] | 422 `data.reason` bilan (`table_occupied`, `nothing_to_send`, `mxik_missing` ...) |
/// | [PosValidationException] | 422 maydon xatolari (`errors`), sababsiz |
/// | [PosForbidden] | boshqa 403 (ruxsat yo'q) |
/// | [PosNotFound] | 404 |
/// | [PosNetworkException] | javob kelmadi: tarmoq yo'q (qayta urinishda o'sha [idempotencyKey] bilan yuboring) |
/// | [PosException] | qolgani (401, 429, 5xx, o'qib bo'lmagan javob) |
class PosException extends ApiException {
  const PosException({
    required super.message,
    super.statusCode,
    super.reason,
    super.errors,
    super.retryAfter,
    super.cause,
    this.data = const <String, Object?>{},
    this.idempotencyKey,
  });

  /// Javobning `data` qismi (xatoda: `reason` va qo'shimcha maydonlar).
  final Map<String, Object?> data;

  /// O'zgartiruvchi so'rov qaysi `Idempotency-Key` bilan yuborilgan edi. Tarmoq xatosidan keyin **aynan o'sha
  /// so'rovni** shu kalit bilan qayta yuborish xavfsiz (server ikki marta bajarmaydi). Tana o'zgarsa (masalan,
  /// `approval_token` qo'shilsa) — yangi kalit kerak.
  final String? idempotencyKey;

  /// Ekrandagi buyurtma eskirgan: uni (va schyotni) serverdan qayta yuklang ([PosReasons.reloadOrder]).
  bool get needsOrderReload => PosReasons.reloadOrder.contains(reason);

  @override
  String toString() =>
      '$runtimeType($statusCode${reason != null ? ', $reason' : ''}): $message';
}

/// 409 `version_conflict`: buyurtma boshqa qurilmada o'zgargan. [currentVersion] — serverdagi versiya; ilova
/// buyurtmani qayta yuklaydi (`GET /pos/orders/{uuid}`) va amalni foydalanuvchi bilan takrorlaydi.
class PosVersionConflict extends PosException {
  const PosVersionConflict({
    required super.message,
    super.statusCode,
    super.reason,
    super.cause,
    super.data,
    super.idempotencyKey,
  });

  int? get currentVersion => _int(data['version']);
}

/// 403 `approval_required`: shu amal uchun menejer tasdig'i kerak. [permission] uchun tasdiq olib
/// (`ApprovalService.request`), amalni `approvalToken` bilan **yangi** kalit bilan qayta yuboring.
class PosApprovalRequired extends PosException {
  const PosApprovalRequired({
    required super.message,
    super.statusCode,
    super.reason,
    super.cause,
    super.data,
    super.idempotencyKey,
  });

  /// Qaysi ruxsat uchun tasdiq kerak (`orders.void_item`, `orders.manage` ...).
  String? get permission => data['permission'] as String?;

  /// Ofitsiant qoidasi: buyurtma boshqa ofitsiantniki bo'lsa — uning id'si.
  int? get waiterId => _int(data['waiter_id']);
}

/// 403: yuborilgan tasdiq yaroqsiz (`approval_invalid`, `approval_expired`, `approval_used`,
/// `approval_wrong_device`, `approval_wrong_permission`) yoki tasdiqlovchida ruxsat yo'q (`approver_not_allowed`).
class PosApprovalRejected extends PosException {
  const PosApprovalRejected({
    required super.message,
    super.statusCode,
    super.reason,
    super.cause,
    super.data,
    super.idempotencyKey,
  });
}

/// Boshqa 409: holat bunga yo'l qo'ymaydi (`has_payments`, `shift_not_open`, `shift_already_open`,
/// `order_changed`, `booking_conflict` (`data.conflicts`), `not_enough_cash`, `order_not_settled`, ...).
class PosConflict extends PosException {
  const PosConflict({
    required super.message,
    super.statusCode,
    super.reason,
    super.cause,
    super.data,
    super.idempotencyKey,
  });
}

/// 422 biznes qoidasi `data.reason` bilan (`table_occupied`, `table_inactive`, `nothing_to_send`, `already_void`,
/// `order_closed`, `mxik_missing`, `overpayment`, `invalid_transition`, ...).
class PosRejected extends PosException {
  const PosRejected({
    required super.message,
    super.statusCode,
    super.reason,
    super.errors,
    super.cause,
    super.data,
    super.idempotencyKey,
  });
}

/// 422 maydon xatolari (Laravel validatsiyasi: `errors`), masalan "Osh kamida 0.5 kg buyurtma qilinadi".
class PosValidationException extends PosException {
  const PosValidationException({
    required super.message,
    super.statusCode,
    super.errors,
    super.cause,
    super.data,
    super.idempotencyKey,
  });

  /// Birinchi maydon xatosi (yoki umumiy matn).
  String get firstError {
    for (final List<String> list in errors.values) {
      if (list.isNotEmpty) return list.first;
    }
    return message;
  }
}

/// Boshqa 403: xodimda ruxsat yo'q yoki boshqa filial.
class PosForbidden extends PosException {
  const PosForbidden({
    required super.message,
    super.statusCode,
    super.reason,
    super.cause,
    super.data,
    super.idempotencyKey,
  });
}

/// 404: buyurtma, schyot, smena yoki bron topilmadi (boshqa do'kon yoki filialniki ham).
class PosNotFound extends PosException {
  const PosNotFound({
    required super.message,
    super.statusCode,
    super.reason,
    super.cause,
    super.data,
    super.idempotencyKey,
  });
}

/// Javob kelmadi (tarmoq yo'q, server o'chiq, vaqt tugadi). O'zgartiruvchi so'rov serverda bajarilgan bo'lishi ham
/// mumkin: o'sha so'rovni [idempotencyKey] bilan qayta yuboring.
class PosNetworkException extends PosException {
  const PosNetworkException({
    required super.message,
    super.cause,
    super.idempotencyKey,
  });

  @override
  bool get isNetworkError => true;
}

/// `data.reason` kodlari va ularning o'zbekcha matni (server matni bo'lmaganda yoki inglizcha bo'lganda).
abstract final class PosReasons {
  static const String versionConflict = 'version_conflict';
  static const String approvalRequired = 'approval_required';
  static const String approvalInvalid = 'approval_invalid';
  static const String approvalExpired = 'approval_expired';
  static const String approvalUsed = 'approval_used';
  static const String approvalWrongDevice = 'approval_wrong_device';
  static const String approvalWrongPermission = 'approval_wrong_permission';
  static const String approverNotAllowed = 'approver_not_allowed';
  static const String terminalRequired = 'terminal_required';
  static const String idempotencyKeyRequired = 'idempotency_key_required';

  /// P3-35: o'zgarish to'lovi olingan schyotdagi taom yoki xizmat haqiga tegadi (409; o'chirish, mehmonlar,
  /// ko'chirish, birlashtirish, xizmat qatori). Hech narsa o'zgarmagan: buyurtmani qayta yuklang, avval to'lovni
  /// qaytaring.
  static const String paidBillAffected = 'paid_bill_affected';

  /// P3-35: ikkala buyurtma boshqa-boshqa bronniki — birlashtirib bo'lmaydi (422).
  static const String mergeBookingsConflict = 'merge_bookings_conflict';

  /// P3-35: smena shu orada (Z bilan) yopildi — to'lov yoki qaytarish yozilmadi (409).
  static const String shiftClosed = 'shift_closed';

  /// P3-35 qaytarish: avans qismi schyotga ayirilganidan ko'p (409).
  static const String reverseExceedsApplied = 'reverse_exceeds_applied';

  /// P3-35 qaytarish: nasiya qismi schyotga yozilganidan ko'p (409).
  static const String reverseExceedsCharge = 'reverse_exceeds_charge';

  /// P3-35 qaytarish: mijoz nasiyani qisman to'lagan, qolgan qarz qaytarishdan kam (409).
  static const String moreThanDebt = 'more_than_debt';

  /// P3-35 qaytarish: nasiya to'lovining hisobi topilmadi (409).
  static const String accountUnknown = 'account_unknown';

  /// Rad etilgan amaldan keyin buyurtma (va schyot) serverdan qayta yuklanadi: ekrandagi ko'rinish eskirgan.
  static const Set<String> reloadOrder = <String>{
    versionConflict,
    'order_closed',
    'order_changed',
    paidBillAffected,
  };

  /// Bu sabablarda ilovaning o'z matni server matnidan ustun (kassada nima qilishni aniq aytadi). Summa aytadigan
  /// sabablarda (`reverse_exceeds_*`, `more_than_debt`) server matni qoladi.
  static const Set<String> clientWorded = <String>{
    paidBillAffected,
    mergeBookingsConflict,
    shiftClosed,
    accountUnknown,
  };

  /// Tasdiq rad etilganini bildiruvchi sabablar ([PosApprovalRejected]).
  static const Set<String> approvalRejections = <String>{
    approvalInvalid,
    approvalExpired,
    approvalUsed,
    approvalWrongDevice,
    approvalWrongPermission,
    approverNotAllowed,
  };

  static const Map<String, String> _messages = <String, String>{
    versionConflict: "Buyurtma boshqa qurilmada o'zgartirildi. Uni qayta yuklang va amalni takrorlang.",
    approvalRequired: "Bu amal uchun menejer tasdig'i kerak.",
    approvalInvalid: "Tasdiq yaroqsiz. Menejer PIN'ini qayta tering.",
    approvalExpired:
        "Tasdiq muddati o'tdi (60 soniya). Menejer PIN'ini qayta tering.",
    approvalUsed:
        'Bu tasdiq allaqachon ishlatilgan. Menejer PIN\'ini qayta tering.',
    approvalWrongDevice: 'Tasdiq boshqa terminal uchun berilgan.',
    approvalWrongPermission: 'Tasdiq boshqa amal uchun berilgan.',
    approverNotAllowed: 'Bu xodim bu amalni tasdiqlay olmaydi.',
    terminalRequired: "Tasdiq faqat ulangan terminalda olinadi.",
    idempotencyKeyRequired: "So'rov kalitsiz yuborildi. Ilovani yangilang.",
    'pin_invalid': "PIN noto'g'ri. Qayta urinib ko'ring.",
    'pin_locked':
        "PIN ko'p marta noto'g'ri terildi. Kirish vaqtincha bloklandi.",
    'table_occupied':
        'Xona band: unda ochiq buyurtma bor. Buyurtmalarni birlashtiring.',
    'table_inactive': "Bu joy yoki zona hozir yopiq.",
    'same_table': 'Buyurtma allaqachon shu joyda.',
    'not_hall_order': "Olib ketish buyurtmasini stolga ko'chirib bo'lmaydi.",
    'nothing_to_send': "Oshxonaga yuboriladigan yangi taom yo'q.",
    'already_void': "Bu taom allaqachon o'chirilgan.",
    'already_served': 'Bu taom allaqachon berilgan.',
    'item_not_ready': 'Taom hali oshxonaga yuborilmagan yoki tayyor emas.',
    'order_closed':
        "Buyurtma yopilgan yoki bekor qilingan: uni o'zgartirib bo'lmaydi.",
    'not_pos_order': 'Bu kassa buyurtmasi emas.',
    'bill_locked': 'Schyot chiqarilgan buyurtmani birlashtirib bo\'lmaydi.',
    'has_payments': "Buyurtmada to'lov bor: avval to'lovni qaytaring.",
    'merge_not_allowed': "Bu buyurtmalarni birlashtirib bo'lmaydi.",
    'mxik_missing':
        "Taom uchun MXIK kodi kiritilmagan: fiskal rejimda u sotilmaydi.",
    'order_changed':
        "Schyotdan keyin buyurtma o'zgardi. Schyotni qayta tuzing.",
    'items_not_covered': "Hamma taom schyotlarga taqsimlanmadi.",
    'overpayment': "To'lov schyot qoldig'idan ko'p.",
    'card_type_required': 'Karta turini tanlang.',
    'transaction_id_required': 'Tranzaksiya raqamini kiriting.',
    'advance_insufficient': "Avans qoldig'i yetmaydi.",
    'shift_not_open': 'Kassada ochiq smena yo\'q. Avval smenani oching.',
    'shift_already_open': 'Bu terminalda smena allaqachon ochiq.',
    'shift_has_open_orders': 'Smenada ochiq buyurtmalar bor.',
    'shift_has_pending_receipts': 'Cheki kutilayotgan to\'lovlar bor.',
    'not_enough_cash': 'Kassada naqd pul yetmaydi.',
    'order_not_settled': "Buyurtma hali to'liq to'lanmagan.",
    'order_receipted_online':
        "Buyurtmaga onlayn to'lovda chek chiqqan: kassada qayta hisoblanmaydi.",
    'refund_outside_shift': 'Faqat shu smenadagi to\'lovni qaytarish mumkin.',
    'refund_tender_invalid': "Bu to'lov turini bu yerda qaytarib bo'lmaydi.",
    'refund_exceeds_payment': "Qaytariladigan summa to'lovdan ko'p.",
    'bill_not_fiscalized': 'Schyotning sotuv cheki hali chiqmagan.',
    'booking_conflict': 'Bu vaqtda xona band.',
    'booking_already_arrived':
        'Mehmonlar allaqachon kelgan: buyurtma ochilgan.',
    'invalid_transition': "Bron holatini bunday o'zgartirib bo'lmaydi.",
    'stale_version': "Bron boshqa qurilmada o'zgartirildi. Qayta yuklang.",
    'kitchen_status': "Taom hali oshxonaga yuborilmagan.",
    paidBillAffected:
        "Schyotning bir qismi to'langan: bu o'zgarish to'langan taom yoki xizmat haqiga tegadi. Avval o'sha "
        "to'lovni qaytaring, keyin o'zgartiring. Buyurtma o'zgarmadi.",
    mergeBookingsConflict: "Ikkala buyurtma ham boshqa-boshqa bronga tegishli: ularni birlashtirib bo'lmaydi.",
    shiftClosed: "Smena shu orada yopildi: pul olinmadi va qaytarilmadi. Yangi smena oching va amalni qayta bajaring.",
    reverseExceedsApplied:
        "Bu schyotga avansdan shuncha ayirilmagan: kamroq summani qaytaring.",
    reverseExceedsCharge:
        "Bu schyot nasiyaga shuncha yozilmagan: kamroq summani qaytaring.",
    moreThanDebt:
        "Mijoz qarzining bir qismini to'lab bo'lgan: qolgan qarzdan ko'pini qaytarib bo'lmaydi. Farqni menejer "
        "qo'lda hal qilsin.",
    accountUnknown:
        "Nasiya to'lovining hisobi topilmadi: qarzni menejer qo'lda tuzatsin.",
  };

  /// [reason] uchun o'zbekcha matn (noma'lum — `null`).
  static String? describe(String? reason) =>
      reason == null ? null : _messages[reason];
}

int? _int(Object? v) =>
    v is num ? v.toInt() : (v is String ? int.tryParse(v) : null);
