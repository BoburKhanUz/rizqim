// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_branches_branch_pickup_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerBranchesBranchPickupSettingsResponse
_$PutV1OwnerBranchesBranchPickupSettingsResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerBranchesBranchPickupSettingsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data49.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerBranchesBranchPickupSettingsResponseToJson(
  PutV1OwnerBranchesBranchPickupSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
