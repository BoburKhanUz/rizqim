// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_owner_branches_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1OwnerBranchesIdResponse _$PatchV1OwnerBranchesIdResponseFromJson(
  Map<String, dynamic> json,
) => PatchV1OwnerBranchesIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data45.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PatchV1OwnerBranchesIdResponseToJson(
  PatchV1OwnerBranchesIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
