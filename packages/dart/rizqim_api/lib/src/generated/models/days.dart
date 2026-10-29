// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'days.g.dart';

@JsonSerializable()
class Days {
  const Days({
    required this.businessDate,
    required this.productId,
    required this.productName,
    required this.unit,
    required this.batches,
    required this.produced,
    required this.sold,
    required this.writtenOff,
    required this.left,
    required this.variance,
    required this.soldPercent,
    required this.writtenOffPercent,
  });

  factory Days.fromJson(Map<String, Object?> json) => _$DaysFromJson(json);

  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'product_id')
  final String productId;
  @JsonKey(name: 'product_name')
  final String productName;
  final String unit;
  final String batches;
  final String produced;
  final String sold;
  @JsonKey(name: 'written_off')
  final String writtenOff;
  final String left;
  final String variance;
  @JsonKey(name: 'sold_percent')
  final String? soldPercent;
  @JsonKey(name: 'written_off_percent')
  final String? writtenOffPercent;

  Map<String, Object?> toJson() => _$DaysToJson(this);
}
