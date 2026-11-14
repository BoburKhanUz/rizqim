// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_ingredients_id_products_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerIngredientsIdProductsResponse
_$PutV1OwnerIngredientsIdProductsResponseFromJson(Map<String, dynamic> json) =>
    PutV1OwnerIngredientsIdProductsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: json['data'] as String,
    );

Map<String, dynamic> _$PutV1OwnerIngredientsIdProductsResponseToJson(
  PutV1OwnerIngredientsIdProductsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
