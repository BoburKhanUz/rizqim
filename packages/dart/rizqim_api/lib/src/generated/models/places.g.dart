// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'places.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Places _$PlacesFromJson(Map<String, dynamic> json) => Places(
  tableId: (json['table_id'] as num).toInt(),
  hallId: (json['hall_id'] as num).toInt(),
  roomFee: json['room_fee'] as String,
  minSpend: json['min_spend'] as String,
  minSpendMode: json['min_spend_mode'] as String,
);

Map<String, dynamic> _$PlacesToJson(Places instance) => <String, dynamic>{
  'table_id': instance.tableId,
  'hall_id': instance.hallId,
  'room_fee': instance.roomFee,
  'min_spend': instance.minSpend,
  'min_spend_mode': instance.minSpendMode,
};
