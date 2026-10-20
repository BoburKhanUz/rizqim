// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'product_modifier_group.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ProductModifierGroup _$ProductModifierGroupFromJson(
  Map<String, dynamic> json,
) => ProductModifierGroup(
  id: (json['id'] as num).toInt(),
  productId: (json['product_id'] as num).toInt(),
  modifierGroupId: (json['modifier_group_id'] as num).toInt(),
  minSelect: (json['min_select'] as num?)?.toInt(),
  maxSelect: (json['max_select'] as num?)?.toInt(),
  isRequired: json['is_required'] as bool?,
  sortOrder: (json['sort_order'] as num).toInt(),
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  product: json['product'] == null
      ? null
      : Product.fromJson(json['product'] as Map<String, dynamic>),
  modifierGroup: json['modifier_group'] == null
      ? null
      : ModifierGroup.fromJson(json['modifier_group'] as Map<String, dynamic>),
);

Map<String, dynamic> _$ProductModifierGroupToJson(
  ProductModifierGroup instance,
) => <String, dynamic>{
  'id': instance.id,
  'product_id': instance.productId,
  'modifier_group_id': instance.modifierGroupId,
  'min_select': instance.minSelect,
  'max_select': instance.maxSelect,
  'is_required': instance.isRequired,
  'sort_order': instance.sortOrder,
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
  'product': instance.product,
  'modifier_group': instance.modifierGroup,
};
