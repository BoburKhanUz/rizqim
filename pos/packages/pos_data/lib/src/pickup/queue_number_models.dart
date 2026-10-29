import 'package:pos_core/pos_core.dart';
import 'package:pos_sync/pos_sync.dart';

import '../billing/billing_models.dart';
import '../bootstrap/bootstrap_models.dart';
import '../json.dart';
import '../orders/order_models.dart';

/// P6-13: navbat raqami olib ketish navbatida qayerda (`orders.pickup_status`, docs/POS_SPEC.md 2.14).
enum PosPickupStatus {
  /// Oshxonada: mijoz ekranining "Tayyorlanmoqda" ustuni.
  preparing('preparing', 'Tayyorlanmoqda'),

  /// Tayyor: "Tayyor" ustuni, raqam chaqiriladi.
  ready('ready', 'Tayyor'),

  /// Mijoz oldi.
  handed('handed', 'Berildi');

  const PosPickupStatus(this.wire, this.label);

  final String wire;
  final String label;

  static PosPickupStatus? fromWire(String? v) {
    for (final PosPickupStatus s in values) {
      if (s.wire == v) return s;
    }
    return null;
  }
}

/// Buyurtmaning navbat raqami va olib ketish holati (`GET /pos/orders/{uuid}` va ro'yxatlarda `queue`).
class PosOrderQueue {
  const PosOrderQueue({
    required this.number,
    this.businessDate,
    this.status,
    this.readyAt,
    this.calledAt,
    this.callCount = 0,
    this.handedAt,
  });

  factory PosOrderQueue.fromJson(PosJson j) => PosOrderQueue(
    number: j.integer('number'),
    businessDate: j.strOrNull('business_date'),
    status: PosPickupStatus.fromWire(j.strOrNull('status')),
    readyAt: j.dateOrNull('ready_at'),
    calledAt: j.dateOrNull('called_at'),
    callCount: j.intOrNull('call_count') ?? 0,
    handedAt: j.dateOrNull('handed_at'),
  );

  static PosOrderQueue? maybe(Object? raw) => raw is Map
      ? PosOrderQueue.fromJson(PosJson(raw.cast<String, Object?>()))
      : null;

  final int number;
  final String? businessDate;

  /// `null`: raqam bor, lekin olib ketish navbatida emas (choyxona xonasi, `queue_calling` o'chiq).
  final PosPickupStatus? status;
  final DateTime? readyAt;
  final DateTime? calledAt;
  final int callCount;
  final DateTime? handedAt;
}

/// Chek bilan chiqadigan talon (`bill.ticket`, `order.ticket`): raqam, "Olib ketish" / "Zalda" va matn.
class PosQueueTicket {
  const PosQueueTicket({
    required this.number,
    required this.orderLabel,
    required this.message,
    this.businessDate,
  });

  factory PosQueueTicket.fromJson(PosJson j) => PosQueueTicket(
    number: j.integer('number'),
    businessDate: j.strOrNull('business_date'),
    orderLabel: j.str('order_label'),
    message: j.str('message'),
  );

  static PosQueueTicket? maybe(Object? raw) => raw is Map
      ? PosQueueTicket.fromJson(PosJson(raw.cast<String, Object?>()))
      : null;

  final int number;
  final String? businessDate;
  final String orderLabel;
  final String message;

  /// `pos_core` talon hujjati (chop etish shabloni uchun).
  QueueSlip slip({
    required ReceiptHeader header,
    required DateTime createdAt,
    String? orderCode,
    List<String> items = const <String>[],
  }) => QueueSlip(
    header: header,
    number: '$number',
    createdAt: createdAt,
    orderCode: orderCode,
    orderLabel: orderLabel,
    items: items,
    message: message,
  );
}

/// Kassaning bugungi raqam bloklari (`GET /pos/bootstrap` `queue`, `POST /pos/queue/blocks`).
class PosQueueState {
  const PosQueueState({
    this.numbers = false,
    this.calling = false,
    this.businessDate,
    this.blockSize = 100,
    this.lowWater = 20,
    this.blocks = const <QueueRange>[],
    this.nextNumber,
  });

  factory PosQueueState.fromJson(PosJson j) => PosQueueState(
    numbers: j.boolean('numbers'),
    calling: j.boolean('calling'),
    businessDate: j.strOrNull('business_date'),
    blockSize: j.intOrNull('block_size') ?? 100,
    lowWater: j.intOrNull('low_water') ?? 20,
    blocks: <QueueRange>[
      for (final Object? e in j.list('blocks'))
        if (e is Map) QueueRange.fromJson(e.cast<String, Object?>()),
    ],
    nextNumber: j.intOrNull('next_number'),
  );

  /// Filial kassa buyurtmalarini blokdan raqamlaydi (`queue_numbers` moduli).
  final bool numbers;

  /// Tayyor raqam chaqiriladi (`queue_calling` moduli).
  final bool calling;
  final String? businessDate;
  final int blockSize;
  final int lowWater;
  final List<QueueRange> blocks;
  final int? nextNumber;

  /// Aloqa borida yangi blok so'rash vaqti keldi ([store] dagi oxirgi ishlatilgan raqam bilan).
  bool runningLow(SyncStore store) =>
      numbers &&
      businessDate != null &&
      store.queueNumbersLeft(businessDate!, blocks) < lowWater;
}

/// Raqamlar taxtasidagi bitta raqam (`GET /pos/queue`).
class PosQueueEntry {
  const PosQueueEntry({
    required this.orderUuid,
    required this.number,
    required this.channel,
    required this.orderStatus,
    this.businessDate,
    this.status,
    this.readyAt,
    this.calledAt,
    this.callCount = 0,
    this.handedAt,
    this.openedAt,
  });

  factory PosQueueEntry.fromJson(PosJson j) => PosQueueEntry(
    orderUuid: j.str('order_uuid'),
    number: j.integer('number'),
    businessDate: j.strOrNull('business_date'),
    status: PosPickupStatus.fromWire(j.strOrNull('status')),
    channel: j.str('channel'),
    orderStatus: j.str('order_status'),
    readyAt: j.dateOrNull('ready_at'),
    calledAt: j.dateOrNull('called_at'),
    callCount: j.intOrNull('call_count') ?? 0,
    handedAt: j.dateOrNull('handed_at'),
    openedAt: j.dateOrNull('opened_at'),
  );

  final String orderUuid;
  final int number;
  final String? businessDate;
  final PosPickupStatus? status;

  /// `pos_takeaway`, `pos_hall`, `telegram`, `mobile`.
  final String channel;
  final String orderStatus;
  final DateTime? readyAt;
  final DateTime? calledAt;
  final int callCount;
  final DateTime? handedAt;
  final DateTime? openedAt;
}

/// `GET /pos/queue`: bugungi "Tayyorlanmoqda / Tayyor" raqamlari.
class PosQueueBoard {
  const PosQueueBoard({
    required this.businessDate,
    this.preparing = const <PosQueueEntry>[],
    this.ready = const <PosQueueEntry>[],
  });

  factory PosQueueBoard.fromJson(PosJson j) => PosQueueBoard(
    businessDate: j.str('business_date'),
    preparing: j.objects('preparing', PosQueueEntry.fromJson),
    ready: j.objects('ready', PosQueueEntry.fromJson),
  );

  final String businessDate;
  final List<PosQueueEntry> preparing;
  final List<PosQueueEntry> ready;
}

/// P6-13 maydonlari mavjud modellarda (javobning `raw` qismidan o'qiladi).
extension PosOrderQueueX on PosOrder {
  /// Navbat raqami va olib ketish holati; raqamsiz buyurtmada `null`.
  PosOrderQueue? get queue => PosOrderQueue.maybe(raw['queue']);
}

extension PosBillTicketX on PosBill {
  /// Chek bilan chiqadigan talon; `queue_numbers` moduli yo'q filialda yoki stol buyurtmasida `null`.
  PosQueueTicket? get ticket => PosQueueTicket.maybe(raw['ticket']);
}

extension PosBootstrapQueueX on PosBootstrap {
  /// Kassaning bugungi raqam bloklari (eski server — bo'sh, raqamlash o'chiq).
  PosQueueState get queue {
    final Object? q = raw['queue'];
    return q is Map
        ? PosQueueState.fromJson(PosJson(q.cast<String, Object?>()))
        : const PosQueueState();
  }
}
