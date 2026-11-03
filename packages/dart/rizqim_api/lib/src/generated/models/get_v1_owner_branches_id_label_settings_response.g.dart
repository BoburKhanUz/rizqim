// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_branches_id_label_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerBranchesIdLabelSettingsResponse
_$GetV1OwnerBranchesIdLabelSettingsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerBranchesIdLabelSettingsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data55.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerBranchesIdLabelSettingsResponseToJson(
  GetV1OwnerBranchesIdLabelSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
