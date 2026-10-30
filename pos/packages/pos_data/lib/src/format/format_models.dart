import '../bootstrap/bootstrap_models.dart';
import '../json.dart';

/// P6-11: filial formati yoqadigan yoki o'chiradigan modul (`App\Domain\Pos\Setup\PosModule`, docs/POS_SPEC.md
/// "Format profili"). Kassa, ofitsiant, KDS va mijoz ekrani ekran va tugmalarni faqat [PosModules] ga qarab
/// ko'rsatadi; backend ham har so'rovda shu ro'yxatni tekshiradi (403 `module_disabled`, [PosModuleDisabled]).
///
/// To'lov, schyot, chek, smena va qaytarish hech qachon modulga bog'liq emas.
enum PosModule {
  quickCounter('quick_counter', 'Tez kassa'),
  phoneMode('phone_mode', 'Telefon rejimi'),
  queueNumbers('queue_numbers', 'Buyurtma raqami'),
  queueCalling('queue_calling', 'Raqamni chaqirish'),
  customerDisplay('customer_display', 'Mijoz ekrani'),
  dailyMenu('daily_menu', 'Bugungi menyu'),
  comboMeal('combo_meal', 'Kompleks'),
  batches('batches', 'Partiya va qozon'),
  batchWriteoff('batch_writeoff', 'Hisobdan chiqarish'),
  terminalCardFlow('terminal_card_flow', 'Bank terminali oqimi'),
  rooms('rooms', 'Xonalar va tapchanlar'),
  roomFee('room_fee', 'Xona puli'),
  guestItems('guest_items', 'Choy-non'),
  bookings('bookings', 'Bron va oldindan buyurtma'),
  customerAccounts('customer_accounts', 'Nasiya'),
  serviceCharge('service_charge', 'Xizmat haqi'),
  kds('kds', 'Oshxona ekrani (KDS)'),

  // 2-to'lqin "Restoran va kafe"
  courses('courses', 'Kurslar'),
  tips('tips', 'Choy puli'),
  staffPay('staff_pay', 'Ofitsiant ish haqi'),
  guestSplit('guest_split', "Mehmon bo'yicha bo'lish"),
  waiterTransfer('waiter_transfer', "Stolni boshqa ofitsiantga o'tkazish"),
  hostess('hostess', 'Hostes'),
  tableBooking('table_booking', 'Stol broni'),
  skewerButtons('skewer_buttons', 'Six tugmalari'),
  weightLater('weight_later', "Og'irlikni keyin kiritish"),
  tableService('table_service', 'Stolga olib kelish'),
  serviceChargeRules('service_charge_rules', 'Xizmat haqi qoidalari');

  const PosModule(this.wire, this.label);

  /// Serverdagi kalit (`format.modules` elementi).
  final String wire;

  /// Ekrandagi nomi.
  final String label;

  static PosModule? fromWire(String? wire) {
    for (final PosModule m in PosModule.values) {
      if (m.wire == wire) return m;
    }
    return null;
  }
}

/// Kassa qaysi ekrandan boshlanadi (`format.first_screen`).
enum PosFirstScreen {
  hallMap('hall_map'),
  quickCounter('quick_counter'),
  bookingCalendar('booking_calendar'),
  onlineOrders('online_orders');

  const PosFirstScreen(this.wire);

  final String wire;

  static PosFirstScreen fromWire(String? wire) =>
      PosFirstScreen.values.firstWhere(
        (PosFirstScreen s) => s.wire == wire,
        orElse: () => PosFirstScreen.hallMap,
      );
}

/// Filialning samarali modullari: profil + ega o'zgarishi − qonun qulfi − tarifda yo'qlari (server hisoblaydi).
class PosModules {
  const PosModules(this.enabled);

  /// Eski server (`format` yo'q): 3-bosqich choyxonasi — hamma eski modul ochiq, yangilari yopiq.
  static const PosModules legacy = PosModules(<PosModule>{
    PosModule.quickCounter,
    PosModule.batches,
    PosModule.rooms,
    PosModule.roomFee,
    PosModule.guestItems,
    PosModule.bookings,
    PosModule.customerAccounts,
    PosModule.serviceCharge,
    PosModule.kds,
  });

  final Set<PosModule> enabled;

  bool has(PosModule module) => enabled.contains(module);

  /// Hammasi yoqilganmi.
  bool hasAll(Iterable<PosModule> modules) => modules.every(enabled.contains);

  /// Kamida bittasi yoqilganmi.
  bool hasAny(Iterable<PosModule> modules) => modules.any(enabled.contains);
}

/// `GET /pos/bootstrap` → `format` (P6-11): profil, kassa rejimi va birinchi ekran, samarali modullar, qulflar.
class PosFormat {
  const PosFormat({
    required this.profile,
    required this.profileLabel,
    required this.mode,
    required this.firstScreen,
    required this.modules,
    this.serviceChargeZero = false,
    this.unknownModules = const <String>[],
  });

  factory PosFormat.fromJson(PosJson j, {PosMode? fallbackMode}) {
    final List<String> keys = j.strings('modules');
    final Set<PosModule> modules = <PosModule>{};
    final List<String> unknown = <String>[];
    for (final String key in keys) {
      final PosModule? module = PosModule.fromWire(key);
      if (module == null) {
        unknown.add(key);
      } else {
        modules.add(module);
      }
    }
    final PosJson? locks = j.objOrNull('locks');
    return PosFormat(
      profile: j.strOrNull('profile') ?? 'choyxona',
      profileLabel: j.strOrNull('profile_label') ?? 'Choyxona',
      mode: j.has('mode')
          ? PosMode.fromWire(j.strOrNull('mode'))
          : (fallbackMode ?? PosMode.hybrid),
      firstScreen: PosFirstScreen.fromWire(j.strOrNull('first_screen')),
      modules: PosModules(modules),
      serviceChargeZero: locks?.boolean('service_charge_zero') ?? false,
      unknownModules: unknown,
    );
  }

  /// Eski server: choyxona, filial sozlamasidagi rejim, zal xaritasidan boshlanadi.
  factory PosFormat.legacy(PosMode mode) => PosFormat(
    profile: 'choyxona',
    profileLabel: 'Choyxona',
    mode: mode,
    firstScreen: mode == PosMode.counter
        ? PosFirstScreen.quickCounter
        : PosFirstScreen.hallMap,
    modules: PosModules.legacy,
  );

  /// `choyxona`, `osh_markazi`, `stolovaya`, ... (14 ta, docs/PHASE6_PLAN.md §6).
  final String profile;
  final String profileLabel;
  final PosMode mode;
  final PosFirstScreen firstScreen;
  final PosModules modules;

  /// Stolovaya: xizmat haqi qonun bo'yicha 0% (tugma va qator ko'rsatilmaydi).
  final bool serviceChargeZero;

  /// Bu ilova versiyasi bilmaydigan modul kalitlari (yangi server): e'tiborsiz qoldiriladi.
  final List<String> unknownModules;

  bool has(PosModule module) => modules.has(module);
}

/// Bootstrap'ning `format` qismi: konstruktorga tegmasdan [PosBootstrap.raw] dan o'qiladi.
extension PosBootstrapFormat on PosBootstrap {
  /// Filial formati; eski server yoki o'qib bo'lmasa — [PosFormat.legacy] (3-bosqich choyxonasi).
  PosFormat get format {
    final PosMode mode = branch.settings.mode;
    final Object? block = raw['format'];
    if (block is! Map) return PosFormat.legacy(mode);
    try {
      return PosFormat.fromJson(
        PosJson.of(block, 'format'),
        fallbackMode: mode,
      );
    } on FormatException {
      return PosFormat.legacy(mode);
    }
  }

  /// Samarali modullar (qisqa yo'l: `bootstrap.modules.has(PosModule.queueNumbers)`).
  PosModules get modules => format.modules;
}
