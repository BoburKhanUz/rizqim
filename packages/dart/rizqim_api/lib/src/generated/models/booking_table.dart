// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'booking.dart';

part 'booking_table.g.dart';

@JsonSerializable()
class BookingTable {
  const BookingTable({
    required this.id,
    required this.bookingId,
    required this.diningTableId,
    required this.createdAt,
    required this.updatedAt,
    this.booking,
  });

  factory BookingTable.fromJson(Map<String, Object?> json) =>
      _$BookingTableFromJson(json);

  final int id;
  @JsonKey(name: 'booking_id')
  final int bookingId;
  @JsonKey(name: 'dining_table_id')
  final int diningTableId;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Booking? booking;

  Map<String, Object?> toJson() => _$BookingTableToJson(this);
}
