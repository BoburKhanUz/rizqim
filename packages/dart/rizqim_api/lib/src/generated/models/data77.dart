// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data77.g.dart';

@JsonSerializable()
class Data77 {
  const Data77({
    required this.id,
    required this.branchId,
    required this.stationId,
    required this.categoryId,
    required this.categoryName,
    required this.productId,
    required this.productName,
  });

  factory Data77.fromJson(Map<String, Object?> json) => _$Data77FromJson(json);

  final int id;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'station_id')
  final int stationId;
  @JsonKey(name: 'category_id')
  final int? categoryId;
  @JsonKey(name: 'category_name')
  final String? categoryName;
  @JsonKey(name: 'product_id')
  final int? productId;
  @JsonKey(name: 'product_name')
  final String? productName;

  Map<String, Object?> toJson() => _$Data77ToJson(this);
}
