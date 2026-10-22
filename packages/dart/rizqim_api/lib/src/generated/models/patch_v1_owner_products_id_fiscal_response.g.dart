// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_owner_products_id_fiscal_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1OwnerProductsIdFiscalResponse
_$PatchV1OwnerProductsIdFiscalResponseFromJson(Map<String, dynamic> json) =>
    PatchV1OwnerProductsIdFiscalResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Product.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PatchV1OwnerProductsIdFiscalResponseToJson(
  PatchV1OwnerProductsIdFiscalResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
