// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_branches_branch_pickup_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerBranchesBranchPickupSettingsResponse
_$GetV1OwnerBranchesBranchPickupSettingsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerBranchesBranchPickupSettingsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data48.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerBranchesBranchPickupSettingsResponseToJson(
  GetV1OwnerBranchesBranchPickupSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
