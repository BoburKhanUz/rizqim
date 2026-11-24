// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data349.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data349 _$Data349FromJson(Map<String, dynamic> json) => Data349(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift4.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data349ToJson(Data349 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
