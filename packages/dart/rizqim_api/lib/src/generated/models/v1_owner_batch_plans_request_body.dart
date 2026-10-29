// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_batch_plans_request_body.g.dart';

@JsonSerializable()
class V1OwnerBatchPlansRequestBody {
  const V1OwnerBatchPlansRequestBody({
    required this.productId,
    this.branchId,
    this.productVariantId,
    this.time,
    this.plannedAt,
    this.quantity,
    this.label,
  });

  factory V1OwnerBatchPlansRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerBatchPlansRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  final String? time;
  @JsonKey(name: 'planned_at')
  final DateTime? plannedAt;
  final num? quantity;
  final String? label;

  Map<String, Object?> toJson() => _$V1OwnerBatchPlansRequestBodyToJson(this);
}
