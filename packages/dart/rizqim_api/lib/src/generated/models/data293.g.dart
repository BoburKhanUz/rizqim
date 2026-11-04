// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data293.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data293 _$Data293FromJson(Map<String, dynamic> json) => Data293(
  enabled: json['enabled'] as bool,
  minLeadMinutes: (json['min_lead_minutes'] as num).toInt(),
  slotMinutes: (json['slot_minutes'] as num).toInt(),
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data293ToJson(Data293 instance) => <String, dynamic>{
  'enabled': instance.enabled,
  'min_lead_minutes': instance.minLeadMinutes,
  'slot_minutes': instance.slotMinutes,
  'slots': instance.slots,
};
