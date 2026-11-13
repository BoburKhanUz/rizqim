// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data295.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data295 _$Data295FromJson(Map<String, dynamic> json) => Data295(
  enabled: json['enabled'] as bool,
  minLeadMinutes: (json['min_lead_minutes'] as num).toInt(),
  slotMinutes: (json['slot_minutes'] as num).toInt(),
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data295ToJson(Data295 instance) => <String, dynamic>{
  'enabled': instance.enabled,
  'min_lead_minutes': instance.minLeadMinutes,
  'slot_minutes': instance.slotMinutes,
  'slots': instance.slots,
};
