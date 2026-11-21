// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data345.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data345 _$Data345FromJson(Map<String, dynamic> json) => Data345(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift4.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data345ToJson(Data345 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
