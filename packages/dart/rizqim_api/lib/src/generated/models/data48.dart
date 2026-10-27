// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data48.g.dart';

@JsonSerializable()
class Data48 {
  const Data48({
    required this.id,
    required this.branchId,
    required this.hallId,
    required this.productVariantId,
    required this.productName,
    required this.variantName,
    required this.unit,
    required this.quantityPerGuest,
    required this.isActive,
    required this.sortOrder,
  });

  factory Data48.fromJson(Map<String, Object?> json) => _$Data48FromJson(json);

  final int id;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'hall_id')
  final int? hallId;
  @JsonKey(name: 'product_variant_id')
  final int productVariantId;
  @JsonKey(name: 'product_name')
  final String? productName;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final String? unit;
  @JsonKey(name: 'quantity_per_guest')
  final num quantityPerGuest;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'sort_order')
  final int sortOrder;

  Map<String, Object?> toJson() => _$Data48ToJson(this);
}
