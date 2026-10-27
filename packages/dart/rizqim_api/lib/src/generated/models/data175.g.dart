// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data175.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data175 _$Data175FromJson(Map<String, dynamic> json) => Data175(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift4.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data175ToJson(Data175 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
