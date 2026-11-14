// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data341.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data341 _$Data341FromJson(Map<String, dynamic> json) => Data341(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift4.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data341ToJson(Data341 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
