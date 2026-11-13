// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data317.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data317 _$Data317FromJson(Map<String, dynamic> json) => Data317(
  enabled: json['enabled'] as bool,
  minLeadMinutes: (json['min_lead_minutes'] as num).toInt(),
  slotMinutes: (json['slot_minutes'] as num).toInt(),
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data317ToJson(Data317 instance) => <String, dynamic>{
  'enabled': instance.enabled,
  'min_lead_minutes': instance.minLeadMinutes,
  'slot_minutes': instance.slotMinutes,
  'slots': instance.slots,
};
