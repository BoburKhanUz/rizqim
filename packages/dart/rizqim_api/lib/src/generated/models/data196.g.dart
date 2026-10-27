// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data196.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data196 _$Data196FromJson(Map<String, dynamic> json) => Data196(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift4.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data196ToJson(Data196 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
