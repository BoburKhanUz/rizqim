import 'package:pos_sync/pos_sync.dart';
import 'package:test/test.dart';

void main() {
  final DateTime now = DateTime.utc(2026, 10, 22, 18);
  const OfflineLimits limits = OfflineLimits();

  OfflineStatus status({
    bool online = false,
    int queueSize = 1,
    int queuedOrders = 1,
    Duration offlineFor = Duration.zero,
    Duration menuAge = const Duration(hours: 1),
    Duration? saleAge,
  }) => OfflineStatus.evaluate(
    now: now,
    limits: limits,
    online: online,
    queueSize: queueSize,
    queuedOrders: queuedOrders,
    errorsCount: 0,
    lastContactAt: now.subtract(offlineFor),
    oldestPendingAt: now.subtract(offlineFor),
    oldestUnsyncedSaleAt: saleAge == null ? null : now.subtract(saleAge),
    menuTakenAt: now.subtract(menuAge),
  );

  test('15 minutes offline is yellow, 2 hours red', () {
    expect(
      status(offlineFor: const Duration(minutes: 14)).level,
      OfflineLevel.none,
    );
    expect(
      status(offlineFor: const Duration(minutes: 15)).level,
      OfflineLevel.warning,
    );
    expect(
      status(offlineFor: const Duration(minutes: 119)).level,
      OfflineLevel.warning,
    );
    expect(
      status(offlineFor: const Duration(hours: 2)).level,
      OfflineLevel.alert,
    );
  });

  test('online with an empty queue is the normal mode', () {
    final OfflineStatus s = status(
      online: true,
      queueSize: 0,
      queuedOrders: 0,
      offlineFor: const Duration(hours: 5),
    );
    expect(s.active, isFalse);
    expect(s.level, OfflineLevel.none);
    expect(s.canCloseShift, isTrue);
  });

  test('back online but not drained yet: still limited, Z still closed', () {
    final OfflineStatus s = status(online: true, queueSize: 3);
    expect(s.active, isTrue);
    expect(s.canCloseShift, isFalse);
  });

  test('500 queued orders block new ones', () {
    expect(status(queuedOrders: 499).newOrdersBlocked, isFalse);
    expect(status(queuedOrders: 500).newOrdersBlocked, isTrue);
  });

  test('selling offline needs contact and a menu from the last 24 hours', () {
    expect(status().canSell, isTrue);
    final OfflineStatus stale = status(menuAge: const Duration(hours: 25));
    expect(stale.canSell, isFalse);
    expect(stale.blockReason, contains('menyu'));
    final OfflineStatus noContact = status(
      offlineFor: const Duration(hours: 25),
    );
    expect(noContact.canSell, isFalse);
    expect(noContact.blockReason, contains('24 soat'));
  });

  test('the oldest unsynced sale warns at 24 and 40 hours, well before the 48-hour limit', () {
    expect(status().fiscalLevel, FiscalQueueLevel.none);
    expect(
      status(saleAge: const Duration(hours: 23)).fiscalLevel,
      FiscalQueueLevel.none,
    );
    expect(
      status(saleAge: const Duration(hours: 24)).fiscalLevel,
      FiscalQueueLevel.notice,
    );
    expect(
      status(saleAge: const Duration(hours: 40)).fiscalLevel,
      FiscalQueueLevel.urgent,
    );
    expect(
      status(saleAge: const Duration(hours: 48)).fiscalLevel,
      FiscalQueueLevel.limit,
    );
  });

  test('limits come from the bootstrap', () {
    final OfflineLimits l = OfflineLimits.fromJson(<String, Object?>{
      'batch_size': 20,
      'warn_after_minutes': 10,
      'alert_after_minutes': 60,
      'max_queued_orders': 100,
      'fiscal_warn_hours': <int>[40, 20],
      'fiscal_limit_hours': 48,
    });
    expect(l.batchSize, 20);
    expect(l.warnAfter, const Duration(minutes: 10));
    expect(l.alertAfter, const Duration(hours: 1));
    expect(l.maxQueuedOrders, 100);
    expect(l.fiscalNoticeAfter, const Duration(hours: 20));
    expect(l.fiscalUrgentAfter, const Duration(hours: 40));
  });
}
