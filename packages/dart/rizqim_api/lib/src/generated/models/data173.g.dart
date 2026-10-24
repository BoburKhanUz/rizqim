// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data173.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data173 _$Data173FromJson(Map<String, dynamic> json) => Data173(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift4.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data173ToJson(Data173 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
