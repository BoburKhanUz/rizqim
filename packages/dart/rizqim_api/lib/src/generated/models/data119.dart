// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'period3.dart';
import 'products.dart';

part 'data119.g.dart';

@JsonSerializable()
class Data119 {
  const Data119({
    required this.period,
    required this.branchId,
    required this.sort,
    required this.products,
  });

  factory Data119.fromJson(Map<String, Object?> json) =>
      _$Data119FromJson(json);

  final Period3 period;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final String sort;
  final List<Products> products;

  Map<String, Object?> toJson() => _$Data119ToJson(this);
}
