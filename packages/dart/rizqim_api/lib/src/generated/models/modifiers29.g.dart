// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers29.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers29 _$Modifiers29FromJson(Map<String, dynamic> json) => Modifiers29(
  name: json['name'] as String,
  id: (json['id'] as num?)?.toInt(),
  nameI18n: (json['name_i18n'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, e as String),
  ),
  priceDelta: json['price_delta'] as num?,
  maxQuantity: (json['max_quantity'] as num?)?.toInt(),
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  sortOrder: (json['sort_order'] as num?)?.toInt(),
  isActive: json['is_active'] as bool?,
);

Map<String, dynamic> _$Modifiers29ToJson(Modifiers29 instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'name_i18n': instance.nameI18n,
      'price_delta': instance.priceDelta,
      'max_quantity': instance.maxQuantity,
      'product_variant_id': instance.productVariantId,
      'sort_order': instance.sortOrder,
      'is_active': instance.isActive,
    };
