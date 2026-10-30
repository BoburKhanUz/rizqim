import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';

/// "Mening xonalarim" ro'yxati filtri.
enum RoomsFilter {
  mine('Mening'),
  all('Hammasi'),
  free("Bo'sh");

  const RoomsFilter(this.label);

  final String label;
}

/// Ro'yxatdagi bitta qator: ochiq buyurtma ([order]) yoki bo'sh joy ([order] `null`). Stolsiz buyurtmada [table]
/// `null`.
class PlaceEntry {
  const PlaceEntry({required this.hall, required this.table, this.order});

  final FloorHall? hall;
  final FloorTable? table;
  final FloorOrder? order;

  bool get isFree => order == null;

  /// Kartochka sarlavhasi: "2-xona" yoki "Stolsiz buyurtma".
  String get title => table?.name ?? 'Stolsiz buyurtma';

  FloorTableKind get kind => table?.kind ?? FloorTableKind.table;

  /// Holat belgisi: schyot chiqqan, uzoq ochiq, band yoki bo'sh (faol bo'lmagan joy bu ro'yxatga kirmaydi).
  FloorTableStatus get status {
    final FloorOrder? o = order;
    if (o == null) {
      return table?.status == FloorTableStatus.booked
          ? FloorTableStatus.booked
          : FloorTableStatus.free;
    }
    if (o.billPrinted) return FloorTableStatus.billPrinted;
    if (o.overdue) return FloorTableStatus.overdue;
    return FloorTableStatus.occupied;
  }
}

/// Ofitsiantning bosh ekrani ma'lumoti: zal xaritasi (`GET /pos/floor`). Har buyurtmaning tayyor (`ready_count`) va
/// yuborilmagan (`unsent_count`) qatorlari xaritaning o'zida (P3-26): buyurtmalar alohida so'ralmaydi.
///
/// Yangilanish: real-time hodisa ([PosRefreshScheduler], jonli bo'lmasa polling), aloqa yo'q paytda har
/// [retryInterval] da qayta urinish. Xato bo'lsa oxirgi ma'lumot ko'rinib turadi (faqat ko'rish rejimi).
class RoomsController extends ChangeNotifier {
  RoomsController({
    required this.data,
    required this.staffId,
    this.realtime,
    this.pollInterval = const Duration(seconds: 15),
    this.livePollInterval = const Duration(seconds: 60),
    this.retryInterval = const Duration(seconds: 5),
    DateTime Function()? clock,
  }) : _clock = clock ?? DateTime.now;

  final PosData data;

  /// Kirgan ofitsiant (`waiter_id` shu bo'lgan buyurtmalar "Mening").
  final int staffId;
  final PosRealtime? realtime;
  final Duration pollInterval;
  final Duration livePollInterval;
  final Duration retryInterval;
  final DateTime Function() _clock;

  PosFloor? floor;
  DateTime? loadedAt;
  Object? error;
  bool loading = false;
  RoomsFilter filter = RoomsFilter.mine;

  PosRefreshScheduler? _scheduler;
  Timer? _retry;
  bool _disposed = false;

  bool get hasData => floor != null;

  void start() {
    _scheduler = PosRefreshScheduler(
      realtime: realtime,
      pollInterval: pollInterval,
      livePollInterval: livePollInterval,
      eventFilter: (PosEvent e) =>
          e is PosOrderEvent ||
          e is PosOrderItemsChanged ||
          e is PosOrderBillChanged ||
          e is PosOrderBillRequested ||
          e is PosKitchenItemChanged ||
          e is PosBookingChanged ||
          // P6-24/P6-26: a table handed to another waiter, a table marked as being cleaned
          e is PosOrderTransferred ||
          e is PosTableStateChanged,
      onRefresh: (PosRefreshReason _) => load(),
    )..start();
    unawaited(load());
  }

  /// Hozir qayta yuklash ("tortib yangilash", "Qayta urinish").
  Future<void> refresh() =>
      _scheduler == null ? load() : _scheduler!.refreshNow();

  Future<void> load() async {
    if (_disposed) return;
    loading = true;
    _notify();
    try {
      final PosFloor next = await data.floor.load();
      floor = next;
      loadedAt = _clock();
      error = null;
      _retry?.cancel();
      _retry = null;
    } on Object catch (e) {
      error = e;
      if (e is PosNetworkException) {
        _retry ??= Timer(retryInterval, () {
          _retry = null;
          unawaited(load());
        });
      }
    } finally {
      loading = false;
      _notify();
    }
  }

  /// Shu buyurtmada berilishi kutilayotgan (tayyor) taomlar soni (zal xaritasidagi `ready_count`).
  int readyCount(String orderUuid) {
    for (final FloorOrder o in floor?.orders ?? const <FloorOrder>[]) {
      if (o.uuid == orderUuid) return o.readyCount;
    }
    return 0;
  }

  /// Buyurtma qancha vaqtdan beri ochiq: serverdagi daqiqalar + yuklangandan beri o'tgan vaqt.
  Duration openFor(FloorOrder o) {
    final DateTime? at = loadedAt;
    return Duration(minutes: o.openMinutes) +
        (at == null ? Duration.zero : _clock().difference(at));
  }

  /// Hamma qatorlar (zallar tartibida; stolsiz buyurtmalar oxirida).
  List<PlaceEntry> get allEntries {
    final PosFloor? f = floor;
    if (f == null) return const <PlaceEntry>[];
    final List<FloorHall> halls = <FloorHall>[...f.halls]
      ..sort((FloorHall a, FloorHall b) => a.sortOrder.compareTo(b.sortOrder));
    return <PlaceEntry>[
      for (final FloorHall h in halls)
        for (final FloorTable t in h.tables) ...<PlaceEntry>[
          if (t.orders.isEmpty && t.isActive && h.isActive)
            PlaceEntry(hall: h, table: t),
          for (final FloorOrder o in t.orders)
            PlaceEntry(hall: h, table: t, order: o),
        ],
      for (final FloorOrder o in f.untabledOrders)
        PlaceEntry(hall: null, table: null, order: o),
    ];
  }

  List<PlaceEntry> entries(RoomsFilter which) => switch (which) {
    RoomsFilter.all => allEntries,
    RoomsFilter.mine =>
      allEntries
          .where((PlaceEntry e) => e.order?.waiter?.id == staffId)
          .toList(),
    RoomsFilter.free => allEntries.where((PlaceEntry e) => e.isFree).toList(),
  };

  int count(RoomsFilter which) => entries(which).length;

  /// Berilishi kutilayotgan taomlari bor o'z buyurtmalari soni (tab yonida belgi).
  int get readyOrders =>
      entries(RoomsFilter.mine)
          .where((PlaceEntry e) => readyCount(e.order!.uuid) > 0)
          .length;

  void setFilter(RoomsFilter next) {
    if (filter == next) return;
    filter = next;
    _notify();
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _retry?.cancel();
    final PosRefreshScheduler? s = _scheduler;
    if (s != null) unawaited(s.dispose());
    super.dispose();
  }
}
