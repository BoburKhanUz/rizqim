// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_batches_id_adjust_request_body.g.dart';

@JsonSerializable()
class V1OwnerBatchesIdAdjustRequestBody {
  const V1OwnerBatchesIdAdjustRequestBody({
    this.add,
    this.quantityLeft,
    this.reason,
  });

  factory V1OwnerBatchesIdAdjustRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerBatchesIdAdjustRequestBodyFromJson(json);

  final num? add;
  @JsonKey(name: 'quantity_left')
  final num? quantityLeft;
  final String? reason;

  Map<String, Object?> toJson() =>
      _$V1OwnerBatchesIdAdjustRequestBodyToJson(this);
}
