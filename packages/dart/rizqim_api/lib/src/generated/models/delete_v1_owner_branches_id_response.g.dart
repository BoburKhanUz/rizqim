// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_branches_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerBranchesIdResponse _$DeleteV1OwnerBranchesIdResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1OwnerBranchesIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$DeleteV1OwnerBranchesIdResponseToJson(
  DeleteV1OwnerBranchesIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
