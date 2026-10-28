// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_branches_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerBranchesIdResponse _$GetV1OwnerBranchesIdResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerBranchesIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data39.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerBranchesIdResponseToJson(
  GetV1OwnerBranchesIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
