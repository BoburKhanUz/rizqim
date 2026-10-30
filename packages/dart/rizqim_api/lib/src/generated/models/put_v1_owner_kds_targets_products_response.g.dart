// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_kds_targets_products_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerKdsTargetsProductsResponse
_$PutV1OwnerKdsTargetsProductsResponseFromJson(Map<String, dynamic> json) =>
    PutV1OwnerKdsTargetsProductsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data106.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1OwnerKdsTargetsProductsResponseToJson(
  PutV1OwnerKdsTargetsProductsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
