// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'modifier_group.dart';
import 'product.dart';

part 'product_modifier_group.g.dart';

@JsonSerializable()
class ProductModifierGroup {
  const ProductModifierGroup({
    required this.id,
    required this.productId,
    required this.modifierGroupId,
    required this.minSelect,
    required this.maxSelect,
    required this.isRequired,
    required this.sortOrder,
    required this.createdAt,
    required this.updatedAt,
    this.product,
    this.modifierGroup,
  });

  factory ProductModifierGroup.fromJson(Map<String, Object?> json) =>
      _$ProductModifierGroupFromJson(json);

  final int id;
  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'modifier_group_id')
  final int modifierGroupId;
  @JsonKey(name: 'min_select')
  final int? minSelect;
  @JsonKey(name: 'max_select')
  final int? maxSelect;
  @JsonKey(name: 'is_required')
  final bool? isRequired;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Product? product;
  @JsonKey(name: 'modifier_group')
  final ModifierGroup? modifierGroup;

  Map<String, Object?> toJson() => _$ProductModifierGroupToJson(this);
}
