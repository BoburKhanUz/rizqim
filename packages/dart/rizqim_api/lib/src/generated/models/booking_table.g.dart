// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'booking_table.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BookingTable _$BookingTableFromJson(Map<String, dynamic> json) => BookingTable(
  id: (json['id'] as num).toInt(),
  bookingId: (json['booking_id'] as num).toInt(),
  diningTableId: (json['dining_table_id'] as num).toInt(),
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  booking: json['booking'] == null
      ? null
      : Booking.fromJson(json['booking'] as Map<String, dynamic>),
);

Map<String, dynamic> _$BookingTableToJson(BookingTable instance) =>
    <String, dynamic>{
      'id': instance.id,
      'booking_id': instance.bookingId,
      'dining_table_id': instance.diningTableId,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'booking': instance.booking,
    };
