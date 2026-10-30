import 'dart:convert';
import 'dart:math';

import 'package:dio/dio.dart';

import '../auth/api_exception.dart';
import '../generated/models/get_v1_staff_kitchen_tickets_response.dart';
import '../generated/models/status6.dart';
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
  /// [canceledWithin] (P3-26, daqiqa, 1–60): shuncha vaqt ichida bekor qilingan yoki rad etilgan, oshxonaga yetgan
  /// buyurtmalar ham ([KitchenTicket.isCanceled], [KitchenTicket.canceledAt]) — doskada chizib ko'rsatish uchun.
  ///
  /// Yaratilgan klient (`api.kitchen.staffOrderTickets`) butun javobni bitta model bilan o'qiydi: bitta mos kelmagan
  /// chipta hammasini yiqitardi. Shuning uchun javob xom olinadi va har chipta yaratilgan javob modeli bilan alohida
  /// tekshiriladi; o'qilmaganlari [KitchenTicketList.skipped] ga tushadi.
  Future<KitchenTicketList> tickets({
    int? branchId,
    bool includeReady = false,
    int? canceledWithin,
  }) async {
    final Response<Object?> response;
    try {
      response = await _dio.get<Object?>(
        _ticketsPath,
        queryParameters: <String, Object?>{
          'branch_id': ?branchId,
          if (includeReady) 'include_ready': true,
          'canceled_within': ?canceledWithin,
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
          KitchenTicket.fromJson(
            _withRawTicketFields(
              _withRawItemFields(_plain(one.data.single), raw),
              raw,
            ),
            receivedAt: now,
          ),
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
      serverTime: parseHttpDate(response.headers.value('date')),
      receivedAt: now,
    );
  }

  /// Chiptaning spetsifikatsiyada (hali) yo'q maydonlari (P6-14: navbat raqami va olib ketish holati; P6-25: stol tablichkasi) xom javobdan.
  static const List<String> _rawTicketFields = <String>[
    'queue_number',
    'pickup_status',
    // P6-25: kafe stol tablichkasi
    'table_label',
    // P6-33: yig'ish (expo) ko'rinishi
    'assembly',
  ];

  static Map<String, Object?> _withRawTicketFields(
    Map<String, Object?> plain,
    Object? raw,
  ) {
    if (raw is! Map) return plain;
    for (final String key in _rawTicketFields) {
      if (plain[key] == null && raw[key] != null) plain[key] = raw[key];
    }
    return plain;
  }

  /// Qatorlarning spetsifikatsiyada (hali) yo'q maydonlari xom javobdan: yaratilgan model ularni tushirib yuboradi.
  static const List<String> _rawItemFields = <String>[
    'product_id',
    'category_id',
    // P6-21 kurs, P6-25 og'irlik kutilmoqda va mangal vaqti
    'course_no',
    'weight_status',
    'wait_minutes',
    // P6-33: vaqt maqsadi va kechikish; P6-31: kombo konteksti
    'target_seconds',
    'late',
    'combo',
  ];

  static Map<String, Object?> _withRawItemFields(
    Map<String, Object?> plain,
    Object? raw,
  ) {
    final Object? rawItems = raw is Map ? raw['items'] : null;
    final Object? items = plain['items'];
    if (rawItems is! List || items is! List) return plain;
    for (var i = 0; i < items.length && i < rawItems.length; i++) {
      final Object? item = items[i];
      final Object? rawItem = rawItems[i];
      if (item is! Map || rawItem is! Map || rawItem['id'] != item['id']) {
        continue;
      }
      for (final String key in _rawItemFields) {
        if (item[key] == null && rawItem[key] != null) item[key] = rawItem[key];
      }
    }
    return plain;
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

  /// P6-33, yig'ish (expo) "Yig'ildi": `POST /staff/orders/{uuid}/assembled` (`kds_expo`). Olib ketish, yetkazish va
  /// onlayn buyurtma `ready` bo'ladi, navbat raqami chaqiriladi (`queue.ready`); `kitchen.order_assembled` hodisasi
  /// ketadi. Qayta yuborish — 200, o'zgarishsiz. 422: `assembly_not_required`, `stations_not_ready`, `kitchen_status`.
  /// Har amalga yangi `Idempotency-Key`. `null` — [bumpLine] dagidek (amal bajarilgan, javob o'qilmadi).
  Future<KitchenTicket?> assembled(
    String orderUuid, {
    String? idempotencyKey,
  }) => _change(() async {
    final response = await _api.kitchen.kitchenExpoAssembled(
      uuid: orderUuid,
      idempotencyKey: idempotencyKey ?? _newKey(),
    );
    final Map<String, Object?> plain = _plain(response.data);
    return KitchenTicket.fromJson(plain, receivedAt: _clock());
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
      body: const V1StaffOrdersUuidStatusRequestBody(status: Status6.ready),
    );
  });

  /// P6-14, "yig'ish" (expo) tugmasi: `POST /pos/orders/{uuid}/ready` — kassa buyurtmasining navbat raqami tayyor, qolgan
  /// pishayotgan qatorlar tayyor deb belgilanadi, raqam e'lon qilinadi (`queue.ready`). Avval to'langan (yopilgan)
  /// buyurtmada `POST /staff/orders/{uuid}/status` ishlamaydi, shu uchun alohida yo'l. Har amalga yangi `Idempotency-Key`;
  /// 2xx javob o'qilmasa ham amal bajarilgan.
  Future<void> markQueueReady(
    String orderUuid, {
    String? idempotencyKey,
  }) async {
    try {
      await _dio.post<Object?>(
        '/v1/pos/orders/$orderUuid/ready',
        data: const <String, Object?>{},
        options: Options(
          headers: <String, Object?>{
            'Idempotency-Key': idempotencyKey ?? _newKey(),
          },
        ),
      );
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
  }

  static final Random _random = Random.secure();

  static String _newKey() {
    final List<int> b = List<int>.generate(16, (_) => _random.nextInt(256));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    String hex(int from, int to) => <String>[
      for (var i = from; i < to; i++) b[i].toRadixString(16).padLeft(2, '0'),
    ].join();
    return '${hex(0, 4)}-${hex(4, 6)}-${hex(6, 8)}-${hex(8, 10)}-${hex(10, 16)}';
  }

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

const List<String> _months = <String>[
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'May',
  'Jun',
  'Jul',
  'Aug',
  'Sep',
  'Oct',
  'Nov',
  'Dec',
];

/// HTTP `Date` sarlavhasi (RFC 7231 IMF-fixdate: `Thu, 22 Oct 2026 07:00:00 GMT`), UTC. O'qilmasa `null`.
DateTime? parseHttpDate(String? value) {
  final RegExpMatch? m = RegExp(
    r'^\s*[A-Za-z]{3},\s+(\d{1,2})\s+([A-Za-z]{3})\s+(\d{4})\s+(\d{2}):(\d{2}):(\d{2})\s+GMT\s*$',
  ).firstMatch(value ?? '');
  if (m == null) return null;
  final int month = _months.indexOf(m[2]!) + 1;
  if (month == 0) return null;
  return DateTime.utc(
    int.parse(m[3]!),
    month,
    int.parse(m[1]!),
    int.parse(m[4]!),
    int.parse(m[5]!),
    int.parse(m[6]!),
  );
}
