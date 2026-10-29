// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'batches2.g.dart';

@JsonSerializable()
class Batches2 {
  const Batches2({
    required this.id,
    required this.uuid,
    required this.businessDate,
    required this.productId,
    required this.productName,
    required this.productVariantId,
    required this.variantName,
    required this.unit,
    required this.label,
    required this.startedAt,
    required this.closedAt,
    required this.isOpen,
    required this.produced,
    required this.sold,
    required this.writtenOff,
    required this.left,
    required this.oversold,
    required this.variance,
    required this.soldPercent,
    required this.writtenOffPercent,
  });

  factory Batches2.fromJson(Map<String, Object?> json) =>
      _$Batches2FromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'product_name')
  final String? productName;
  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final String unit;
  final String label;
  @JsonKey(name: 'started_at')
  final String startedAt;
  @JsonKey(name: 'closed_at')
  final String? closedAt;
  @JsonKey(name: 'is_open')
  final bool isOpen;
  final num produced;
  final num sold;
  @JsonKey(name: 'written_off')
  final num writtenOff;
  final num left;
  final num oversold;
  final num variance;
  @JsonKey(name: 'sold_percent')
  final String? soldPercent;
  @JsonKey(name: 'written_off_percent')
  final String? writtenOffPercent;

  Map<String, Object?> toJson() => _$Batches2ToJson(this);
}
