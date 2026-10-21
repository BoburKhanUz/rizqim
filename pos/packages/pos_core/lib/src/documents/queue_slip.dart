import 'common.dart';

/// Navbat raqami talonchasi (olib ketish, osh markazi).
final class QueueSlip {
  const QueueSlip({
    required this.header,
    required this.number,
    required this.createdAt,
    this.orderCode,
    this.orderLabel,
    this.items = const <String>[],
    this.message = "Buyurtmangiz tayyor bo'lganda raqamingiz ekranda chiqadi va chaqiriladi.",
  });

  final ReceiptHeader header;

  /// Navbat raqami ("57").
  final String number;
  final DateTime createdAt;
  final String? orderCode;

  /// "Olib ketish", "Zalda".
  final String? orderLabel;

  /// Qisqa ro'yxat ("2 x Osh", "1 x Choy").
  final List<String> items;
  final String message;
}
