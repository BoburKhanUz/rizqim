// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'devices.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Devices _$DevicesFromJson(Map<String, dynamic> json) => Devices(
  pos: Pos.fromJson(json['pos'] as Map<String, dynamic>),
  kds: Kds.fromJson(json['kds'] as Map<String, dynamic>),
  waiter: Waiter.fromJson(json['waiter'] as Map<String, dynamic>),
);

Map<String, dynamic> _$DevicesToJson(Devices instance) => <String, dynamic>{
  'pos': instance.pos,
  'kds': instance.kds,
  'waiter': instance.waiter,
};
