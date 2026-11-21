// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data344.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data344 _$Data344FromJson(Map<String, dynamic> json) => Data344(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift4.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data344ToJson(Data344 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
