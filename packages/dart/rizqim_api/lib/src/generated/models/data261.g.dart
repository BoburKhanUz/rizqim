// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data261.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data261 _$Data261FromJson(Map<String, dynamic> json) => Data261(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift4.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data261ToJson(Data261 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
