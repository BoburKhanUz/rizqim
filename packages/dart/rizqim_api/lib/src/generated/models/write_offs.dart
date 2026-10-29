// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'written_off_by.dart';

part 'write_offs.g.dart';

@JsonSerializable()
class WriteOffs {
  const WriteOffs({
    required this.uuid,
    required this.batchId,
    required this.productId,
    required this.productName,
    required this.variantName,
    required this.quantity,
    required this.unit,
    required this.reason,
    required this.reasonLabel,
    required this.note,
    required this.businessDate,
    required this.shiftId,
    required this.writtenOffBy,
    required this.createdAt,
  });

  factory WriteOffs.fromJson(Map<String, Object?> json) =>
      _$WriteOffsFromJson(json);

  final String uuid;
  @JsonKey(name: 'batch_id')
  final int batchId;
  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'product_name')
  final String productName;
  @JsonKey(name: 'variant_name')
  final String? variantName;
  final num? quantity;
  final String unit;
  final String reason;
  @JsonKey(name: 'reason_label')
  final String reasonLabel;
  final String? note;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'shift_id')
  final int? shiftId;
  @JsonKey(name: 'written_off_by')
  final WrittenOffBy? writtenOffBy;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$WriteOffsToJson(this);
}
