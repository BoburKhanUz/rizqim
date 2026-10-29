// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_branches_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerBranchesResponse _$PostV1OwnerBranchesResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerBranchesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data42.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerBranchesResponseToJson(
  PostV1OwnerBranchesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
