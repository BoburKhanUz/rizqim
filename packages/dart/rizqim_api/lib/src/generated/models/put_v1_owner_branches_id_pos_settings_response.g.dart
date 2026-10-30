// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_branches_id_pos_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerBranchesIdPosSettingsResponse
_$PutV1OwnerBranchesIdPosSettingsResponseFromJson(Map<String, dynamic> json) =>
    PutV1OwnerBranchesIdPosSettingsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data58.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1OwnerBranchesIdPosSettingsResponseToJson(
  PutV1OwnerBranchesIdPosSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
