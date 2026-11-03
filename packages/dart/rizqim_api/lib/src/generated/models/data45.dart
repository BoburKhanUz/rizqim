// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data45.g.dart';

@JsonSerializable()
class Data45 {
  const Data45({
    required this.id,
    required this.branchId,
    required this.name,
    required this.basis,
    required this.basisLabel,
    required this.categoryId,
    required this.productId,
    required this.quantity,
    required this.unitPrice,
    required this.mxik,
    required this.packageCode,
    required this.unit,
    required this.orderTypes,
    required this.isActive,
    required this.sortOrder,
  });

  factory Data45.fromJson(Map<String, Object?> json) => _$Data45FromJson(json);

  final int id;
  @JsonKey(name: 'branch_id')
  final int branchId;
  final String name;
  final String basis;
  @JsonKey(name: 'basis_label')
  final String basisLabel;
  @JsonKey(name: 'category_id')
  final int? categoryId;
  @JsonKey(name: 'product_id')
  final int? productId;
  final int quantity;
  @JsonKey(name: 'unit_price')
  final String unitPrice;
  final String? mxik;
  @JsonKey(name: 'package_code')
  final String? packageCode;
  final String unit;
  @JsonKey(name: 'order_types')
  final List<String> orderTypes;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'sort_order')
  final int sortOrder;

  Map<String, Object?> toJson() => _$Data45ToJson(this);
}
