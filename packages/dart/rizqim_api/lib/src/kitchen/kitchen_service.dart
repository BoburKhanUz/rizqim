import 'dart:convert';

import 'package:dio/dio.dart';

import '../auth/api_exception.dart';
import '../generated/models/get_v1_staff_kitchen_tickets_response.dart';
import '../generated/models/status5.dart';
import '../generated/models/v1_staff_orders_uuid_status_request_body.dart';
import '../generated/rizqim_api.dart';
import 'kitchen_models.dart';

/// Oshxona ekrani endpointlari (docs/API.md 9.1) yaratilgan klient (`api.kitchen`, `api.staffOrders`) ustida.
///
/// Hammasi xodim PIN sessiyasi tokeni bilan ketadi ([AuthInterceptor] o'zi qo'yadi); terminalning o'z tokeni bu
/// yo'llarga kirmaydi. Xatolar [ApiException] bo'ladi (backend matni o'zbekcha, `reason` mashina uchun).
///
/// O'qib bo'lmagan javob "aloqa yo'q" emas: [ApiException.unexpectedResponse] sababi va HTTP kodi bilan keladi. Chiptalar
/// ro'yxatida bitta chipta o'qilmasa, faqat o'sha tushiriladi ([KitchenTicketList.skipped]).
class KitchenService {
  KitchenService(this._api, this._dio, {DateTime Function()? clock})
    : _clock = clock ?? DateTime.now;

  final RizqimApi _api;

  /// Chiptalar ro'yxati xom JSON sifatida olinadi: har chipta yaratilgan model bilan alohida tekshiriladi.
  final Dio _dio;
  final DateTime Function() _clock;

  static const String _ticketsPath = '/v1/staff/kitchen/tickets';

  /// `GET /staff/kitchen/tickets`: `accepted` va `preparing` buyurtmalar ([includeReady] bilan `ready` ham),
  /// eskisidan yangisiga. [branchId] berilmasa — xodim ishlaydigan filial(lar); terminal sessiyasida terminal filiali.
  ///
  /// Yaratilgan klient (`api.kitchen.staffOrderTickets`) butun javobni bitta model bilan o'qiydi: bitta mos kelmagan
  /// chipta hammasini yiqitardi. Shuning uchun javob xom olinadi va har chipta yaratilgan javob modeli bilan alohida
  /// tekshiriladi; o'qilmaganlari [KitchenTicketList.skipped] ga tushadi.
  Future<KitchenTicketList> tickets({
    int? branchId,
    bool includeReady = false,
  }) async {
    final Response<Object?> response;
    try {
      response = await _dio.get<Object?>(
        _ticketsPath,
        queryParameters: <String, Object?>{
          'branch_id': ?branchId,
          if (includeReady) 'include_ready': true,
        },
      );
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
    final Object? body = response.data;
    final Object? data = body is Map ? body['data'] : null;
    if (body is! Map || data is! List) {
      throw _unexpected(response.statusCode, body);
    }
    final Object? meta = body['meta'];
    final DateTime now = _clock();
    final List<KitchenTicket> tickets = <KitchenTicket>[];
    final List<KitchenTicketSkip> skipped = <KitchenTicketSkip>[];
    for (var i = 0; i < data.length; i++) {
      final Object? raw = data[i];
      try {
        // Yaratilgan model spetsifikatsiya bo'yicha tekshiradi (majburiy maydonlar, turlar)
        final GetV1StaffKitchenTicketsResponse one =
            GetV1StaffKitchenTicketsResponse.fromJson(<String, Object?>{
              'success': true,
              'message': '',
              'data': <Object?>[raw],
              'meta': <String, Object?>{'truncated': false},
            });
        tickets.add(
          KitchenTicket.fromJson(_plain(one.data.single), receivedAt: now),
        );
      } on Object catch (e) {
        final Object? uuid = raw is Map ? raw['uuid'] : null;
        skipped.add(
          KitchenTicketSkip(
            index: i,
            uuid: uuid is String ? uuid : null,
            error: e,
          ),
        );
      }
    }
    return KitchenTicketList(
      tickets: tickets,
      truncated: meta is Map && meta['truncated'] == true,
      skipped: skipped,
    );
  }

  /// `POST /staff/orders/{uuid}/items/{id}/ready`: qator tayyor ("bump"). Yangilangan chiptani qaytaradi. Oxirgi qator
  /// tayyor bo'lsa yetkazish va olib ketish buyurtmasi o'zi `ready` bo'ladi (server hal qiladi).
  ///
  /// `null`: server amalni bajardi (2xx), lekin javobni o'qib bo'lmadi — chaqiruvchi ro'yxatni qayta yuklasin
  /// (amalni "bajarilmadi" deb orqaga qaytarmasin).
  Future<KitchenTicket?> bumpLine(String orderUuid, int lineId) =>
      _change(() async {
        final response = await _api.kitchen.staffOrderBumpItem(
          uuid: orderUuid,
          item: '$lineId',
        );
        return KitchenTicket.fromJson(
          _plain(response.data),
          receivedAt: _clock(),
        );
      });

  /// `POST /staff/orders/{uuid}/items/{id}/recall`: xato bosilgan "tayyor"ni qaytarish. Buyurtma `ready` bo'lib
  /// bo'lgan bo'lsa server 422 qaytaradi. `null` — [bumpLine] dagidek.
  Future<KitchenTicket?> recallLine(String orderUuid, int lineId) =>
      _change(() async {
        final response = await _api.kitchen.staffOrderRecallItem(
          uuid: orderUuid,
          item: '$lineId',
        );
        return KitchenTicket.fromJson(
          _plain(response.data),
          receivedAt: _clock(),
        );
      });

  /// `POST /staff/orders/{uuid}/status {status: ready}`: butun buyurtma tayyor (oshpaz uchun faqat
  /// `preparing → ready`; boshqa holatda server 422 qaytaradi). Hamma qatorlar ham tayyor bo'ladi.
  Future<void> markOrderReady(String orderUuid) => _change(() async {
    await _api.staffOrders.staffOrderUpdateStatus(
      uuid: orderUuid,
      body: const V1StaffOrdersUuidStatusRequestBody(status: Status5.ready),
    );
  });

  static ApiException _unexpected(int? statusCode, Object? cause) =>
      ApiException(
        message: "Server javobini o'qib bo'lmadi. Ilovani yangilang yoki administratorga ayting.",
        statusCode: statusCode,
        reason: ApiException.unexpectedResponse,
        cause: cause,
      );

  /// O'zgartirish: HTTP xatosi [ApiException]; 2xx javobni o'qib bo'lmasa — amal bajarilgan, natija `null`.
  Future<T?> _change<T>(Future<T> Function() call) async {
    try {
      return await call();
    } on DioException catch (e) {
      throw ApiException.from(e);
    } on ApiException {
      rethrow;
    } on Object {
      return null;
    }
  }

  /// Yaratilgan model -> oddiy JSON xaritasi (ichki modellar ham `toJson` bilan).
  static Map<String, Object?> _plain(Object generated) =>
      (jsonDecode(jsonEncode(generated)) as Map<Object?, Object?>)
          .cast<String, Object?>();
}
