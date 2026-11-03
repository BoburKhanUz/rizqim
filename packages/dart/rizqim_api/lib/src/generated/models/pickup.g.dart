// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'pickup.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Pickup _$PickupFromJson(Map<String, dynamic> json) => Pickup(
  minLeadMinutes: (json['min_lead_minutes'] as num).toInt(),
  slotMinutes: (json['slot_minutes'] as num).toInt(),
  maxDaysAhead: (json['max_days_ahead'] as num).toInt(),
  prepMinutes: (json['prep_minutes'] as num).toInt(),
);

Map<String, dynamic> _$PickupToJson(Pickup instance) => <String, dynamic>{
  'min_lead_minutes': instance.minLeadMinutes,
  'slot_minutes': instance.slotMinutes,
  'max_days_ahead': instance.maxDaysAhead,
  'prep_minutes': instance.prepMinutes,
};
