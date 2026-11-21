// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data374.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data374 _$Data374FromJson(Map<String, dynamic> json) => Data374(
  enabled: json['enabled'] as bool,
  minLeadMinutes: (json['min_lead_minutes'] as num).toInt(),
  slotMinutes: (json['slot_minutes'] as num).toInt(),
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots11.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data374ToJson(Data374 instance) => <String, dynamic>{
  'enabled': instance.enabled,
  'min_lead_minutes': instance.minLeadMinutes,
  'slot_minutes': instance.slotMinutes,
  'slots': instance.slots,
};
