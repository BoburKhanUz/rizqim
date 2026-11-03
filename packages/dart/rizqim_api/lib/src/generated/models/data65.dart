// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'products3.dart';

part 'data65.g.dart';

@JsonSerializable()
class Data65 {
  const Data65({
    required this.branchId,
    required this.date,
    required this.businessDate,
    required this.enforced,
    required this.setValue,
    required this.productIds,
    required this.previousDate,
    required this.products,
  });

  factory Data65.fromJson(Map<String, Object?> json) => _$Data65FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String? date;
  @JsonKey(name: 'business_date')
  final String businessDate;
  final bool enforced;

  /// The name has been replaced because it contains a keyword. Original name: `set`.
  @JsonKey(name: 'set')
  final bool setValue;
  @JsonKey(name: 'product_ids')
  final Map<String, int> productIds;
  @JsonKey(name: 'previous_date')
  final String? previousDate;
  final List<Products3> products;

  Map<String, Object?> toJson() => _$Data65ToJson(this);
}
