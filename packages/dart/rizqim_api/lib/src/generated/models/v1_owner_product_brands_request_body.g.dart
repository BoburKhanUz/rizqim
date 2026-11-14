// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_product_brands_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerProductBrandsRequestBody _$V1OwnerProductBrandsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerProductBrandsRequestBody(
  brandId: (json['brand_id'] as num?)?.toInt(),
  productIds: (json['product_ids'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
);

Map<String, dynamic> _$V1OwnerProductBrandsRequestBodyToJson(
  V1OwnerProductBrandsRequestBody instance,
) => <String, dynamic>{
  'brand_id': instance.brandId,
  'product_ids': instance.productIds,
};
