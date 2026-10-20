// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch.dart';
import 'product_variant.dart';

part 'branch_product_variant.g.dart';

@JsonSerializable()
class BranchProductVariant {
  const BranchProductVariant({
    required this.id,
    required this.branchId,
    required this.variantId,
    required this.isActive,
    required this.sortOrder,
    required this.hiddenReason,
    required this.createdAt,
    required this.updatedAt,
    this.branch,
    this.variant,
  });

  factory BranchProductVariant.fromJson(Map<String, Object?> json) =>
      _$BranchProductVariantFromJson(json);

  final int id;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'variant_id')
  final int variantId;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  @JsonKey(name: 'hidden_reason')
  final String? hiddenReason;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Branch? branch;
  final ProductVariant? variant;

  Map<String, Object?> toJson() => _$BranchProductVariantToJson(this);
}
