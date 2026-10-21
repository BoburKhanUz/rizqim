// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'order3.g.dart';

@JsonSerializable()
class Order3 {
  const Order3({
    required this.uuid,
    required this.shortCode,
    required this.branchId,
    required this.totalPrice,
  });

  factory Order3.fromJson(Map<String, Object?> json) => _$Order3FromJson(json);

  final String uuid;
  @JsonKey(name: 'short_code')
  final String? shortCode;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'total_price')
  final String totalPrice;

  Map<String, Object?> toJson() => _$Order3ToJson(this);
}
