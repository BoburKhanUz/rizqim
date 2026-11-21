import 'package:pos_core/pos_core.dart';
import 'package:pos_sync/pos_sync.dart';

import '../json.dart';
import '../takeaway/takeaway_models.dart';

/// Bootstrap'ning `offline` qismi (P3-31): kassa internetsiz sotish uchun saqlaydigan oddiy narx qoidalari va
/// chegaralar.
class PosOfflineSettings {
  const PosOfflineSettings({
    this.devicePrefix,
    this.nextLocalNumber = 1,
    this.businessDate,
    this.dayType,
    this.fees = const <PosOfflineFee>[],
    this.places = const <PosOfflinePlace>[],
    this.guestItems = const <PosOfflineGuestItem>[],
    this.limits = const OfflineLimits(),
    this.serviceChargeLocked = false,
    this.packaging = const <PosPackagingRule>[],
    this.discount = false,
    this.signing = false,
    this.signingKid,
    this.export = false,
    this.staffResume = false,
  });

  factory PosOfflineSettings.fromJson(PosJson j) => PosOfflineSettings(
    devicePrefix: j.strOrNull('device_prefix'),
    nextLocalNumber: j.intOrNull('next_local_number') ?? 1,
    businessDate: j.strOrNull('business_date'),
    dayType: j.strOrNull('day_type'),
    fees: j.objects('fees', PosOfflineFee.fromJson),
    places: j.objects('places', PosOfflinePlace.fromJson),
    guestItems: j.objects('guest_items', PosOfflineGuestItem.fromJson),
    limits: OfflineLimits.fromJson(j.objOrNull('limits')?.map),
    serviceChargeLocked: j.boolean('service_charge_locked'),
    discount: j.boolean('discount'),
    signing: j.boolean('signing'),
    signingKid: j.intOrNull('signing_kid'),
    export: j.boolean('export'),
    staffResume: j.boolean('staff_resume'),
  );

  /// Bootstrap javobidan (`offline` bo'lmasa — standart qiymatlar). P6-34: qadoq qoidalari `takeaway` qismidan.
  static PosOfflineSettings of(Map<String, Object?> bootstrapRaw) {
    final Object? o = bootstrapRaw['offline'];
    final PosOfflineSettings base = o is Map
        ? PosOfflineSettings.fromJson(PosJson.of(o))
        : const PosOfflineSettings();
    final List<PosPackagingRule> packaging = PosTakeaway.of(bootstrapRaw)
        .packaging;
    return packaging.isEmpty ? base : base.withPackaging(packaging);
  }

  /// P6-34: shu sozlamalar qadoq qoidalari bilan.
  PosOfflineSettings withPackaging(List<PosPackagingRule> rules) =>
      PosOfflineSettings(
        devicePrefix: devicePrefix,
        nextLocalNumber: nextLocalNumber,
        businessDate: businessDate,
        dayType: dayType,
        fees: fees,
        places: places,
        guestItems: guestItems,
        limits: limits,
        serviceChargeLocked: serviceChargeLocked,
        packaging: rules,
        discount: discount,
        signing: signing,
        signingKid: signingKid,
        export: export,
        staffResume: staffResume,
      );

  /// Lokal raqam prefiksi (`K1`); server bermagan bo'lsa `K`.
  final String? devicePrefix;
  final int nextLocalNumber;
  final String? businessDate;
  final String? dayType;
  final List<PosOfflineFee> fees;
  final List<PosOfflinePlace> places;
  final List<PosOfflineGuestItem> guestItems;
  final OfflineLimits limits;

  /// P6-22: stolovaya qulfi (VM 75): xizmat haqi qoidalaridagi har foiz 0 (kuver qoladi).
  final bool serviceChargeLocked;

  /// P6-34: filialning qadoq qoidalari (`packaging` moduli o'chiq — bo'sh; `OfflinePackaging`).
  final List<PosPackagingRule> packaging;

  /// M5 S2: server `order.discount` amalini qabul qiladi (`offline.discount: true`). Eski server aytmaydi: kassa
  /// internetsiz chegirma bermaydi (aks holda butun navbat 422 bilan to'xtab qolardi).
  final bool discount;

  /// M5 S3: the server takes signed operations (`offline.signing: true`). An older server does not say so: the till
  /// queues in the old unsigned shape, even when it holds a key.
  final bool signing;

  /// M5 S3: the number of the key the server holds for this till (`offline.signing_kid`), when the server says it.
  /// Larger than the number of the till's own key: the key was rotated on the server and the till fetches a new one
  /// (`PosSyncKeys`). `null`: not said (nothing is concluded from that).
  final int? signingKid;

  /// M5 S4: the server imports the till's rescue file (`offline.export: true`, only while it signs). Only then does
  /// the till offer "Navbatni faylga saqlash" (`SyncExporter`). An older server does not say so: nothing is offered.
  /// (The restore from the mirror copy does not depend on it: it is read from the folder the copy replaces.)
  final bool export;

  /// M5 S6-a: the server records the mark of operations queued by a staff member resumed without a PIN after an app
  /// restart (`offline.staff_resume: true`; `staff_resumed` in the payload, shown as "PIN'siz davom etilgan"). Only
  /// then does the till offer "Davom etish" (PosSession.resumeOffer). An older server does not say so: nothing is
  /// offered and the mark is never sent.
  final bool staffResume;

  /// Zal va kanal bo'yicha xizmat haqi va joy narxi (zalniki bo'lmasa — filialniki).
  PosOfflineFee? feeFor(int? hallId, String channel) {
    PosOfflineFee? branch;
    for (final PosOfflineFee f in fees) {
      if (f.channel != channel) continue;
      if (hallId != null && f.hallId == hallId) return f;
      if (f.hallId == null) branch = f;
    }
    return branch;
  }

  PosOfflinePlace? place(int tableId) {
    for (final PosOfflinePlace p in places) {
      if (p.tableId == tableId) return p;
    }
    return null;
  }

  /// Zal yoki filialning choy-non qoidalari (zalniki filialnikining o'rnini oladi).
  List<PosOfflineGuestItem> guestItemsFor(int? hallId) {
    final Map<String, PosOfflineGuestItem> byVariant =
        <String, PosOfflineGuestItem>{};
    for (final PosOfflineGuestItem g in guestItems.where(
      (PosOfflineGuestItem g) => g.hallId == null,
    )) {
      byVariant[g.variantUuid] = g;
    }
    if (hallId != null) {
      for (final PosOfflineGuestItem g in guestItems.where(
        (PosOfflineGuestItem g) => g.hallId == hallId,
      )) {
        byVariant[g.variantUuid] = g;
      }
    }
    return byVariant.values.toList()..sort(
      (PosOfflineGuestItem a, PosOfflineGuestItem b) =>
          a.sortOrder.compareTo(b.sortOrder),
    );
  }
}

class PosOfflineFee {
  const PosOfflineFee({
    required this.channel,
    this.hallId,
    this.serviceChargePercent = '0.00',
    this.placeFeeAmount = PosMoney.zero,
    this.placeFeeMode = 'per_person',
    this.rules,
    this.serviceChargeNotice,
  });

  factory PosOfflineFee.fromJson(PosJson j) => PosOfflineFee(
    hallId: j.intOrNull('hall_id'),
    channel: j.str('channel'),
    serviceChargePercent: j.strOrNull('service_charge_percent') ?? '0.00',
    placeFeeAmount: j.money('place_fee_amount'),
    placeFeeMode: j.strOrNull('place_fee_mode') ?? 'per_person',
    rules: j.map['rules'] is Map
        ? PosServiceChargeRules.fromJson(j.obj('rules'))
        : null,
    serviceChargeNotice: j.strOrNull('service_charge_notice'),
  );

  final int? hallId;
  final String channel;
  final String serviceChargePercent;
  final PosMoney placeFeeAmount;
  final String placeFeeMode;

  /// P6-22 (C1): shu qatorning xizmat haqi qoidalari (`service_charge_rules` moduli bilan; yo'q — `null`).
  final PosServiceChargeRules? rules;

  /// Schyotdagi xizmat haqi e'loni (server matni).
  final String? serviceChargeNotice;
}

/// P6-22 (C1): xizmat haqi qoidalari (backend `ServiceChargeRules`): buyurtma turi bo'yicha foiz (`hall`,
/// `table_service`, `takeaway`), katta guruh qoidasi va restoran kuveri (mehmon boshiga).
class PosServiceChargeRules {
  const PosServiceChargeRules({
    this.byOrderType = const <String, String>{},
    this.largeGroupMinGuests,
    this.largeGroupPercent,
    this.coverAmount,
  });

  factory PosServiceChargeRules.fromJson(PosJson j) {
    // PHP sends an empty map as `[]`
    final Object? types = j.map['by_order_type'];
    return PosServiceChargeRules(
      byOrderType: <String, String>{
        if (types is Map)
          for (final MapEntry<Object?, Object?> e in types.entries)
            if (e.value != null) '${e.key}': '${e.value}',
      },
      largeGroupMinGuests: j.intOrNull('large_group_min_guests'),
      largeGroupPercent: j.strOrNull('large_group_percent'),
      coverAmount: j.strOrNull('cover_amount') == null
          ? null
          : j.money('cover_amount'),
    );
  }

  /// Turi → foiz (`"15.00"`; `"0.00"` — olinmaydi). Berilmagan tur qatorning o'z foizini oladi.
  final Map<String, String> byOrderType;
  final int? largeGroupMinGuests;
  final String? largeGroupPercent;
  final PosMoney? coverAmount;

  bool get isEmpty =>
      byOrderType.isEmpty && largeGroupMinGuests == null && coverAmount == null;
}

class PosOfflinePlace {
  const PosOfflinePlace({
    required this.tableId,
    this.hallId,
    this.roomFee = PosMoney.zero,
    this.minSpend = PosMoney.zero,
    this.minSpendMode = 'per_table',
  });

  factory PosOfflinePlace.fromJson(PosJson j) => PosOfflinePlace(
    tableId: j.integer('table_id'),
    hallId: j.intOrNull('hall_id'),
    roomFee: j.money('room_fee'),
    minSpend: j.money('min_spend'),
    minSpendMode: j.strOrNull('min_spend_mode') ?? 'per_table',
  );

  final int tableId;
  final int? hallId;
  final PosMoney roomFee;
  final PosMoney minSpend;
  final String minSpendMode;
}

class PosOfflineGuestItem {
  const PosOfflineGuestItem({
    required this.variantUuid,
    required this.quantityPerGuest,
    this.hallId,
    this.sortOrder = 0,
  });

  factory PosOfflineGuestItem.fromJson(PosJson j) => PosOfflineGuestItem(
    hallId: j.intOrNull('hall_id'),
    variantUuid: j.str('variant_uuid'),
    quantityPerGuest: j.quantity('quantity_per_guest'),
    sortOrder: j.intOrNull('sort_order') ?? 0,
  );

  final int? hallId;
  final String variantUuid;
  final PosQuantity quantityPerGuest;
  final int sortOrder;
}
