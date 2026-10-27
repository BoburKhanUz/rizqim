// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data53.g.dart';

@JsonSerializable()
class Data53 {
  const Data53({
    required this.id,
    required this.uuid,
    required this.branchId,
    required this.productId,
    required this.productName,
    required this.productVariantId,
    required this.variantName,
    required this.unit,
    required this.label,
    required this.quantityProduced,
    required this.quantityLeft,
    required this.isOpen,
    required this.startedAt,
    required this.soldOutAt,
    required this.closedAt,
  });

  factory Data53.fromJson(Map<String, Object?> json) => _$Data53FromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'product_name')
  final String productName;
  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final String? unit;
  final String? label;
  @JsonKey(name: 'quantity_produced')
  final num? quantityProduced;
  @JsonKey(name: 'quantity_left')
  final num? quantityLeft;
  @JsonKey(name: 'is_open')
  final bool isOpen;
  @JsonKey(name: 'started_at')
  final String? startedAt;
  @JsonKey(name: 'sold_out_at')
  final String? soldOutAt;
  @JsonKey(name: 'closed_at')
  final String? closedAt;

  Map<String, Object?> toJson() => _$Data53ToJson(this);
}
