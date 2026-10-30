import 'package:pos_data/pos_data.dart';

/// P6 2-to'lqin: ofitsiant ilovasidagi restoran tugmalari faqat filialning samarali moduli yoqilganda ko'rinadi
/// (`bootstrap.format.modules`, server ham 403 `module_disabled` bilan tekshiradi). Bootstrap yo'q — hammasi yopiq.
extension WaiterModules on PosSession {
  PosModules get modules => bootstrap?.modules ?? PosModules.legacy;

  bool hasModule(PosModule module) => modules.has(module);

  /// Kurslar ("N-kursni yuborish", qatorning kursi).
  bool get coursesOn => hasModule(PosModule.courses);

  /// Qatorga mehmon raqami (mehmon bo'yicha bo'lish uchun).
  bool get guestSplitOn => hasModule(PosModule.guestSplit);

  /// Six va dona taomning ×N tugmalari, mangal vaqti.
  bool get skewersOn => hasModule(PosModule.skewerButtons);

  /// "Og'irlikni keyin kiritish" taomlari.
  bool get weightLaterOn => hasModule(PosModule.weightLater);

  /// Stolni boshqa ofitsiantga berish.
  bool get transferOn => hasModule(PosModule.waiterTransfer);

  /// "Mening choy pulim": modul va bootstrap'dagi `tips.enabled`.
  bool get tipsOn =>
      hasModule(PosModule.tips) && (bootstrap?.tips.enabled ?? false);

  /// "Ishga keldim / Ishdan ketdim" (`bootstrap.staff_pay.clock`).
  bool get clockOn =>
      hasModule(PosModule.staffPay) && (bootstrap?.staffPay?.clock ?? false);

  /// Hostes stoli: modul va hostes roli yoki `hostess.manage`.
  bool get hostessDeskOn =>
      hasModule(PosModule.hostess) &&
      (staffRole == StaffRoles.hostess || can(PosPermissions.hostessManage));

  /// Stol broni qoidalari (bo'nak, oldindan vaqt).
  bool get tableBookingOn => hasModule(PosModule.tableBooking);

  /// Kirgan xodim hostes (bosh sahifasi — hostes stoli).
  bool get isHostess => staffRole == StaffRoles.hostess;
}

/// Rol kalitlari (`PosSession.staffRole`).
abstract final class StaffRoles {
  static const String waiter = 'waiter';
  static const String hostess = 'hostess';
  static const String manager = 'manager';
  static const String owner = 'owner';
}

/// Shu oynaning (stol, buyurtma) mehmonlar soni bo'yicha o'rin tugmalari: 1..N (N kamida 1, ko'pi bilan [max]).
List<int> seatNumbers(int? guests, {int max = 20}) {
  final int n = (guests ?? 1).clamp(1, max);
  return <int>[for (var i = 1; i <= n; i++) i];
}
