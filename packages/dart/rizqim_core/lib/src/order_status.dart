import 'order_type.dart';

/// Holatning ko'rinish guruhi (docs/DESIGN.md, "Holat ranglari"). Rang guruhga qarab, ikonka va yorliq
/// holatga qarab tanlanadi: rang yolg'iz belgi emas.
enum StatusTone { warning, info, success, danger, neutral }

/// `App\Enums\OrderStatus` bilan bir xil to'plam. `delivery` — `on_the_way` ning eski nomi, ko'rinishi bir xil.
enum OrderStatus {
  newOrder('new', StatusTone.warning, 'Kutilmoqda', 'Yangi'),
  accepted('accepted', StatusTone.info, 'Qabul qilindi', 'Qabul qilindi'),
  preparing('preparing', StatusTone.info, 'Tayyorlanmoqda', 'Tayyorlanmoqda'),
  ready('ready', StatusTone.info, 'Tayyor', 'Tayyor'),
  courierAssigned(
    'courier_assigned',
    StatusTone.info,
    'Kuryer biriktirildi',
    'Kuryer kutilmoqda',
  ),
  onTheWay('on_the_way', StatusTone.info, "Yo'lda", "Yo'lda"),
  delivery('delivery', StatusTone.info, "Yo'lda", "Yo'lda"),
  delivered('delivered', StatusTone.success, 'Yetkazildi', 'Yetkazildi'),
  canceled('canceled', StatusTone.danger, 'Bekor qilindi', 'Bekor qilindi'),
  rejected('rejected', StatusTone.danger, 'Rad etildi', 'Rad etildi'),
  refunded('refunded', StatusTone.neutral, 'Qaytarildi', 'Qaytarildi');

  const OrderStatus(this.wire, this.tone, this.label, this.staffLabel);

  /// API'dagi qiymat.
  final String wire;

  final StatusTone tone;

  /// Mijoz o'qiydigan yorliq (Mini App: `status_*`).
  final String label;

  /// Xodim o'qiydigan yorliq (Mini App: `staffStatus_*`), masalan "Yangi".
  final String staffLabel;

  /// API qiymatidan. Noma'lum holat (backend yangisini qo'shgan) `null` — chaqiruvchi [StatusTone.neutral] va
  /// xom nomni ko'rsatadi, boshqa holatning ko'rinishini emas.
  static OrderStatus? fromWire(String? value) {
    for (final OrderStatus s in OrderStatus.values) {
      if (s.wire == value) return s;
    }
    return null;
  }

  /// [fromWire] bo'lsa uning guruhi, bo'lmasa neytral.
  static StatusTone toneOf(String? wire) =>
      fromWire(wire)?.tone ?? StatusTone.neutral;

  /// Bekor qilingan yoki rad etilgan.
  bool get isCancellation => this == canceled || this == rejected;

  /// Bu holatdan keyingi o'zgarish yo'q (bekor, rad, qaytarilgan).
  bool get isFinal => this == canceled || this == rejected || this == refunded;

  /// Kuryer bilan (yoki kuryer kutilmoqda): `App\Enums\OrderStatus::withCourier()`.
  bool get isWithCourier =>
      this == courierAssigned || this == onTheWay || this == delivery;

  /// Oshxona ekrani (KDS) ko'rsatadigan, hali tayyor bo'lmagan holatlar.
  bool get isInKitchen => this == accepted || this == preparing;

  /// Shu holatdan o'tish mumkin bo'lgan holatlar: `App\Enums\OrderStatus::allowedTransitions()` bilan bir xil.
  /// Kim o'zgartira olishini backend (`OrderPolicy`) hal qiladi.
  List<OrderStatus> allowedTransitions(OrderType? type) {
    final bool byCourier = type == OrderType.delivery;
    switch (this) {
      case newOrder:
        return const <OrderStatus>[accepted, rejected, canceled];
      case accepted:
        return const <OrderStatus>[preparing, canceled];
      case preparing:
        return byCourier
            ? const <OrderStatus>[
                ready,
                courierAssigned,
                onTheWay,
                delivery,
                canceled,
              ]
            : const <OrderStatus>[ready, delivered, canceled];
      case ready:
        return byCourier
            ? const <OrderStatus>[courierAssigned, onTheWay, delivery, canceled]
            : const <OrderStatus>[delivered, canceled];
      case courierAssigned:
        return const <OrderStatus>[onTheWay, delivery, canceled];
      case onTheWay:
      case delivery:
        return const <OrderStatus>[delivered, canceled];
      case delivered:
        return const <OrderStatus>[refunded];
      case canceled:
      case rejected:
      case refunded:
        return const <OrderStatus>[];
    }
  }

  bool canTransitionTo(OrderStatus to, OrderType? type) =>
      allowedTransitions(type).contains(to);
}
