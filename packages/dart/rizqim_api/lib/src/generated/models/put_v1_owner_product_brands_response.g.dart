// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_product_brands_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerProductBrandsResponse _$PutV1OwnerProductBrandsResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerProductBrandsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data175.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerProductBrandsResponseToJson(
  PutV1OwnerProductBrandsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
