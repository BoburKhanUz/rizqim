// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'room_fee4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RoomFee4 _$RoomFee4FromJson(Map<String, dynamic> json) => RoomFee4(
  amount: json['amount'] as String,
  dayType: DayType.fromJson(json['day_type'] as String),
  tariffId: (json['tariff_id'] as num).toInt(),
);

Map<String, dynamic> _$RoomFee4ToJson(RoomFee4 instance) => <String, dynamic>{
  'amount': instance.amount,
  'day_type': instance.dayType,
  'tariff_id': instance.tariffId,
};
