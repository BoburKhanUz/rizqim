// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'period2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Period2 _$Period2FromJson(Map<String, dynamic> json) => Period2(
  from: json['from'] as String,
  to: json['to'] as String,
  days: (json['days'] as num).toInt(),
  timezone: json['timezone'] as String,
  basis: json['basis'] as String,
  groupBy: json['group_by'] as String,
);

Map<String, dynamic> _$Period2ToJson(Period2 instance) => <String, dynamic>{
  'from': instance.from,
  'to': instance.to,
  'days': instance.days,
  'timezone': instance.timezone,
  'basis': instance.basis,
  'group_by': instance.groupBy,
};
