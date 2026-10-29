// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data144.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data144 _$Data144FromJson(Map<String, dynamic> json) => Data144(
  branch: Branch11.fromJson(json['branch'] as Map<String, dynamic>),
  period: json['period'] as String,
  periodLabel: json['period_label'] as String,
  periods: (json['periods'] as List<dynamic>)
      .map((e) => Periods.fromJson(e as Map<String, dynamic>))
      .toList(),
  componentTypes: (json['component_types'] as List<dynamic>)
      .map((e) => ComponentTypes.fromJson(e as Map<String, dynamic>))
      .toList(),
  guaranteeModes: (json['guarantee_modes'] as List<dynamic>)
      .map((e) => GuaranteeModes.fromJson(e as Map<String, dynamic>))
      .toList(),
  guaranteePer: (json['guarantee_per'] as List<dynamic>)
      .map((e) => GuaranteePer.fromJson(e as Map<String, dynamic>))
      .toList(),
  percentBases: (json['percent_bases'] as List<dynamic>)
      .map((e) => PercentBases.fromJson(e as Map<String, dynamic>))
      .toList(),
  eventTypes: (json['event_types'] as List<dynamic>)
      .map((e) => EventTypes.fromJson(e as Map<String, dynamic>))
      .toList(),
  roles: (json['roles'] as List<dynamic>)
      .map((e) => Roles4.fromJson(e as Map<String, dynamic>))
      .toList(),
  staff: (json['staff'] as List<dynamic>)
      .map((e) => Staff7.fromJson(e as Map<String, dynamic>))
      .toList(),
  rules: (json['rules'] as List<dynamic>)
      .map((e) => Rules3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data144ToJson(Data144 instance) => <String, dynamic>{
  'branch': instance.branch,
  'period': instance.period,
  'period_label': instance.periodLabel,
  'periods': instance.periods,
  'component_types': instance.componentTypes,
  'guarantee_modes': instance.guaranteeModes,
  'guarantee_per': instance.guaranteePer,
  'percent_bases': instance.percentBases,
  'event_types': instance.eventTypes,
  'roles': instance.roles,
  'staff': instance.staff,
  'rules': instance.rules,
};
