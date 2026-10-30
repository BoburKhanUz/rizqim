// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'components2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Components2 _$Components2FromJson(Map<String, dynamic> json) => Components2(
  type: json['type'] as String,
  label: json['label'] as String,
  amount: json['amount'] as String?,
  percent: json['percent'] as String?,
  per: json['per'] as String?,
  mode: json['mode'] as String?,
  baseValue: json['base'] as String?,
  eventTypes: (json['event_types'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Components2ToJson(Components2 instance) =>
    <String, dynamic>{
      'type': instance.type,
      'label': instance.label,
      'amount': instance.amount,
      'percent': instance.percent,
      'per': instance.per,
      'mode': instance.mode,
      'base': instance.baseValue,
      'event_types': instance.eventTypes,
    };
