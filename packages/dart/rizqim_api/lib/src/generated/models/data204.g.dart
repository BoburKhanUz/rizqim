// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data204.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data204 _$Data204FromJson(Map<String, dynamic> json) => Data204(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift4.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data204ToJson(Data204 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
