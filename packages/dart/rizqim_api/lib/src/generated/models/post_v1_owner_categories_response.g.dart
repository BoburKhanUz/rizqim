// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_categories_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerCategoriesResponse _$PostV1OwnerCategoriesResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerCategoriesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Category.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerCategoriesResponseToJson(
  PostV1OwnerCategoriesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
