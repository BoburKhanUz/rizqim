// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'weight_basis.dart';

part 'data144.g.dart';

@JsonSerializable()
class Data144 {
  const Data144({
    required this.productId,
    required this.quickMultipliers,
    required this.waitMinutes,
    required this.weightMode,
  });

  factory Data144.fromJson(Map<String, Object?> json) =>
      _$Data144FromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'quick_multipliers')
  final List<int>? quickMultipliers;
  @JsonKey(name: 'wait_minutes')
  final int? waitMinutes;
  @JsonKey(name: 'weight_mode')
  final WeightBasis? weightMode;

  Map<String, Object?> toJson() => _$Data144ToJson(this);
}
