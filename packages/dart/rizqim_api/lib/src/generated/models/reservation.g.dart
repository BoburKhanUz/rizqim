// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'reservation.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Reservation _$ReservationFromJson(Map<String, dynamic> json) => Reservation(
  bookingUuid: json['booking_uuid'] as String,
  startsAt: json['starts_at'] as String,
  guests: (json['guests'] as num?)?.toInt(),
);

Map<String, dynamic> _$ReservationToJson(Reservation instance) =>
    <String, dynamic>{
      'booking_uuid': instance.bookingUuid,
      'starts_at': instance.startsAt,
      'guests': instance.guests,
    };
