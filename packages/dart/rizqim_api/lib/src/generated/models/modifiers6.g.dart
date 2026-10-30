// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers6 _$Modifiers6FromJson(Map<String, dynamic> json) => Modifiers6(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  name: json['name'] as String,
  nameI18n: (json['name_i18n'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, e as String),
  ),
  priceDelta: json['price_delta'] as String,
  maxQuantity: (json['max_quantity'] as num).toInt(),
  isDefault: json['is_default'] as bool,
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  sortOrder: (json['sort_order'] as num).toInt(),
  isActive: json['is_active'] as bool,
);

Map<String, dynamic> _$Modifiers6ToJson(Modifiers6 instance) =>
    <String, dynamic>{
      'id': instance.id,
      'uuid': instance.uuid,
      'name': instance.name,
      'name_i18n': instance.nameI18n,
      'price_delta': instance.priceDelta,
      'max_quantity': instance.maxQuantity,
      'is_default': instance.isDefault,
      'product_variant_id': instance.productVariantId,
      'sort_order': instance.sortOrder,
      'is_active': instance.isActive,
    };
