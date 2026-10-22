// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rooms.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Rooms _$RoomsFromJson(Map<String, dynamic> json) => Rooms(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  kind: json['kind'] as String,
  seats: (json['seats'] as num?)?.toInt(),
  hallId: (json['hall_id'] as num).toInt(),
  hallName: json['hall_name'] as String?,
  isActive: json['is_active'] as bool,
  minSpend: json['min_spend'] as String?,
  bookings: (json['bookings'] as List<dynamic>)
      .map((e) => Bookings2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$RoomsToJson(Rooms instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'kind': instance.kind,
  'seats': instance.seats,
  'hall_id': instance.hallId,
  'hall_name': instance.hallName,
  'is_active': instance.isActive,
  'min_spend': instance.minSpend,
  'bookings': instance.bookings,
};
