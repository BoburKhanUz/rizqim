// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data289.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data289 _$Data289FromJson(Map<String, dynamic> json) => Data289(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift4.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data289ToJson(Data289 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
