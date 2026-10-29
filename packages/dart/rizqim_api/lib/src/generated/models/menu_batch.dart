// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch.dart';
import 'product.dart';
import 'product_variant.dart';
import 'store.dart';

part 'menu_batch.g.dart';

@JsonSerializable()
class MenuBatch {
  const MenuBatch({
    required this.id,
    required this.uuid,
    required this.storeId,
    required this.branchId,
    required this.productId,
    required this.productVariantId,
    required this.label,
    required this.quantityProduced,
    required this.quantityLeft,
    required this.startedAt,
    required this.soldOutAt,
    required this.closedAt,
    required this.openedBy,
    required this.closedBy,
    required this.createdAt,
    required this.updatedAt,
    required this.oversoldQuantity,
    required this.writtenOffQuantity,
    required this.isOpen,
    this.store,
    this.branch,
    this.product,
    this.variant,
  });

  factory MenuBatch.fromJson(Map<String, Object?> json) =>
      _$MenuBatchFromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  final String? label;
  @JsonKey(name: 'quantity_produced')
  final String quantityProduced;
  @JsonKey(name: 'quantity_left')
  final String quantityLeft;
  @JsonKey(name: 'started_at')
  final DateTime startedAt;
  @JsonKey(name: 'sold_out_at')
  final DateTime? soldOutAt;
  @JsonKey(name: 'closed_at')
  final DateTime? closedAt;
  @JsonKey(name: 'opened_by')
  final int? openedBy;
  @JsonKey(name: 'closed_by')
  final int? closedBy;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  @JsonKey(name: 'oversold_quantity')
  final String oversoldQuantity;
  @JsonKey(name: 'written_off_quantity')
  final String writtenOffQuantity;
  @JsonKey(name: 'is_open')
  final bool isOpen;
  final Store? store;
  final Branch? branch;
  final Product? product;
  final ProductVariant? variant;

  Map<String, Object?> toJson() => _$MenuBatchToJson(this);
}
