// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_products_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerProductsResponse _$PostV1OwnerProductsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerProductsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Product.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerProductsResponseToJson(
  PostV1OwnerProductsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
