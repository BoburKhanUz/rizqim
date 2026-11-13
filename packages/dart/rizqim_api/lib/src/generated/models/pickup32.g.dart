// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'pickup32.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Pickup32 _$Pickup32FromJson(Map<String, dynamic> json) => Pickup32(
  pickupAt: json['pickup_at'] as String,
  kitchenFireAt: json['kitchen_fire_at'] as String?,
  kitchenFiredAt: json['kitchen_fired_at'] as String?,
  waiting: json['waiting'] as bool,
  localTime: json['local_time'] as String,
);

Map<String, dynamic> _$Pickup32ToJson(Pickup32 instance) => <String, dynamic>{
  'pickup_at': instance.pickupAt,
  'kitchen_fire_at': instance.kitchenFireAt,
  'kitchen_fired_at': instance.kitchenFiredAt,
  'waiting': instance.waiting,
  'local_time': instance.localTime,
};
