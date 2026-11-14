// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_ingredients_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerIngredientsResponse _$GetV1OwnerIngredientsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerIngredientsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data106.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerIngredientsResponseToJson(
  GetV1OwnerIngredientsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
