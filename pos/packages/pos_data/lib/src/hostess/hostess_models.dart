import 'package:pos_core/pos_core.dart';

import '../bootstrap/bootstrap_models.dart';
import '../json.dart';

/// Kutish ro'yxatidagi yozuv (`/pos/waitlist*`, P6-24): mehmon, necha kishi, hostes aytgan kutish vaqti. Holatlar:
/// `waiting` → `seated` | `canceled` | `no_show`; faqat kutayotgani o'zgaradi.
class PosWaitlistEntry {
  const PosWaitlistEntry({
    required this.uuid,
    required this.guestName,
    required this.partySize,
    required this.status,
    required this.version,
    this.branchId,
    this.businessDate,
    this.guestPhone,
    this.guestPhoneMasked,
    this.quotedWaitMinutes,
    this.note,
    this.statusLabel,
    this.position,
    this.waitedMinutes = 0,
    this.diningTableId,
    this.tableName,
    this.orderUuid,
    this.notifiedAt,
    this.seatedAt,
    this.closedAt,
    this.createdAt,
  });

  factory PosWaitlistEntry.fromJson(PosJson j) => PosWaitlistEntry(
    uuid: j.str('uuid'),
    branchId: j.intOrNull('branch_id'),
    businessDate: j.strOrNull('business_date'),
    guestName: j.str('guest_name'),
    guestPhone: j.strOrNull('guest_phone'),
    guestPhoneMasked: j.strOrNull('guest_phone_masked'),
    partySize: j.intOrNull('party_size') ?? 1,
    quotedWaitMinutes: j.intOrNull('quoted_wait_minutes'),
    note: j.strOrNull('note'),
    status: j.str('status'),
    statusLabel: j.strOrNull('status_label'),
    position: j.intOrNull('position'),
    waitedMinutes: j.intOrNull('waited_minutes') ?? 0,
    diningTableId: j.intOrNull('dining_table_id'),
    tableName: j.strOrNull('table_name'),
    orderUuid: j.strOrNull('order_uuid'),
    notifiedAt: j.dateOrNull('notified_at'),
    seatedAt: j.dateOrNull('seated_at'),
    closedAt: j.dateOrNull('closed_at'),
    createdAt: j.dateOrNull('created_at'),
    version: j.intOrNull('version') ?? 0,
  );

  final String uuid;
  final int? branchId;

  /// Filialning biznes kuni (`YYYY-MM-DD`).
  final String? businessDate;
  final String guestName;

  /// Mehmon raqami (+998XXXXXXXXX): hostes qo'ng'iroq qiladi; egaga ko'rinishida `null`.
  final String? guestPhone;

  /// Yashirilgan raqam (`+99890*****67`), hamma joyda bor.
  final String? guestPhoneMasked;
  final int partySize;

  /// Hostes mehmonga aytgan kutish vaqti (daqiqa).
  final int? quotedWaitMinutes;
  final String? note;
  final String status;
  final String? statusLabel;

  /// Navbatdagi o'rni (1 dan), faqat kutayotganlarda.
  final int? position;

  /// Yozilganidan beri (yopilgan bo'lsa — yopilguncha) necha daqiqa.
  final int waitedMinutes;
  final int? diningTableId;
  final String? tableName;

  /// Joylashtirishda ochilgan buyurtma.
  final String? orderUuid;

  /// Mehmon chaqirilgan vaqt ("stol tayyor").
  final DateTime? notifiedAt;
  final DateTime? seatedAt;
  final DateTime? closedAt;
  final DateTime? createdAt;

  /// O'zgartiruvchi so'rovlarda (`stale_version` — boshqa qurilma o'zgartirgan).
  final int version;

  bool get isWaiting => status == 'waiting';
  bool get isNotified => notifiedAt != null;

  /// Aytilgan vaqtdan oshib ketgan kutish (soniyalarsiz, daqiqa bo'yicha).
  bool get isOverQuoted =>
      isWaiting &&
      quotedWaitMinutes != null &&
      waitedMinutes > quotedWaitMinutes!;
}

/// Kutish ro'yxati xulosasi: holat bo'yicha soni, mehmonlar, o'rtacha kutish.
class PosWaitlistSummary {
  const PosWaitlistSummary({
    this.total = 0,
    this.waiting = 0,
    this.seated = 0,
    this.canceled = 0,
    this.noShow = 0,
    this.partySizeTotal = 0,
    this.avgQuotedWaitMinutes,
    this.avgSeatedWaitMinutes,
    this.longestWaitingMinutes,
  });

  factory PosWaitlistSummary.fromJson(PosJson j) => PosWaitlistSummary(
    total: j.intOrNull('total') ?? 0,
    waiting: j.intOrNull('waiting') ?? 0,
    seated: j.intOrNull('seated') ?? 0,
    canceled: j.intOrNull('canceled') ?? 0,
    noShow: j.intOrNull('no_show') ?? 0,
    partySizeTotal: j.intOrNull('party_size_total') ?? 0,
    avgQuotedWaitMinutes: j.intOrNull('avg_quoted_wait_minutes'),
    avgSeatedWaitMinutes: j.intOrNull('avg_seated_wait_minutes'),
    longestWaitingMinutes: j.intOrNull('longest_waiting_minutes'),
  );

  final int total;
  final int waiting;
  final int seated;
  final int canceled;
  final int noShow;
  final int partySizeTotal;
  final int? avgQuotedWaitMinutes;
  final int? avgSeatedWaitMinutes;
  final int? longestWaitingMinutes;
}

/// `GET /pos/waitlist`: kunning yozuvlari (kelish tartibida) va xulosa.
class PosWaitlist {
  const PosWaitlist({
    required this.entries,
    this.branchId,
    this.date,
    this.summary = const PosWaitlistSummary(),
  });

  factory PosWaitlist.fromJson(PosJson j) => PosWaitlist(
    branchId: j.intOrNull('branch_id'),
    date: j.strOrNull('date'),
    entries: j.objects('entries', PosWaitlistEntry.fromJson),
    summary: j.has('summary')
        ? PosWaitlistSummary.fromJson(j.obj('summary'))
        : const PosWaitlistSummary(),
  );

  final int? branchId;
  final String? date;
  final List<PosWaitlistEntry> entries;
  final PosWaitlistSummary summary;

  List<PosWaitlistEntry> get waiting =>
      entries.where((PosWaitlistEntry e) => e.isWaiting).toList();
}

/// Stolning hostes qo'ygan belgisi javobi (`PUT /pos/tables/{id}/state`).
class PosTableState {
  const PosTableState({
    required this.id,
    required this.name,
    this.serviceState,
    this.serviceStateAt,
  });

  factory PosTableState.fromJson(PosJson j) => PosTableState(
    id: j.integer('id'),
    name: j.str('name'),
    serviceState: j.strOrNull('service_state'),
    serviceStateAt: j.dateOrNull('service_state_at'),
  );

  final int id;
  final String name;

  /// `cleaning` yoki `null` (tayyor).
  final String? serviceState;
  final DateTime? serviceStateAt;

  bool get isCleaning => serviceState == 'cleaning';
}

/// P6-26: filialning stol broni qoidalari (`booking_rules` bootstrap'da va `GET /owner/branches/{id}/booking-rules`).
class PosBookingRules {
  const PosBookingRules({
    this.tableSlotMinutes = 120,
    this.roomSlotMinutes,
    this.tapchanSlotMinutes,
    this.tableMinAdvanceMinutes = 0,
    this.roomMinAdvanceMinutes = 0,
    this.tapchanMinAdvanceMinutes = 0,
    this.maxAdvanceDays,
    this.depositMode = 'off',
    this.depositAmount = PosMoney.zero,
    this.depositFromGuests = 1,
    this.depositRequiredToConfirm = false,
    this.configured = false,
  });

  factory PosBookingRules.fromJson(PosJson j) => PosBookingRules(
    configured: j.boolean('configured'),
    tableSlotMinutes: j.intOrNull('table_slot_minutes') ?? 120,
    roomSlotMinutes: j.intOrNull('room_slot_minutes'),
    tapchanSlotMinutes: j.intOrNull('tapchan_slot_minutes'),
    tableMinAdvanceMinutes: j.intOrNull('table_min_advance_minutes') ?? 0,
    roomMinAdvanceMinutes: j.intOrNull('room_min_advance_minutes') ?? 0,
    tapchanMinAdvanceMinutes: j.intOrNull('tapchan_min_advance_minutes') ?? 0,
    maxAdvanceDays: j.intOrNull('max_advance_days'),
    depositMode: j.strOrNull('deposit_mode') ?? 'off',
    depositAmount: j.money('deposit_amount'),
    depositFromGuests: j.intOrNull('deposit_from_guests') ?? 1,
    depositRequiredToConfirm: j.boolean('deposit_required_to_confirm'),
  );

  final bool configured;

  /// Stol broni necha daqiqa ushlab turadi (tugash vaqti berilmasa).
  final int tableSlotMinutes;

  /// `null` — filialning `booking_slot_minutes` (5 soat).
  final int? roomSlotMinutes;
  final int? tapchanSlotMinutes;

  /// Eng kam oldindan vaqt (daqiqa): xona uchun choyxonada 180 ("kamida 3 soat").
  final int tableMinAdvanceMinutes;
  final int roomMinAdvanceMinutes;
  final int tapchanMinAdvanceMinutes;

  /// Eng ko'p necha kun oldin (`null` — cheklov yo'q).
  final int? maxAdvanceDays;

  /// `off`, `flat`, `per_guest`.
  final String depositMode;
  final PosMoney depositAmount;
  final int depositFromGuests;
  final bool depositRequiredToConfirm;

  /// Tugash vaqti berilmagan bron uchun slot (daqiqa); xona/tapchan — qoida bo'lmasa `null` (filial standarti).
  int? slotMinutesFor(String kind) => switch (kind) {
    'table' => tableSlotMinutes,
    'room' => roomSlotMinutes,
    'tapchan' => tapchanSlotMinutes,
    _ => null,
  };

  int minAdvanceMinutesFor(String kind) => switch (kind) {
    'room' => roomMinAdvanceMinutes,
    'tapchan' => tapchanMinAdvanceMinutes,
    _ => tableMinAdvanceMinutes,
  };

  bool get asksDeposit => depositMode != 'off' && depositAmount.tiyin > 0;
}

/// Bootstrap'ning `booking_rules` qismi: konstruktorga tegmasdan [PosBootstrap.raw] dan o'qiladi.
extension PosBootstrapBookingRules on PosBootstrap {
  /// Filialning stol broni qoidalari; `table_booking` moduli yo'q yoki eski server bo'lsa — `null` (choyxona kabi).
  PosBookingRules? get bookingRules {
    final Object? block = raw['booking_rules'];
    if (block is! Map) return null;
    try {
      return PosBookingRules.fromJson(PosJson.of(block, 'booking_rules'));
    } on FormatException {
      return null;
    }
  }
}
