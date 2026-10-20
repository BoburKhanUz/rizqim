// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'branch_product_variant.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BranchProductVariant _$BranchProductVariantFromJson(
  Map<String, dynamic> json,
) => BranchProductVariant(
  id: (json['id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  variantId: (json['variant_id'] as num).toInt(),
  isActive: json['is_active'] as bool,
  sortOrder: (json['sort_order'] as num).toInt(),
  hiddenReason: json['hidden_reason'] as String?,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  branch: json['branch'] == null
      ? null
      : Branch.fromJson(json['branch'] as Map<String, dynamic>),
  variant: json['variant'] == null
      ? null
      : ProductVariant.fromJson(json['variant'] as Map<String, dynamic>),
);

Map<String, dynamic> _$BranchProductVariantToJson(
  BranchProductVariant instance,
) => <String, dynamic>{
  'id': instance.id,
  'branch_id': instance.branchId,
  'variant_id': instance.variantId,
  'is_active': instance.isActive,
  'sort_order': instance.sortOrder,
  'hidden_reason': instance.hiddenReason,
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
  'branch': instance.branch,
  'variant': instance.variant,
};
