import 'dart:async';

import 'package:flutter/foundation.dart';

/// P4-21: oshxona ekrani tarifdan nimani biladi. `GET /owner/billing/entitlements` (xodim sessiyasi bilan; real
/// backend'da `GET /pos/bootstrap` dagi `entitlements` bilan bir xil): tarifda KDS bormi va to'lanmagan hisob-faktura
/// xabari. Ekran **hech qachon to'xtamaydi**: doska, "tayyor", qaytarish tarifga bog'lanmagan; bu faqat xabar.
///
/// Noma'lum bo'lsa (eski server, aloqa yo'q, o'qib bo'lmadi) — hammasi ochiq, xabar yo'q: oxirgi ma'lum holat qoladi.
class KdsPlan {
  const KdsPlan({
    this.planName,
    this.kdsIncluded = true,
    this.kdsPlanName,
    this.notice,
  });

  /// Javobdan ([data]: `data` qismi); o'qib bo'lmasa [open].
  factory KdsPlan.fromJson(Object? data) {
    if (data is! Map) return open;
    final Map<String, Object?> j = data.cast<String, Object?>();
    final Object? features = j['features'];
    final Object? required = j['required_plans'];
    final Object? plan = j['plan'];
    final bool legacy = j['legacy'] == true;
    final String? kdsCode = required is Map && required['kds'] is String
        ? required['kds']! as String
        : null;
    final Object? noticeJson = j['notice'];
    return KdsPlan(
      planName: plan is Map && plan['name'] is String
          ? plan['name']! as String
          : null,
      kdsIncluded: legacy || !(features is Map && features['kds'] == false),
      kdsPlanName: kdsCode == null || kdsCode.isEmpty
          ? null
          : kdsCode[0].toUpperCase() + kdsCode.substring(1),
      notice: noticeJson is Map
          ? KdsPlanNotice.fromJson(noticeJson.cast<String, Object?>())
          : null,
    );
  }

  /// Tarif noma'lum: KDS ochiq, xabar yo'q.
  static const KdsPlan open = KdsPlan();

  final String? planName;

  /// Tarifda oshxona ekrani (KDS) bormi.
  final bool kdsIncluded;

  /// KDS bor eng arzon tarif ("Asosiy").
  final String? kdsPlanName;
  final KdsPlanNotice? notice;

  /// "Oshxona ekrani (KDS) Asosiy tarifda bor" yoki "... tarifingizda yo'q".
  String get kdsLockLabel => kdsPlanName == null
      ? "Oshxona ekrani (KDS) tarifingizda yo'q"
      : 'Oshxona ekrani (KDS) $kdsPlanName tarifda bor';
}

/// To'lanmagan hisob-faktura xabari ([KdsPlan.notice]).
class KdsPlanNotice {
  const KdsPlanNotice({
    required this.locked,
    this.lockAt,
    this.invoiceNumber,
    this.outstanding,
  });

  factory KdsPlanNotice.fromJson(Map<String, Object?> j) {
    final Object? invoice = j['invoice'];
    return KdsPlanNotice(
      locked: j['level'] == 'locked',
      lockAt: DateTime.tryParse('${j['lock_at'] ?? ''}'),
      invoiceNumber: invoice is Map && invoice['number'] is String
          ? invoice['number']! as String
          : null,
      outstanding: invoice is Map && invoice['outstanding'] != null
          ? '${invoice['outstanding']}'
          : null,
    );
  }

  final bool locked;
  final DateTime? lockAt;
  final String? invoiceNumber;

  /// Qoldiq, `"249000.00"`.
  final String? outstanding;

  /// Sozlamalar yopilishiga necha kun (yuqoriga; 0 — bugun); sana yo'q yoki yopilgan — `null`.
  int? daysUntilLock(DateTime now) {
    final DateTime? at = lockAt;
    if (locked || at == null) return null;
    final Duration left = at.difference(now);
    if (left.isNegative) return 0;
    return (left.inMinutes / (24 * 60)).ceil();
  }

  /// Sarlavha, oddiy so'zlar bilan.
  String title(DateTime now) {
    if (locked) return "Sozlamalar yopiq — to'lov qiling";
    final int? days = daysUntilLock(now);
    if (days == null) {
      return "To'lov muddati o'tdi — sozlamalar yopilmasidan oldin to'lang";
    }
    if (days <= 0) return "To'lov muddati o'tdi — bugun sozlamalar yopiladi";
    return "To'lov muddati o'tdi — $days kundan keyin sozlamalar yopiladi";
  }

  /// Tushuntirish: oshxona ekrani ishlashda davom etadi, to'lovni ega qiladi.
  String get text {
    final String number = invoiceNumber == null
        ? ''
        : '$invoiceNumber hisob-faktura. ';
    return locked
        ? "${number}Oshxona ekrani ishlashda davom etadi. To'lov qilinishi bilan yangi sozlamalar darhol ochiladi."
        : "${number}Oshxona ekrani ishlashda davom etadi. To'lovni ega hamkor panelida («Tarif va to'lov») qiladi.";
  }
}

/// Tarifni javob qismi ([KdsPlanFetcher]: `data` obyekti) sifatida o'qiydi va vaqti-vaqti bilan yangilaydi (ega to'lov
/// qilsa xabar o'zi yo'qoladi). Har qanday xatoda (aloqa yo'q, eski server) oxirgi ma'lum holat qoladi; hech qachon
/// doskani to'xtatmaydi.
typedef KdsPlanFetcher = Future<Object?> Function();

class KdsPlanController extends ChangeNotifier {
  KdsPlanController(this._fetch, {this.every = const Duration(minutes: 10)});

  final KdsPlanFetcher _fetch;
  final Duration every;
  KdsPlan _plan = KdsPlan.open;
  Timer? _timer;
  bool _disposed = false;

  KdsPlan get plan => _plan;

  /// Hozir va keyin har [every] da o'qiydi.
  void start() {
    unawaited(refresh());
    _timer ??= Timer.periodic(every, (_) => unawaited(refresh()));
  }

  /// Doska yopildi (xodim chiqdi, terminal uzildi): yangilash to'xtaydi, oxirgi ma'lum holat qoladi.
  void stop() {
    _timer?.cancel();
    _timer = null;
  }

  Future<void> refresh() async {
    try {
      final Object? data = await _fetch();
      if (_disposed || data is! Map) return;
      _plan = KdsPlan.fromJson(data);
      notifyListeners();
    } on Object {
      // Aloqa yo'q yoki endpoint yo'q (eski server): oxirgi ma'lum holat qoladi
    }
  }

  @override
  void dispose() {
    _disposed = true;
    _timer?.cancel();
    _timer = null;
    super.dispose();
  }
}
