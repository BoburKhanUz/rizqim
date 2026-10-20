// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'modifier.dart';
import 'product.dart';
import 'store.dart';

part 'modifier_group.g.dart';

@JsonSerializable()
class ModifierGroup {
  const ModifierGroup({
    required this.id,
    required this.uuid,
    required this.storeId,
    required this.name,
    required this.nameI18n,
    required this.minSelect,
    required this.maxSelect,
    required this.isRequired,
    required this.sortOrder,
    required this.isActive,
    required this.createdAt,
    required this.updatedAt,
    required this.deletedAt,
    this.store,
    this.modifiers,
    this.products,
  });

  factory ModifierGroup.fromJson(Map<String, Object?> json) =>
      _$ModifierGroupFromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'store_id')
  final int storeId;
  final String name;
  @JsonKey(name: 'name_i18n')
  final Map<String, String>? nameI18n;
  @JsonKey(name: 'min_select')
  final int minSelect;
  @JsonKey(name: 'max_select')
  final int? maxSelect;
  @JsonKey(name: 'is_required')
  final bool isRequired;
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
  final Store? store;
  final List<Modifier>? modifiers;
  final List<Product>? products;

  Map<String, Object?> toJson() => _$ModifierGroupToJson(this);
}
