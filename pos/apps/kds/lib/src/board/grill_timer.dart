import 'package:rizqim_api/rizqim_api.dart';

import 'board_view.dart';

/// Mangal vaqti (P6-25): taom mahsulotida belgilangan `wait_minutes` yuborilgan paytdan sanaladi.
///
/// [remaining] — qolgan vaqt (manfiy: muddat o'tgan); `null` — taymer yo'q (taomda vaqt belgilanmagan, qator hali
/// yuborilmagan, tayyor, berilgan yoki o'chirilgan). Vaqt server soati bo'yicha ([serverNowAt]): planshet soati
/// serverdan farq qilsa ham taymer to'g'ri.
class GrillTimer {
  const GrillTimer({required this.target, required this.remaining});

  /// Mahsulotdagi mangal vaqti.
  final Duration target;

  /// Qolgan vaqt; manfiy — muddat o'tgan (necha vaqt kechikkani `-remaining`).
  final Duration remaining;

  bool get overdue => remaining.isNegative;

  /// Bir daqiqadan kam qoldi (yoki o'tdi): oshpazga "hozir olish kerak" belgisi.
  bool get soon => remaining < const Duration(minutes: 1);

  /// [line] uchun [ticket] soatida [now] paytidagi taymer, yoki `null`.
  static GrillTimer? of(KitchenTicket ticket, KitchenLine line, DateTime now) {
    final int? minutes = line.waitMinutes;
    final DateTime? sent = line.sentAt;
    if (minutes == null ||
        minutes <= 0 ||
        sent == null ||
        line.isDone ||
        line.isVoid) {
      return null;
    }
    final Duration target = Duration(minutes: minutes);
    final Duration elapsed = serverNowAt(ticket, now).difference(sent);
    return GrillTimer(target: target, remaining: target - elapsed);
  }
}
