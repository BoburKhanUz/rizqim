import 'dart:convert';

import 'package:dio/dio.dart';

import '../auth/api_exception.dart';
import '../generated/models/status4.dart';
import '../generated/models/v1_staff_orders_uuid_status_request_body.dart';
import '../generated/rizqim_api.dart';
import 'kitchen_models.dart';

/// Oshxona ekrani endpointlari (docs/API.md 9.1) yaratilgan klient (`api.kitchen`, `api.staffOrders`) ustida.
///
/// Hammasi xodim PIN sessiyasi tokeni bilan ketadi ([AuthInterceptor] o'zi qo'yadi); terminalning o'z tokeni bu
/// yo'llarga kirmaydi. Xatolar [ApiException] bo'ladi (backend matni o'zbekcha, `reason` mashina uchun).
class KitchenService {
  KitchenService(this._api, {DateTime Function()? clock})
    : _clock = clock ?? DateTime.now;

  final RizqimApi _api;
  final DateTime Function() _clock;

  /// `GET /staff/kitchen/tickets`: `accepted` va `preparing` buyurtmalar ([includeReady] bilan `ready` ham),
  /// eskisidan yangisiga. [branchId] berilmasa — xodim ishlaydigan filial(lar); terminal sessiyasida terminal filiali.
  Future<KitchenTicketList> tickets({
    int? branchId,
    bool includeReady = false,
  }) => _call(() async {
    final response = await _api.kitchen.staffOrderTickets(
      branchId: branchId,
      includeReady: includeReady ? true : null,
    );
    final DateTime now = _clock();
    return KitchenTicketList(
      tickets: response.data
          .map((Object t) => KitchenTicket.fromJson(_plain(t), receivedAt: now))
          .toList(),
      truncated: response.meta.truncated,
    );
  });

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
      body: const V1StaffOrdersUuidStatusRequestBody(status: Status4.ready),
    );
  });

  /// O'qish: HTTP xatosi ham, o'qib bo'lmaydigan javob ham [ApiException].
  Future<T> _call<T>(Future<T> Function() call) async {
    try {
      return await call();
    } on DioException catch (e) {
      throw ApiException.from(e);
    } on ApiException {
      rethrow;
    } on Object catch (e) {
      // Yaratilgan model javobni o'qiy olmadi (spetsifikatsiyaga mos emas)
      throw ApiException(
        message: 'Server kutilmagan javob qaytardi.',
        cause: e,
      );
    }
  }

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
