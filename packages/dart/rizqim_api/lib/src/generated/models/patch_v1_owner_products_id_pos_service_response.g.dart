// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_owner_products_id_pos_service_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1OwnerProductsIdPosServiceResponse
_$PatchV1OwnerProductsIdPosServiceResponseFromJson(Map<String, dynamic> json) =>
    PatchV1OwnerProductsIdPosServiceResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data129.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PatchV1OwnerProductsIdPosServiceResponseToJson(
  PatchV1OwnerProductsIdPosServiceResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
