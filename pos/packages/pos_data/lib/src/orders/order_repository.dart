import 'package:pos_core/pos_core.dart';

import '../catalog/catalog_models.dart';
import '../http/pos_exceptions.dart';
import '../http/pos_http.dart';
import '../json.dart';
import '../offline/pos_offline.dart';
import '../offline/sync_models.dart';
import 'order_models.dart';

/// POS buyurtmalari (docs/API.md, "Zal va xona hisobi (P3-12)").
///
/// Har o'zgartiruvchi metod:
/// * `Idempotency-Key` yuboradi — [idempotencyKey] berilmasa yangisi. Tarmoq xatosidan ([PosNetworkException]) keyin
///   **aynan o'sha** amalni xatodagi `idempotencyKey` bilan qayta chaqiring: server ikki marta bajarmaydi.
/// * `version` — buyurtmaning oxirgi ko'rgan versiyasi ([PosOrder.version]); boshqa qurilma o'zgartirgan bo'lsa
///   [PosVersionConflict] (`currentVersion`): buyurtmani [get] bilan qayta yuklang.
/// * `approvalToken` — menejer tasdig'i ([PosApprovalRequired] dan keyin `ApprovalService.request`), **yangi** kalit
///   bilan (tana o'zgardi).
/// * To'liq [PosOrder] qaytaradi.
///
/// P3-31: [offline] berilgan bo'lsa (kassa) — cheklangan rejimda (aloqa yo'q yoki navbat bo'shamagan) ochish, bosqich,
/// yuborish, o'chirish (yuborilmaganini) va "berildi" navbatga yoziladi va lokal ko'rinish qaytadi; qolgan amallar
/// [PosOfflineUnavailable]. Onlayn javoblar nusxa sifatida saqlanadi (uzilishda ochiq xona hisoblari qurilmada).
class PosOrderRepository {
  PosOrderRepository(this._http, {this.offline});

  final PosHttp _http;
  final PosOffline? offline;

  static PosOrder _order(Object? data) => PosOrder.fromJson(dataObject(data));

  bool get _limited => offline?.active ?? false;

  /// Oflayn ochilgan buyurtmaning server uuid'i (sinxronlangandan keyin), bo'lmasa o'zi.
  String _id(String uuid) => offline?.store.resolve(uuid) ?? uuid;

  /// `GET /pos/orders/{uuid}`.
  Future<PosOrder> get(String uuid) async {
    final PosOffline? o = offline;
    if (o != null && o.active) return o.order(uuid);
    try {
      // An order opened offline is asked for by the device's id until the screen reloads
      final PosOrder order = await _http.get(
        '/v1/pos/orders/${_id(uuid)}',
        _order,
      );
      if (o != null) await o.captureOrder(order.raw);
      return order;
    } on PosNetworkException {
      if (o != null) return o.order(uuid);
      rethrow;
    }
  }

  /// Onlayn amal; tarmoq xatosida (javob kelmadi) — kassa uni navbat orqali davom ettiradi (o'sha kalit bilan:
  /// server so'rovni bajargan bo'lsa ikki marta qo'llamaydi).
  Future<PosOrder> _either(
    Future<PosOrder> Function() online,
    Future<PosOrder> Function(String? key) local, {
    String? idempotencyKey,
  }) async {
    final PosOffline? o = offline;
    if (o != null && o.active) return local(idempotencyKey);
    try {
      final PosOrder order = await online();
      if (o != null) await o.captureOrder(order.raw);
      return order;
    } on PosNetworkException catch (e) {
      if (o != null && o.status.canSell) {
        return local(e.idempotencyKey ?? idempotencyKey);
      }
      rethrow;
    }
  }

  /// Internetsiz bajarilmaydigan amal: cheklangan rejimda sababi bilan rad etiladi.
  void _onlineOnly(String what) {
    if (_limited) {
      throw PosOfflineUnavailable(
        "Internet yo'q: $what faqat internet tiklangach.",
      );
    }
  }

  /// `GET /pos/orders?scope=open`: filialning ochiq buyurtmalari navbati (POS va qabul qilingan onlayn), eskisi
  /// birinchi, ko'pi bilan 300.
  Future<List<PosOpenOrder>> openOrders({int? branchId}) async {
    final PosOffline? o = offline;
    if (o != null && o.active) return o.openOrders();
    Object? raw;
    try {
      final List<PosOpenOrder> list = await _http.get('/v1/pos/orders', (
        Object? data,
      ) {
        raw = data;
        return <PosOpenOrder>[
          for (final Object? e in (data as List<Object?>?) ?? const <Object?>[])
            PosOpenOrder.fromJson(PosJson.of(e)),
        ];
      }, query: <String, Object?>{'scope': 'open', 'branch_id': branchId});
      if (o != null && branchId == null) await o.captureOpenOrders(raw);
      return list;
    } on PosNetworkException {
      if (o != null) return o.openOrders();
      rethrow;
    }
  }

  /// `GET /pos/orders?scope=closed`: shu ish kunida yopilgan (to'langan) POS buyurtmalari, yangisi birinchi —
  /// chek nusxasi va qaytarish uchun (P3-26). [businessDate] (`YYYY-MM-DD`) berilmasa — filialning hozirgi ish kuni.
  Future<List<PosOpenOrder>> closedOrders({
    String? businessDate,
    int? branchId,
  }) => _http.get(
    '/v1/pos/orders',
    (Object? data) => <PosOpenOrder>[
      for (final Object? e in (data as List<Object?>?) ?? const <Object?>[])
        PosOpenOrder.fromJson(PosJson.of(e)),
    ],
    query: <String, Object?>{
      'scope': 'closed',
      'business_date': businessDate,
      'branch_id': branchId,
    },
  );

  /// `POST /pos/orders/{uuid}/request-bill`: ofitsiant kassadan schyot so'raydi (P3-26). Kassaga
  /// `order.bill_requested` boradi, zal xaritasida "Schyot so'raldi". Takroriy so'rov o'zgarishsiz qaytadi.
  Future<PosOrder> requestBill(
    String uuid, {
    int? version,
    String? approvalToken,
    String? idempotencyKey,
  }) async {
    _onlineOnly("schyot so'rash");
    return _post(uuid, 'request-bill', idempotencyKey, <String, Object?>{
      'version': ?version,
      'approval_token': ?approvalToken,
    });
  }

  /// `POST /pos/orders`: buyurtma ochish. Zal — [tableId] (xona, stol, tapchan) yoki stolsiz; olib ketish —
  /// [PosChannel.takeaway]. [items] — birinchi bosqich, [send] — darhol oshxonaga. [externalId] — qurilmaning o'z
  /// raqami (shu raqam bilan qayta yuborilsa o'sha buyurtma qaytadi). P6-13: [queueNumber] — kassa o'z blokidan olgan
  /// navbat raqami (`SyncStore.takeQueueNumber`); server boshqasini bersa javobdagi `queue.number` chop etiladi.
  Future<PosOrder> open({
    required PosChannel channel,
    int? tableId,
    int? guestsCount,
    int? waiterId,
    int? branchId,
    String? externalId,
    String? note,
    List<PosLineInput> items = const <PosLineInput>[],
    bool send = false,
    String? approvalToken,
    String? idempotencyKey,
    int? queueNumber,
    String? tableLabel,
  }) => _either(
    () => _http.mutate(
      'POST',
      '/v1/pos/orders',
      _order,
      idempotencyKey: idempotencyKey,
      body: <String, Object?>{
        'channel': channel.wire,
        'branch_id': ?branchId,
        'table_id': ?tableId,
        'guests_count': ?guestsCount,
        'waiter_id': ?waiterId,
        'external_id': ?externalId,
        'note': ?note,
        'queue_number': ?queueNumber,
        'table_label': ?tableLabel,
        if (items.isNotEmpty)
          'items': <Map<String, Object?>>[
            for (final PosLineInput i in items) i.toJson(),
          ],
        if (send) 'send': true,
        'approval_token': ?approvalToken,
      },
    ),
    (String? key) => offline!.open(
      channel: channel,
      tableId: tableId,
      guestsCount: guestsCount,
      externalId: externalId,
      note: note,
      items: items,
      send: send,
      idempotencyKey: key,
      queueNumber: queueNumber,
      tableLabel: tableLabel,
    ),
    idempotencyKey: idempotencyKey,
  );

  /// `POST /pos/orders/{uuid}/rounds`: yangi bosqich. [fireMode] — qatorlarning standart rejimi, [send] — `now`
  /// qatorlarni darhol oshxonaga.
  Future<PosOrder> addRound(
    String uuid, {
    required int version,
    required List<PosLineInput> items,
    PosFireMode? fireMode,
    bool send = false,
    String? approvalToken,
    String? idempotencyKey,
  }) => _either(
    () => _post(uuid, 'rounds', idempotencyKey, <String, Object?>{
      'version': version,
      'items': <Map<String, Object?>>[
        for (final PosLineInput i in items) i.toJson(),
      ],
      'fire_mode': ?fireMode?.wire,
      if (send) 'send': true,
      'approval_token': ?approvalToken,
    }),
    (String? key) => offline!.addRound(
      uuid,
      items: items,
      fireMode: fireMode,
      send: send,
      idempotencyKey: key,
    ),
    idempotencyKey: idempotencyKey,
  );

  /// `POST /pos/orders/{uuid}/send`: [itemIds] siz — yuborilmagan hamma `now` qator; bilan — aynan shular (`hold`
  /// ham). Yangi qator yo'q — [PosRejected] `nothing_to_send`.
  Future<PosOrder> send(
    String uuid, {
    required int version,
    List<int>? itemIds,
    String? approvalToken,
    String? idempotencyKey,
  }) => _either(
    () => _post(uuid, 'send', idempotencyKey, <String, Object?>{
      'version': version,
      'item_ids': ?itemIds,
      'approval_token': ?approvalToken,
    }),
    (String? key) => offline!.send(uuid, itemIds: itemIds, idempotencyKey: key),
    idempotencyKey: idempotencyKey,
  );

  /// P6-21 `POST /pos/orders/{uuid}/courses/{n}/send`: "N-kursni yuborish" — kursning ushlab turilgan qatorlari
  /// oshxonaga. Yuboriladigan taom yo'q — [PosRejected] `nothing_to_send`. Internetsiz ham (navbat orqali).
  Future<PosOrder> sendCourse(
    String uuid,
    int courseNo, {
    required int version,
    String? approvalToken,
    String? idempotencyKey,
  }) => _either(
    () => _post(
      uuid,
      'courses/$courseNo/send',
      idempotencyKey,
      <String, Object?>{'version': version, 'approval_token': ?approvalToken},
    ),
    (String? key) => offline!.sendCourse(uuid, courseNo, idempotencyKey: key),
    idempotencyKey: idempotencyKey,
  );

  /// P6-25 `POST /pos/orders/{uuid}/items/{id}/weight`: "og'irlikni keyin kiritish" qatorining haqiqiy og'irligi
  /// (narx shunga qarab). Tortilgan qatorni qayta tortish — `orders.void_item` yoki menejer tasdig'i
  /// ([PosApprovalRequired]). Internetsiz ham birinchi og'irlik (navbat orqali).
  Future<PosOrder> setWeight(
    String uuid,
    int itemId, {
    required int version,
    required PosQuantity quantity,
    String? approvalToken,
    String? idempotencyKey,
  }) => _either(
    () => _post(uuid, 'items/$itemId/weight', idempotencyKey, <String, Object?>{
      'version': version,
      'quantity': quantity.toApiString(),
      'approval_token': ?approvalToken,
    }),
    (String? key) => offline!.setWeight(
      uuid,
      itemId,
      quantity: quantity,
      idempotencyKey: key,
    ),
    idempotencyKey: idempotencyKey,
  );

  /// P6-25 `POST /pos/orders/{uuid}/table-label` (`table_service` moduli): kassada to'langan buyurtmaning stol
  /// tablichkasi raqami; `null` — olib tashlash.
  Future<PosOrder> setTableLabel(
    String uuid, {
    required int version,
    required String? tableLabel,
    String? approvalToken,
    String? idempotencyKey,
  }) async {
    _onlineOnly('stol raqamini yozish');
    return _post(uuid, 'table-label', idempotencyKey, <String, Object?>{
      'version': version,
      'table_label': tableLabel,
      'approval_token': ?approvalToken,
    });
  }

  /// `POST /pos/orders/{uuid}/items/{id}/void`. Yuborilgan qator yoki schyot chiqqan buyurtmada [reason] majburiy va
  /// `orders.void_item` yoki tasdiq kerak. [restock] — yuborilgan qatorning qoldig'ini qaytarish (pishmagan bo'lsa).
  Future<PosOrder> voidItem(
    String uuid,
    int itemId, {
    required int version,
    String? reason,
    bool? restock,
    String? approvalToken,
    String? idempotencyKey,
  }) => _either(
    () => _post(uuid, 'items/$itemId/void', idempotencyKey, <String, Object?>{
      'version': version,
      'reason': ?reason,
      'restock': ?restock,
      'approval_token': ?approvalToken,
    }),
    (String? key) =>
        offline!.voidItem(uuid, itemId, reason: reason, idempotencyKey: key),
    idempotencyKey: idempotencyKey,
  );

  /// `POST /pos/orders/{uuid}/items/{id}/served`: taom mehmonga berildi.
  Future<PosOrder> markServed(
    String uuid,
    int itemId, {
    required int version,
    String? approvalToken,
    String? idempotencyKey,
  }) => _either(
    () => _post(uuid, 'items/$itemId/served', idempotencyKey, <String, Object?>{
      'version': version,
      'approval_token': ?approvalToken,
    }),
    (String? key) => offline!.markServed(uuid, itemId, idempotencyKey: key),
    idempotencyKey: idempotencyKey,
  );

  /// `POST /pos/orders/{uuid}/guests`: mehmonlar soni (choy-non qatorlari server tomonidan qo'shiladi).
  Future<PosOrder> setGuests(
    String uuid, {
    required int version,
    required int guestsCount,
    String? approvalToken,
    String? idempotencyKey,
  }) async {
    _onlineOnly("mehmonlar sonini o'zgartirish");
    return _post(uuid, 'guests', idempotencyKey, <String, Object?>{
      'version': version,
      'guests_count': guestsCount,
      'approval_token': ?approvalToken,
    });
  }

  /// `POST /pos/orders/{uuid}/move`: boshqa stol yoki xonaga.
  Future<PosOrder> move(
    String uuid, {
    required int version,
    required int tableId,
    String? approvalToken,
    String? idempotencyKey,
  }) async {
    _onlineOnly("ko'chirish");
    return _post(uuid, 'move', idempotencyKey, <String, Object?>{
      'version': version,
      'table_id': tableId,
      'approval_token': ?approvalToken,
    });
  }

  /// `POST /pos/orders/{uuid}/merge`: [sourceUuid] buyurtmasini shu buyurtmaga qo'shish (manba bekor bo'ladi).
  Future<PosOrder> merge(
    String uuid, {
    required int version,
    required String sourceUuid,
    required int sourceVersion,
    String? approvalToken,
    String? idempotencyKey,
  }) async {
    _onlineOnly("birlashtirish");
    return _post(uuid, 'merge', idempotencyKey, <String, Object?>{
      'version': version,
      'source_uuid': sourceUuid,
      'source_version': sourceVersion,
      'approval_token': ?approvalToken,
    });
  }

  /// `POST /pos/orders/{uuid}/cancel`: butun buyurtmani bekor qilish ([reason] majburiy). To'lov bo'lsa —
  /// [PosConflict] `has_payments` (`data.paid_amount`).
  Future<PosOrder> cancel(
    String uuid, {
    required int version,
    required String reason,
    String? approvalToken,
    String? idempotencyKey,
  }) async {
    _onlineOnly("bekor qilish");
    return _post(uuid, 'cancel', idempotencyKey, <String, Object?>{
      'version': version,
      'reason': reason,
      'approval_token': ?approvalToken,
    });
  }

  /// `POST /pos/orders/{uuid}/discount` (P3-37): buyurtmaga chegirma. [kind] — foiz yoki summa, [value] — API
  /// satri (`"10"`, `"50000.00"`); `"0"` chegirmani olib tashlaydi ([reason] shunda shart emas, aks holda majburiy).
  ///
  /// Kassir o'zi filial chegarasigacha (`pos_settings.cashier_discount_max_percent`) beradi; undan ko'pi —
  /// [PosApprovalRequired] (`orders.discount` menejer tasdig'i). To'lov olingan schyot bo'lsa — [PosConflict]
  /// `paid_bill_affected`. Internetsiz rejimda — [PosOfflineUnavailable] (navbatga yozilmaydi).
  Future<PosOrder> discount(
    String uuid, {
    required int version,
    required PosDiscountKind kind,
    required String value,
    String? reason,
    String? approvalToken,
    String? idempotencyKey,
  }) async {
    if (_limited) {
      throw const PosOfflineUnavailable(
        'Chegirma uchun internet kerak: aloqa tiklangach bering.',
      );
    }
    final String? why = reason?.trim();
    return _post(uuid, 'discount', idempotencyKey, <String, Object?>{
      'version': version,
      'kind': kind.wire,
      'value': value,
      if (why != null && why.isNotEmpty) 'reason': why,
      'approval_token': ?approvalToken,
    });
  }

  /// `POST /pos/orders/{uuid}/charges/{charge}/waive`: xizmat qatorini sabab bilan olib tashlash.
  Future<PosOrder> waiveCharge(
    String uuid,
    PosChargeKind charge, {
    required int version,
    required String reason,
    String? approvalToken,
    String? idempotencyKey,
  }) async {
    _onlineOnly("xizmat qatorini olib tashlash");
    return _post(
      uuid,
      'charges/${charge.wire}/waive',
      idempotencyKey,
      <String, Object?>{
        'version': version,
        'reason': reason,
        'approval_token': ?approvalToken,
      },
    );
  }

  /// `POST /pos/orders/{uuid}/charges/{charge}/restore`: olib tashlangan qatorni qaytarish.
  Future<PosOrder> restoreCharge(
    String uuid,
    PosChargeKind charge, {
    required int version,
    String? approvalToken,
    String? idempotencyKey,
  }) async {
    _onlineOnly("xizmat qatorini qaytarish");
    return _post(
      uuid,
      'charges/${charge.wire}/restore',
      idempotencyKey,
      <String, Object?>{'version': version, 'approval_token': ?approvalToken},
    );
  }

  Future<PosOrder> _post(
    String uuid,
    String action,
    String? idempotencyKey,
    Map<String, Object?> body,
  ) => _http.mutate(
    'POST',
    '/v1/pos/orders/${_id(uuid)}/$action',
    _order,
    body: body,
    idempotencyKey: idempotencyKey,
  );
}
