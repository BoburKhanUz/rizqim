// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data49.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data49 _$Data49FromJson(Map<String, dynamic> json) => Data49(
  branchId: (json['branch_id'] as num).toInt(),
  configured: json['configured'] as bool,
  pickupMinLeadMinutes: (json['pickup_min_lead_minutes'] as num).toInt(),
  pickupSlotMinutes: (json['pickup_slot_minutes'] as num).toInt(),
  pickupMaxDaysAhead: (json['pickup_max_days_ahead'] as num).toInt(),
  pickupPrepMinutes: (json['pickup_prep_minutes'] as num).toInt(),
  roundTheClock: json['round_the_clock'] as bool,
  openingHours: OpeningHours2.fromJson(
    json['opening_hours'] as Map<String, dynamic>,
  ),
  updatedAt: json['updated_at'] as String?,
);

Map<String, dynamic> _$Data49ToJson(Data49 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'configured': instance.configured,
  'pickup_min_lead_minutes': instance.pickupMinLeadMinutes,
  'pickup_slot_minutes': instance.pickupSlotMinutes,
  'pickup_max_days_ahead': instance.pickupMaxDaysAhead,
  'pickup_prep_minutes': instance.pickupPrepMinutes,
  'round_the_clock': instance.roundTheClock,
  'opening_hours': instance.openingHours,
  'updated_at': instance.updatedAt,
};
