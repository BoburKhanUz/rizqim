import '../http/pos_http.dart';
import '../orders/order_models.dart';
import 'hostess_models.dart';

/// Hostes stoli va ofitsiant almashtirish (docs/API.md, "Hostes, ofitsiantni almashtirish va stol broni", P6-24/P6-26):
/// kutish ro'yxati, stolning "tozalanmoqda" belgisi, ochiq stolni boshqa ofitsiantga berish va qatorga mehmon raqami
/// qo'yish. Yupqa qatlam: har o'zgartiruvchi so'rov `Idempotency-Key` bilan; modul o'chiq bo'lsa — 403
/// `module_disabled`, ruxsat yo'q — 403.
///
/// Xatolar: eskirgan versiya — [PosConflict] `stale_version` (yozuv) yoki `version_conflict` (buyurtma); yopilgan yozuv
/// — [PosConflict] `entry_closed`; stol topilmasa — [PosRejected] `table_not_found`; menejer tasdig'i kerak —
/// [PosApprovalRequired] (`orders.transfer`).
class HostessRepository {
  HostessRepository(this._http);

  final PosHttp _http;

  static PosWaitlistEntry _entry(Object? data) =>
      PosWaitlistEntry.fromJson(dataObject(data));

  /// `GET /pos/waitlist`: filialning biznes kuni ([date], standart — bugun) yozuvlari, kelish tartibida.
  Future<PosWaitlist> waitlist({String? date, String? status, int? branchId}) =>
      _http.get(
        '/v1/pos/waitlist',
        (Object? data) => PosWaitlist.fromJson(dataObject(data)),
        query: <String, Object?>{
          'date': date,
          'status': status,
          'branch_id': branchId,
        },
      );

  /// `POST /pos/waitlist`: mehmonni ro'yxatga yozish.
  Future<PosWaitlistEntry> add({
    required String guestName,
    required int partySize,
    String? guestPhone,
    int? quotedWaitMinutes,
    String? note,
    int? branchId,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/waitlist',
    _entry,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'guest_name': guestName,
      'party_size': partySize,
      'guest_phone': ?guestPhone,
      'quoted_wait_minutes': ?quotedWaitMinutes,
      'note': ?note,
      'branch_id': ?branchId,
    },
  );

  /// `PATCH /pos/waitlist/{uuid}`: faqat berilgan maydonlar o'zgaradi (kutayotgan yozuv).
  Future<PosWaitlistEntry> update(
    String uuid, {
    String? guestName,
    String? guestPhone,
    int? partySize,
    int? quotedWaitMinutes,
    String? note,
    int? version,
    String? idempotencyKey,
  }) => _http.mutate(
    'PATCH',
    '/v1/pos/waitlist/$uuid',
    _entry,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'guest_name': ?guestName,
      'guest_phone': ?guestPhone,
      'party_size': ?partySize,
      'quoted_wait_minutes': ?quotedWaitMinutes,
      'note': ?note,
      'version': ?version,
    },
  );

  /// `POST /pos/waitlist/{uuid}/notify`: mehmon chaqirildi ("stol tayyor").
  Future<PosWaitlistEntry> notify(
    String uuid, {
    int? version,
    String? idempotencyKey,
  }) => _action(uuid, 'notify', <String, Object?>{
    'version': ?version,
  }, idempotencyKey);

  /// `POST /pos/waitlist/{uuid}/seat`: mehmonlarni [diningTableId] ga joylashtirish; [openOrder] — shu stolda
  /// buyurtma ham ochiladi (`orders.manage` kerak), [waiterId] — uning ofitsianti.
  Future<PosWaitlistEntry> seat(
    String uuid, {
    required int diningTableId,
    bool openOrder = false,
    int? waiterId,
    int? version,
    String? idempotencyKey,
  }) => _action(uuid, 'seat', <String, Object?>{
    'dining_table_id': diningTableId,
    if (openOrder) 'open_order': true,
    'waiter_id': ?waiterId,
    'version': ?version,
  }, idempotencyKey);

  /// `POST /pos/waitlist/{uuid}/cancel`: mehmon ketdi.
  Future<PosWaitlistEntry> cancel(
    String uuid, {
    String? reason,
    int? version,
    String? idempotencyKey,
  }) => _action(uuid, 'cancel', <String, Object?>{
    'reason': ?reason,
    'version': ?version,
  }, idempotencyKey);

  /// `POST /pos/waitlist/{uuid}/no-show`: mehmon kelmadi.
  Future<PosWaitlistEntry> noShow(
    String uuid, {
    int? version,
    String? idempotencyKey,
  }) => _action(uuid, 'no-show', <String, Object?>{
    'version': ?version,
  }, idempotencyKey);

  /// `PUT /pos/tables/{id}/state`: stolni "tozalanmoqda" ([cleaning] true) deb belgilash yoki tayyor deb olib tashlash.
  Future<PosTableState> setTableCleaning(
    int tableId, {
    required bool cleaning,
    String? idempotencyKey,
  }) => _http.mutate(
    'PUT',
    '/v1/pos/tables/$tableId/state',
    (Object? data) => PosTableState.fromJson(dataObject(data)),
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{'state': cleaning ? 'cleaning' : 'free'},
  );

  /// `POST /pos/orders/{uuid}/transfer`: ochiq stolni [waiterId] ofitsiantga berish. `orders.transfer` (menejer, ega)
  /// yoki menejerning shu ruxsat uchun [approvalToken]'i; aks holda [PosApprovalRequired]. Eski ofitsiantga ham,
  /// yangisiga ham xabar boradi (`order.transferred`).
  Future<PosOrder> transferOrder(
    String orderUuid, {
    required int version,
    required int waiterId,
    String? reason,
    String? approvalToken,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/orders/$orderUuid/transfer',
    (Object? data) => PosOrder.fromJson(dataObject(data)),
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'version': version,
      'waiter_id': waiterId,
      'reason': ?reason,
      'approval_token': ?approvalToken,
    },
  );

  /// `POST /pos/orders/{uuid}/items/{item}/seat`: qatorga mehmon raqami (1–500) qo'yish; `null` — hech kimniki emas
  /// (umumiy). Mehmon bo'yicha bo'lish (`PosSplit.byGuest`) shu raqamlardan foydalanadi.
  Future<PosOrder> setLineSeat(
    String orderUuid,
    int itemId, {
    required int version,
    required int? seatNo,
    String? approvalToken,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/orders/$orderUuid/items/$itemId/seat',
    (Object? data) => PosOrder.fromJson(dataObject(data)),
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'version': version,
      'seat_no': seatNo,
      'approval_token': ?approvalToken,
    },
  );

  Future<PosWaitlistEntry> _action(
    String uuid,
    String action,
    Map<String, Object?> body,
    String? key,
  ) => _http.mutate(
    'POST',
    '/v1/pos/waitlist/$uuid/$action',
    _entry,
    idempotencyKey: key,
    body: body,
  );
}
