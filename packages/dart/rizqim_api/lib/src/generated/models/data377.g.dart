// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data377.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data377 _$Data377FromJson(Map<String, dynamic> json) => Data377(
  enabled: json['enabled'] as bool,
  minLeadMinutes: (json['min_lead_minutes'] as num).toInt(),
  slotMinutes: (json['slot_minutes'] as num).toInt(),
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots11.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data377ToJson(Data377 instance) => <String, dynamic>{
  'enabled': instance.enabled,
  'min_lead_minutes': instance.minLeadMinutes,
  'slot_minutes': instance.slotMinutes,
  'slots': instance.slots,
};
