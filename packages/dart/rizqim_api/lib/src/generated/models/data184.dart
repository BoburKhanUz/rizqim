// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'weight_basis.dart';

part 'data184.g.dart';

@JsonSerializable()
class Data184 {
  const Data184({
    required this.productId,
    required this.quickMultipliers,
    required this.waitMinutes,
    required this.weightMode,
  });

  factory Data184.fromJson(Map<String, Object?> json) =>
      _$Data184FromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'quick_multipliers')
  final List<int>? quickMultipliers;
  @JsonKey(name: 'wait_minutes')
  final int? waitMinutes;
  @JsonKey(name: 'weight_mode')
  final WeightBasis? weightMode;

  Map<String, Object?> toJson() => _$Data184ToJson(this);
}
