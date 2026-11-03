// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_branches_branch_pickup_settings_request_body.g.dart';

@JsonSerializable()
class V1OwnerBranchesBranchPickupSettingsRequestBody {
  const V1OwnerBranchesBranchPickupSettingsRequestBody({
    this.pickupMinLeadMinutes,
    this.pickupSlotMinutes,
    this.pickupMaxDaysAhead,
    this.pickupPrepMinutes,
  });

  factory V1OwnerBranchesBranchPickupSettingsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerBranchesBranchPickupSettingsRequestBodyFromJson(json);

  @JsonKey(name: 'pickup_min_lead_minutes')
  final int? pickupMinLeadMinutes;
  @JsonKey(name: 'pickup_slot_minutes')
  final int? pickupSlotMinutes;
  @JsonKey(name: 'pickup_max_days_ahead')
  final int? pickupMaxDaysAhead;
  @JsonKey(name: 'pickup_prep_minutes')
  final int? pickupPrepMinutes;

  Map<String, Object?> toJson() =>
      _$V1OwnerBranchesBranchPickupSettingsRequestBodyToJson(this);
}
