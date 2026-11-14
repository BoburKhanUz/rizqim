// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_ingredients_id_products_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerIngredientsIdProductsRequestBody
_$V1OwnerIngredientsIdProductsRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerIngredientsIdProductsRequestBody(
      links: (json['links'] as List<dynamic>)
          .map((e) => Links.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$V1OwnerIngredientsIdProductsRequestBodyToJson(
  V1OwnerIngredientsIdProductsRequestBody instance,
) => <String, dynamic>{'links': instance.links};
