// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_branch_variants_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerBranchVariantsRequestBody _$V1OwnerBranchVariantsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerBranchVariantsRequestBody(
  branchId: (json['branch_id'] as num).toInt(),
  productId: (json['product_id'] as num).toInt(),
  productVariantId: (json['product_variant_id'] as num).toInt(),
  isActive: json['is_active'] as bool,
  hiddenReason: json['hidden_reason'] as String?,
  sortOrder: (json['sort_order'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1OwnerBranchVariantsRequestBodyToJson(
  V1OwnerBranchVariantsRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'product_id': instance.productId,
  'product_variant_id': instance.productVariantId,
  'is_active': instance.isActive,
  'hidden_reason': instance.hiddenReason,
  'sort_order': instance.sortOrder,
};
