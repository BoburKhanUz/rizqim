/// Cheklangan rejim chegaralari (docs/POS_SPEC.md 5.4). Server bootstrap'ining `offline.limits` qismidan
/// ([OfflineLimits.fromJson]); bo'lmasa — standart qiymatlar.
class OfflineLimits {
  const OfflineLimits({
    this.batchSize = 50,
    this.warnAfter = const Duration(minutes: 15),
    this.alertAfter = const Duration(hours: 2),
    this.maxQueuedOrders = 500,
    this.menuMaxAge = const Duration(hours: 24),
    this.contactMaxAge = const Duration(hours: 24),
    this.fiscalNoticeAfter = const Duration(hours: 24),
    this.fiscalUrgentAfter = const Duration(hours: 40),
    this.fiscalLimit = const Duration(hours: 48),
    this.clockSkewWarn = const Duration(minutes: 5),
  });

  factory OfflineLimits.fromJson(Map<String, Object?>? j) {
    if (j == null) return const OfflineLimits();
    int n(String key, int fallback) => (j[key] as num?)?.toInt() ?? fallback;
    final List<int> fiscal = <int>[
      for (final Object? v
          in (j['fiscal_warn_hours'] as List<Object?>?) ?? const <Object?>[])
        if (v is num) v.toInt(),
    ]..sort();
    return OfflineLimits(
      batchSize: n('batch_size', 50),
      warnAfter: Duration(minutes: n('warn_after_minutes', 15)),
      alertAfter: Duration(minutes: n('alert_after_minutes', 120)),
      maxQueuedOrders: n('max_queued_orders', 500),
      menuMaxAge: Duration(hours: n('menu_max_age_hours', 24)),
      contactMaxAge: Duration(hours: n('contact_max_age_hours', 24)),
      fiscalNoticeAfter: Duration(hours: fiscal.isNotEmpty ? fiscal.first : 24),
      fiscalUrgentAfter: Duration(hours: fiscal.length > 1 ? fiscal[1] : 40),
      fiscalLimit: Duration(hours: n('fiscal_limit_hours', 48)),
      clockSkewWarn: Duration(seconds: n('clock_skew_warn_seconds', 300)),
    );
  }

  final int batchSize;

  /// Shuncha vaqt aloqa bo'lmasa — sariq belgi.
  final Duration warnAfter;

  /// Shuncha vaqt — qizil, menejerga eslatma.
  final Duration alertAfter;

  /// Navbatda shuncha oflayn buyurtma bo'lsa yangi buyurtma bloklanadi.
  final int maxQueuedOrders;

  /// Menyu nusxasi shundan eski bo'lsa oflayn sotuv boshlanmaydi.
  final Duration menuMaxAge;

  /// Terminal server bilan shundan oldin bog'langan bo'lsa oflayn sotuv boshlanmaydi.
  final Duration contactMaxAge;

  /// Serverga yetmagan eng eski to'lovning yoshi: ogohlantirish va qizil ogohlantirish (virtual kassa 48 soatda
  /// bloklanadi, 4.6).
  final Duration fiscalNoticeAfter;
  final Duration fiscalUrgentAfter;
  final Duration fiscalLimit;
  final Duration clockSkewWarn;
}

/// Aloqasizlik darajasi: yo'q, sariq (15 daqiqa), qizil (2 soat).
enum OfflineLevel { none, warning, alert }

/// Fiskal chek navbati: yo'q, ogohlantirish (24 soat), qizil (40 soat), chegara (48 soat).
enum FiscalQueueLevel { none, notice, urgent, limit }

/// UI uchun holat: aloqa, navbat, yoshi, chegaralar va nima mumkin emasligi (sababi bilan).
class OfflineStatus {
  const OfflineStatus({
    required this.online,
    required this.active,
    required this.queueSize,
    required this.queuedOrders,
    required this.level,
    required this.fiscalLevel,
    required this.canSell,
    required this.newOrdersBlocked,
    required this.errorsCount,
    this.offlineSince,
    this.oldestPendingAt,
    this.oldestUnsyncedSaleAt,
    this.lastContactAt,
    this.menuTakenAt,
    this.blockReason,
    this.syncing = false,
    this.clockWarning = false,
    this.lastSyncError,
  });

  /// Holatni hisoblaydi. [online] — server javob beryaptimi; [active] — cheklangan rejim (aloqa yo'q yoki navbat
  /// hali bo'shamagan).
  factory OfflineStatus.evaluate({
    required DateTime now,
    required OfflineLimits limits,
    required bool online,
    required int queueSize,
    required int queuedOrders,
    required int errorsCount,
    DateTime? oldestPendingAt,
    DateTime? oldestUnsyncedSaleAt,
    DateTime? lastContactAt,
    DateTime? menuTakenAt,
    bool syncing = false,
    bool clockWarning = false,
    String? lastSyncError,
  }) {
    final bool active = !online || queueSize > 0;
    final DateTime? since = online
        ? oldestPendingAt
        : (lastContactAt ?? oldestPendingAt);
    final Duration age = since == null ? Duration.zero : now.difference(since);
    final OfflineLevel level = !active
        ? OfflineLevel.none
        : age >= limits.alertAfter
        ? OfflineLevel.alert
        : age >= limits.warnAfter
        ? OfflineLevel.warning
        : OfflineLevel.none;

    final Duration saleAge = oldestUnsyncedSaleAt == null
        ? Duration.zero
        : now.difference(oldestUnsyncedSaleAt);
    final FiscalQueueLevel fiscal = oldestUnsyncedSaleAt == null
        ? FiscalQueueLevel.none
        : saleAge >= limits.fiscalLimit
        ? FiscalQueueLevel.limit
        : saleAge >= limits.fiscalUrgentAfter
        ? FiscalQueueLevel.urgent
        : saleAge >= limits.fiscalNoticeAfter
        ? FiscalQueueLevel.notice
        : FiscalQueueLevel.none;

    String? reason;
    if (active && !online) {
      if (lastContactAt == null ||
          now.difference(lastContactAt) > limits.contactMaxAge) {
        reason = "Internet kerak: kassa so'nggi 24 soatda server bilan bog'lanmagan.";
      } else if (menuTakenAt == null ||
          now.difference(menuTakenAt) > limits.menuMaxAge) {
        reason = "Internet kerak: menyu nusxasi 24 soatdan eski.";
      }
    }
    final bool blocked = queuedOrders >= limits.maxQueuedOrders;

    return OfflineStatus(
      online: online,
      active: active,
      queueSize: queueSize,
      queuedOrders: queuedOrders,
      level: level,
      fiscalLevel: fiscal,
      canSell: reason == null,
      newOrdersBlocked: blocked,
      errorsCount: errorsCount,
      offlineSince: online ? null : lastContactAt,
      oldestPendingAt: oldestPendingAt,
      oldestUnsyncedSaleAt: oldestUnsyncedSaleAt,
      lastContactAt: lastContactAt,
      menuTakenAt: menuTakenAt,
      blockReason: reason,
      syncing: syncing,
      clockWarning: clockWarning,
      lastSyncError: lastSyncError,
    );
  }

  static const OfflineStatus initial = OfflineStatus(
    online: true,
    active: false,
    queueSize: 0,
    queuedOrders: 0,
    level: OfflineLevel.none,
    fiscalLevel: FiscalQueueLevel.none,
    canSell: true,
    newOrdersBlocked: false,
    errorsCount: 0,
  );

  final bool online;

  /// Cheklangan rejim: aloqa yo'q yoki navbat hali serverga yetib bormagan.
  final bool active;
  final int queueSize;
  final int queuedOrders;
  final OfflineLevel level;
  final FiscalQueueLevel fiscalLevel;

  /// Oflayn sotish mumkin (aks holda [blockReason]).
  final bool canSell;
  final String? blockReason;

  /// Navbatda 500 ta oflayn buyurtma: yangisi ochilmaydi.
  final bool newOrdersBlocked;
  final int errorsCount;

  /// Aloqa qachondan beri yo'q (oxirgi javob).
  final DateTime? offlineSince;
  final DateTime? oldestPendingAt;
  final DateTime? oldestUnsyncedSaleAt;
  final DateTime? lastContactAt;
  final DateTime? menuTakenAt;

  /// Navbat hozir yuborilmoqda.
  final bool syncing;

  /// Qurilma soati serverdan 5 daqiqadan ko'p farq qiladi.
  final bool clockWarning;
  final String? lastSyncError;

  /// Smenani yopish va Z-hisobot: navbat bo'sh bo'lishi kerak.
  bool get canCloseShift => queueSize == 0 && online;
}
