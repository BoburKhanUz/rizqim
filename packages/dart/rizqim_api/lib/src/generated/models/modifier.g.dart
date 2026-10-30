// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifier.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifier _$ModifierFromJson(Map<String, dynamic> json) => Modifier(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  modifierGroupId: (json['modifier_group_id'] as num).toInt(),
  name: json['name'] as String,
  nameI18n: (json['name_i18n'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, e as String),
  ),
  priceDelta: json['price_delta'] as String,
  maxQuantity: (json['max_quantity'] as num).toInt(),
  productVariantId: (json['product_variant_id'] as num?)?.toInt(),
  sortOrder: (json['sort_order'] as num).toInt(),
  isActive: json['is_active'] as bool,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  deletedAt: json['deleted_at'] == null
      ? null
      : DateTime.parse(json['deleted_at'] as String),
  isDefault: json['is_default'] as bool,
  group: json['group'] == null
      ? null
      : ModifierGroup.fromJson(json['group'] as Map<String, dynamic>),
  variant: json['variant'] == null
      ? null
      : ProductVariant.fromJson(json['variant'] as Map<String, dynamic>),
);

Map<String, dynamic> _$ModifierToJson(Modifier instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'modifier_group_id': instance.modifierGroupId,
  'name': instance.name,
  'name_i18n': instance.nameI18n,
  'price_delta': instance.priceDelta,
  'max_quantity': instance.maxQuantity,
  'product_variant_id': instance.productVariantId,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
  'deleted_at': instance.deletedAt?.toIso8601String(),
  'is_default': instance.isDefault,
  'group': instance.group,
  'variant': instance.variant,
};
