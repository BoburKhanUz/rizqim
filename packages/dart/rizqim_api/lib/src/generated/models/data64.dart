// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'products2.dart';

part 'data64.g.dart';

@JsonSerializable()
class Data64 {
  const Data64({
    required this.branchId,
    required this.date,
    required this.businessDate,
    required this.enforced,
    required this.setValue,
    required this.productIds,
    required this.previousDate,
    required this.products,
  });

  factory Data64.fromJson(Map<String, Object?> json) => _$Data64FromJson(json);

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
  final List<Products2> products;

  Map<String, Object?> toJson() => _$Data64ToJson(this);
}
