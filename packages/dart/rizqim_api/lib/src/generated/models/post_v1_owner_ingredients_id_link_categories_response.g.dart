// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_ingredients_id_link_categories_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerIngredientsIdLinkCategoriesResponse
_$PostV1OwnerIngredientsIdLinkCategoriesResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerIngredientsIdLinkCategoriesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'] as List<dynamic>,
);

Map<String, dynamic> _$PostV1OwnerIngredientsIdLinkCategoriesResponseToJson(
  PostV1OwnerIngredientsIdLinkCategoriesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
