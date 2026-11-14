// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_brands_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerBrandsResponse _$PostV1OwnerBrandsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerBrandsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'] as String,
);

Map<String, dynamic> _$PostV1OwnerBrandsResponseToJson(
  PostV1OwnerBrandsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
