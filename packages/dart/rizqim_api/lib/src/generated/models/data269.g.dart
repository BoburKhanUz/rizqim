// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data269.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data269 _$Data269FromJson(Map<String, dynamic> json) => Data269(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift4.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data269ToJson(Data269 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
