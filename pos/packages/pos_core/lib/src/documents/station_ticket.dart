import 'common.dart';

/// Markadagi taom.
final class StationTicketItem {
  const StationTicketItem({
    required this.name,
    required this.quantity,
    this.modifiers = const <String>[],
    this.note,
    this.seat,
  });

  final String name;
  final ItemQuantity quantity;

  /// "Qazi", "Achchiq".
  final List<String> modifiers;

  /// Qator izohi ("kamroq yog'").
  final String? note;

  /// Mehmon (o'rin) raqami.
  final int? seat;
}

/// Sex (oshxona, mangal, bar, choy) markasi: bitta bosqich (`round_no`) taomlari. "Ushlab turish" (`fire_mode =
/// hold`) bo'lsa tayyor bo'lish vaqti bilan; o'chirilgan qatorlar uchun "O'CHIRILDI" markasi.
final class StationTicket {
  const StationTicket({
    required this.stationName,
    required this.sentAt,
    required this.items,
    this.tableLabel,
    this.orderLabel,
    this.orderCode,
    this.waiter,
    this.round,
    this.guests,
    this.orderNote,
    this.held = false,
    this.dueAt,
    this.isVoid = false,
    this.voidReason,
    this.voidedBy,
    this.isCopy = false,
  });

  /// "Osh sexi", "Mangal".
  final String stationName;
  final DateTime sentAt;
  final List<StationTicketItem> items;

  /// "Xona 3", "Tapchan 2". Stolsiz bo'lsa `null`.
  final String? tableLabel;

  /// Buyurtma turi: "Olib ketish", "Yetkazish", "Telegram".
  final String? orderLabel;
  final String? orderCode;
  final String? waiter;

  /// Bosqich (`round_no`).
  final int? round;
  final int? guests;
  final String? orderNote;

  /// Hozir pishirilmaydi: [dueAt] da tayyor bo'lishi kerak.
  final bool held;
  final DateTime? dueAt;

  /// "O'CHIRILDI" markasi: bu taomlarni pishirmang.
  final bool isVoid;
  final String? voidReason;
  final String? voidedBy;
  final bool isCopy;
}
