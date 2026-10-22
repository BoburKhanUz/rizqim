import 'package:pos_core/pos_core.dart';

import '../json.dart';

/// `GET /pos/bootstrap`: terminal ishga tushganda (PIN bilan kirilgandan keyin) kerak bo'ladigan hamma narsa
/// (docs/API.md, "POS sozlamalari (P3-13)").
class PosBootstrap {
  const PosBootstrap({
    required this.serverTime,
    required this.receivedAt,
    required this.menuVersion,
    required this.store,
    required this.branch,
    required this.device,
    required this.user,
    required this.stations,
    required this.stationRoutes,
    required this.printers,
    required this.staff,
    required this.halls,
    required this.tables,
    required this.shift,
    this.raw = const <String, Object?>{},
  });

  factory PosBootstrap.fromJson(PosJson j, {DateTime? receivedAt}) =>
      PosBootstrap(
        serverTime: j.date('server_time'),
        receivedAt: receivedAt ?? DateTime.now(),
        menuVersion: j.str('menu_version'),
        store: PosStoreInfo.fromJson(j.obj('store')),
        branch: PosBranchInfo.fromJson(j.obj('branch')),
        device: j.has('device')
            ? PosDeviceInfo.fromJson(j.obj('device'))
            : null,
        user: PosUserInfo.fromJson(j.obj('user')),
        stations: j.objects('stations', PosStation.fromJson),
        stationRoutes: j.has('station_routes')
            ? PosStationRoutes.fromJson(j.obj('station_routes'))
            : const PosStationRoutes(),
        printers: j.objects('printers', PosPrinterInfo.fromJson),
        staff: j.objects('staff', PosStaffRef.fromJson),
        halls: j.objects('halls', PosHall.fromJson),
        tables: j.objects('tables', PosTable.fromJson),
        shift: j.has('shift') ? PosShiftSummary.fromJson(j.obj('shift')) : null,
        raw: j.map,
      );

  final DateTime serverTime;

  /// Javob qurilmaga kelgan vaqt (qurilma soati bilan).
  final DateTime receivedAt;

  /// Menyu versiyasi: o'zgarsa `GET /catalog?channel=pos_*` qayta olinadi ([PosCatalogRepository]).
  final String menuVersion;
  final PosStoreInfo store;
  final PosBranchInfo branch;

  /// Terminal (terminalsiz kirishda `null`).
  final PosDeviceInfo? device;
  final PosUserInfo user;
  final List<PosStation> stations;
  final PosStationRoutes stationRoutes;

  /// Shu terminal ishlata oladigan faol printerlar.
  final List<PosPrinterInfo> printers;

  /// Filial va do'kon xodimlari (ofitsiant tanlash uchun; PIN yo'q).
  final List<PosStaffRef> staff;
  final List<PosHall> halls;
  final List<PosTable> tables;

  /// Shu terminalda ochiq smena (yo'q — `null`).
  final PosShiftSummary? shift;
  final Map<String, Object?> raw;

  /// Server soati qurilmanikidan qancha oldinda (manfiy — orqada). Vaqt hisoblarida (necha daqiqa ochiq) qo'shiladi.
  Duration get clockSkew => serverTime.difference(receivedAt);

  PosBootstrap withShift(PosShiftSummary? next) => PosBootstrap(
    serverTime: serverTime,
    receivedAt: receivedAt,
    menuVersion: menuVersion,
    store: store,
    branch: branch,
    device: device,
    user: user,
    stations: stations,
    stationRoutes: stationRoutes,
    printers: printers,
    staff: staff,
    halls: halls,
    tables: tables,
    shift: next,
    raw: raw,
  );

  PosStation? station(int? id) {
    for (final PosStation s in stations) {
      if (s.id == id) return s;
    }
    return null;
  }

  List<PosTable> tablesOf(int hallId) =>
      tables.where((PosTable t) => t.hallId == hallId).toList();
}

class PosStoreInfo {
  const PosStoreInfo({
    required this.id,
    required this.name,
    required this.timezone,
  });

  factory PosStoreInfo.fromJson(PosJson j) => PosStoreInfo(
    id: j.integer('id'),
    name: j.str('name'),
    timezone: j.strOrNull('timezone') ?? 'Asia/Tashkent',
  );

  final int id;
  final String name;
  final String timezone;
}

class PosBranchInfo {
  const PosBranchInfo({
    required this.id,
    required this.name,
    this.address,
    this.phone,
    required this.timezone,
    required this.businessDayStartsAt,
    required this.businessDate,
    required this.settings,
  });

  factory PosBranchInfo.fromJson(PosJson j) => PosBranchInfo(
    id: j.integer('id'),
    name: j.str('name'),
    address: j.strOrNull('address'),
    phone: j.strOrNull('phone'),
    timezone: j.strOrNull('timezone') ?? 'Asia/Tashkent',
    businessDayStartsAt: j.strOrNull('business_day_starts_at') ?? '05:00',
    businessDate: j.strOrNull('business_date') ?? '',
    settings: j.has('pos_settings')
        ? PosSettings.fromJson(j.obj('pos_settings'))
        : const PosSettings(),
  );

  final int id;
  final String name;
  final String? address;
  final String? phone;
  final String timezone;

  /// `"05:00"`: shu vaqtgacha bo'lgan savdo avvalgi biznes kunga kiradi.
  final String businessDayStartsAt;

  /// Hozirgi biznes kun, `YYYY-MM-DD`.
  final String businessDate;
  final PosSettings settings;
}

/// Kassa rejimi (`pos_settings.mode`).
enum PosMode {
  counter('counter'),
  table('table'),
  hybrid('hybrid');

  const PosMode(this.wire);

  final String wire;

  static PosMode fromWire(String? value) => PosMode.values.firstWhere(
    (PosMode m) => m.wire == value,
    orElse: () => PosMode.hybrid,
  );
}

/// Filial POS sozlamalari (`branch.pos_settings`).
class PosSettings {
  const PosSettings({
    this.mode = PosMode.hybrid,
    this.printingDeviceId,
    this.screenLockMinutes = 5,
    this.queueNumbers = false,
    this.alerts = const PosAlerts(),
    this.holidays = const <String>[],
    this.bookingSlotMinutes,
  });

  factory PosSettings.fromJson(PosJson j) => PosSettings(
    mode: PosMode.fromWire(j.strOrNull('mode')),
    printingDeviceId: j.strOrNull('printing_device_id'),
    screenLockMinutes: j.intOrNull('screen_lock_minutes') ?? 5,
    queueNumbers: j.boolean('queue_numbers'),
    alerts: j.has('alerts')
        ? PosAlerts.fromJson(j.obj('alerts'))
        : const PosAlerts(),
    holidays: j.strings('holidays'),
    bookingSlotMinutes: j.intOrNull('booking_slot_minutes'),
  );

  final PosMode mode;

  /// "Chop etuvchi" kassaning uuid'si.
  final String? printingDeviceId;

  /// 0 — ekran qulflanmaydi.
  final int screenLockMinutes;
  final bool queueNumbers;
  final PosAlerts alerts;
  final List<String> holidays;
  final int? bookingSlotMinutes;

  /// Ekran qulfi muddati (0 — `null`: qulflanmaydi).
  Duration? get screenLock =>
      screenLockMinutes <= 0 ? null : Duration(minutes: screenLockMinutes);
}

class PosAlerts {
  const PosAlerts({
    this.openRoomHours,
    this.voidAmount,
    this.discountPercent,
    this.unacceptedOnlineMinutes,
  });

  factory PosAlerts.fromJson(PosJson j) => PosAlerts(
    openRoomHours: j.intOrNull('open_room_hours'),
    voidAmount: j.moneyOrNull('void_amount'),
    discountPercent: j.strOrNull('discount_percent'),
    unacceptedOnlineMinutes: j.intOrNull('unaccepted_online_minutes'),
  );

  final int? openRoomHours;
  final PosMoney? voidAmount;
  final String? discountPercent;
  final int? unacceptedOnlineMinutes;
}

class PosDeviceInfo {
  const PosDeviceInfo({
    required this.uuid,
    required this.type,
    this.name,
    this.isPrintingDevice = false,
  });

  factory PosDeviceInfo.fromJson(PosJson j) => PosDeviceInfo(
    uuid: j.str('uuid'),
    type: j.strOrNull('type') ?? 'pos',
    name: j.strOrNull('name'),
    isPrintingDevice: j.boolean('is_printing_device'),
  );

  final String uuid;
  final String type;
  final String? name;

  /// Onlayn buyurtmalar markasini shu kassa chiqaradi.
  final bool isPrintingDevice;
}

class PosUserInfo {
  const PosUserInfo({
    required this.id,
    required this.name,
    required this.role,
    required this.permissions,
  });

  factory PosUserInfo.fromJson(PosJson j) => PosUserInfo(
    id: j.integer('id'),
    name: j.str('name'),
    role: j.str('role'),
    permissions: j.strings('permissions').toSet(),
  );

  final int id;
  final String name;
  final String role;
  final Set<String> permissions;
}

/// Sex (oshxona bo'limi): osh, mangal, salat, choy ...
class PosStation {
  const PosStation({
    required this.id,
    required this.name,
    required this.kind,
    this.kindLabel,
    this.sortOrder = 0,
    this.showOnKds = true,
  });

  factory PosStation.fromJson(PosJson j) => PosStation(
    id: j.integer('id'),
    name: j.str('name'),
    kind: j.strOrNull('kind') ?? 'other',
    kindLabel: j.strOrNull('kind_label'),
    sortOrder: j.intOrNull('sort_order') ?? 0,
    showOnKds: j.boolean('show_on_kds', fallback: true),
  );

  final int id;
  final String name;

  /// `osh`, `mangal`, `salad_bar`, `tea`, `kitchen`, `other`.
  final String kind;
  final String? kindLabel;
  final int sortOrder;
  final bool showOnKds;
}

/// Taom va kategoriyaning sexi: taomning o'zi → kategoriyasi → `null` (standart marka printeri).
class PosStationRoutes {
  const PosStationRoutes({
    this.categories = const <int, int>{},
    this.products = const <int, int>{},
  });

  factory PosStationRoutes.fromJson(PosJson j) => PosStationRoutes(
    categories: <int, int>{
      for (final PosJson r in j.objects('categories', (PosJson x) => x))
        r.integer('category_id'): r.integer('station_id'),
    },
    products: <int, int>{
      for (final PosJson r in j.objects('products', (PosJson x) => x))
        r.integer('product_id'): r.integer('station_id'),
    },
  );

  final Map<int, int> categories;
  final Map<int, int> products;

  /// Oflayn rejimda qatorning sexi (server `KitchenStationRouter` bilan bir xil tartib).
  int? stationFor({required int productId, int? categoryId}) =>
      products[productId] ??
      (categoryId == null ? null : categories[categoryId]);
}

/// Printer (`purpose`: `receipt`, `kitchen`, `label`; `connection`: `lan`, `bluetooth`, `usb`, `builtin`).
class PosPrinterInfo {
  const PosPrinterInfo({
    required this.id,
    required this.name,
    required this.purpose,
    required this.connection,
    this.address,
    this.port,
    this.paperWidth = 80,
    this.stationId,
    this.deviceId,
    this.isActive = true,
    this.encoding,
    this.printAsImage = false,
  });

  factory PosPrinterInfo.fromJson(PosJson j) => PosPrinterInfo(
    id: j.integer('id'),
    name: j.str('name'),
    purpose: j.strOrNull('purpose') ?? 'receipt',
    connection: j.strOrNull('connection') ?? 'lan',
    address: j.strOrNull('address'),
    port: j.intOrNull('port'),
    paperWidth: j.intOrNull('paper_width') ?? 80,
    stationId: j.intOrNull('station_id'),
    deviceId: j.strOrNull('device_id'),
    isActive: j.boolean('is_active', fallback: true),
    encoding: j.strOrNull('encoding'),
    printAsImage: j.boolean('print_as_image'),
  );

  final int id;
  final String name;
  final String purpose;
  final String connection;
  final String? address;
  final int? port;

  /// 58 yoki 80.
  final int paperWidth;
  final int? stationId;

  /// USB va ichki printer ulangan terminal uuid'si.
  final String? deviceId;
  final bool isActive;

  /// Masalan `cp866`; `null` — UTF-8.
  final String? encoding;
  final bool printAsImage;

  bool get isReceipt => purpose == 'receipt';
  bool get isKitchen => purpose == 'kitchen';
  bool get isLabel => purpose == 'label';
}

class PosStaffRef {
  const PosStaffRef({
    required this.id,
    required this.name,
    required this.role,
    this.photoUrl,
  });

  factory PosStaffRef.fromJson(PosJson j) => PosStaffRef(
    id: j.integer('id'),
    name: j.str('name'),
    role: j.str('role'),
    photoUrl: j.strOrNull('photo_url'),
  );

  final int id;
  final String name;
  final String role;
  final String? photoUrl;

  bool get isWaiter => role == 'waiter';
  bool get isManagerOrOwner => role == 'manager' || role == 'owner';
}

class PosHall {
  const PosHall({required this.id, required this.name, this.sortOrder = 0});

  factory PosHall.fromJson(PosJson j) => PosHall(
    id: j.integer('id'),
    name: j.str('name'),
    sortOrder: j.intOrNull('sort_order') ?? 0,
  );

  final int id;
  final String name;
  final int sortOrder;
}

/// Zal xaritasidagi joy o'rni va o'lchami (`layout {x, y, w, h}`; kiritilmagan bo'lsa `null`).
class PosLayout {
  const PosLayout({this.x, this.y, this.w, this.h});

  factory PosLayout.fromJson(PosJson j) => PosLayout(
    x: _num(j['x']),
    y: _num(j['y']),
    w: _num(j['w']),
    h: _num(j['h']),
  );

  static PosLayout? maybe(PosJson j, String key) {
    final PosJson? o = j.objOrNull(key);
    if (o == null) return null;
    final PosLayout l = PosLayout.fromJson(o);
    return l.isEmpty ? null : l;
  }

  final double? x;
  final double? y;
  final double? w;
  final double? h;

  bool get isEmpty => x == null && y == null && w == null && h == null;
}

double? _num(Object? v) =>
    v is num ? v.toDouble() : (v is String ? double.tryParse(v) : null);

/// Bootstrap'dagi joy: stol, xona, tapchan (bandligi — [FloorRepository] da).
class PosTable {
  const PosTable({
    required this.id,
    required this.hallId,
    required this.name,
    required this.kind,
    this.seats,
    this.layout,
    this.sortOrder = 0,
    this.minSpend,
    this.minSpendMode = 'per_table',
  });

  factory PosTable.fromJson(PosJson j) => PosTable(
    id: j.integer('id'),
    hallId: j.integer('hall_id'),
    name: j.str('name'),
    kind: FloorTableKind.fromWire(j.strOrNull('kind')),
    seats: j.intOrNull('seats'),
    layout: PosLayout.maybe(j, 'layout'),
    sortOrder: j.intOrNull('sort_order') ?? 0,
    minSpend: j.moneyOrNull('min_spend'),
    minSpendMode: j.strOrNull('min_spend_mode') ?? 'per_table',
  );

  final int id;
  final int hallId;
  final String name;
  final FloorTableKind kind;
  final int? seats;
  final PosLayout? layout;
  final int sortOrder;
  final PosMoney? minSpend;

  /// `per_table` yoki `per_person`.
  final String minSpendMode;
}

/// Bootstrap'dagi qisqa smena (to'liq smena — [ShiftRepository]).
class PosShiftSummary {
  const PosShiftSummary({
    required this.uuid,
    required this.status,
    this.businessDate,
    this.openedAt,
    this.openedBy,
    this.openingCash = PosMoney.zero,
    this.deviceId,
  });

  factory PosShiftSummary.fromJson(PosJson j) {
    final Object? openedBy = j['opened_by'];
    return PosShiftSummary(
      uuid: j.str('uuid'),
      status: j.strOrNull('status') ?? 'open',
      businessDate: j.strOrNull('business_date'),
      openedAt: j.dateOrNull('opened_at'),
      // bootstrap: id; smena endpointlari: {id, name}
      openedBy: openedBy is Map
          ? PosJson.of(openedBy).intOrNull('id')
          : j.intOrNull('opened_by'),
      openingCash: j.money('opening_cash'),
      deviceId: j['device'] is Map
          ? j.obj('device').strOrNull('uuid')
          : j.strOrNull('device_id'),
    );
  }

  final String uuid;

  /// `open`, `closed`.
  final String status;
  final String? businessDate;
  final DateTime? openedAt;
  final int? openedBy;
  final PosMoney openingCash;

  /// Terminal uuid'si.
  final String? deviceId;

  bool get isOpen => status == 'open';
}
