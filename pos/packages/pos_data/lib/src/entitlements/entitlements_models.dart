import 'package:pos_core/pos_core.dart';

import '../json.dart';

/// Tarifga qarab yopiladigan funksiyalar (`App\Enums\PlanFeature` ning yopiq qismi, docs/BILLING.md "Funksiyalar va
/// cheklovlar"). Asosiylari (zal, xona puli, choy-non, smena, RIZQIM buyurtmalari) hech qachon yopilmaydi va bu yerda yo'q.
enum PosFeature {
  bookings('bookings', 'Bron'),
  deposits('deposits', "Bo'nak (avans)"),
  customerAccounts('customer_accounts', 'Nasiya'),
  kds('kds', 'Oshxona ekrani (KDS)'),
  serviceCharge('service_charge', 'Xizmat haqi'),
  multiBranchReports('multi_branch_reports', 'Filiallar hisoboti'),
  aggregatorOrders('aggregator_orders', 'Yandex va Uzum buyurtmalari'),
  banquet('banquet', 'Banket');

  const PosFeature(this.wire, this.label);

  /// Serverdagi kalit (`entitlements.features.<wire>`).
  final String wire;

  /// Ekrandagi nomi.
  final String label;

  static PosFeature? fromWire(String? wire) {
    for (final PosFeature f in PosFeature.values) {
      if (f.wire == wire) return f;
    }
    return null;
  }
}

/// Obuna holati (`entitlements.status`).
enum PosBillingStatus {
  none('none'),
  trialing('trialing'),
  active('active'),
  pastDue('past_due'),

  /// To'lanmagan hisob-faktura muddatidan 7 kun o'tdi: yangi sozlamalar va tarifdan ortiq imkoniyatlar yopiq.
  /// **Kassa to'lov, chek, smena va qaytarishda davom etadi.**
  locked('locked');

  const PosBillingStatus(this.wire);

  final String wire;

  static PosBillingStatus of(String? wire) {
    for (final PosBillingStatus s in PosBillingStatus.values) {
      if (s.wire == wire) return s;
    }
    return PosBillingStatus.none;
  }
}

/// Bir turdagi qurilmalar: tarifga kirgani, hozir faol, tarifdan ortiq va ortiqcha narxi.
class PosDeviceEntitlement {
  const PosDeviceEntitlement({
    this.included,
    this.active = 0,
    this.extra = 0,
    this.extraPrice,
    this.canAdd = true,
    this.unlimited = false,
  });

  factory PosDeviceEntitlement.fromJson(PosJson j) => PosDeviceEntitlement(
    included: j.intOrNull('included'),
    active: j.intOrNull('active') ?? 0,
    extra: j.intOrNull('extra') ?? 0,
    extraPrice: j.moneyOrNull('extra_price'),
    canAdd: j.boolean('can_add', fallback: true),
    unlimited: j.boolean('unlimited'),
  );

  /// Tarifga kirgan soni (`null` — cheklov yo'q).
  final int? included;
  final int active;

  /// Tarifdan ortiq faol qurilmalar (qo'shimcha narxi bilan hisoblanadi).
  final int extra;
  final PosMoney? extraPrice;

  /// Yangi qurilma ulash mumkinmi (KDS tarifda yo'q yoki cheklangan obunada limitga yetgan — `false`).
  final bool canAdd;

  /// Ofitsiant telefonlari: cheksiz va bepul.
  final bool unlimited;
}

/// To'lanmagan hisob-faktura haqidagi eslatma yoki cheklov xabari (`entitlements.notice`).
class PosBillingNotice {
  const PosBillingNotice({
    required this.locked,
    required this.message,
    this.daysOverdue = 0,
    this.lockAt,
    this.invoiceNumber,
    this.outstanding,
  });

  factory PosBillingNotice.fromJson(PosJson j) {
    final PosJson? invoice = j.objOrNull('invoice');
    return PosBillingNotice(
      locked: j.strOrNull('level') == 'locked',
      message: j.strOrNull('message') ?? '',
      daysOverdue: j.intOrNull('days_overdue') ?? 0,
      lockAt: j.dateOrNull('lock_at'),
      invoiceNumber: invoice?.strOrNull('number'),
      outstanding: invoice?.moneyOrNull('outstanding'),
    );
  }

  /// `true` — sozlamalar yopilgan; `false` — eslatma (muddati o'tdi, [lockAt] da yopiladi).
  final bool locked;

  /// Serverning oddiy so'zlar bilan yozgan matni (o'zbekcha).
  final String message;
  final int daysOverdue;
  final DateTime? lockAt;
  final String? invoiceNumber;
  final PosMoney? outstanding;

  /// Sozlamalar yopilishiga necha kun (butun, yuqoriga; 0 — bugun); sana yo'q yoki allaqachon yopilgan — `null`.
  int? daysUntilLock(DateTime now) {
    final DateTime? at = lockAt;
    if (locked || at == null) return null;
    final Duration left = at.difference(now);
    if (left.isNegative) return 0;
    return (left.inMinutes / (24 * 60)).ceil();
  }

  /// Lentaning sarlavhasi, oddiy so'zlar bilan: "To'lov muddati o'tdi — 4 kundan keyin sozlamalar yopiladi" yoki
  /// "Sozlamalar yopiq — to'lov qiling".
  String title(DateTime now) {
    if (locked) return "Sozlamalar yopiq — to'lov qiling";
    final int? days = daysUntilLock(now);
    if (days == null) {
      return "To'lov muddati o'tdi — sozlamalar yopilmasidan oldin to'lang";
    }
    if (days <= 0) return "To'lov muddati o'tdi — bugun sozlamalar yopiladi";
    return "To'lov muddati o'tdi — $days kundan keyin sozlamalar yopiladi";
  }

  /// Sarlavha ostidagi tushuntirish: qaysi hisob-faktura, qancha qoldi, kassa nima qilishda davom etadi va kim to'laydi.
  String get text {
    final String invoice = invoiceNumber == null
        ? ''
        : '$invoiceNumber hisob-faktura${outstanding == null ? '' : ', qoldiq ${outstanding!.format()}'}. ';
    return locked
        ? "${invoice}To'lov qilinishi bilan darhol ochiladi. Kassa to'lov qabul qilish, chek chiqarish va smenada davom etadi."
        : "${invoice}Kassa ishlashda davom etadi. To'lovni ega hamkor panelida («Tarif va to'lov») qiladi.";
  }
}

/// Do'kon tarifi nimaga yo'l qo'yadi (P4-21): `GET /pos/bootstrap` dagi `entitlements`
/// (= `GET /owner/billing/entitlements`). Terminal faqat **yashiradi va tushuntiradi**: hal qilish backend'da (yopiq
/// yozuv 403 `plan_feature_missing` bilan qaytadi).
///
/// **Hech qachon cheklanmaydi:** to'lov qabul qilish, schyot yopish, chek (fiskal ham), smena, qaytarish, oflayn
/// sinxron. Bular uchun bu sinf so'ralmaydi (ilovada tekshiruv yo'q).
///
/// Noma'lum bo'lsa (eski server, bootstrap yo'q) — hammasi ochiq ([open]).
class PosEntitlements {
  const PosEntitlements({
    this.planCode,
    this.planName,
    this.legacy = false,
    this.status = PosBillingStatus.none,
    this.period,
    this.trialEndsAt,
    this.lockAt,
    this.settingsLocked = false,
    this.features = const <String, bool>{},
    this.requiredPlans = const <String, String?>{},
    this.pos = const PosDeviceEntitlement(),
    this.kds = const PosDeviceEntitlement(),
    this.waiter = const PosDeviceEntitlement(unlimited: true),
    this.notice,
  });

  factory PosEntitlements.fromJson(PosJson j) {
    final PosJson? plan = j.objOrNull('plan');
    final PosJson? devices = j.objOrNull('devices');
    final PosJson? features = j.objOrNull('features');
    final PosJson? required = j.objOrNull('required_plans');
    PosDeviceEntitlement device(String key, PosDeviceEntitlement fallback) {
      final PosJson? d = devices?.objOrNull(key);
      return d == null ? fallback : PosDeviceEntitlement.fromJson(d);
    }

    return PosEntitlements(
      planCode: plan?.strOrNull('code'),
      planName: plan?.strOrNull('name'),
      legacy: j.boolean('legacy'),
      status: PosBillingStatus.of(j.strOrNull('status')),
      period: j.strOrNull('period'),
      trialEndsAt: j.dateOrNull('trial_ends_at'),
      lockAt: j.dateOrNull('lock_at'),
      settingsLocked: j.boolean('settings_locked'),
      features: <String, bool>{
        if (features != null)
          for (final MapEntry<String, Object?> e in features.map.entries)
            if (e.value is bool) e.key: e.value! as bool,
      },
      requiredPlans: <String, String?>{
        if (required != null)
          for (final MapEntry<String, Object?> e in required.map.entries)
            e.key: e.value is String ? e.value as String : null,
      },
      pos: device('pos', const PosDeviceEntitlement()),
      kds: device('kds', const PosDeviceEntitlement()),
      waiter: device('waiter', const PosDeviceEntitlement(unlimited: true)),
      notice: j.has('notice')
          ? PosBillingNotice.fromJson(j.obj('notice'))
          : null,
    );
  }

  /// Tarif noma'lum: hamma funksiya ochiq, eslatma yo'q.
  static const PosEntitlements open = PosEntitlements(legacy: true);

  final String? planCode;
  final String? planName;

  /// Do'kon eski (P1-23) tarifda yoki tarifi yo'q: hamma funksiya ochiq.
  final bool legacy;
  final PosBillingStatus status;

  /// `monthly` | `yearly`.
  final String? period;
  final DateTime? trialEndsAt;

  /// To'lanmagan hisob-faktura sozlamalarni qachon yopadi (yopilgan bo'lsa — qachon yopilgan).
  final DateTime? lockAt;

  /// Yangi sozlamalar yopiq (kassa ishlaydi).
  final bool settingsLocked;

  /// `entitlements.features`: serverning "yoqilgan/o'chirilgan" ro'yxati.
  final Map<String, bool> features;

  /// Funksiyaga eng arzon tarifning kodi (`asosiy`, `pro`).
  final Map<String, String?> requiredPlans;
  final PosDeviceEntitlement pos;
  final PosDeviceEntitlement kds;
  final PosDeviceEntitlement waiter;

  /// To'lanmagan hisob-faktura haqidagi xabar (yo'q — `null`).
  final PosBillingNotice? notice;

  /// Funksiya tarifda bormi. Server aytmagan funksiya ochiq hisoblanadi: ekran faqat serverning aniq "yo'q" iga
  /// yopiladi, yozuvni baribir backend tekshiradi.
  bool has(PosFeature feature) => legacy || features[feature.wire] != false;

  /// Funksiya bor tarifning nomi ("Asosiy"); hech bir tarifda yo'q yoki noma'lum — `null`.
  String? planNameFor(PosFeature feature) {
    final String? code = requiredPlans[feature.wire];
    if (code == null || code.isEmpty) return null;
    return code[0].toUpperCase() + code.substring(1);
  }

  /// Qulf yorlig'i: "Asosiy tarifda bor" yoki "Tarifingizda yo'q".
  String lockLabel(PosFeature feature) {
    final String? plan = planNameFor(feature);
    return plan == null ? "Tarifingizda yo'q" : '$plan tarifda bor';
  }

  /// Yopiq funksiya tugmasi ostidagi tushuntirish.
  String lockReason(PosFeature feature) {
    final String? plan = planNameFor(feature);
    return plan == null
        ? "${feature.label} hozirgi tarifingizda yo'q."
        : '${feature.label} $plan tarifda bor. Hozirgi tarifingizda yo\'q.';
  }

  /// Sinov tugashiga necha kun (butun, yuqoriga); sinovda emas — `null`.
  int? trialDaysLeft(DateTime now) {
    final DateTime? ends = trialEndsAt;
    if (status != PosBillingStatus.trialing || ends == null) return null;
    final Duration left = ends.difference(now);
    if (left.isNegative) return 0;
    return (left.inMinutes / (24 * 60)).ceil();
  }
}
