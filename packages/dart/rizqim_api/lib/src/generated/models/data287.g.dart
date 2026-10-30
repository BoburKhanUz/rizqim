// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data287.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data287 _$Data287FromJson(Map<String, dynamic> json) => Data287(
  enabled: json['enabled'] as bool,
  minLeadMinutes: (json['min_lead_minutes'] as num).toInt(),
  slotMinutes: (json['slot_minutes'] as num).toInt(),
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data287ToJson(Data287 instance) => <String, dynamic>{
  'enabled': instance.enabled,
  'min_lead_minutes': instance.minLeadMinutes,
  'slot_minutes': instance.slotMinutes,
  'slots': instance.slots,
};
