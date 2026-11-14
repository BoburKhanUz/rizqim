// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'slots4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Slots4 _$Slots4FromJson(Map<String, dynamic> json) => Slots4(
  morning: Morning.fromJson(json['morning'] as Map<String, dynamic>),
  day: Day.fromJson(json['day'] as Map<String, dynamic>),
  evening: Evening.fromJson(json['evening'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Slots4ToJson(Slots4 instance) => <String, dynamic>{
  'morning': instance.morning,
  'day': instance.day,
  'evening': instance.evening,
};
