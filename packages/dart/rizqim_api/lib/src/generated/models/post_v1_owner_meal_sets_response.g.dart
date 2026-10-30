// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_meal_sets_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerMealSetsResponse _$PostV1OwnerMealSetsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerMealSetsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data113.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerMealSetsResponseToJson(
  PostV1OwnerMealSetsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
