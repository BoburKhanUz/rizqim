// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_branches_branch_pickup_settings_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerBranchesBranchPickupSettingsRequestBody
_$V1OwnerBranchesBranchPickupSettingsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerBranchesBranchPickupSettingsRequestBody(
  pickupMinLeadMinutes: (json['pickup_min_lead_minutes'] as num?)?.toInt(),
  pickupSlotMinutes: (json['pickup_slot_minutes'] as num?)?.toInt(),
  pickupMaxDaysAhead: (json['pickup_max_days_ahead'] as num?)?.toInt(),
  pickupPrepMinutes: (json['pickup_prep_minutes'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1OwnerBranchesBranchPickupSettingsRequestBodyToJson(
  V1OwnerBranchesBranchPickupSettingsRequestBody instance,
) => <String, dynamic>{
  'pickup_min_lead_minutes': instance.pickupMinLeadMinutes,
  'pickup_slot_minutes': instance.pickupSlotMinutes,
  'pickup_max_days_ahead': instance.pickupMaxDaysAhead,
  'pickup_prep_minutes': instance.pickupPrepMinutes,
};
