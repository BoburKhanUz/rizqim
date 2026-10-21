// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data164.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data164 _$Data164FromJson(Map<String, dynamic> json) => Data164(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift2.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data164ToJson(Data164 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
