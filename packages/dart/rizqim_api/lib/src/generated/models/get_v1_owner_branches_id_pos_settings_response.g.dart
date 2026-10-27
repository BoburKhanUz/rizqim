// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_branches_id_pos_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerBranchesIdPosSettingsResponse
_$GetV1OwnerBranchesIdPosSettingsResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerBranchesIdPosSettingsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data40.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerBranchesIdPosSettingsResponseToJson(
  GetV1OwnerBranchesIdPosSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
