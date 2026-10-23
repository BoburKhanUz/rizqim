// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data172.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data172 _$Data172FromJson(Map<String, dynamic> json) => Data172(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift4.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data172ToJson(Data172 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
