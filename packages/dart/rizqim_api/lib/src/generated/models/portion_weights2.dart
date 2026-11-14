// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'portion_weights2.g.dart';

@JsonSerializable()
class PortionWeights2 {
  const PortionWeights2({required this.productId, required this.grams});

  factory PortionWeights2.fromJson(Map<String, Object?> json) =>
      _$PortionWeights2FromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  final int grams;

  Map<String, Object?> toJson() => _$PortionWeights2ToJson(this);
}
