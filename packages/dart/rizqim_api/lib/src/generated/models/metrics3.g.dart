// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'metrics3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Metrics3 _$Metrics3FromJson(Map<String, dynamic> json) => Metrics3(
  shifts: (json['shifts'] as num).toInt(),
  minutes: (json['minutes'] as num).toInt(),
  bills: (json['bills'] as num).toInt(),
  sales: json['sales'] as String,
  serviceCharge: json['service_charge'] as String,
  events: (json['events'] as num).toInt(),
  tips: json['tips'] as String,
  uncountedSessions: (json['uncounted_sessions'] as num).toInt(),
  rules: (json['rules'] as List<dynamic>).map((e) => e as String).toList(),
  ownRule: json['own_rule'] as bool,
);

Map<String, dynamic> _$Metrics3ToJson(Metrics3 instance) => <String, dynamic>{
  'shifts': instance.shifts,
  'minutes': instance.minutes,
  'bills': instance.bills,
  'sales': instance.sales,
  'service_charge': instance.serviceCharge,
  'events': instance.events,
  'tips': instance.tips,
  'uncounted_sessions': instance.uncountedSessions,
  'rules': instance.rules,
  'own_rule': instance.ownRule,
};
