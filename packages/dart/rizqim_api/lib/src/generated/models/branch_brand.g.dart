// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'branch_brand.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BranchBrand _$BranchBrandFromJson(Map<String, dynamic> json) => BranchBrand(
  id: (json['id'] as num).toInt(),
  storeId: (json['store_id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  brandId: (json['brand_id'] as num).toInt(),
  isActive: json['is_active'] as bool,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  brand: json['brand'] == null
      ? null
      : Brand.fromJson(json['brand'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch.fromJson(json['branch'] as Map<String, dynamic>),
);

Map<String, dynamic> _$BranchBrandToJson(BranchBrand instance) =>
    <String, dynamic>{
      'id': instance.id,
      'store_id': instance.storeId,
      'branch_id': instance.branchId,
      'brand_id': instance.brandId,
      'is_active': instance.isActive,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'brand': instance.brand,
      'branch': instance.branch,
    };
