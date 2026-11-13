// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers42.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers42 _$Modifiers42FromJson(Map<String, dynamic> json) => Modifiers42(
  name: json['name'] as String,
  id: (json['id'] as num?)?.toInt(),
  nameI18n: (json['name_i18n'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, e as String),
  ),
  priceDelta: json['price_delta'] as num?,
  maxQuantity: (json['max_quantity'] as num?)?.toInt(),
  isDefault: json['is_default'] as bool?,
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  sortOrder: (json['sort_order'] as num?)?.toInt(),
  isActive: json['is_active'] as bool?,
);

Map<String, dynamic> _$Modifiers42ToJson(Modifiers42 instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'name_i18n': instance.nameI18n,
      'price_delta': instance.priceDelta,
      'max_quantity': instance.maxQuantity,
      'is_default': instance.isDefault,
      'product_variant_id': instance.productVariantId,
      'sort_order': instance.sortOrder,
      'is_active': instance.isActive,
    };
