// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data343.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data343 _$Data343FromJson(Map<String, dynamic> json) => Data343(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift4.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data343ToJson(Data343 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
