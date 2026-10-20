// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_branch_variants_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerBranchVariantsResponse _$PostV1OwnerBranchVariantsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerBranchVariantsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: BranchProductVariant.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerBranchVariantsResponseToJson(
  PostV1OwnerBranchVariantsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
