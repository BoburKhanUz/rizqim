// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_branches_id_pos_settings_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerBranchesIdPosSettingsRequestBody
_$V1OwnerBranchesIdPosSettingsRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerBranchesIdPosSettingsRequestBody(
      businessDayStartsAt: json['business_day_starts_at'] as String?,
      posSettings: json['pos_settings'] == null
          ? null
          : PosSettings3.fromJson(json['pos_settings'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$V1OwnerBranchesIdPosSettingsRequestBodyToJson(
  V1OwnerBranchesIdPosSettingsRequestBody instance,
) => <String, dynamic>{
  'business_day_starts_at': instance.businessDayStartsAt,
  'pos_settings': instance.posSettings,
};
