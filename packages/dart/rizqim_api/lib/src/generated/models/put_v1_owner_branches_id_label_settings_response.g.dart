// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_branches_id_label_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerBranchesIdLabelSettingsResponse
_$PutV1OwnerBranchesIdLabelSettingsResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerBranchesIdLabelSettingsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data56.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerBranchesIdLabelSettingsResponseToJson(
  PutV1OwnerBranchesIdLabelSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
