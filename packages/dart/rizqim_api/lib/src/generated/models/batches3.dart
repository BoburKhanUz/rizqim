// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'batches3.g.dart';

@JsonSerializable()
class Batches3 {
  const Batches3({
    required this.id,
    required this.uuid,
    required this.productId,
    required this.productVariantId,
    required this.label,
    required this.quantityProduced,
    required this.quantityLeft,
    required this.startedAt,
  });

  factory Batches3.fromJson(Map<String, Object?> json) =>
      _$Batches3FromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  final String? label;
  @JsonKey(name: 'quantity_produced')
  final num? quantityProduced;
  @JsonKey(name: 'quantity_left')
  final num? quantityLeft;
  @JsonKey(name: 'started_at')
  final String startedAt;

  Map<String, Object?> toJson() => _$Batches3ToJson(this);
}
