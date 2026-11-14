// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data340.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data340 _$Data340FromJson(Map<String, dynamic> json) => Data340(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift4.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data340ToJson(Data340 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
