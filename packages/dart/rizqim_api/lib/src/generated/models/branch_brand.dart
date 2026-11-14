// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch.dart';
import 'brand.dart';

part 'branch_brand.g.dart';

@JsonSerializable()
class BranchBrand {
  const BranchBrand({
    required this.id,
    required this.storeId,
    required this.branchId,
    required this.brandId,
    required this.isActive,
    required this.createdAt,
    required this.updatedAt,
    this.brand,
    this.branch,
  });

  factory BranchBrand.fromJson(Map<String, Object?> json) =>
      _$BranchBrandFromJson(json);

  final int id;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'brand_id')
  final int brandId;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Brand? brand;
  final Branch? branch;

  Map<String, Object?> toJson() => _$BranchBrandToJson(this);
}
