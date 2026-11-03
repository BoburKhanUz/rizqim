// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data262.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data262 _$Data262FromJson(Map<String, dynamic> json) => Data262(
  movement: Movement.fromJson(json['movement'] as Map<String, dynamic>),
  shift: Shift4.fromJson(json['shift'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data262ToJson(Data262 instance) => <String, dynamic>{
  'movement': instance.movement,
  'shift': instance.shift,
};
