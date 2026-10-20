// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_batches_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerBatchesRequestBody _$V1OwnerBatchesRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerBatchesRequestBody(
  productId: (json['product_id'] as num).toInt(),
  quantity: json['quantity'] as num,
  branchId: (json['branch_id'] as num?)?.toInt(),
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  label: json['label'] as String?,
);

Map<String, dynamic> _$V1OwnerBatchesRequestBodyToJson(
  V1OwnerBatchesRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'product_id': instance.productId,
  'product_variant_id': instance.productVariantId,
  'quantity': instance.quantity,
  'label': instance.label,
};
