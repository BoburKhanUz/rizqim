// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'period3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Period3 _$Period3FromJson(Map<String, dynamic> json) => Period3(
  from: json['from'] as String,
  to: json['to'] as String,
  days: (json['days'] as num).toInt(),
  timezone: json['timezone'] as String,
  basis: json['basis'] as String,
);

Map<String, dynamic> _$Period3ToJson(Period3 instance) => <String, dynamic>{
  'from': instance.from,
  'to': instance.to,
  'days': instance.days,
  'timezone': instance.timezone,
  'basis': instance.basis,
};
