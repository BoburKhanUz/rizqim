// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data49.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data49 _$Data49FromJson(Map<String, dynamic> json) => Data49(
  id: (json['id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  hallId: (json['hall_id'] as num?)?.toInt(),
  productVariantId: (json['product_variant_id'] as num).toInt(),
  productName: json['product_name'] as String?,
  variantName: json['variant_name'] as String?,
  unit: json['unit'] as String?,
  quantityPerGuest: json['quantity_per_guest'] as num,
  isActive: json['is_active'] as bool,
  sortOrder: (json['sort_order'] as num).toInt(),
);

Map<String, dynamic> _$Data49ToJson(Data49 instance) => <String, dynamic>{
  'id': instance.id,
  'branch_id': instance.branchId,
  'hall_id': instance.hallId,
  'product_variant_id': instance.productVariantId,
  'product_name': instance.productName,
  'variant_name': instance.variantName,
  'unit': instance.unit,
  'quantity_per_guest': instance.quantityPerGuest,
  'is_active': instance.isActive,
  'sort_order': instance.sortOrder,
};
