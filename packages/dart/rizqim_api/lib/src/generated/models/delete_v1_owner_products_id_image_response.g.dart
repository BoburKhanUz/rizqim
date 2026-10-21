// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_products_id_image_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerProductsIdImageResponse
_$DeleteV1OwnerProductsIdImageResponseFromJson(Map<String, dynamic> json) =>
    DeleteV1OwnerProductsIdImageResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Product.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$DeleteV1OwnerProductsIdImageResponseToJson(
  DeleteV1OwnerProductsIdImageResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
