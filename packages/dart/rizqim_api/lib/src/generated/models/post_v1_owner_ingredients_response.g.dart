// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_ingredients_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerIngredientsResponse _$PostV1OwnerIngredientsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerIngredientsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'] as String,
);

Map<String, dynamic> _$PostV1OwnerIngredientsResponseToJson(
  PostV1OwnerIngredientsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
