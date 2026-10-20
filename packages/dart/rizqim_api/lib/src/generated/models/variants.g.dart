// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'variants.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Variants _$VariantsFromJson(Map<String, dynamic> json) => Variants(
  variantId: (json['variant_id'] as num).toInt(),
  variantUuid: json['variant_uuid'] as String,
  name: json['name'] as String?,
  price: json['price'] as String,
  isActive: json['is_active'] as bool,
  hiddenReason: json['hidden_reason'] as String?,
  sortOrder: (json['sort_order'] as num).toInt(),
);

Map<String, dynamic> _$VariantsToJson(Variants instance) => <String, dynamic>{
  'variant_id': instance.variantId,
  'variant_uuid': instance.variantUuid,
  'name': instance.name,
  'price': instance.price,
  'is_active': instance.isActive,
  'hidden_reason': instance.hiddenReason,
  'sort_order': instance.sortOrder,
};
