// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data241.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data241 _$Data241FromJson(Map<String, dynamic> json) => Data241(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift4.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data241ToJson(Data241 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
