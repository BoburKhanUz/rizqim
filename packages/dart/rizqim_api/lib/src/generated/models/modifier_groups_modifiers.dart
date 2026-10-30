// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'modifier.dart';
import 'modifier_group.dart';
import 'product_variant.dart';

part 'modifier_groups_modifiers.g.dart';

@JsonSerializable()
class ModifierGroupsModifiers {
  const ModifierGroupsModifiers({
    required this.id,
    required this.uuid,
    required this.modifierGroupId,
    required this.name,
    required this.nameI18n,
    required this.priceDelta,
    required this.maxQuantity,
    required this.productVariantId,
    required this.sortOrder,
    required this.isActive,
    required this.createdAt,
    required this.updatedAt,
    required this.deletedAt,
    required this.isDefault,
    required this.isAvailable,
    required this.unavailableReason,
    this.group,
    this.variant,
  });

  factory ModifierGroupsModifiers.fromJson(Map<String, Object?> json) =>
      _$ModifierGroupsModifiersFromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'modifier_group_id')
  final int modifierGroupId;
  final String name;
  @JsonKey(name: 'name_i18n')
  final Map<String, String>? nameI18n;
  @JsonKey(name: 'price_delta')
  final String priceDelta;
  @JsonKey(name: 'max_quantity')
  final int maxQuantity;
  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  @JsonKey(name: 'deleted_at')
  final DateTime? deletedAt;
  @JsonKey(name: 'is_default')
  final bool isDefault;
  final ModifierGroup? group;
  final ProductVariant? variant;
  @JsonKey(name: 'is_available')
  final bool isAvailable;
  @JsonKey(name: 'unavailable_reason')
  final String? unavailableReason;

  Map<String, Object?> toJson() => _$ModifierGroupsModifiersToJson(this);
}
